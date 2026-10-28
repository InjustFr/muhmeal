<?php

declare(strict_types=1);

namespace App\Presentation\Api\Cooking;

use App\Application\Cooking\RemoveRecipe\RemoveRecipe;
use App\Application\Cooking\RemoveRecipe\RemoveRecipeHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/recipes/{id}', name: 'api_recipes_remove', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class RemoveRecipeController extends AbstractController
{
    public function __invoke(Ulid $id, RemoveRecipeHandler $removeRecipe): Response
    {
        $removeRecipe(new RemoveRecipe($id));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
