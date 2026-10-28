<?php

declare(strict_types=1);

namespace App\Presentation\Api\Cooking;

use App\Application\Cooking\MakePrep\MakePrep;
use App\Application\Cooking\MakePrep\MakePrepHandler;
use App\Application\Cooking\UsedInput;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/recipes/{id}/make', name: 'api_recipes_make', requirements: ['id' => Requirement::ULID], methods: ['POST'], format: 'json')]
final class MakePrepController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] MakePrepPayload $payload, MakePrepHandler $makePrep): JsonResponse
    {
        return $this->json($makePrep(new MakePrep(
            $id,
            array_map(static fn (UsedPayload $used): UsedInput => new UsedInput($used->ingredientId, $used->amount), $payload->used),
            $payload->made,
        )));
    }
}
