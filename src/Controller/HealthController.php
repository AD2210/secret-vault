<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

final class HealthController
{
    public function healthz(): Response
    {
        return new Response('OK', Response::HTTP_OK);
    }

    public function ready(): Response
    {
        return new Response('READY', Response::HTTP_OK);
    }
}
