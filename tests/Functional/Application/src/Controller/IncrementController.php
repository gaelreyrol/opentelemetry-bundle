<?php

namespace App\Controller;

use App\Service\DummyMeterService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class IncrementController
{
    public function __construct(
        private readonly DummyMeterService $meterService,
    ) {
    }

    #[Route('/increment/{value}', methods: ['GET'], requirements: ['value' => '-?\d+'])]
    public function __invoke(int $value): JsonResponse
    {
        $this->meterService->count([$value]);

        return new JsonResponse(['value' => $value]);
    }
}
