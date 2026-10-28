<?php

declare(strict_types=1);

namespace App\Presentation\Api\Groceries;

use App\Application\Groceries\StartGroceryList\StartGroceryList;
use App\Application\Groceries\StartGroceryList\StartGroceryListHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/grocery-list', name: 'api_grocery_list_start', methods: ['POST'], format: 'json')]
final class StartGroceryListController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] StartGroceryListPayload $payload, StartGroceryListHandler $startGroceryList): JsonResponse
    {
        return $this->json($startGroceryList(new StartGroceryList($payload->ingredientIds)), Response::HTTP_CREATED);
    }
}
