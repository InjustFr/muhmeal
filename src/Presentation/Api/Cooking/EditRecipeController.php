<?php

declare(strict_types=1);

namespace App\Presentation\Api\Cooking;

use App\Application\Cooking\EditRecipe\EditRecipe;
use App\Application\Cooking\EditRecipe\EditRecipeHandler;
use App\Application\Cooking\RecipeLineInput;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/recipes/{id}', name: 'api_recipes_edit', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class EditRecipeController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] RecipePayload $payload, EditRecipeHandler $editRecipe): JsonResponse
    {
        return $this->json($editRecipe(new EditRecipe(
            $id,
            $payload->name,
            array_map(static fn (RecipeLinePayload $line): RecipeLineInput => new RecipeLineInput($line->ingredientId, $line->amount), $payload->lines),
            $payload->output?->ingredientId,
            $payload->output?->yield,
        )));
    }
}
