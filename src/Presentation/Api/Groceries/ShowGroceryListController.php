<?php

declare(strict_types=1);

namespace App\Presentation\Api\Groceries;

use App\Application\Groceries\GroceryQueries;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/grocery-list', name: 'api_grocery_list_show', methods: ['GET'], format: 'json')]
final class ShowGroceryListController extends AbstractController
{
    public function __invoke(GroceryQueries $groceries): JsonResponse
    {
        return $this->json($groceries->open());
    }
}
