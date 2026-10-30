<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RecipeApiTest extends WebTestCase
{
    use SignsInClient;

    private const string UNKNOWN = '01JAAAAAAAAAAAAAAAAAAAAAAA';

    public function testRecipeLifecycle(): void
    {
        $client = self::signedInClient();
        $pasta = self::ingredient($client, 'Pasta', 24, 'pack');
        $oil = self::ingredient($client, 'Oil', 6, 'bottle', manual: true);

        $client->jsonRequest('POST', '/api/recipes', ['name' => '  Aglio e olio ', 'lines' => [['ingredientId' => $pasta, 'amount' => 12], ['ingredientId' => $oil, 'amount' => null]]]);
        self::assertResponseStatusCodeSame(201);
        $recipe = self::body($client);
        $id = Json::string($recipe, 'id');
        self::assertSame(['Aglio e olio', 'meal', null, [], true], [$recipe['name'], $recipe['makes'], $recipe['output'], $recipe['missing'], $recipe['makeable']]);
        self::assertSame(
            [[$pasta, 'Pasta', 'pack', false, 12, 24, true], [$oil, 'Oil', 'bottle', true, null, 6, true]],
            array_map(static fn (mixed $line): array => [Json::string($line, 'ingredientId'), Json::string($line, 'name'), Json::string($line, 'unit'), Json::at($line, 'manual'), Json::at($line, 'amount'), Json::int($line, 'stock'), Json::at($line, 'covered')], Json::array($recipe, 'lines')),
        );

        $client->jsonRequest('GET', '/api/recipes');
        self::assertResponseIsSuccessful();
        self::assertSame([$id], array_column(self::body($client), 'id'));

        $client->jsonRequest('PUT', "/api/recipes/$id", ['name' => 'Aglio', 'lines' => [['ingredientId' => $pasta, 'amount' => 36]]]);
        self::assertResponseIsSuccessful();
        self::assertSame(['Aglio', false, [$pasta]], [self::body($client)['name'], self::body($client)['makeable'], self::body($client)['missing']]);

        $client->jsonRequest('DELETE', "/api/recipes/$id");
        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $client->getResponse()->getContent());

        $client->jsonRequest('GET', '/api/recipes');
        self::assertSame([], self::body($client));
    }

    public function testAPrepIsWrittenAndMade(): void
    {
        $client = self::signedInClient();
        $gluten = self::ingredient($client, 'Gluten', 24, 'bag');
        $seitan = self::ingredient($client, 'Seitan', 0, 'bag');
        $client->jsonRequest('POST', '/api/recipes', ['name' => 'Seitan', 'lines' => [['ingredientId' => $gluten, 'amount' => 12]], 'output' => ['ingredientId' => $seitan, 'yield' => 12]]);
        self::assertResponseStatusCodeSame(201);
        $recipe = self::body($client);
        $id = Json::string($recipe, 'id');
        self::assertSame(['prep', [$seitan, 'Seitan', 'bag', 12, 0]], [$recipe['makes'], [Json::string($recipe, 'output', 'ingredientId'), Json::string($recipe, 'output', 'name'), Json::string($recipe, 'output', 'unit'), Json::int($recipe, 'output', 'yield'), Json::int($recipe, 'output', 'stock')]]);

        $client->jsonRequest('POST', "/api/recipes/$id/make", ['made' => 24, 'used' => [['ingredientId' => $gluten, 'amount' => 18]]]);

        self::assertResponseIsSuccessful();
        self::assertSame(24, Json::int(self::body($client), 'output', 'stock'));
        $client->jsonRequest('GET', '/api/ingredients');
        self::assertSame([6, 24], array_column(self::body($client), 'stock'));
        $client->jsonRequest('GET', '/api/meals');
        self::assertSame([], Json::array(self::body($client), 'meals'));
    }

    public function testADishWaitsForThePrepThatMakesWhatItLacks(): void
    {
        $client = self::signedInClient();
        $flour = self::ingredient($client, 'Flour', 12, 'bag');
        $dough = self::ingredient($client, 'Dough', 0);
        $client->jsonRequest('POST', '/api/recipes', ['name' => 'Dough', 'lines' => [['ingredientId' => $flour, 'amount' => 6]], 'output' => ['ingredientId' => $dough, 'yield' => 12]]);
        $prep = Json::string(self::body($client), 'id');

        $client->jsonRequest('POST', '/api/recipes', ['name' => 'Tart', 'lines' => [['ingredientId' => $dough, 'amount' => 12]]]);
        self::assertResponseStatusCodeSame(201);
        $tart = self::body($client);

        self::assertSame([false, [], [['id' => $prep, 'name' => 'Dough']], false], [$tart['makeable'], $tart['missing'], $tart['preps'], Json::at($tart, 'lines', 0, 'covered')]);
    }

    public function testAVariantInStockStandsInForTheLine(): void
    {
        $client = self::signedInClient();
        $zucchini = self::ingredient($client, 'Zucchini', 0);
        $client->jsonRequest('POST', '/api/ingredients', ['name' => 'Frozen zucchini', 'location' => 'freezer', 'unit' => 'bag', 'stock' => 6, 'variantOf' => $zucchini]);
        $frozen = Json::string(self::body($client), 'id');

        $client->jsonRequest('POST', '/api/recipes', ['name' => 'Gratin', 'lines' => [['ingredientId' => $zucchini, 'amount' => 24]]]);
        $gratin = self::body($client);

        self::assertTrue($gratin['makeable']);
        self::assertSame(['ingredientId' => $frozen, 'name' => 'Frozen zucchini', 'unit' => 'bag', 'manual' => false, 'stock' => 6], Json::at($gratin, 'lines', 0, 'variant'));
    }

    public function testAMealRecipeCannotBeMade(): void
    {
        $client = self::signedInClient();
        $pasta = self::ingredient($client, 'Pasta', 24);
        $client->jsonRequest('POST', '/api/recipes', ['name' => 'Pasta', 'lines' => [['ingredientId' => $pasta, 'amount' => 12]]]);
        $id = Json::string(self::body($client), 'id');

        $client->jsonRequest('POST', "/api/recipes/$id/make", ['made' => 12, 'used' => []]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('“Pasta” makes a meal, not an ingredient.', Json::string(self::body($client), 'detail'));
    }

    public function testWritesAnswerWithTheRefreshedListWhenAsked(): void
    {
        $client = self::signedInClient();
        $pasta = self::ingredient($client, 'Pasta', 24);

        $client->jsonRequest('POST', '/api/recipes', ['name' => 'Pasta bake', 'lines' => [['ingredientId' => $pasta, 'amount' => 12]]], ['HTTP_X_REFRESH' => '["/api/recipes"]']);

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHasHeader('X-Refreshed');
        self::assertSame(['Pasta bake'], array_column(Json::array(self::body($client), 'refreshed', '/api/recipes'), 'name'));
    }

    public function testEveryEndpointNeedsASignedInUser(): void
    {
        $client = self::createClient();

        foreach ([
            ['GET', '/api/recipes', []],
            ['POST', '/api/recipes', ['name' => 'Soup', 'lines' => []]],
            ['PUT', '/api/recipes/'.self::UNKNOWN, ['name' => 'Soup', 'lines' => []]],
            ['DELETE', '/api/recipes/'.self::UNKNOWN, []],
            ['POST', '/api/recipes/'.self::UNKNOWN.'/make', ['made' => 12, 'used' => []]],
        ] as [$method, $path, $payload]) {
            $client->jsonRequest($method, $path, $payload);
            self::assertResponseStatusCodeSame(401, "$method $path");
        }
    }

    public function testUnknownRecipesAndIngredientsAre404(): void
    {
        $client = self::signedInClient();
        $pasta = self::ingredient($client, 'Pasta', 24);

        foreach ([
            ['PUT', '/api/recipes/'.self::UNKNOWN, ['name' => 'Soup', 'lines' => [['ingredientId' => $pasta]]], 'This recipe no longer exists.'],
            ['DELETE', '/api/recipes/'.self::UNKNOWN, [], 'This recipe no longer exists.'],
            ['POST', '/api/recipes/'.self::UNKNOWN.'/make', ['made' => 12, 'used' => []], 'This recipe no longer exists.'],
            ['POST', '/api/recipes', ['name' => 'Soup', 'lines' => [['ingredientId' => self::UNKNOWN]]], 'This ingredient no longer exists.'],
        ] as [$method, $path, $payload, $message]) {
            $client->jsonRequest($method, $path, $payload);
            self::assertResponseStatusCodeSame(404, "$method $path");
            self::assertSame($message, Json::string(self::body($client), 'detail'));
        }
    }

    public function testMalformedIdentifiersAre404(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('DELETE', '/api/recipes/not-an-id');

        self::assertResponseStatusCodeSame(404);
    }

    public function testNamesAreUniqueIgnoringCase(): void
    {
        $client = self::signedInClient();
        $pasta = self::ingredient($client, 'Pasta', 24);
        $lines = [['ingredientId' => $pasta, 'amount' => 12]];
        $client->jsonRequest('POST', '/api/recipes', ['name' => 'Pasta bake', 'lines' => $lines]);
        $bake = Json::string(self::body($client), 'id');
        $client->jsonRequest('POST', '/api/recipes', ['name' => 'Soup', 'lines' => $lines]);
        $soup = Json::string(self::body($client), 'id');

        $client->jsonRequest('POST', '/api/recipes', ['name' => 'PASTA BAKE', 'lines' => $lines]);
        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('There is already a recipe called “PASTA BAKE”.', Json::string(self::body($client), 'detail'));

        $client->jsonRequest('PUT', "/api/recipes/$soup", ['name' => 'pasta bake', 'lines' => $lines]);
        self::assertResponseStatusCodeSame(422);

        $client->jsonRequest('PUT', "/api/recipes/$bake", ['name' => 'pasta BAKE', 'lines' => $lines]);
        self::assertResponseIsSuccessful();
    }

    public function testBusinessRulesAnswerInTheCallersLanguage(): void
    {
        $client = self::signedInClient();
        $pasta = self::ingredient($client, 'Pasta', 24);

        $client->jsonRequest('POST', '/api/recipes', ['name' => 'Soupe', 'lines' => []], ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Une recette demande au moins un ingrédient.', Json::string(self::body($client), 'detail'));

        $client->jsonRequest('POST', '/api/recipes', ['name' => 'Soupe', 'lines' => [['ingredientId' => $pasta, 'amount' => 12], ['ingredientId' => $pasta, 'amount' => 6]]], ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        self::assertSame('« Pasta » apparaît deux fois.', Json::string(self::body($client), 'detail'));
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidRecipes')]
    public function testInvalidRecipesAre422(array $payload): void
    {
        $client = self::signedInClient();
        $pasta = self::ingredient($client, 'Pasta', 24);
        $client->jsonRequest('POST', '/api/recipes', ['name' => 'Existing', 'lines' => [['ingredientId' => $pasta, 'amount' => 12]]]);
        $id = Json::string(self::body($client), 'id');
        $payload = Json::decode(str_replace('PASTA', $pasta, (string) json_encode($payload)));

        $client->jsonRequest('POST', '/api/recipes', $payload);
        self::assertResponseStatusCodeSame(422, (string) json_encode($payload));

        $client->jsonRequest('PUT', "/api/recipes/$id", $payload);
        self::assertResponseStatusCodeSame(422, (string) json_encode($payload));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidRecipes(): iterable
    {
        $line = ['ingredientId' => 'PASTA', 'amount' => 12];

        yield 'no name' => [['lines' => [$line]]];
        yield 'blank name' => [['name' => '  ', 'lines' => [$line]]];
        yield 'long name' => [['name' => str_repeat('a', 81), 'lines' => [$line]]];
        yield 'no lines' => [['name' => 'Soup', 'lines' => []]];
        yield 'lines missing' => [['name' => 'Soup']];
        yield 'blank ingredient' => [['name' => 'Soup', 'lines' => [['ingredientId' => '', 'amount' => 12]]]];
        yield 'malformed ingredient' => [['name' => 'Soup', 'lines' => [['ingredientId' => 'pasta', 'amount' => 12]]]];
        yield 'zero amount' => [['name' => 'Soup', 'lines' => [['ingredientId' => 'PASTA', 'amount' => 0]]]];
        yield 'negative amount' => [['name' => 'Soup', 'lines' => [['ingredientId' => 'PASTA', 'amount' => -1]]]];
        yield 'amount above 999 units' => [['name' => 'Soup', 'lines' => [['ingredientId' => 'PASTA', 'amount' => 11989]]]];
        yield 'amount is a word' => [['name' => 'Soup', 'lines' => [['ingredientId' => 'PASTA', 'amount' => 'lots']]]];
        yield 'ingredient listed twice' => [['name' => 'Soup', 'lines' => [$line, $line]]];
        yield 'prep without yield' => [['name' => 'Soup', 'lines' => [$line], 'output' => ['ingredientId' => 'PASTA']]];
        yield 'prep with a zero yield' => [['name' => 'Soup', 'lines' => [$line], 'output' => ['ingredientId' => 'PASTA', 'yield' => 0]]];
        yield 'prep with a malformed output' => [['name' => 'Soup', 'lines' => [$line], 'output' => ['ingredientId' => 'pasta', 'yield' => 12]]];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidMakes')]
    public function testInvalidMakesAre422(array $payload): void
    {
        $client = self::signedInClient();
        $gluten = self::ingredient($client, 'Gluten', 24);
        $seitan = self::ingredient($client, 'Seitan', 0);
        $client->jsonRequest('POST', '/api/recipes', ['name' => 'Seitan', 'lines' => [['ingredientId' => $gluten, 'amount' => 12]], 'output' => ['ingredientId' => $seitan, 'yield' => 12]]);
        $id = Json::string(self::body($client), 'id');
        $payload = Json::decode(str_replace('GLUTEN', $gluten, (string) json_encode($payload)));

        $client->jsonRequest('POST', "/api/recipes/$id/make", $payload);

        self::assertResponseStatusCodeSame(422, (string) json_encode($payload));
        $client->jsonRequest('GET', '/api/ingredients');
        self::assertSame([24, 0], array_column(self::body($client), 'stock'));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidMakes(): iterable
    {
        yield 'nothing' => [[]];
        yield 'zero made' => [['made' => 0, 'used' => []]];
        yield 'negative made' => [['made' => -1, 'used' => []]];
        yield 'made above 999 units' => [['made' => 11989, 'used' => []]];
        yield 'zero used' => [['made' => 12, 'used' => [['ingredientId' => 'GLUTEN', 'amount' => 0]]]];
        yield 'malformed used ingredient' => [['made' => 12, 'used' => [['ingredientId' => 'gluten', 'amount' => 6]]]];
        yield 'the same ingredient twice' => [['made' => 12, 'used' => [['ingredientId' => 'GLUTEN', 'amount' => 6], ['ingredientId' => 'GLUTEN', 'amount' => 6]]]];
    }

    public function testAnIngredientUsedByARecipeCannotBeDeleted(): void
    {
        $client = self::signedInClient();
        $pasta = self::ingredient($client, 'Pasta', 24);
        $client->jsonRequest('POST', '/api/recipes', ['name' => 'Pasta bake', 'lines' => [['ingredientId' => $pasta, 'amount' => 12]]]);
        $recipe = Json::string(self::body($client), 'id');

        $client->jsonRequest('DELETE', "/api/ingredients/$pasta");
        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('“Pasta” cannot be deleted: it is used by Pasta bake.', Json::string(self::body($client), 'detail'));

        $client->jsonRequest('DELETE', "/api/recipes/$recipe");
        $client->jsonRequest('DELETE', "/api/ingredients/$pasta");
        self::assertResponseStatusCodeSame(204);
    }

    /**
     * @return array<mixed>
     */
    private static function body(KernelBrowser $client): array
    {
        return Json::decode((string) $client->getResponse()->getContent());
    }

    private static function ingredient(KernelBrowser $client, string $name, int $stock, string $unit = 'piece', bool $manual = false): string
    {
        $client->jsonRequest('POST', '/api/ingredients', ['name' => $name, 'location' => 'pantry', 'unit' => $unit, 'manual' => $manual, 'stock' => $stock]);
        self::assertResponseStatusCodeSame(201);

        return Json::string(self::body($client), 'id');
    }
}
