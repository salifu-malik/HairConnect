<?php

namespace App\Services;

use App\Repositories\QueueRepository;
use App\Repositories\ShopRepository;
use App\Repositories\UserRepository;
use App\Models\Shop;

class QueueService
{
    private QueueRepository $queueRepository;
    private ShopRepository $shopRepository;
    private UserRepository $userRepository;

    public function __construct(
        QueueRepository $queueRepository,
        ShopRepository $shopRepository,
        UserRepository $userRepository
    ) {
        $this->queueRepository = $queueRepository;
        $this->shopRepository = $shopRepository;
        $this->userRepository = $userRepository;
    }

     //Verify that the authenticated user owns the specified shop.
    private function verifyShopOwnership(
        int $ownerId,
        int $shopId
    ): Shop {
        if ($ownerId <= 0) {
            throw new \RuntimeException('Invalid shop owner.');
        }

        if ($shopId <= 0) {
            throw new \RuntimeException('Invalid shop.');
        }

        $shop = $this->shopRepository->findById($shopId);

        if (!$shop) {
            throw new \RuntimeException('Shop not found.');
        }

        if ((int) $shop->ownerId !== $ownerId) {
            throw new \RuntimeException(
                'You are not authorized to manage this shop queue.'
            );
        }

        return $shop;
    }


    /**
     * Join a shop's walk-in queue.
     * Only approved and active shops can accept
     * walk-in queue entries.
     */
    public function joinQueue(
        int $customerId,
        int $shopId
    ): array {
        if ($customerId <= 0) {
            throw new \RuntimeException('Invalid customer.');
        }

        if ($shopId <= 0) {
            throw new \RuntimeException('Invalid shop.');
        }

        // Verify that the shop exists.
        $shop = $this->shopRepository->findById($shopId);

        if (!$shop) {
            throw new \RuntimeException('Shop not found.');
        }

        // Only approved shops can accept queue entries.
        if ($shop->approvalStatus !== 'approved') {
            throw new \RuntimeException(
                'This shop is not approved.'
            );
        }

        // Only active shops can accept queue entries.
        if ($shop->status !== 'active') {
            throw new \RuntimeException(
                'This shop is not currently active.'
            );
        }

        //Fast check before opening the transaction.
        $existingQueue =
            $this->queueRepository->findActiveByCustomerAndShop(
                $customerId,
                $shopId
            );

        if ($existingQueue) {
            throw new \RuntimeException(
                'You already have an active queue at this shop.'
            );
        }

        $this->queueRepository->beginTransaction();

        try {

            /*
             * Lock the shop row.
             * This serializes queue-number generation
             * for this particular shop.
             */
            $this->queueRepository->lockShop($shopId);

            /*
             * Re-check inside the transaction.
             * This protects against concurrent requests
             * from the same customer.
             */
            $existingQueue =
                $this->queueRepository->findActiveByCustomerAndShop(
                    $customerId,
                    $shopId
                );

            if ($existingQueue) {
                throw new \RuntimeException(
                    'You already have an active queue at this shop.'
                );
            }

            /*
             * Generate the next queue number while
             * the shop row is locked.
             */
            $queueNumber =
                $this->queueRepository->getNextQueueNumber($shopId);

            // Create the queue entry.
            $queueId = $this->queueRepository->create(
                $shopId,
                $customerId,
                $queueNumber
            );

            $this->queueRepository->commit();

            /*
             * Calculate the customer's position after
             * the transaction has committed.
             */
            $position =
                $this->queueRepository->getCurrentPosition(
                    $shopId,
                    $queueNumber
                );

            return [
                'id' => $queueId,
                'shop_id' => $shopId,
                'customer_id' => $customerId,
                'queue_number' => $queueNumber,
                'status' => 'waiting',
                'position' => $position,
            ];

        } catch (\Throwable $e) {

            $this->queueRepository->rollback();

            throw $e;
        }
    }


    //Get a customer's active queue at a shop.
    public function getMyQueue(
        int $customerId,
        int $shopId
    ): ?array {
        if ($customerId <= 0) {
            throw new \RuntimeException('Invalid customer.');
        }

        if ($shopId <= 0) {
            throw new \RuntimeException('Invalid shop.');
        }

        $queue =
            $this->queueRepository->findActiveByCustomerAndShop(
                $customerId,
                $shopId
            );

        if (!$queue) {
            return null;
        }

        $queue['position'] =
            $this->queueRepository->getCurrentPosition(
                $shopId,
                (int) $queue['queue_number']
            );

        return $queue;
    }


