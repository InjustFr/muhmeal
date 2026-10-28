<?php

declare(strict_types=1);

namespace App\Presentation\Web;

use App\Application\Identity\KitchenMembers;
use App\Presentation\ApiPreload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{path}', name: 'app', requirements: ['path' => '(?!api/|build|_|login|logout|robots\.txt).*'], defaults: ['path' => ''], methods: ['GET'], priority: -100)]
final class AppShellController extends AbstractController
{
    private const array ALWAYS = ['/api/ingredients'];

    public function __construct(
        private readonly ApiPreload $preload,
        private readonly KitchenMembers $members,
    ) {
    }

    public function __invoke(string $path): Response
    {
        return $this->render('app.html.twig', [
            'preloaded' => $this->preload->json([...self::ALWAYS, ...$this->pageUrls(trim($path, '/'))]),
            'members' => $this->members->all(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function pageUrls(string $path): array
    {
        return match (true) {
            '' === $path, 'recipes' === $path, str_starts_with($path, 'recipes/') => ['/api/recipes'],
            'history' === $path => ['/api/meals'],
            default => [],
        };
    }
}
