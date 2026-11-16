<?php

declare(strict_types=1);

namespace App\Services;

use App\Issuers\AccessTokenIssuerInterface;
use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class RefreshTokenRotationService
{
    private PDO $db;
    private SessionRepository $sessionRepository;
    private UserRepository $userRepository;
    private AccessTokenIssuerInterface $accessTokenIssuer;

    public function __construct(
        PDO $connection,
        SessionRepository $sessionRepository,
        UserRepository $userRepository,
        AccessTokenIssuerInterface $accessTokenIssuer
    ) {
        if (!$sessionRepository->usesConnection($connection)) {
            throw new \InvalidArgumentException(
                'SessionRepository must use the coordinator PDO connection.'
            );
        }

        if (!$userRepository->usesConnection($connection)) {
            throw new \InvalidArgumentException(
                'UserRepository must use the coordinator PDO connection.'
            );
        }

        $this->db = $connection;
        $this->sessionRepository = $sessionRepository;
        $this->userRepository = $userRepository;
        $this->accessTokenIssuer = $accessTokenIssuer;
    }

    public function rotate(
        string $currentRefreshToken,
        string $newRefreshToken,
        string $newExpiresAt
    ): array {

        if (
            $currentRefreshToken === '' ||
            $newRefreshToken === ''
        ) {
            throw new \InvalidArgumentException(
                'Refresh tokens must not be empty.'
            );
        }

        if (hash_equals($currentRefreshToken, $newRefreshToken)) {
            throw new \InvalidArgumentException(
                'Replacement refresh token must differ from the current token.'
            );
        }

        $expiry = \DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $newExpiresAt,
            new \DateTimeZone('UTC')
        );

        $dateErrors = \DateTimeImmutable::getLastErrors();

        if (
            $expiry === false ||
            (
                $dateErrors !== false &&
                (
                    $dateErrors['warning_count'] > 0 ||
                    $dateErrors['error_count'] > 0
                )
            ) ||
            $expiry->format('Y-m-d H:i:s') !== $newExpiresAt
        ) {
            throw new \InvalidArgumentException(
                'Replacement refresh-token expiry must use a valid Y-m-d H:i:s UTC timestamp.'
            );
        }

        if ($expiry->getTimestamp() <= time()) {
            throw new \InvalidArgumentException(
                'Replacement refresh-token expiry must be in the future.'
            );
        }

        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $this->db->beginTransaction();

                // Lock the existing refresh session.
                $session = $this->sessionRepository
                    ->lockValidSessionByToken($currentRefreshToken);

                if (!$session) {
                    $this->db->rollBack();

                    return ['status' => 'invalid_token'];
                }

                // Lock and retrieve the complete user record.
                $user = $this->userRepository->findByIdForUpdate(
                    (int) $session['user_id']
                );

                if (!$user) {
                    $this->db->rollBack();

                    return ['status' => 'user_not_found'];
                }

                if ($user->status !== 'active') {
                    $this->db->rollBack();

                    return ['status' => $user->status];
                }

                if ($user->emailVerifiedAt === null) {
                    $this->db->rollBack();

                    return ['status' => 'email_unverified'];
                }

                // Retrieve roles while using the same transaction.
                $roles = $this->userRepository->getRoles($user->id);
                $user->roles = $roles;

                // Generate the access token BEFORE committing.
                $accessToken = $this->accessTokenIssuer->issue([
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'roles' => $roles,
                ], 3600);

                // Replace the refresh token within this transaction.
                $this->sessionRepository->replaceTokenWithinTransaction(
                    $currentRefreshToken,
                    $newRefreshToken,
                    $user->id,
                    $newExpiresAt
                );

                // Commit only after all critical operations succeed.
                $this->db->commit();

                return [
                    'status' => 'rotated',
                    'user' => $user,
                    'token' => $accessToken,
                    'refresh_token' => $newRefreshToken,
                ];

            } catch (PDOException $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }

                $errorCode = $e->errorInfo[1] ?? null;

                if ($errorCode === 1213 && $attempt < $maxAttempts) {
                    usleep(random_int(50000, 150000));
                    continue;
                }

                throw $e;

            } catch (Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }

                throw $e;
            }
        }

        throw new RuntimeException(
            'Refresh-token rotation could not be completed.'
        );
    }
}