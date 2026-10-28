<?php

declare(strict_types=1);

namespace App\Presentation\Api\Groceries;

use App\Application\Groceries\BoughtInput;
use App\Application\Groceries\PutAwayGroceries\PutAwayGroceries;
use App\Application\Groceries\PutAwayGroceries\PutAwayGroceriesHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/grocery-list/put-away', name: 'api_grocery_list_put_away', methods: ['POST'], format: 'json')]
final class PutAwayGroceriesController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] PutAwayPayload $payload, PutAwayGroceriesHandler $putAwayGroceries): Response
    {
        $putAwayGroceries(new PutAwayGroceries(array_map(
            static fn (BoughtPayload $bought): BoughtInput => new BoughtInput($bought->ingredientId, $bought->amount),
            $payload->bought,
        )));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
