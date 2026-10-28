<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ShellTest extends WebTestCase
{
    use SignsInClient;

    public function testSignedInUsersSkipTheLogin(): void
    {
        $client = self::signedInClient();

        $client->request('GET', '/login');

        self::assertResponseRedirects('/');
    }

    public function testSignedOutVisitorsAreSentToLogin(): void
    {
        $client = self::createClient();

        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    #[DataProvider('tabs')]
    public function testEveryTabGetsTheShell(string $path): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#app'));
        self::assertSame([], Json::decode($crawler->filter('#app-preload')->text()));
    }

    /** @return iterable<array{string}> */
    public static function tabs(): iterable
    {
        yield 'cook' => ['/'];
        yield 'pantry' => ['/pantry'];
        yield 'recipes' => ['/recipes'];
        yield 'groceries' => ['/groceries'];
        yield 'history' => ['/history'];
        yield 'credits' => ['/credits'];
        yield 'unknown page' => ['/nowhere'];
    }

    public function testTheSessionCarriesTheUserAndTheMembersOfTheKitchen(): void
    {
        $client = self::signedInClient('louis@mossy-meals.test', 'Louis');
        $ada = self::createUser('ada@mossy-meals.test', 'ada');

        $crawler = $client->request('GET', '/pantry');

        $session = Json::decode($crawler->filter('#app-session')->text());
        self::assertSame(['louis@mossy-meals.test', 'Louis', 'https://accounts.test'], [Json::string($session, 'email'), Json::string($session, 'displayName'), Json::string($session, 'accountsUrl')]);
        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', Json::string($session, 'userId'));
        self::assertNotSame('', Json::string($session, 'logoutToken'));
        self::assertSame([['id' => $ada->id()->toBase32(), 'name' => 'ada'], ['id' => Json::string($session, 'userId'), 'name' => 'Louis']], Json::array($session, 'members'));
    }
}
