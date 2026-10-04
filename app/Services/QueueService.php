<?php

namespace App\Services;

use App\Repositories\QueueRepository;
use App\Repositories\ShopRepository;

class QueueService
{
    private QueueRepository $queueRepository;
    private ShopRepository $shopRepository;

    public function __construct(
        QueueRepository $queueRepository,
        ShopRepository $shopRepository
    ) {
        $this->queueRepository = $queueRepository;
        $this->shopRepository = $shopRepository;
    }

    //Join a shop's walk-in queue.
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

         //Verify that the shop exists.
        $shop = $this->shopRepository->findById($shopId);

        if (!$shop) {
            throw new \RuntimeException('Shop not found.');
        }

        /*
         * Only approved and active shops can accept
         * walk-in queue entries.
         */
        if ($shop->approvalStatus !== 'approved') {
            throw new \RuntimeException(
                'This shop is not approved.'
            );
        }

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
             *
             * This serializes queue-number generation
             * for this particular shop.
             */
            $this->queueRepository->lockShop($shopId);

            /*
             * Re-check inside the transaction.
             *
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
             * Generate the next queue number while the
             * shop row is locked.
             */
            $queueNumber =
                $this->queueRepository->getNextQueueNumber($shopId);

             //Create the queue entry.
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
}