<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Cooking;

use App\Application\Cooking\LogMeal\LogMeal;
use App\Application\Cooking\LogMeal\LogMealHandler;
use App\Application\Cooking\MealHistory;
use App\Application\Cooking\MealPage;
use App\Application\Cooking\MealView;
use App\Application\Cooking\RemoveMeal\RemoveMeal;
use App\Application\Cooking\RemoveMeal\RemoveMealHandler;
use App\Application\Cooking\UsedInput;
use App\Application\Identity\MemberView;
use App\Application\Pantry\AddIngredient\AddIngredient;
use App\Application\Pantry\AddIngredient\AddIngredientHandler;
use App\Application\Pantry\IngredientQueries;
use App\Application\Pantry\IngredientView;
use App\Application\Pantry\RemoveIngredient\RemoveIngredient;
use App\Application\Pantry\RemoveIngredient\RemoveIngredientHandler;
use App\Domain\Cooking\Exception\EmptyMeal;
use App\Domain\Cooking\Exception\IngredientListedTwice;
use App\Domain\Cooking\Exception\MealNeedsEater;
use App\Domain\Cooking\Exception\MealTitleTooLong;
use App\Domain\Cooking\Exception\ZeroQuantity;
use App\Domain\Cooking\MealType;
use App\Domain\Identity\User;
use App\Domain\Pantry\Exception\QuantityOutOfRange;
use App\Domain\Pantry\Location;
use App\Domain\Pantry\Unit;
use App\Domain\Shared\Day;
use App\Domain\Shared\Exception\NotFound;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\FreezesClock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

final class MealUseCasesTest extends KernelTestCase
{
    use ActsAsUser;
    use FreezesClock;

