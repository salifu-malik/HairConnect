<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SubscriptionDatabaseConfigTest extends TestCase
{
    public function testSubscriptionDatabaseUsesIsolatedTestDatabase(): void
    {
        $config = require dirname(__DIR__) . '/config/database.php';

        $this->assertSame(
            'subscription_test_db',
            $config['connections']['subscription_db']['dbname']
        );
    }
}
