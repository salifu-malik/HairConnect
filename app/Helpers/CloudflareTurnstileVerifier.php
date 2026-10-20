<?php

namespace App\Helpers;

class CloudflareTurnstileVerifier implements TurnstileVerifierInterface
{
    public function verify(string $token): bool
    {
        return TurnstileVerifier::verify($token);
    }
}
