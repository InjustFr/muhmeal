<?php

declare(strict_types=1);

namespace App\Presentation\Api\Pantry;

use App\Application\Pantry\IngredientQueries;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/ingredients', name: 'api_ingredients_list', methods: ['GET'], format: 'json')]
final class ListIngredientsController extends AbstractController
{
    public function __invoke(IngredientQueries $ingredients): JsonResponse
    {
        return $this->json($ingredients->all());
    }
}
