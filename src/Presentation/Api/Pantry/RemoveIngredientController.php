<?php

declare(strict_types=1);

namespace App\Presentation\Api\Pantry;

use App\Application\Pantry\RemoveIngredient\RemoveIngredient;
use App\Application\Pantry\RemoveIngredient\RemoveIngredientHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/ingredients/{id}', name: 'api_ingredients_remove', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class RemoveIngredientController extends AbstractController
{
    public function __invoke(Ulid $id, RemoveIngredientHandler $removeIngredient): Response
    {
        $removeIngredient(new RemoveIngredient($id));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
