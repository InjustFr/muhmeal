<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CrawlersTest extends WebTestCase
{
    use SignsInClient;

    public function testRobotsTxtDisallowsEverything(): void
    {
        $robots = file_get_contents(self::getContainer()->getParameter('kernel.project_dir').'/public/robots.txt');

        self::assertSame("User-agent: *\nDisallow: /\n", $robots);
    }

    public function testRobotsTxtIsNeverAnsweredByTheShell(): void
    {
        $client = self::createClient();

        $client->request('GET', '/robots.txt');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheShellAsksNotToBeIndexed(): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', '/');

        self::assertSame('noindex, nofollow', $crawler->filter('meta[name=robots]')->attr('content'));
    }

    public function testTheSignInPageAsksNotToBeIndexed(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');
        $client->request('GET', '/login/check');

        $crawler = $client->followRedirect();

        self::assertSame('noindex, nofollow', $crawler->filter('meta[name=robots]')->attr('content'));
    }
}
