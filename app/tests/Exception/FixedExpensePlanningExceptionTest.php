<?php

declare(strict_types=1);

namespace App\Tests\Exception;

use App\Exception\FixedExpensePlanningException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FixedExpensePlanningExceptionTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{FixedExpensePlanningException}>
     */
    public static function exceptions(): iterable
    {
        yield 'no main account' => [FixedExpensePlanningException::noMainAccount()];
        yield 'main account not ours' => [FixedExpensePlanningException::mainAccountNotOwn('ACC-1', 'Matka')];
        yield 'currency mismatch' => [FixedExpensePlanningException::currencyMismatch('PLN', 'ACC-1', 'CHF')];
        yield 'suspected duplicate' => [FixedExpensePlanningException::suspectedDuplicate('Rent', '-150.00', '2026-11-10')];
    }

    /**
     * The API shows the exception message, the web UI the translation of the same key: they must say the same.
     */
    #[DataProvider('exceptions')]
    public function testEnglishTranslationMatchesTheExceptionMessage(FixedExpensePlanningException $exception): void
    {
        /** @var TranslatorInterface $translator */
        $translator = self::getContainer()->get(TranslatorInterface::class);

        self::assertSame(
            $exception->getMessage(),
            $translator->trans($exception->translationKey, $exception->translationParameters, 'messages', 'en'),
        );
    }

    #[DataProvider('exceptions')]
    public function testPolishTranslationExistsAndHasNoUnreplacedPlaceholders(FixedExpensePlanningException $exception): void
    {
        /** @var TranslatorInterface $translator */
        $translator = self::getContainer()->get(TranslatorInterface::class);

        $polish = $translator->trans($exception->translationKey, $exception->translationParameters, 'messages', 'pl');

        self::assertNotSame($exception->translationKey, $polish);
        self::assertStringNotContainsString('%', $polish);
        self::assertNotSame($exception->getMessage(), $polish);
    }
}
