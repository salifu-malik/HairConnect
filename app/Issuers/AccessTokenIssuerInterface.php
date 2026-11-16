<?php

declare(strict_types=1);

namespace App\Issuers;

interface AccessTokenIssuerInterface
{
    public function issue(array $payload, int $expiry = 3600): string;
}