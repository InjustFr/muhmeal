<?php

declare(strict_types=1);

namespace App\Tests\Functional\Presentation;

use App\Domain\Cooking\Exception\EmptyMeal;
use App\Domain\Cooking\Exception\EmptyRecipeName;
use App\Domain\Cooking\Exception\IngredientListedTwice;
use App\Domain\Cooking\Exception\MealNeedsEater;
use App\Domain\Cooking\Exception\MealTitleTooLong;
use App\Domain\Cooking\Exception\NotAPrep;
use App\Domain\Cooking\Exception\RecipeNameTaken;
use App\Domain\Cooking\Exception\RecipeNameTooLong;
use App\Domain\Cooking\Exception\RecipeNeedsIngredient;
use App\Domain\Cooking\Exception\ZeroQuantity;
use App\Domain\Cooking\Exception\ZeroYield;
use App\Domain\Identity\Exception\EmailAlreadyTaken;
use App\Domain\Identity\Exception\EmptyDisplayName;
use App\Domain\Identity\Exception\InvalidEmail;
use App\Domain\Pantry\Exception\EmptyIngredientName;
use App\Domain\Pantry\Exception\IngredientInUse;
use App\Domain\Pantry\Exception\IngredientNameTaken;
use App\Domain\Pantry\Exception\IngredientNameTooLong;
use App\Domain\Pantry\Exception\QuantityOutOfRange;
use App\Domain\Shared\Exception\DomainException;
use App\Domain\Shared\Exception\InvalidDay;
use App\Domain\Shared\Exception\NotFound;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ExceptionMessagesTest extends KernelTestCase
{
    #[DataProvider('exceptions')]
    public function testEveryRuleViolationSpeaksEnglishAndFrench(DomainException $exception, string $english, string $french): void
    {
        $translator = self::getContainer()->get(TranslatorInterface::class);

        self::assertSame($english, $translator->trans($exception->getMessage(), $exception->parameters(), 'exceptions', 'en'));
        self::assertSame($french, $translator->trans($exception->getMessage(), $exception->parameters(), 'exceptions', 'fr'));
    }

    /** @return iterable<array{DomainException, string, string}> */
    public static function exceptions(): iterable
    {
        yield 'invalid day' => [new InvalidDay('2026-02-30'), '“2026-02-30” is not a valid date.', '« 2026-02-30 » n’est pas une date valide.'];
        yield 'missing user' => [new NotFound('user', 'abc'), 'This person no longer exists.', 'Cette personne n’existe plus.'];
        yield 'missing ingredient' => [new NotFound('ingredient', 'abc'), 'This ingredient no longer exists.', 'Cet ingrédient n’existe plus.'];
        yield 'missing recipe' => [new NotFound('recipe', 'abc'), 'This recipe no longer exists.', 'Cette recette n’existe plus.'];
        yield 'missing meal' => [new NotFound('meal', 'abc'), 'This meal no longer exists.', 'Ce repas n’existe plus.'];
        yield 'missing anything else' => [new NotFound('grocery list', 'abc'), 'This item no longer exists.', 'Cet élément n’existe plus.'];
        yield 'invalid email' => [new InvalidEmail('lou'), '“lou” is not a valid email address.', '« lou » n’est pas une adresse e-mail valide.'];
        yield 'empty display name' => [new EmptyDisplayName(), 'Tell us what to call you.', 'Dites-nous comment vous appeler.'];
        yield 'email taken' => [new EmailAlreadyTaken('lou@example.com'), 'An account already uses lou@example.com. Sign in instead.', 'Un compte utilise déjà lou@example.com. Connectez-vous plutôt.'];
        yield 'empty ingredient name' => [new EmptyIngredientName(), 'Give the ingredient a name.', 'Donnez un nom à l’ingrédient.'];
        yield 'long ingredient name' => [new IngredientNameTooLong(60), 'An ingredient name has at most 60 characters.', 'Un nom d’ingrédient compte au plus 60 caractères.'];
        yield 'ingredient name taken' => [new IngredientNameTaken('Milk'), 'There is already an ingredient called “Milk”.', 'Il existe déjà un ingrédient nommé « Milk ».'];
        yield 'quantity out of range' => [new QuantityOutOfRange(999), 'A quantity goes from 0 to 999 units.', 'Une quantité va de 0 à 999 unités.'];
        yield 'ingredient in use' => [new IngredientInUse('Pasta', 'Aglio, Soup'), '“Pasta” cannot be deleted: it is used by Aglio, Soup.', '« Pasta » ne peut pas être supprimé : il est utilisé par Aglio, Soup.'];
        yield 'empty recipe name' => [new EmptyRecipeName(), 'Give the recipe a name.', 'Donnez un nom à la recette.'];
        yield 'long recipe name' => [new RecipeNameTooLong(80), 'A recipe name has at most 80 characters.', 'Un nom de recette compte au plus 80 caractères.'];
        yield 'recipe name taken' => [new RecipeNameTaken('Soup'), 'There is already a recipe called “Soup”.', 'Il existe déjà une recette nommée « Soup ».'];
        yield 'recipe without ingredient' => [new RecipeNeedsIngredient(), 'A recipe needs at least one ingredient.', 'Une recette demande au moins un ingrédient.'];
        yield 'ingredient listed twice' => [new IngredientListedTwice('Pasta'), '“Pasta” is listed twice.', '« Pasta » apparaît deux fois.'];
        yield 'zero quantity' => [new ZeroQuantity('Pasta'), 'The amount of “Pasta” has to be more than zero.', 'La quantité de « Pasta » doit être supérieure à zéro.'];
        yield 'zero yield' => [new ZeroYield(), 'A prep has to make more than zero.', 'Une préparation doit produire plus de zéro.'];
        yield 'not a prep' => [new NotAPrep('Pasta'), '“Pasta” makes a meal, not an ingredient.', '« Pasta » fait un repas, pas un ingrédient.'];
        yield 'meal without eater' => [new MealNeedsEater(), 'Someone has to eat the meal.', 'Quelqu’un doit manger ce repas.'];
        yield 'empty meal' => [new EmptyMeal(), 'Give the meal a title or at least one ingredient.', 'Donnez un titre au repas ou au moins un ingrédient.'];
        yield 'long meal title' => [new MealTitleTooLong(80), 'A meal title has at most 80 characters.', 'Un titre de repas compte au plus 80 caractères.'];
    }
}
