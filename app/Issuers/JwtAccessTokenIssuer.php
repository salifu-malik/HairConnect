<?php

declare(strict_types=1);

namespace App\Issuers;

use App\Helpers\JwtHelper;

class JwtAccessTokenIssuer implements AccessTokenIssuerInterface
{
    public function issue(array $payload, int $expiry = 3600): string
    {
        return JwtHelper::encode($payload, $expiry);
    }
}