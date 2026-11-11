<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Form\TransferType;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class TransferTypeTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testSubmitWithSameAccountShowsDestinationAccountError(): void
    {
        $account = $this->createAccount('PLN');
        $form = $this->createTransferForm();
        $form->submit([
            'fromAccount' => (string) $account->getId(),
            'toAccount' => (string) $account->getId(),
            'amountFrom' => '100',
            'date' => '2026-01-15',
        ]);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->getErrors(true));
        self::assertStringContainsString(
            'Choose a different destination account.',
            (string) $form->get('toAccount')->getErrors(),
        );
    }

    /**
     * The form and the API (TransferInput) report an empty amount with the same code.
     */
    public function testEmptyAmountUsesTheSameCodeAsTheApi(): void
    {
        $form = $this->createTransferForm();
        $form->submit([
            'fromAccount' => (string) $this->createAccount('PLN')->getId(),
            'toAccount' => (string) $this->createAccount('PLN')->getId(),
            'amountFrom' => '',
            'date' => '2026-01-15',
        ]);

        $errors = iterator_to_array($form->get('amountFrom')->getErrors());
        self::assertCount(1, $errors);
        self::assertInstanceOf(FormError::class, $errors[0]);
        self::assertSame('transfer.amount_from_required', $errors[0]->getMessageTemplate());
    }

    public function testSubmitWithSameCurrencyAccountsIsValidWithoutAmountTo(): void
    {
        $fromAccount = $this->createAccount('PLN');
        $toAccount = $this->createAccount('PLN');

        $form = $this->createTransferForm();
        $form->submit([
            'fromAccount' => (string) $fromAccount->getId(),
            'toAccount' => (string) $toAccount->getId(),
            'amountFrom' => '100',
            'date' => new \DateTimeImmutable()->format('Y-m-d'),
        ]);

        self::assertTrue($form->isValid());
    }

    public function testSubmitWithDifferentCurrencyAccountsWithoutAmountToIsInvalid(): void
    {
        $fromAccount = $this->createAccount('PLN');
        $toAccount = $this->createAccount('CHF');

        $form = $this->createTransferForm();
        $form->submit([
            'fromAccount' => (string) $fromAccount->getId(),
            'toAccount' => (string) $toAccount->getId(),
            'amountFrom' => '100',
            'date' => new \DateTimeImmutable()->format('Y-m-d'),
        ]);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->getErrors(true));
    }

    public function testSubmitWithDifferentCurrencyAccountsWithAmountToIsValid(): void
    {
        $fromAccount = $this->createAccount('PLN');
        $toAccount = $this->createAccount('CHF');

        $form = $this->createTransferForm();
        $form->submit([
            'fromAccount' => (string) $fromAccount->getId(),
            'toAccount' => (string) $toAccount->getId(),
            'amountFrom' => '100',
            'amountTo' => '23',
            'date' => new \DateTimeImmutable()->format('Y-m-d'),
        ]);

        self::assertTrue($form->isValid());
    }

    private function createTransferForm(): FormInterface
    {
        /** @var FormFactoryInterface $formFactory */
        $formFactory = self::getContainer()->get('form.factory');

        return $formFactory->create(TransferType::class, null, ['csrf_protection' => false]);
    }
}
