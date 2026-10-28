<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GroceryApiTest extends WebTestCase
{
    use SignsInClient;

    private const string UNKNOWN = '01JAAAAAAAAAAAAAAAAAAAAAAA';

    public function testGroceryLifecycle(): void
    {
        $client = self::signedInClient();
        $milk = self::ingredient($client, 'Milk', 0, 'carton', 'fridge');
        $eggs = self::ingredient($client, 'Eggs', 24);

        $client->jsonRequest('GET', '/api/grocery-list');
        self::assertResponseStatusCodeSame(200);
        self::assertSame('null', $client->getResponse()->getContent());

        $client->jsonRequest('POST', '/api/grocery-list', ['ingredientIds' => [$milk]]);
        self::assertResponseStatusCodeSame(201);
        $list = self::body($client);
        self::assertSame(['Louis', [[$milk, 'Milk', 'carton', 'fridge', 0, false]]], [Json::string($list, 'startedBy', 'name'), array_map(static fn (mixed $item): array => [Json::string($item, 'ingredientId'), Json::string($item, 'name'), Json::string($item, 'unit'), Json::string($item, 'location'), Json::int($item, 'stock'), Json::at($item, 'ticked')], Json::array($list, 'items'))]);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', Json::string($list, 'startedAt'));
        self::assertSame(Json::string($list, 'id'), Json::string(self::get($client), 'id'));

        $client->jsonRequest('POST', '/api/grocery-list/items', ['ingredientId' => $eggs]);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['Eggs', 'Milk'], array_column(Json::array(self::body($client), 'items'), 'name'));

        $client->jsonRequest('POST', '/api/grocery-list/items', ['ingredientId' => $eggs]);
        self::assertResponseStatusCodeSame(200);
        self::assertCount(2, Json::array(self::body($client), 'items'));

        $milkItem = Json::string(self::body($client), 'items', 1, 'id');
        $client->jsonRequest('PUT', "/api/grocery-list/items/$milkItem/ticked", ['ticked' => true]);
        self::assertResponseStatusCodeSame(200);
        self::assertSame([false, true], array_column(Json::array(self::body($client), 'items'), 'ticked'));

        $client->jsonRequest('PUT', "/api/grocery-list/items/$milkItem/ticked", ['ticked' => true]);
        self::assertSame([false, true], array_column(Json::array(self::body($client), 'items'), 'ticked'));

        $client->jsonRequest('PUT', "/api/grocery-list/items/$milkItem/ticked", ['ticked' => false]);
        self::assertSame([false, false], array_column(Json::array(self::body($client), 'items'), 'ticked'));

        $client->jsonRequest('POST', '/api/grocery-list/put-away', ['bought' => [['ingredientId' => $milk, 'amount' => 12]]]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $client->getResponse()->getContent());
        self::assertSame([24, 12], self::stocks($client));

        $client->jsonRequest('GET', '/api/grocery-list');
        self::assertSame('null', $client->getResponse()->getContent());
    }

    public function testIngredientsCarryTheHomemadeAndSuggestedFlags(): void
    {
        $client = self::signedInClient();
        self::ingredient($client, 'Milk', 0, 'carton');
        self::ingredient($client, 'Cheese', 36, 'pack');
        $gluten = self::ingredient($client, 'Gluten', 24, 'bag');
        $seitan = self::ingredient($client, 'Seitan', 0, 'bag');
        $client->jsonRequest('POST', '/api/recipes', ['name' => 'Seitan', 'lines' => [['ingredientId' => $gluten, 'amount' => 12]], 'output' => ['ingredientId' => $seitan, 'yield' => 12]]);
        self::assertResponseStatusCodeSame(201);

        $client->jsonRequest('GET', '/api/ingredients');

        $flags = [];
        foreach (Json::array(self::body($client)) as $ingredient) {
            $flags[Json::string($ingredient, 'name')] = [Json::at($ingredient, 'homemade'), Json::at($ingredient, 'suggested')];
        }
        self::assertSame(['Cheese' => [false, false], 'Gluten' => [false, false], 'Milk' => [false, true], 'Seitan' => [true, false]], $flags);
    }

    public function testOnlyOneListCanBeOpen(): void
    {
        $client = self::signedInClient();
        $milk = self::ingredient($client, 'Milk', 0);
        $client->jsonRequest('POST', '/api/grocery-list', ['ingredientIds' => [$milk]]);

        $client->jsonRequest('POST', '/api/grocery-list', ['ingredientIds' => [$milk]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('A shopping trip is already open. Finish it first.', Json::string(self::body($client), 'detail'));
    }

    public function testAListNeedsAtLeastOneIngredient(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/grocery-list', ['ingredientIds' => []]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Pick at least one ingredient to shop for.', Json::string(self::body($client), 'detail'));
    }

    public function testWritesAnswerWithTheRefreshedListWhenAsked(): void
    {
        $client = self::signedInClient();
        $milk = self::ingredient($client, 'Milk', 0);
        $client->jsonRequest('POST', '/api/grocery-list', ['ingredientIds' => [$milk]], ['HTTP_X_REFRESH' => '["/api/grocery-list"]']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['Milk'], array_column(Json::array(self::body($client), 'refreshed', '/api/grocery-list', 'items'), 'name'));

        $client->jsonRequest('POST', '/api/grocery-list/put-away', ['bought' => []], ['HTTP_X_REFRESH' => '["/api/grocery-list"]']);

        self::assertResponseStatusCodeSame(200);
        self::assertNull(Json::at(self::body($client), 'refreshed', '/api/grocery-list'));
    }

    public function testThePageIsPreloadedWithTheOpenList(): void
    {
        $client = self::signedInClient();
        $milk = self::ingredient($client, 'Milk', 0);
        $client->jsonRequest('POST', '/api/grocery-list', ['ingredientIds' => [$milk]]);

        foreach (['/groceries', '/groceries/put-away'] as $path) {
            $preload = Json::decode($client->request('GET', $path)->filter('#app-preload')->text());

            self::assertSame(['Milk'], array_column(Json::array($preload, '/api/grocery-list', 'items'), 'name'), $path);
        }

        $client->jsonRequest('POST', '/api/grocery-list/put-away', ['bought' => []]);
        self::assertNull(Json::at(Json::decode($client->request('GET', '/groceries')->filter('#app-preload')->text()), '/api/grocery-list'));
    }

    public function testEveryoneInTheKitchenSharesTheList(): void
    {
        $client = self::signedInClient('louis@muhmeal.test', 'Louis');
        $milk = self::ingredient($client, 'Milk', 0);
        $client->jsonRequest('POST', '/api/grocery-list', ['ingredientIds' => [$milk]]);
        $item = Json::string(self::body($client), 'items', 0, 'id');

        $client->loginUser(SecurityUser::fromUser(self::createUser('ada@muhmeal.test', 'Ada')));
        $client->jsonRequest('PUT', "/api/grocery-list/items/$item/ticked", ['ticked' => true]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('GET', '/api/grocery-list');
        self::assertSame([true, 'Louis'], [Json::at(self::body($client), 'items', 0, 'ticked'), Json::string(self::body($client), 'startedBy', 'name')]);
    }

    public function testEveryEndpointNeedsASignedInUser(): void
    {
        $client = self::createClient();

        foreach ([
            ['GET', '/api/grocery-list', []],
            ['POST', '/api/grocery-list', ['ingredientIds' => [self::UNKNOWN]]],
            ['POST', '/api/grocery-list/items', ['ingredientId' => self::UNKNOWN]],
            ['PUT', '/api/grocery-list/items/'.self::UNKNOWN.'/ticked', ['ticked' => true]],
            ['POST', '/api/grocery-list/put-away', ['bought' => []]],
        ] as [$method, $path, $payload]) {
            $client->jsonRequest($method, $path, $payload);
            self::assertResponseStatusCodeSame(401, "$method $path");
        }
    }

    public function testWithoutAnOpenListEveryWriteIs404(): void
    {
        $client = self::signedInClient();
        $milk = self::ingredient($client, 'Milk', 0);

        foreach ([
            ['POST', '/api/grocery-list/items', ['ingredientId' => $milk]],
            ['PUT', '/api/grocery-list/items/'.self::UNKNOWN.'/ticked', ['ticked' => true]],
            ['POST', '/api/grocery-list/put-away', ['bought' => []]],
        ] as [$method, $path, $payload]) {
            $client->jsonRequest($method, $path, $payload);
            self::assertResponseStatusCodeSame(404, "$method $path");
            self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
            self::assertSame('This grocery list no longer exists.', Json::string(self::body($client), 'detail'));
        }
    }

    public function testAPutAwayListIsClosedForEveryone(): void
    {
        $client = self::signedInClient();
        $milk = self::ingredient($client, 'Milk', 0);
        $client->jsonRequest('POST', '/api/grocery-list', ['ingredientIds' => [$milk]]);
        $item = Json::string(self::body($client), 'items', 0, 'id');
        $client->jsonRequest('POST', '/api/grocery-list/put-away', ['bought' => []]);

        $client->jsonRequest('PUT', "/api/grocery-list/items/$item/ticked", ['ticked' => true]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnknownItemsAndIngredientsAre404(): void
    {
        $client = self::signedInClient();
        $milk = self::ingredient($client, 'Milk', 0);
        $client->jsonRequest('POST', '/api/grocery-list', ['ingredientIds' => [$milk]]);

        $client->jsonRequest('PUT', '/api/grocery-list/items/'.self::UNKNOWN.'/ticked', ['ticked' => true]);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('This grocery item no longer exists.', Json::string(self::body($client), 'detail'));

        foreach ([
            ['POST', '/api/grocery-list/items', ['ingredientId' => self::UNKNOWN]],
            ['POST', '/api/grocery-list/put-away', ['bought' => [['ingredientId' => self::UNKNOWN, 'amount' => 12]]]],
        ] as [$method, $path, $payload]) {
            $client->jsonRequest($method, $path, $payload);
            self::assertResponseStatusCodeSame(404, "$method $path");
            self::assertSame('This ingredient no longer exists.', Json::string(self::body($client), 'detail'));
        }

        $client->jsonRequest('POST', '/api/grocery-list', ['ingredientIds' => [self::UNKNOWN]]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testMalformedIdentifiersAre404(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/grocery-list/items/not-an-id/ticked', ['ticked' => true]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeletingAnIngredientRemovesItFromTheOpenList(): void
    {
        $client = self::signedInClient();
        $milk = self::ingredient($client, 'Milk', 0);
        $eggs = self::ingredient($client, 'Eggs', 0);
        $client->jsonRequest('POST', '/api/grocery-list', ['ingredientIds' => [$milk, $eggs]]);

        $client->jsonRequest('DELETE', "/api/ingredients/$milk");
        self::assertResponseStatusCodeSame(204);

        self::assertSame(['Eggs'], array_column(Json::array(self::get($client), 'items'), 'name'));
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidStarts')]
    public function testInvalidStartsAre422(array $payload): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/grocery-list', $payload);

        self::assertResponseStatusCodeSame(422, (string) json_encode($payload));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidStarts(): iterable
    {
        yield 'not a list' => [['ingredientIds' => 'milk']];
        yield 'not an id' => [['ingredientIds' => ['milk']]];
        yield 'not a string' => [['ingredientIds' => [12]]];
        yield 'nested' => [['ingredientIds' => [['x']]]];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidAdditions')]
    public function testInvalidAdditionsAre422(array $payload): void
    {
        $client = self::signedInClient();
        $milk = self::ingredient($client, 'Milk', 0);
        $client->jsonRequest('POST', '/api/grocery-list', ['ingredientIds' => [$milk]]);

        $client->jsonRequest('POST', '/api/grocery-list/items', $payload);

        self::assertResponseStatusCodeSame(422, (string) json_encode($payload));
        self::assertNotSame([], Json::array(self::body($client), 'violations'));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidAdditions(): iterable
    {
        yield 'nothing' => [[]];
        yield 'blank' => [['ingredientId' => '']];
        yield 'not an id' => [['ingredientId' => 'milk']];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidTicks')]
    public function testInvalidTicksAre422(array $payload): void
    {
        $client = self::signedInClient();
        $milk = self::ingredient($client, 'Milk', 0);
        $client->jsonRequest('POST', '/api/grocery-list', ['ingredientIds' => [$milk]]);
        $item = Json::string(self::body($client), 'items', 0, 'id');

        $client->jsonRequest('PUT', "/api/grocery-list/items/$item/ticked", $payload);

        self::assertResponseStatusCodeSame(422, (string) json_encode($payload));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidTicks(): iterable
    {
        yield 'nothing' => [[]];
        yield 'a word' => [['ticked' => 'yes']];
        yield 'null' => [['ticked' => null]];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPutAways')]
    public function testInvalidPutAwaysAre422(array $payload): void
    {
        $client = self::signedInClient();
        $milk = self::ingredient($client, 'Milk', 3, 'carton');
        $client->jsonRequest('POST', '/api/grocery-list', ['ingredientIds' => [$milk]]);
        $payload = json_decode(str_replace('MILK', $milk, (string) json_encode($payload)), true);
        self::assertIsArray($payload);

        $client->jsonRequest('POST', '/api/grocery-list/put-away', $payload);

        self::assertResponseStatusCodeSame(422, (string) json_encode($payload));
        self::assertSame([3], self::stocks($client));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidPutAways(): iterable
    {
        yield 'zero' => [['bought' => [['ingredientId' => 'MILK', 'amount' => 0]]]];
        yield 'negative' => [['bought' => [['ingredientId' => 'MILK', 'amount' => -12]]]];
        yield 'above 999 units' => [['bought' => [['ingredientId' => 'MILK', 'amount' => 11989]]]];
        yield 'a word' => [['bought' => [['ingredientId' => 'MILK', 'amount' => 'lots']]]];
        yield 'not an id' => [['bought' => [['ingredientId' => 'milk', 'amount' => 12]]]];
        yield 'bought twice' => [['bought' => [['ingredientId' => 'MILK', 'amount' => 12], ['ingredientId' => 'MILK', 'amount' => 12]]]];
        yield 'not a list' => [['bought' => 'milk']];
    }

    public function testPuttingAwayPastTheCeilingIs422AndKeepsTheList(): void
    {
        $client = self::signedInClient();
        $milk = self::ingredient($client, 'Milk', 11988, 'carton');
        $client->jsonRequest('POST', '/api/grocery-list', ['ingredientIds' => [$milk]]);

        $client->jsonRequest('POST', '/api/grocery-list/put-away', ['bought' => [['ingredientId' => $milk, 'amount' => 1]]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('A quantity goes from 0 to 999 units.', Json::string(self::body($client), 'detail'));
        self::assertSame('Milk', Json::string(self::get($client), 'items', 0, 'name'));
    }

    public function testBusinessRulesAnswerInTheCallersLanguage(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/grocery-list', ['ingredientIds' => []], ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Choisissez au moins un ingrédient à acheter.', Json::string(self::body($client), 'detail'));
    }

    private static function ingredient(KernelBrowser $client, string $name, int $stock, string $unit = 'piece', string $location = 'pantry'): string
    {
        $client->jsonRequest('POST', '/api/ingredients', ['name' => $name, 'location' => $location, 'unit' => $unit, 'stock' => $stock]);
        self::assertResponseStatusCodeSame(201);

        return Json::string(self::body($client), 'id');
    }

    /**
     * @return array<mixed>
     */
    private static function get(KernelBrowser $client): array
    {
        $client->jsonRequest('GET', '/api/grocery-list');

        return self::body($client);
    }

    /**
     * @return list<int>
     */
    private static function stocks(KernelBrowser $client): array
    {
        $client->jsonRequest('GET', '/api/ingredients');

        return array_values(array_map(static fn (mixed $ingredient): int => Json::int($ingredient, 'stock'), Json::array(self::body($client))));
    }

    /**
     * @return array<mixed>
     */
    private static function body(KernelBrowser $client): array
    {
        return Json::decode((string) $client->getResponse()->getContent());
    }
}
