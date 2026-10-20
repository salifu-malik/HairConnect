<?php

namespace App\Helpers;

class RequestBodyReader
{
    public function read(): mixed
    {
        $rawBody = file_get_contents('php://input');

        if ($rawBody === false || $rawBody === '') {
            return null;
        }

        return json_decode($rawBody, true);
    }
}