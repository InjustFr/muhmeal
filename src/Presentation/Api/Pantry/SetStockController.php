<?php

declare(strict_types=1);

namespace App\Presentation\Api\Pantry;

use App\Application\Pantry\SetStock\SetStock;
use App\Application\Pantry\SetStock\SetStockHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/ingredients/{id}/stock', name: 'api_ingredients_set_stock', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class SetStockController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] StockPayload $payload, SetStockHandler $setStock): JsonResponse
    {
        return $this->json($setStock(new SetStock($id, $payload->stock)));
    }
}
