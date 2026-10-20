<?php

namespace App\Helpers;

class LoginRiskAssessor
{
    private RedisManager $redis;

    public function __construct(RedisManager $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Assess login risk using request volume and account failures.
     *
     * This is a heuristic assessment, not definitive bot detection.
     */
    public function assess(string $email, string $ip): array
    {
        $emailHash = hash(
            'sha256',
            strtolower(trim($email))
        );

        $ipHash = hash('sha256', $ip);

        $ipKey = "login:risk:requests:{$ipHash}";
        $accountFailureKey = "login:risk:failures:{$emailHash}";

        /*
         * Count requests from this IP within a 60-second window.
         */
        $ipRequests = $this->redis->increment($ipKey, 60);

        /*
         * Count failed passwords for this account.
         */
        $accountFailures = (int) (
            $this->redis->get($accountFailureKey) ?? 0
        );

        /*
         * High risk indicates that the request should be rate-limited.
         * It does not prove that the requester is a bot.
         */
        if ($ipRequests > 30) {
            return [
                'level' => 'high',
                'reason' => 'excessive_ip_request_rate',
            ];
        }

        /*
         * Medium risk requires an additional Turnstile challenge.
         */
        if ($ipRequests > 8 || $accountFailures >= 3) {
            return [
                'level' => 'medium',
                'reason' => 'elevated_login_risk',
            ];
        }

        return [
            'level' => 'low',
            'reason' => 'normal_request_pattern',
        ];
    }

    /**
     * Record a failed password attempt for an account.
     */
    public function recordFailure(string $email): int
    {
        $emailHash = hash(
            'sha256',
            strtolower(trim($email))
        );

        return $this->redis->increment(
            "login:risk:failures:{$emailHash}",
            15 * 60
        );
    }

    /**
     * Read the current account failure count.
     */
    public function getFailureCount(string $email): int
    {
        $emailHash = hash(
            'sha256',
            strtolower(trim($email))
        );

        return (int) (
            $this->redis->get(
                "login:risk:failures:{$emailHash}"
            ) ?? 0
        );
    }

    /**
     * Clear the account failure counter.
     */
    public function clearFailures(string $email): void
    {
        $emailHash = hash(
            'sha256',
            strtolower(trim($email))
        );

        $this->redis->delete(
            "login:risk:failures:{$emailHash}"
        );
    }
}