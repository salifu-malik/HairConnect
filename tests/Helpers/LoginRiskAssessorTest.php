<?php

declare(strict_types=1);

namespace Helpers;

use App\Helpers\LoginRiskAssessor;
use App\Helpers\RedisManager;
use PHPUnit\Framework\TestCase;

final class LoginRiskAssessorTest extends TestCase
{
    private RedisManager $redis;
    private LoginRiskAssessor $assessor;

    protected function setUp(): void
    {
        $this->redis = new RedisManager();
        $this->assessor = new LoginRiskAssessor($this->redis);
    }

    public function testInitialRequestIsLowRisk(): void
    {
        $email = $this->uniqueEmail();
        $ip = $this->uniqueIp();

        try {
            $result = $this->assessor->assess($email, $ip);

            $this->assertSame('low', $result['level']);
            $this->assertSame(
                'normal_request_pattern',
                $result['reason']
            );
        } finally {
            $this->cleanUpKeys($email, $ip);
        }
    }

    public function testNinthRequestBecomesMediumRisk(): void
    {
        $email = $this->uniqueEmail();
        $ip = $this->uniqueIp();

        try {
            for ($i = 1; $i <= 8; $i++) {
                $result = $this->assessor->assess($email, $ip);
            }

            $this->assertSame('low', $result['level']);

            $result = $this->assessor->assess($email, $ip);

            $this->assertSame('medium', $result['level']);
            $this->assertSame(
                'elevated_login_risk',
                $result['reason']
            );
        } finally {
            $this->cleanUpKeys($email, $ip);
        }
    }

    public function testThirtyFirstRequestBecomesHighRisk(): void
    {
        $email = $this->uniqueEmail();
        $ip = $this->uniqueIp();

        try {
            for ($i = 1; $i <= 30; $i++) {
                $result = $this->assessor->assess($email, $ip);
            }

            $this->assertSame('medium', $result['level']);

            $result = $this->assessor->assess($email, $ip);

            $this->assertSame('high', $result['level']);
            $this->assertSame(
                'excessive_ip_request_rate',
                $result['reason']
            );
        } finally {
            $this->cleanUpKeys($email, $ip);
        }
    }

    public function testThreeAccountFailuresProduceMediumRisk(): void
    {
        $email = $this->uniqueEmail();
        $ip = $this->uniqueIp();

        try {
            $this->assertSame(
                1,
                $this->assessor->recordFailure($email)
            );

            $this->assertSame(
                2,
                $this->assessor->recordFailure($email)
            );

            $this->assertSame(
                3,
                $this->assessor->recordFailure($email)
            );

            $result = $this->assessor->assess($email, $ip);

            $this->assertSame('medium', $result['level']);
        } finally {
            $this->cleanUpKeys($email, $ip);
        }
    }

    public function testClearingFailuresResetsAccountCounter(): void
    {
        $email = $this->uniqueEmail();
        $ip = $this->uniqueIp();

        try {
            $this->assessor->recordFailure($email);
            $this->assessor->recordFailure($email);

            $this->assertSame(
                2,
                $this->assessor->getFailureCount($email)
            );

            $this->assessor->clearFailures($email);

            $this->assertSame(
                0,
                $this->assessor->getFailureCount($email)
            );
        } finally {
            $this->cleanUpKeys($email, $ip);
        }
    }

    private function uniqueEmail(): string
    {
        return 'phpunit-'
            . bin2hex(random_bytes(12))
            . '@example.invalid';
    }

    private function uniqueIp(): string
    {
        return 'phpunit-'
            . bin2hex(random_bytes(12));
    }

    private function cleanUpKeys(
        string $email,
        string $ip
    ): void {
        $emailHash = hash(
            'sha256',
            strtolower(trim($email))
        );

        $ipHash = hash('sha256', $ip);

        $this->redis->delete(
            "login:risk:failures:{$emailHash}"
        );

        $this->redis->delete(
            "login:risk:requests:{$ipHash}"
        );
    }
}