    private User $louis;

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 19:30 UTC');
        $this->louis = self::createUser(displayName: 'Louis');
        self::actAs($this->louis);
    }

    public function testLoggingAMealConsumesEveryUsedAmountWhateverTheManualFlag(): void
    {
        $pasta = $this->ingredient('Pasta', 36);
        $oil = $this->ingredient('Oil', 6, Unit::Bottle, manual: true);

        $meal = $this->log('Aglio e olio', [new UsedInput($pasta->id, 12), new UsedInput($oil->id, 1)]);

        self::assertSame([24, 5], $this->stocks('Pasta', 'Oil'));
        self::assertSame(['Aglio e olio', 'dinner', '2026-10-06'], [$meal->title, $meal->type, $meal->eatenOn]);
        self::assertSame(
            [[$pasta->id, 'Pasta', 'piece', 12], [$oil->id, 'Oil', 'bottle', 1]],
            array_map(static fn ($line): array => [$line->ingredientId, $line->name, $line->unit, $line->used], $meal->lines),
        );
    }

    public function testTheStockClampsAtZeroAndTheMealIsNeverRefused(): void
    {
        $pasta = $this->ingredient('Pasta', 6);

        $this->log('Too much', [new UsedInput($pasta->id, 36)]);

        self::assertSame([0], $this->stocks('Pasta'));
    }

    public function testAMealRecordsWhoAteWhoLoggedAndWhen(): void
    {
        $ada = self::createUser(displayName: 'Ada');

        $meal = $this->log('Pizza', [], [(string) $this->louis->id(), (string) $ada->id()]);

        self::assertEquals([new MemberView((string) $ada->id(), 'Ada'), new MemberView((string) $this->louis->id(), 'Louis')], $meal->eaters);
        self::assertEquals(new MemberView((string) $this->louis->id(), 'Louis'), $meal->loggedBy);
        self::assertSame('2026-10-06T19:30:00+00:00', $meal->loggedAt);
    }

    public function testAMealWithoutTitleIsValidWhenItHasLines(): void
    {
        $pasta = $this->ingredient('Pasta', 12);

        $meal = $this->log(null, [new UsedInput($pasta->id, 12)]);

        self::assertNull($meal->title);
    }

    public function testAMealNeedsATitleOrALine(): void
    {
        $this->expectExceptionObject(new EmptyMeal());

        $this->log('  ', []);
    }

    public function testAMealNeedsAnEater(): void
    {
        $this->expectExceptionObject(new MealNeedsEater());

        $this->log('Pizza', [], []);
    }

    public function testAnUnknownEaterIsNotFound(): void
    {
        $id = new Ulid();

        $this->expectExceptionObject(new NotFound('user', (string) $id));

        $this->log('Pizza', [], [(string) $id]);
    }

    public function testAnUnknownIngredientIsNotFound(): void
    {
        $id = new Ulid();

        $this->expectExceptionObject(new NotFound('ingredient', (string) $id));

        $this->log('Pizza', [new UsedInput((string) $id, 12)]);
    }

    public function testTheTitleHasAtMostEightyCharacters(): void
    {
        $this->expectExceptionObject(new MealTitleTooLong(80));

        $this->log(str_repeat('a', 81), []);
    }

    public function testAnAmountOfZeroIsRefused(): void
    {
        $pasta = $this->ingredient('Pasta', 12);

        $this->expectExceptionObject(new ZeroQuantity('Pasta'));

        $this->log('Pasta', [new UsedInput($pasta->id, 0)]);
    }

    public function testAnIngredientIsUsedOncePerMeal(): void
    {
        $pasta = $this->ingredient('Pasta', 36);

        $this->expectExceptionObject(new IngredientListedTwice('Pasta'));

        $this->log('Pasta', [new UsedInput($pasta->id, 12), new UsedInput($pasta->id, 12)]);
    }

    public function testAnAmountOutOfRangeIsRefused(): void
    {
        $pasta = $this->ingredient('Pasta', 12);

        $this->expectExceptionObject(new QuantityOutOfRange(999));

        $this->log('Pasta', [new UsedInput($pasta->id, 11989)]);
    }

    public function testRemovingAMealPutsTheIngredientsBackWhenAsked(): void
    {
        $pasta = $this->ingredient('Pasta', 36);
        $oil = $this->ingredient('Oil', 6, manual: true);
        $meal = $this->log('Aglio', [new UsedInput($pasta->id, 12), new UsedInput($oil->id, 2)]);

        $this->remove($meal, giveBack: true);

        self::assertSame([36, 6], $this->stocks('Pasta', 'Oil'));
        self::assertSame([], $this->history(null)->meals);
    }

    public function testRemovingAMealLeavesTheStockAloneOtherwise(): void
    {
        $pasta = $this->ingredient('Pasta', 36);
        $meal = $this->log('Aglio', [new UsedInput($pasta->id, 12)]);

        $this->remove($meal, giveBack: false);

        self::assertSame([24], $this->stocks('Pasta'));
        self::assertSame([], $this->history(null)->meals);
    }

    public function testGivingBackIsExactWhenTheMealHadEmptiedTheStock(): void
    {
        $pasta = $this->ingredient('Pasta', 6);
        $meal = $this->log('Too much', [new UsedInput($pasta->id, 36)]);

        $this->remove($meal, giveBack: true);

        self::assertSame([36], $this->stocks('Pasta'));
    }

    public function testADeletedIngredientKeepsItsSnapshotInThePastMealsAndIsNotGivenBack(): void
    {
        $pasta = $this->ingredient('Pasta', 36, Unit::Pack);
        $rice = $this->ingredient('Rice', 36);
        $meal = $this->log('Mixed', [new UsedInput($pasta->id, 12), new UsedInput($rice->id, 12)]);
        self::getContainer()->get(RemoveIngredientHandler::class)(new RemoveIngredient(Ulid::fromString($pasta->id)));
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        [$stored] = $this->history(null)->meals;
        self::assertSame(
            [[null, 'Pasta', 'pack', 12], [$rice->id, 'Rice', 'piece', 12]],
            array_map(static fn ($line): array => [$line->ingredientId, $line->name, $line->unit, $line->used], $stored->lines),
        );

        $this->remove($meal, giveBack: true);

        self::assertSame([36], $this->stocks('Rice'));
    }

    public function testRemovingAnUnknownMealIsNotFound(): void
    {
        $id = new Ulid();

        $this->expectExceptionObject(new NotFound('meal', (string) $id));

        self::getContainer()->get(RemoveMealHandler::class)(new RemoveMeal($id, true));
    }

    public function testTheHistoryIsEmptyWithoutMeals(): void
    {
        $page = $this->history(null);

        self::assertSame([[], null], [$page->meals, $page->before]);
    }

    public function testTheHistoryListsTheNewestDaysFirstThenTheLatestLogged(): void
    {
        $this->log('Old', [], day: '2026-10-01');
        $this->log('Lunch', [], day: '2026-10-05');
        self::freezeAt('2026-10-06 20:30 UTC');
        $this->log('Dinner', [], day: '2026-10-05');
        $this->log('Today', [], day: '2026-10-06');

        $page = $this->history(null);

        self::assertSame(['Today', 'Dinner', 'Lunch', 'Old'], array_map(static fn (MealView $meal): ?string => $meal->title, $page->meals));
        self::assertNull($page->before);
    }

    public function testTheHistoryPagesByThirtyDaysWithMeals(): void
    {
        $day = new \DateTimeImmutable('2026-09-01');
        for ($i = 0; $i < 31; ++$i) {
            $this->log('Meal '.$i, [], day: $day->modify("+$i days")->format('Y-m-d'));
        }
        $this->log('Second of the first day', [], day: '2026-09-01');

        $first = $this->history(null);

        self::assertCount(30, $first->meals);
        self::assertSame(['2026-10-01', '2026-09-02', '2026-09-02'], [$first->meals[0]->eatenOn, $first->meals[29]->eatenOn, $first->before]);

        $older = $this->history(new \DateTimeImmutable((string) $first->before));

        self::assertSame(['2026-09-01', '2026-09-01'], array_map(static fn (MealView $meal): string => $meal->eatenOn, $older->meals));
        self::assertNull($older->before);
    }

    public function testTheHistoryCursorExcludesItsOwnDay(): void
    {
        $this->log('Monday', [], day: '2026-10-05');
        $this->log('Sunday', [], day: '2026-10-04');

        $page = $this->history(new \DateTimeImmutable('2026-10-05'));

        self::assertSame(['Sunday'], array_map(static fn (MealView $meal): ?string => $meal->title, $page->meals));
    }

    public function testTheHistoryCarriesEatersAndLines(): void
    {
        $pasta = $this->ingredient('Pasta', 36);
        $ada = self::createUser(displayName: 'Ada');
        $this->log('Pasta', [new UsedInput($pasta->id, 12)], [(string) $this->louis->id(), (string) $ada->id()]);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        [$meal] = $this->history(null)->meals;

        self::assertSame(['Ada', 'Louis'], array_map(static fn (MemberView $eater): string => $eater->name, $meal->eaters));
        self::assertSame(['Pasta'], array_map(static fn ($line): string => $line->name, $meal->lines));
        self::assertSame('Louis', $meal->loggedBy->name);
    }

    private function ingredient(string $name, int $stock, Unit $unit = Unit::Piece, bool $manual = false): IngredientView
    {
        return self::getContainer()->get(AddIngredientHandler::class)(new AddIngredient($name, Location::Pantry, $unit, $manual, null, $stock));
    }

    /**
     * @param list<UsedInput> $used
     * @param ?list<string>   $eaterIds
     */
    private function log(?string $title, array $used, ?array $eaterIds = null, string $day = '2026-10-06'): MealView
    {
        return self::getContainer()->get(LogMealHandler::class)(new LogMeal($title, MealType::Dinner, Day::of($day), $eaterIds ?? [(string) $this->louis->id()], $used));
    }

    private function remove(MealView $meal, bool $giveBack): void
    {
        self::getContainer()->get(RemoveMealHandler::class)(new RemoveMeal(Ulid::fromString($meal->id), $giveBack));
    }

    private function history(?\DateTimeImmutable $before): MealPage
    {
        return self::getContainer()->get(MealHistory::class)->before($before);
    }

    /**
     * @return list<int>
     */
    private function stocks(string ...$names): array
    {
        $byName = [];
        foreach (self::getContainer()->get(IngredientQueries::class)->all() as $view) {
            $byName[$view->name] = $view->stock;
        }

        return array_map(static fn (string $name): int => $byName[$name], array_values($names));
    }
}
