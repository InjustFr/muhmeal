<?php

declare(strict_types=1);

namespace App\Presentation\Api\Cooking;

use App\Application\Cooking\RemoveMeal\RemoveMeal;
use App\Application\Cooking\RemoveMeal\RemoveMealHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/meals/{id}', name: 'api_meals_remove', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class RemoveMealController extends AbstractController
{
    public function __invoke(Ulid $id, RemoveMealHandler $removeMeal, #[MapQueryParameter(filter: \FILTER_VALIDATE_BOOL)] bool $giveBack = false): Response
    {
        $removeMeal(new RemoveMeal($id, $giveBack));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
