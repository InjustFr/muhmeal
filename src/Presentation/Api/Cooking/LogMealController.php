<?php

declare(strict_types=1);

namespace App\Presentation\Api\Cooking;

use App\Application\Cooking\LogMeal\LogMeal;
use App\Application\Cooking\LogMeal\LogMealHandler;
use App\Application\Cooking\UsedInput;
use App\Domain\Shared\Day;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/meals', name: 'api_meals_log', methods: ['POST'], format: 'json')]
final class LogMealController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] MealPayload $payload, LogMealHandler $logMeal): JsonResponse
    {
        return $this->json($logMeal(new LogMeal(
            $payload->title,
            $payload->type,
            Day::of($payload->eatenOn),
            $payload->eaterIds,
            array_map(static fn (UsedPayload $used): UsedInput => new UsedInput($used->ingredientId, $used->amount), $payload->used),
        )), Response::HTTP_CREATED);
    }
}
