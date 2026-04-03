<?php

declare(strict_types=1);

namespace App\Controller;

use App\Health\ReadinessChecker;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class HealthController
{
    public function healthz(): Response
    {
        return new Response('OK', Response::HTTP_OK);
    }

    public function ready(ReadinessChecker $readiness): Response
    {
        $failures = $readiness->getFailures();
        if ([] === $failures) {
            return new Response('READY', Response::HTTP_OK);
        }

        return new JsonResponse([
            'status' => 'NOT_READY',
            'failures' => $failures,
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
