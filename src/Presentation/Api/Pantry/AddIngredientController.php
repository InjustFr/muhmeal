<?php

declare(strict_types=1);

namespace App\Presentation\Api\Pantry;

use App\Application\Pantry\AddIngredient\AddIngredient;
use App\Application\Pantry\AddIngredient\AddIngredientHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/ingredients', name: 'api_ingredients_add', methods: ['POST'], format: 'json')]
final class AddIngredientController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] IngredientPayload $payload, AddIngredientHandler $addIngredient): JsonResponse
    {
        return $this->json($addIngredient(new AddIngredient($payload->name, $payload->location, $payload->unit, $payload->manual, $payload->lowAt)), Response::HTTP_CREATED);
    }
}
