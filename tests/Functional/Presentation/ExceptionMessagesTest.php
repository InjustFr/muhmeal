<?php

declare(strict_types=1);

namespace App\Tests\Functional\Presentation;

use App\Domain\Identity\Exception\EmailAlreadyTaken;
use App\Domain\Identity\Exception\EmptyDisplayName;
use App\Domain\Identity\Exception\InvalidEmail;
use App\Domain\Pantry\Exception\EmptyIngredientName;
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
        yield 'missing anything else' => [new NotFound('recipe', 'abc'), 'This item no longer exists.', 'Cet élément n’existe plus.'];
        yield 'invalid email' => [new InvalidEmail('lou'), '“lou” is not a valid email address.', '« lou » n’est pas une adresse e-mail valide.'];
        yield 'empty display name' => [new EmptyDisplayName(), 'Tell us what to call you.', 'Dites-nous comment vous appeler.'];
        yield 'email taken' => [new EmailAlreadyTaken('lou@example.com'), 'An account already uses lou@example.com. Sign in instead.', 'Un compte utilise déjà lou@example.com. Connectez-vous plutôt.'];
        yield 'empty ingredient name' => [new EmptyIngredientName(), 'Give the ingredient a name.', 'Donnez un nom à l’ingrédient.'];
        yield 'long ingredient name' => [new IngredientNameTooLong(60), 'An ingredient name has at most 60 characters.', 'Un nom d’ingrédient compte au plus 60 caractères.'];
        yield 'ingredient name taken' => [new IngredientNameTaken('Milk'), 'There is already an ingredient called “Milk”.', 'Il existe déjà un ingrédient nommé « Milk ».'];
        yield 'quantity out of range' => [new QuantityOutOfRange(999), 'A quantity goes from 0 to 999 units.', 'Une quantité va de 0 à 999 unités.'];
    }
}
