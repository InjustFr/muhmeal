<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MealApiTest extends WebTestCase
{
    use SignsInClient;

    private const string UNKNOWN = '01JAAAAAAAAAAAAAAAAAAAAAAA';

    public function testMealLifecycle(): void
    {
        [$client, $louis] = self::signIn();
        $pasta = self::ingredient($client, 'Pasta', 36, 'pack');
        $oil = self::ingredient($client, 'Oil', 6, 'bottle', manual: true);

        $client->jsonRequest('POST', '/api/meals', self::meal($louis, ['title' => ' Aglio ', 'type' => 'lunch', 'used' => [['ingredientId' => $pasta, 'amount' => 12], ['ingredientId' => $oil, 'amount' => 1]]]));
        self::assertResponseStatusCodeSame(201);
        $meal = self::body($client);
        $id = Json::string($meal, 'id');
        self::assertSame(['Aglio', 'lunch', '2026-10-06', 'Louis', 'Louis', $louis], [$meal['title'], $meal['type'], $meal['eatenOn'], Json::string($meal, 'eaters', 0, 'name'), Json::string($meal, 'loggedBy', 'name'), Json::string($meal, 'loggedBy', 'id')]);
        self::assertSame(
            [[$pasta, 'Pasta', 'pack', 12], [$oil, 'Oil', 'bottle', 1]],
            array_map(static fn (mixed $line): array => [Json::string($line, 'ingredientId'), Json::string($line, 'name'), Json::string($line, 'unit'), Json::int($line, 'used')], Json::array($meal, 'lines')),
        );
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', Json::string($meal, 'loggedAt'));
        self::assertSame([5, 24], self::stocks($client));

        $client->jsonRequest('GET', '/api/meals');
        self::assertResponseIsSuccessful();
        self::assertSame([[$id], null], [array_column(Json::array(self::body($client), 'meals'), 'id'), self::body($client)['before']]);

        $client->jsonRequest('DELETE', "/api/meals/$id?giveBack=1");
        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $client->getResponse()->getContent());
        self::assertSame([6, 36], self::stocks($client));

        $client->jsonRequest('GET', '/api/meals');
        self::assertSame([], Json::array(self::body($client), 'meals'));
    }

    public function testDeletingWithoutGivingBackLeavesTheStockAlone(): void
    {
        [$client, $louis] = self::signIn();
        $pasta = self::ingredient($client, 'Pasta', 36);
        $first = self::logMeal($client, $louis, ['title' => 'One', 'used' => [['ingredientId' => $pasta, 'amount' => 12]]]);
        $second = self::logMeal($client, $louis, ['title' => 'Two', 'used' => [['ingredientId' => $pasta, 'amount' => 12]]]);

        $client->jsonRequest('DELETE', "/api/meals/$first?giveBack=0");
        self::assertResponseStatusCodeSame(204);
        self::assertSame([12], self::stocks($client));

        $client->jsonRequest('DELETE', "/api/meals/$second");
        self::assertResponseStatusCodeSame(204);
        self::assertSame([12], self::stocks($client));
    }

    public function testAMealCanBeLoggedForSeveralEatersAndNoIngredient(): void
    {
        [$client, $louis] = self::signIn('louis@muhmeal.test', 'Louis');
        $ada = (string) self::createUser('ada@muhmeal.test', 'Ada')->id();

        $client->jsonRequest('POST', '/api/meals', self::meal($louis, ['title' => 'Pizza', 'eatenOn' => '2026-10-05', 'eaterIds' => [$louis, $ada]]));

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['Ada', 'Louis'], array_column(Json::array(self::body($client), 'eaters'), 'name'));
    }

    public function testTheHistoryPagesWithABeforeCursor(): void
    {
        [$client, $louis] = self::signIn();
        foreach (range(1, 32) as $day) {
            self::logMeal($client, $louis, ['title' => "Meal $day", 'eatenOn' => new \DateTimeImmutable('2026-08-31')->modify("+$day days")->format('Y-m-d')]);
        }

        $client->jsonRequest('GET', '/api/meals');
        $first = self::body($client);
        self::assertCount(30, Json::array($first, 'meals'));
        self::assertSame('2026-10-02', Json::string($first, 'meals', 0, 'eatenOn'));
        self::assertSame('2026-09-03', Json::string($first, 'before'));

        $client->jsonRequest('GET', '/api/meals?before=2026-09-03');
        $older = self::body($client);
        self::assertSame(['2026-09-02', '2026-09-01'], array_column(Json::array($older, 'meals'), 'eatenOn'));
        self::assertNull($older['before']);
    }

    public function testAnInvalidBeforeIs422(): void
    {
        [$client] = self::signIn();

        $client->jsonRequest('GET', '/api/meals?before=2026-02-30');

        self::assertResponseStatusCodeSame(422);
    }

    public function testWritesAnswerWithTheRefreshedHistoryWhenAsked(): void
    {
        [$client, $louis] = self::signIn();

        $client->jsonRequest('POST', '/api/meals', self::meal($louis, ['title' => 'Pizza']), ['HTTP_X_REFRESH' => '["/api/meals"]']);

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHasHeader('X-Refreshed');
        self::assertSame(['Pizza'], array_column(Json::array(self::body($client), 'refreshed', '/api/meals', 'meals'), 'title'));
    }

    public function testEveryEndpointNeedsASignedInUser(): void
    {
        $client = self::createClient();

        foreach ([
            ['GET', '/api/meals', []],
            ['POST', '/api/meals', self::meal(self::UNKNOWN, [])],
            ['DELETE', '/api/meals/'.self::UNKNOWN.'?giveBack=1', []],
        ] as [$method, $path, $payload]) {
            $client->jsonRequest($method, $path, $payload);
            self::assertResponseStatusCodeSame(401, "$method $path");
        }
    }

    public function testUnknownMealsEatersAndIngredientsAre404(): void
    {
        [$client, $louis] = self::signIn();

        foreach ([
            ['DELETE', '/api/meals/'.self::UNKNOWN.'?giveBack=1', [], 'This meal no longer exists.'],
            ['POST', '/api/meals', self::meal($louis, ['eaterIds' => [self::UNKNOWN]]), 'This person no longer exists.'],
            ['POST', '/api/meals', self::meal($louis, ['used' => [['ingredientId' => self::UNKNOWN, 'amount' => 12]]]), 'This ingredient no longer exists.'],
        ] as [$method, $path, $payload, $message]) {
            $client->jsonRequest($method, $path, $payload);
            self::assertResponseStatusCodeSame(404, "$method $path");
            self::assertSame($message, Json::string(self::body($client), 'detail'));
        }
    }

    public function testMalformedIdentifiersAre404(): void
    {
        [$client] = self::signIn();

        $client->jsonRequest('DELETE', '/api/meals/not-an-id');

        self::assertResponseStatusCodeSame(404);
    }

    public function testBusinessRulesAnswerInTheCallersLanguage(): void
    {
        [$client, $louis] = self::signIn();

        $client->jsonRequest('POST', '/api/meals', self::meal($louis, ['title' => null]), ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('Donnez un titre au repas ou au moins un ingrédient.', Json::string(self::body($client), 'detail'));
    }

    public function testAMealNeedsAnEater(): void
    {
        [$client, $louis] = self::signIn();

        $client->jsonRequest('POST', '/api/meals', self::meal($louis, ['eaterIds' => []]));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Someone has to eat the meal.', Json::string(self::body($client), 'detail'));
    }

    /**
     * @param array<string, mixed> $changes
     */
    #[DataProvider('invalidMeals')]
    public function testInvalidMealsAre422(array $changes): void
    {
        [$client, $louis] = self::signIn();
        $pasta = self::ingredient($client, 'Pasta', 24);
        $changes = Json::decode(str_replace('PASTA', $pasta, (string) json_encode($changes)));

        $client->jsonRequest('POST', '/api/meals', self::meal($louis, $changes));

        self::assertResponseStatusCodeSame(422, (string) json_encode($changes));
        self::assertSame([24], self::stocks($client));
        $client->jsonRequest('GET', '/api/meals');
        self::assertSame([], Json::array(self::body($client), 'meals'));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidMeals(): iterable
    {
        yield 'unknown type' => [['type' => 'brunch']];
        yield 'no type' => [['type' => null]];
        yield 'no day' => [['eatenOn' => '']];
        yield 'invalid day' => [['eatenOn' => '2026-02-30']];
        yield 'not a day' => [['eatenOn' => 'yesterday']];
        yield 'long title' => [['title' => str_repeat('a', 81)]];
        yield 'malformed eater' => [['eaterIds' => ['louis']]];
        yield 'neither title nor line' => [['title' => null]];
        yield 'blank title and no line' => [['title' => '   ']];
        yield 'zero used' => [['used' => [['ingredientId' => 'PASTA', 'amount' => 0]]]];
        yield 'negative used' => [['used' => [['ingredientId' => 'PASTA', 'amount' => -12]]]];
        yield 'used above 999 units' => [['used' => [['ingredientId' => 'PASTA', 'amount' => 11989]]]];
        yield 'used is a word' => [['used' => [['ingredientId' => 'PASTA', 'amount' => 'lots']]]];
        yield 'malformed used ingredient' => [['used' => [['ingredientId' => 'pasta', 'amount' => 12]]]];
        yield 'the same ingredient twice' => [['used' => [['ingredientId' => 'PASTA', 'amount' => 6], ['ingredientId' => 'PASTA', 'amount' => 6]]]];
    }

    public function testAnInvalidGiveBackFlagIsRefused(): void
    {
        [$client, $louis] = self::signIn();
        $id = self::logMeal($client, $louis, ['title' => 'Pizza']);

        $client->jsonRequest('DELETE', "/api/meals/$id?giveBack=maybe");

        self::assertResponseStatusCodeSame(404);
        $client->jsonRequest('GET', '/api/meals');
        self::assertCount(1, Json::array(self::body($client), 'meals'));
    }

    /**
     * @return array<mixed>
     */
    private static function body(KernelBrowser $client): array
    {
        return Json::decode((string) $client->getResponse()->getContent());
    }

    /**
     * @return array{KernelBrowser, string}
     */
    private static function signIn(?string $email = null, string $displayName = 'Louis'): array
    {
        $client = self::createClient();
        $user = self::createUser($email, $displayName);
        $client->loginUser(SecurityUser::fromUser($user));

        return [$client, (string) $user->id()];
    }

    /**
     * @param array<mixed> $changes
     *
     * @return array<mixed>
     */
    private static function meal(string $eater, array $changes): array
    {
        return [...['title' => 'Dinner', 'type' => 'dinner', 'eatenOn' => '2026-10-06', 'eaterIds' => [$eater], 'used' => []], ...$changes];
    }

    /**
     * @param array<mixed> $changes
     */
    private static function logMeal(KernelBrowser $client, string $eater, array $changes): string
    {
        $client->jsonRequest('POST', '/api/meals', self::meal($eater, $changes));
        self::assertResponseStatusCodeSame(201);

        return Json::string(self::body($client), 'id');
    }

    private static function ingredient(KernelBrowser $client, string $name, int $stock, string $unit = 'piece', bool $manual = false): string
    {
        $client->jsonRequest('POST', '/api/ingredients', ['name' => $name, 'location' => 'pantry', 'unit' => $unit, 'manual' => $manual, 'stock' => $stock]);
        self::assertResponseStatusCodeSame(201);

        return Json::string(self::body($client), 'id');
    }

    /**
     * @return list<int>
     */
    private static function stocks(KernelBrowser $client): array
    {
        $client->jsonRequest('GET', '/api/ingredients');

        return array_values(array_map(static fn (mixed $ingredient): int => Json::int($ingredient, 'stock'), self::body($client)));
    }
}
