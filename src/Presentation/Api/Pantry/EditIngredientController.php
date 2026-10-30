<?php

declare(strict_types=1);

namespace App\Presentation\Api\Pantry;

use App\Application\Pantry\EditIngredient\EditIngredient;
use App\Application\Pantry\EditIngredient\EditIngredientHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/ingredients/{id}', name: 'api_ingredients_edit', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class EditIngredientController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] IngredientPayload $payload, EditIngredientHandler $editIngredient): JsonResponse
    {
        return $this->json($editIngredient(new EditIngredient($id, $payload->name, $payload->location, $payload->unit, $payload->manual, $payload->lowAt, $payload->months, null === $payload->variantOf ? null : Ulid::fromString($payload->variantOf))));
    }
}
