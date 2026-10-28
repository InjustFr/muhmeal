<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Pantry;

use App\Application\Pantry\AddIngredient\AddIngredient;
use App\Application\Pantry\AddIngredient\AddIngredientHandler;
use App\Application\Pantry\AdjustStock\AdjustStock;
use App\Application\Pantry\AdjustStock\AdjustStockHandler;
use App\Application\Pantry\EditIngredient\EditIngredient;
use App\Application\Pantry\EditIngredient\EditIngredientHandler;
use App\Application\Pantry\IngredientQueries;
use App\Application\Pantry\IngredientView;
use App\Application\Pantry\RemoveIngredient\RemoveIngredient;
use App\Application\Pantry\RemoveIngredient\RemoveIngredientHandler;
use App\Application\Pantry\SetStock\SetStock;
use App\Application\Pantry\SetStock\SetStockHandler;
use App\Domain\Pantry\Exception\EmptyIngredientName;
use App\Domain\Pantry\Exception\IngredientNameTaken;
use App\Domain\Pantry\Exception\QuantityOutOfRange;
use App\Domain\Pantry\Location;
use App\Domain\Pantry\Unit;
use App\Domain\Shared\Exception\NotFound;
use App\Tests\Support\FreezesClock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

final class IngredientUseCasesTest extends KernelTestCase
{
    use FreezesClock;

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 08:00 UTC');
    }

    public function testAddAnIngredient(): void
    {
        $leeks = $this->add('  Leeks ', Location::Fridge, Unit::Piece);

        self::assertSame(['Leeks', 'fridge', 'piece', false, 0, 12, true, true], [$leeks->name, $leeks->location, $leeks->unit, $leeks->manual, $leeks->stock, $leeks->lowAt, $leeks->runningLow, $leeks->out]);
        self::assertTrue(Ulid::isValid($leeks->id));
    }

    public function testAddKeepsAnExplicitThreshold(): void
    {
        self::assertSame(6, $this->add('Milk', Location::Fridge, Unit::Carton, lowAt: 6)->lowAt);
    }

    public function testAddIsStoredForGood(): void
    {
        $this->add('Leeks');
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        self::assertSame(['Leeks'], array_map(static fn (IngredientView $view): string => $view->name, self::getContainer()->get(IngredientQueries::class)->all()));
    }

    public function testNameIsRequired(): void
    {
        $this->expectExceptionObject(new EmptyIngredientName());

        $this->add('  ');
    }

    public function testNamesAreUniqueIgnoringCaseAndPadding(): void
    {
        $this->add('Olive oil');

        $this->expectExceptionObject(new IngredientNameTaken('OLIVE OIL'));

        $this->add(' OLIVE OIL ');
    }

    public function testNamesAreUniqueIgnoringCaseOnAccentedLetters(): void
    {
        $this->add('Œufs');

        $this->expectExceptionObject(new IngredientNameTaken('œufs'));

        $this->add('œufs');
    }

    public function testAThresholdOutOfRangeIsRefused(): void
    {
        $this->expectExceptionObject(new QuantityOutOfRange(999));

        $this->add('Milk', lowAt: 11989);
    }

    public function testEditDescribesTheIngredientAgain(): void
    {
        $oil = $this->add('Oil', Location::Pantry, Unit::Piece);

        $edited = $this->edit($oil, 'Olive oil', Location::Freezer, Unit::Bottle, true, 6);

        self::assertSame(['Olive oil', 'freezer', 'bottle', true, 6], [$edited->name, $edited->location, $edited->unit, $edited->manual, $edited->lowAt]);
    }

    public function testEditWithoutThresholdFallsBackOnTheUnit(): void
    {
        $oil = $this->add('Oil', Location::Pantry, Unit::Piece);

        self::assertSame(3, $this->edit($oil, 'Oil', Location::Pantry, Unit::Bottle)->lowAt);
    }

    public function testEditKeepsTheStock(): void
    {
        $oil = $this->add('Oil');
        $this->adjust($oil, 24);

        self::assertSame(24, $this->edit($oil, 'Olive oil')->stock);
    }

    public function testAnIngredientMayChangeCaseWithoutClashingWithItself(): void
    {
        $oil = $this->add('olive oil');

        self::assertSame('Olive oil', $this->edit($oil, 'Olive oil')->name);
    }

    public function testCannotRenameToAnotherIngredientsName(): void
    {
        $this->add('Milk');
        $oil = $this->add('Oil');

        $this->expectExceptionObject(new IngredientNameTaken('milk'));

        $this->edit($oil, 'milk');
    }

    public function testEditOfAnUnknownIngredientIsNotFound(): void
    {
        $id = new Ulid();

        $this->expectExceptionObject(new NotFound('ingredient', (string) $id));

        self::getContainer()->get(EditIngredientHandler::class)(new EditIngredient($id, 'Oil', Location::Pantry, Unit::Bottle, false));
    }

    public function testAdjustAddsAndSubtractsRelatively(): void
    {
        $eggs = $this->add('Eggs');

        self::assertSame(24, $this->adjust($eggs, 24)->stock);
        self::assertSame(17, $this->adjust($eggs, -7)->stock);
    }

    public function testAdjustStopsAtZero(): void
    {
        $eggs = $this->add('Eggs');
        $this->adjust($eggs, 6);

        $view = $this->adjust($eggs, -24);

        self::assertSame([0, true, true], [$view->stock, $view->runningLow, $view->out]);
    }

    public function testAdjustIsStoredForGood(): void
    {
        $eggs = $this->add('Eggs');
        $this->adjust($eggs, 24);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        self::assertSame([24], array_map(static fn (IngredientView $view): int => $view->stock, self::getContainer()->get(IngredientQueries::class)->all()));
    }

    public function testAdjustOfAnUnknownIngredientIsNotFound(): void
    {
        $id = new Ulid();

        $this->expectExceptionObject(new NotFound('ingredient', (string) $id));

        self::getContainer()->get(AdjustStockHandler::class)(new AdjustStock($id, 12));
    }

    public function testSetStockReplacesTheStock(): void
    {
        $milk = $this->add('Milk', Location::Fridge, Unit::Carton);
        $this->adjust($milk, 36);

        $view = self::getContainer()->get(SetStockHandler::class)(new SetStock(Ulid::fromString($milk->id), 9));

        self::assertSame([9, false, false], [$view->stock, $view->out, $view->runningLow]);
    }

    public function testSetStockFlagsWhatRunsLow(): void
    {
        $milk = $this->add('Milk', Location::Fridge, Unit::Carton);

        $view = self::getContainer()->get(SetStockHandler::class)(new SetStock(Ulid::fromString($milk->id), 3));

        self::assertSame([true, false], [$view->runningLow, $view->out]);
    }

    public function testSetStockRefusesWhatIsOutOfRange(): void
    {
        $milk = $this->add('Milk');

        $this->expectExceptionObject(new QuantityOutOfRange(999));

        self::getContainer()->get(SetStockHandler::class)(new SetStock(Ulid::fromString($milk->id), 11989));
    }

    public function testRemoveDeletesTheIngredient(): void
    {
        $oil = $this->add('Oil');
        $this->add('Milk');

        self::getContainer()->get(RemoveIngredientHandler::class)(new RemoveIngredient(Ulid::fromString($oil->id)));
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        self::assertSame(['Milk'], array_map(static fn (IngredientView $view): string => $view->name, self::getContainer()->get(IngredientQueries::class)->all()));
    }

    public function testRemoveOfAnUnknownIngredientIsNotFound(): void
    {
        $id = new Ulid();

        $this->expectExceptionObject(new NotFound('ingredient', (string) $id));

        self::getContainer()->get(RemoveIngredientHandler::class)(new RemoveIngredient($id));
    }

    public function testTheQueryListsByNameIgnoringCase(): void
    {
        $this->add('zucchini');
        $this->add('Apples');
        $this->add('beans');

        self::assertSame(['Apples', 'beans', 'zucchini'], array_map(static fn (IngredientView $view): string => $view->name, self::getContainer()->get(IngredientQueries::class)->all()));
    }

    public function testTheQueryListsNothingForAnEmptyKitchen(): void
    {
        self::assertSame([], self::getContainer()->get(IngredientQueries::class)->all());
    }

    private function add(string $name, Location $location = Location::Pantry, Unit $unit = Unit::Piece, bool $manual = false, ?int $lowAt = null): IngredientView
    {
        return self::getContainer()->get(AddIngredientHandler::class)(new AddIngredient($name, $location, $unit, $manual, $lowAt));
    }

    private function edit(IngredientView $ingredient, string $name, Location $location = Location::Pantry, Unit $unit = Unit::Piece, bool $manual = false, ?int $lowAt = null): IngredientView
    {
        return self::getContainer()->get(EditIngredientHandler::class)(new EditIngredient(Ulid::fromString($ingredient->id), $name, $location, $unit, $manual, $lowAt));
    }

    private function adjust(IngredientView $ingredient, int $by): IngredientView
    {
        return self::getContainer()->get(AdjustStockHandler::class)(new AdjustStock(Ulid::fromString($ingredient->id), $by));
    }
}
