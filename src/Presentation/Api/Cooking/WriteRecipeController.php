<?php

declare(strict_types=1);

namespace App\Presentation\Api\Cooking;

use App\Application\Cooking\RecipeLineInput;
use App\Application\Cooking\WriteRecipe\WriteRecipe;
use App\Application\Cooking\WriteRecipe\WriteRecipeHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/recipes', name: 'api_recipes_write', methods: ['POST'], format: 'json')]
final class WriteRecipeController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] RecipePayload $payload, WriteRecipeHandler $writeRecipe): JsonResponse
    {
        return $this->json($writeRecipe(new WriteRecipe(
            $payload->name,
            array_map(static fn (RecipeLinePayload $line): RecipeLineInput => new RecipeLineInput($line->ingredientId, $line->amount), $payload->lines),
            $payload->output?->ingredientId,
            $payload->output?->yield,
        )), Response::HTTP_CREATED);
    }
}