    /**
     * Get the active queue for a shop.
     * Only the owner of the shop can manage its queue.
     */
    public function getShopQueue(
        int $ownerId,
        int $shopId
    ): array {
        if ($ownerId <= 0) {
            throw new \RuntimeException('Invalid shop owner.');
        }

        if ($shopId <= 0) {
            throw new \RuntimeException('Invalid shop.');
        }

        $shop = $this->shopRepository->findById($shopId);

        if (!$shop) {
            throw new \RuntimeException('Shop not found.');
        }

        if ((int) $shop->ownerId !== $ownerId) {
            throw new \RuntimeException(
                'You are not authorized to manage this shop queue.'
            );
        }

        $queue = $this->queueRepository->findActiveByShop($shopId);

        return array_map(
            fn(array $entry): array => $this->enrichQueueEntry($entry),
            $queue
        );
    }


     //Start serving a waiting queue customer.
    public function startQueue(
        int $ownerId,
        int $queueId
    ): array {
        if ($queueId <= 0) {
            throw new \RuntimeException(
                'Invalid queue entry.'
            );
        }

        $queue = $this->queueRepository->findById($queueId);

        if (!$queue) {
            throw new \RuntimeException(
                'Queue entry not found.'
            );
        }

        /*
         * Verify that the authenticated user owns
         * the shop associated with this queue entry.
         */
        $this->verifyShopOwnership(
            $ownerId,
            (int) $queue['shop_id']
        );

        if ($queue['status'] !== 'waiting') {
            throw new \RuntimeException(
                'Only waiting queue entries can be started.'
            );
        }

        $updated = $this->queueRepository->start(
            $queueId
        );

        if (!$updated) {
            throw new \RuntimeException(
                'Queue entry could not be started.'
            );
        }

        $updatedQueue =
            $this->queueRepository->findById($queueId);

        if (!$updatedQueue) {
            throw new \RuntimeException(
                'Queue entry could not be retrieved after update.'
            );
        }

        return $this->enrichQueueEntry($updatedQueue);
    }


     //Complete a queue customer currently being served.
    public function completeQueue(
        int $ownerId,
        int $queueId
    ): array {
        if ($queueId <= 0) {
            throw new \RuntimeException(
                'Invalid queue entry.'
            );
        }

        $queue = $this->queueRepository->findById($queueId);

        if (!$queue) {
            throw new \RuntimeException(
                'Queue entry not found.'
            );
        }

        /*
         * Verify that the authenticated user owns
         * the shop associated with this queue entry.
         */
        $this->verifyShopOwnership(
            $ownerId,
            (int) $queue['shop_id']
        );

        if ($queue['status'] !== 'in_progress') {
            throw new \RuntimeException(
                'Only in-progress queue entries can be completed.'
            );
        }

        $updated = $this->queueRepository->complete(
            $queueId
        );

        if (!$updated) {
            throw new \RuntimeException(
                'Queue entry could not be completed.'
            );
        }

        $updatedQueue =
            $this->queueRepository->findById($queueId);

        if (!$updatedQueue) {
            throw new \RuntimeException(
                'Queue entry could not be retrieved after update.'
            );
        }

        return $this->enrichQueueEntry($updatedQueue);
    }


    /**
     * Cancel a waiting queue customer.
     * Only waiting entries can be cancelled.
     */
    public function cancelQueue(
        int $ownerId,
        int $queueId
    ): array {
        if ($queueId <= 0) {
            throw new \RuntimeException(
                'Invalid queue entry.'
            );
        }

        $queue = $this->queueRepository->findById($queueId);

        if (!$queue) {
            throw new \RuntimeException(
                'Queue entry not found.'
            );
        }

        /*
         * Verify that the authenticated user owns
         * the shop associated with this queue entry.
         */
        $this->verifyShopOwnership(
            $ownerId,
            (int) $queue['shop_id']
        );

        if ($queue['status'] !== 'waiting') {
            throw new \RuntimeException(
                'Only waiting queue entries can be cancelled.'
            );
        }

        $updated = $this->queueRepository->cancel(
            $queueId
        );

        if (!$updated) {
            throw new \RuntimeException(
                'Queue entry could not be cancelled.'
            );
        }

        $updatedQueue =
            $this->queueRepository->findById($queueId);

        if (!$updatedQueue) {
            throw new \RuntimeException(
                'Queue entry could not be retrieved after update.'
            );
        }

        return $this->enrichQueueEntry($updatedQueue);
    }


    /**
     * Add customer information to a queue entry.
     */
    private function enrichQueueEntry(array $queue): array
    {
        $customer = $this->userRepository->findById(
            (int) $queue['customer_id']
        );

        if (!$customer) {
            $queue['customer_name'] = 'Unknown Customer';
            $queue['customer_phone'] = null;
            $queue['customer_profile_image'] = null;

            return $queue;
        }

        $queue['customer_name'] = trim(
            $customer->firstName . ' ' . $customer->lastName
        );

        $queue['customer_phone'] = $customer->phone ?? null;
        $queue['customer_profile_image'] = $customer->profileImage ?? null;

        return $queue;
    }


}