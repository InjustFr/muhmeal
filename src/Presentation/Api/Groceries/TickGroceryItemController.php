<?php

declare(strict_types=1);

namespace App\Presentation\Api\Groceries;

use App\Application\Groceries\TickGroceryItem\TickGroceryItem;
use App\Application\Groceries\TickGroceryItem\TickGroceryItemHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/grocery-list/items/{id}/ticked', name: 'api_grocery_list_tick_item', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class TickGroceryItemController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] TickPayload $payload, TickGroceryItemHandler $tickGroceryItem): JsonResponse
    {
        return $this->json($tickGroceryItem(new TickGroceryItem($id, $payload->ticked)));
    }
}
