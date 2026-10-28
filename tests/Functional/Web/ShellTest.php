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

    /**
     * @param list<string> $preloaded
     */
    #[DataProvider('tabs')]
    public function testEveryTabGetsTheShellAndTheDataItLoads(string $path, array $preloaded): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#app'));
        self::assertSame(['/api/ingredients', ...$preloaded], array_keys(Json::decode($crawler->filter('#app-preload')->text())));
    }

    public function testTheCookPageCarriesTheRecipesAndTheHistoryTheMeals(): void
    {
        $client = self::signedInClient();

        $cook = Json::decode($client->request('GET', '/')->filter('#app-preload')->text());
        $history = Json::decode($client->request('GET', '/history')->filter('#app-preload')->text());

        self::assertSame([], Json::array($cook, '/api/recipes'));
        self::assertSame(['meals' => [], 'before' => null], Json::array($history, '/api/meals'));
    }

    public function testTheIngredientsTravelWithEveryPage(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/ingredients', ['name' => 'Milk', 'location' => 'fridge', 'unit' => 'carton']);

        $crawler = $client->request('GET', '/recipes');

        $preload = Json::decode($crawler->filter('#app-preload')->text());
        self::assertSame(['Milk'], array_column(Json::array($preload, '/api/ingredients'), 'name'));
    }

    /** @return iterable<array{string, list<string>}> */
    public static function tabs(): iterable
    {
        yield 'cook' => ['/', ['/api/recipes']];
        yield 'pantry' => ['/pantry', []];
        yield 'recipes' => ['/recipes', ['/api/recipes']];
        yield 'new recipe' => ['/recipes/new', ['/api/recipes']];
        yield 'a recipe' => ['/recipes/01JAAAAAAAAAAAAAAAAAAAAAAA', ['/api/recipes']];
        yield 'edit a recipe' => ['/recipes/01JAAAAAAAAAAAAAAAAAAAAAAA/edit', ['/api/recipes']];
        yield 'groceries' => ['/groceries', ['/api/grocery-list']];
        yield 'put away' => ['/groceries/put-away', ['/api/grocery-list']];
        yield 'history' => ['/history', ['/api/meals']];
        yield 'credits' => ['/credits', []];
        yield 'unknown page' => ['/nowhere', []];
        yield 'unknown page starting like a tab' => ['/recipesnowhere', []];
    }

    public function testTheSessionCarriesTheUserAndTheMembersOfTheKitchen(): void
    {
        $client = self::signedInClient('louis@muhmeal.test', 'Louis');
        $ada = self::createUser('ada@muhmeal.test', 'ada');

        $crawler = $client->request('GET', '/pantry');

        $session = Json::decode($crawler->filter('#app-session')->text());
        self::assertSame(['louis@muhmeal.test', 'Louis', 'https://accounts.test'], [Json::string($session, 'email'), Json::string($session, 'displayName'), Json::string($session, 'accountsUrl')]);
        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', Json::string($session, 'userId'));
        self::assertNotSame('', Json::string($session, 'logoutToken'));
        self::assertSame([['id' => $ada->id()->toBase32(), 'name' => 'ada'], ['id' => Json::string($session, 'userId'), 'name' => 'Louis']], Json::array($session, 'members'));
    }
}
