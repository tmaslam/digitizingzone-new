<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

class HttpCache
{
    public static function applyPrivateNoStore(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
