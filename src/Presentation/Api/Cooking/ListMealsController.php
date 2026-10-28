<?php

declare(strict_types=1);

namespace App\Presentation\Api\Cooking;

use App\Application\Cooking\MealHistory;
use App\Presentation\Api\DayParameter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/meals', name: 'api_meals_list', methods: ['GET'], format: 'json')]
final class ListMealsController extends AbstractController
{
    public function __invoke(MealHistory $history, #[MapQueryParameter] ?string $before = null): JsonResponse
    {
        return $this->json($history->before(DayParameter::of($before)));
    }
}
