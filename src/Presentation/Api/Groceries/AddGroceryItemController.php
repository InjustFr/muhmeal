<?php

declare(strict_types=1);

namespace App\Presentation\Api\Groceries;

use App\Application\Groceries\AddGroceryItem\AddGroceryItem;
use App\Application\Groceries\AddGroceryItem\AddGroceryItemHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

#[Route('/api/grocery-list/items', name: 'api_grocery_list_add_item', methods: ['POST'], format: 'json')]
final class AddGroceryItemController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] AddGroceryItemPayload $payload, AddGroceryItemHandler $addGroceryItem): JsonResponse
    {
        return $this->json($addGroceryItem(new AddGroceryItem(Ulid::fromString($payload->ingredientId))));
    }
}
