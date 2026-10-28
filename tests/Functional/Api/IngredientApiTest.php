<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class IngredientApiTest extends WebTestCase
{
    use SignsInClient;

    private const string UNKNOWN = '01JAAAAAAAAAAAAAAAAAAAAAAA';

    public function testIngredientLifecycle(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/ingredients', ['name' => '  Milk ', 'location' => 'fridge', 'unit' => 'carton']);
        self::assertResponseStatusCodeSame(201);
        $milk = self::body($client);
        $id = Json::string($milk, 'id');
        self::assertSame(['Milk', 'fridge', 'carton', false, 0, 3, true, true], [$milk['name'], $milk['location'], $milk['unit'], $milk['manual'], $milk['stock'], $milk['lowAt'], $milk['runningLow'], $milk['out']]);

        $client->jsonRequest('POST', "/api/ingredients/$id/adjust", ['by' => 24]);
        self::assertResponseIsSuccessful();
        self::assertSame([24, false, false], [Json::int(self::body($client), 'stock'), self::body($client)['runningLow'], self::body($client)['out']]);

        $client->jsonRequest('POST', "/api/ingredients/$id/adjust", ['by' => -30]);
        self::assertResponseIsSuccessful();
        self::assertSame([0, true], [Json::int(self::body($client), 'stock'), self::body($client)['out']]);

        $client->jsonRequest('PUT', "/api/ingredients/$id/stock", ['stock' => 9]);
        self::assertResponseIsSuccessful();
        self::assertSame(9, Json::int(self::body($client), 'stock'));

        $client->jsonRequest('PUT', "/api/ingredients/$id", ['name' => 'Oat milk', 'location' => 'pantry', 'unit' => 'bottle', 'manual' => true, 'lowAt' => 6]);
        self::assertResponseIsSuccessful();
        $edited = self::body($client);
        self::assertSame(['Oat milk', 'pantry', 'bottle', true, 9, 6, false], [$edited['name'], $edited['location'], $edited['unit'], $edited['manual'], $edited['stock'], $edited['lowAt'], $edited['runningLow']]);

        $client->jsonRequest('GET', '/api/ingredients');
        self::assertResponseIsSuccessful();
        self::assertSame(['Oat milk'], array_column(self::body($client), 'name'));

        $client->jsonRequest('DELETE', "/api/ingredients/$id");
        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $client->getResponse()->getContent());

        $client->jsonRequest('GET', '/api/ingredients');
        self::assertSame([], self::body($client));
    }

    public function testAnIngredientIsAddedWithItsStock(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/ingredients', ['name' => 'Milk', 'location' => 'fridge', 'unit' => 'carton', 'stock' => 21]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame([21, false, false], [Json::int(self::body($client), 'stock'), self::body($client)['runningLow'], self::body($client)['out']]);
    }

    public function testEditingNeverTouchesTheStock(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/ingredients', ['name' => 'Milk', 'location' => 'fridge', 'unit' => 'carton', 'stock' => 21]);
        $id = Json::string(self::body($client), 'id');

        $client->jsonRequest('PUT', "/api/ingredients/$id", ['name' => 'Milk', 'location' => 'fridge', 'unit' => 'carton', 'stock' => 0]);

        self::assertResponseIsSuccessful();
        self::assertSame(21, Json::int(self::body($client), 'stock'));
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidInitialStocks')]
    public function testInvalidInitialStocksAre422(array $payload): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/ingredients', ['name' => 'Milk', 'location' => 'fridge', 'unit' => 'carton', ...$payload]);

        self::assertResponseStatusCodeSame(422, (string) json_encode($payload));
        self::assertNotSame([], Json::array(self::body($client), 'violations'));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidInitialStocks(): iterable
    {
        yield 'negative' => [['stock' => -1]];
        yield 'above 999 units' => [['stock' => 11989]];
        yield 'a word' => [['stock' => 'plenty']];
    }

    public function testTheListIsSortedByNameIgnoringCase(): void
    {
        $client = self::signedInClient();
        foreach (['zucchini', 'Apples', 'beans'] as $name) {
            $client->jsonRequest('POST', '/api/ingredients', ['name' => $name, 'location' => 'pantry', 'unit' => 'piece']);
        }

        $client->jsonRequest('GET', '/api/ingredients');

        self::assertSame(['Apples', 'beans', 'zucchini'], array_column(self::body($client), 'name'));
    }

    public function testWritesAnswerWithTheRefreshedListWhenAsked(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/ingredients', ['name' => 'Eggs', 'location' => 'fridge', 'unit' => 'piece'], ['HTTP_X_REFRESH' => '["/api/ingredients"]']);

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHasHeader('X-Refreshed');
        self::assertSame(['Eggs'], array_column(Json::array(self::body($client), 'refreshed', '/api/ingredients'), 'name'));
        self::assertSame('Eggs', Json::string(self::body($client), 'data', 'name'));
    }

    public function testEveryEndpointNeedsASignedInUser(): void
    {
        $client = self::createClient();

        foreach ([
            ['GET', '/api/ingredients', []],
            ['POST', '/api/ingredients', ['name' => 'Milk', 'location' => 'fridge', 'unit' => 'carton']],
            ['PUT', '/api/ingredients/'.self::UNKNOWN, ['name' => 'Milk', 'location' => 'fridge', 'unit' => 'carton']],
            ['DELETE', '/api/ingredients/'.self::UNKNOWN, []],
            ['POST', '/api/ingredients/'.self::UNKNOWN.'/adjust', ['by' => 1]],
            ['PUT', '/api/ingredients/'.self::UNKNOWN.'/stock', ['stock' => 1]],
        ] as [$method, $path, $payload]) {
            $client->jsonRequest($method, $path, $payload);
            self::assertResponseStatusCodeSame(401, "$method $path");
        }
    }

    public function testEveryoneInTheKitchenSeesAndEditsTheSameIngredients(): void
    {
        $client = self::signedInClient('louis@muhmeal.test', 'Louis');
        $client->jsonRequest('POST', '/api/ingredients', ['name' => 'Milk', 'location' => 'fridge', 'unit' => 'carton']);
        $id = Json::string(self::body($client), 'id');

        $client->loginUser(SecurityUser::fromUser(self::createUser('ada@muhmeal.test', 'Ada')));
        $client->jsonRequest('POST', "/api/ingredients/$id/adjust", ['by' => 12]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('GET', '/api/ingredients');
        self::assertSame([12], array_column(self::body($client), 'stock'));
    }

    public function testUnknownIngredientsAre404(): void
    {
        $client = self::signedInClient();

        foreach ([
            ['PUT', '/api/ingredients/'.self::UNKNOWN, ['name' => 'Milk', 'location' => 'fridge', 'unit' => 'carton']],
            ['DELETE', '/api/ingredients/'.self::UNKNOWN, []],
            ['POST', '/api/ingredients/'.self::UNKNOWN.'/adjust', ['by' => 1]],
            ['PUT', '/api/ingredients/'.self::UNKNOWN.'/stock', ['stock' => 1]],
        ] as [$method, $path, $payload]) {
            $client->jsonRequest($method, $path, $payload);
            self::assertResponseStatusCodeSame(404, "$method $path");
            self::assertSame('This ingredient no longer exists.', Json::string(self::body($client), 'detail'));
        }
    }

    public function testMalformedIdentifiersAre404(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('DELETE', '/api/ingredients/not-an-id');

        self::assertResponseStatusCodeSame(404);
    }

    public function testNamesAreUniqueIgnoringCase(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/ingredients', ['name' => 'Olive oil', 'location' => 'pantry', 'unit' => 'bottle']);
        $oil = Json::string(self::body($client), 'id');
        $client->jsonRequest('POST', '/api/ingredients', ['name' => 'Milk', 'location' => 'fridge', 'unit' => 'carton']);
        $milk = Json::string(self::body($client), 'id');

        $client->jsonRequest('POST', '/api/ingredients', ['name' => 'OLIVE OIL', 'location' => 'pantry', 'unit' => 'bottle']);
        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('There is already an ingredient called “OLIVE OIL”.', Json::string(self::body($client), 'detail'));

        $client->jsonRequest('PUT', "/api/ingredients/$milk", ['name' => 'olive oil', 'location' => 'fridge', 'unit' => 'carton']);
        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $client->jsonRequest('PUT', "/api/ingredients/$oil", ['name' => 'olive OIL', 'location' => 'pantry', 'unit' => 'bottle']);
        self::assertResponseIsSuccessful();
    }

    public function testBusinessRulesAnswerInTheCallersLanguage(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/ingredients', ['name' => 'Beurre', 'location' => 'fridge', 'unit' => 'pack']);

        $client->jsonRequest('POST', '/api/ingredients', ['name' => 'beurre', 'location' => 'fridge', 'unit' => 'pack'], ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Il existe déjà un ingrédient nommé « beurre ».', Json::string(self::body($client), 'detail'));
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidIngredients')]
    public function testInvalidIngredientsAre422(array $payload): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/ingredients', ['name' => 'Milk', 'location' => 'fridge', 'unit' => 'carton']);
        $id = Json::string(self::body($client), 'id');

        $client->jsonRequest('POST', '/api/ingredients', $payload);
        self::assertResponseStatusCodeSame(422, (string) json_encode($payload));
        self::assertNotSame([], Json::array(self::body($client), 'violations'));

        $client->jsonRequest('PUT', "/api/ingredients/$id", $payload);
        self::assertResponseStatusCodeSame(422, (string) json_encode($payload));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidIngredients(): iterable
    {
        yield 'no name' => [['location' => 'pantry', 'unit' => 'piece']];
        yield 'blank name' => [['name' => '  ', 'location' => 'pantry', 'unit' => 'piece']];
        yield 'long name' => [['name' => str_repeat('a', 61), 'location' => 'pantry', 'unit' => 'piece']];
        yield 'unknown location' => [['name' => 'Rice', 'location' => 'garage', 'unit' => 'piece']];
        yield 'no location' => [['name' => 'Rice', 'unit' => 'piece']];
        yield 'unknown unit' => [['name' => 'Rice', 'location' => 'pantry', 'unit' => 'kilo']];
        yield 'no unit' => [['name' => 'Rice', 'location' => 'pantry']];
        yield 'manual is not a flag' => [['name' => 'Rice', 'location' => 'pantry', 'unit' => 'piece', 'manual' => 'yes']];
        yield 'negative threshold' => [['name' => 'Rice', 'location' => 'pantry', 'unit' => 'piece', 'lowAt' => -1]];
        yield 'threshold above 999 units' => [['name' => 'Rice', 'location' => 'pantry', 'unit' => 'piece', 'lowAt' => 11989]];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidAdjustments')]
    public function testInvalidAdjustmentsAre422(array $payload): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/ingredients', ['name' => 'Eggs', 'location' => 'fridge', 'unit' => 'piece']);
        $id = Json::string(self::body($client), 'id');

        $client->jsonRequest('POST', "/api/ingredients/$id/adjust", $payload);

        self::assertResponseStatusCodeSame(422, (string) json_encode($payload));
        self::assertNotSame([], Json::array(self::body($client), 'violations'));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidAdjustments(): iterable
    {
        yield 'nothing' => [[]];
        yield 'zero' => [['by' => 0]];
        yield 'too much' => [['by' => 11989]];
        yield 'too little' => [['by' => -11989]];
        yield 'a word' => [['by' => 'lots']];
    }

    public function testAdjustingPastTheCeilingIs422(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/ingredients', ['name' => 'Eggs', 'location' => 'fridge', 'unit' => 'piece']);
        $id = Json::string(self::body($client), 'id');
        $client->jsonRequest('PUT', "/api/ingredients/$id/stock", ['stock' => 11988]);

        $client->jsonRequest('POST', "/api/ingredients/$id/adjust", ['by' => 1]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('A quantity goes from 0 to 999 units.', Json::string(self::body($client), 'detail'));
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidStocks')]
    public function testInvalidStocksAre422(array $payload): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/ingredients', ['name' => 'Eggs', 'location' => 'fridge', 'unit' => 'piece']);
        $id = Json::string(self::body($client), 'id');

        $client->jsonRequest('PUT', "/api/ingredients/$id/stock", $payload);

        self::assertResponseStatusCodeSame(422, (string) json_encode($payload));
        self::assertNotSame([], Json::array(self::body($client), 'violations'));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidStocks(): iterable
    {
        yield 'nothing' => [[]];
        yield 'negative' => [['stock' => -1]];
        yield 'above 999 units' => [['stock' => 11989]];
        yield 'a word' => [['stock' => 'plenty']];
    }

    public function testValidationMessagesFollowTheLanguage(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/ingredients', ['name' => '', 'location' => 'pantry', 'unit' => 'piece'], ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['Saisissez un nom.'], array_column(Json::array(self::body($client), 'violations'), 'title'));
    }

    /**
     * @return array<mixed>
     */
    private static function body(KernelBrowser $client): array
    {
        return Json::decode((string) $client->getResponse()->getContent());
    }
}
