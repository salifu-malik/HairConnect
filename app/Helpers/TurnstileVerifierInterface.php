<?php

namespace App\Helpers;

interface TurnstileVerifierInterface
{
    public function verify(string $token): bool;
}
