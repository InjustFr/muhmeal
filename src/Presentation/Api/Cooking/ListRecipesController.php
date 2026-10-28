<?php

declare(strict_types=1);

namespace App\Presentation\Api\Cooking;

use App\Application\Cooking\RecipeQueries;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/recipes', name: 'api_recipes_list', methods: ['GET'], format: 'json')]
final class ListRecipesController extends AbstractController
{
    public function __invoke(RecipeQueries $recipes): JsonResponse
    {
        return $this->json($recipes->all());
    }
}
