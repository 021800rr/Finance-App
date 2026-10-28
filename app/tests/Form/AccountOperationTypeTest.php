<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\Household;
use App\Form\AccountOperationType;
use App\Tests\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class AccountOperationTypeTest extends KernelTestCase
{
    use DatabaseTestTrait;

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

    public function testSubmitWithZeroAmountIsInvalid(): void
    {
        $account = $this->createAccount();

        $form = $this->createOperationForm();
        $form->submit([
            'account' => (string) $account->getId(),
            'amount' => '0',
        ]);

        self::assertFalse($form->get('amount')->isValid());
    }

    public function testSubmitWithAmountExceedingUpperBoundIsInvalid(): void
    {
        $account = $this->createAccount();

        $form = $this->createOperationForm();
        $form->submit([
            'account' => (string) $account->getId(),
            'amount' => '9999999',
        ]);

        self::assertFalse($form->get('amount')->isValid());
    }

    #[DataProvider('validAmounts')]
    public function testSubmitWithValidAmountIsValid(string $amount): void
    {
        $account = $this->createAccount();

        $form = $this->createOperationForm();
        $form->submit([
            'account' => (string) $account->getId(),
            'operationDate' => '2026-01-15',
            'contractor' => '1',
            'amount' => $amount,
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame($amount, $form->get('amount')->getData());
    }

    /**
     * @param array<string, string|null> $amount
     */
    #[DataProvider('blankAmounts')]
    public function testSubmitWithBlankAmountShowsFieldError(array $amount): void
    {
        $account = $this->createAccount();
        $form = $this->createOperationForm();
        $form->submit([
            'account' => (string) $account->getId(),
            'operationDate' => '2026-01-15',
            'contractor' => '1',
        ] + $amount);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('amount')->getErrors());
        self::assertStringContainsString('Please enter an amount.', (string) $form->get('amount')->getErrors());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validAmounts(): array
    {
        return ['income' => ['150.50'], 'expense' => ['-150.50']];
    }

    /**
     * @return array<string, array{array<string, string|null>}>
     */
    public static function blankAmounts(): array
    {
        return [
            'empty' => [['amount' => '']],
            'spaces' => [['amount' => '   ']],
            'null' => [['amount' => null]],
            'missing' => [[]],
        ];
    }

    public function testAccountCanBeChosenWhenCreatingAnOperation(): void
    {
        self::assertFalse($this->createOperationForm()->get('account')->isDisabled());
    }

    public function testAccountIsDisabledWhenEditingAnExistingOperation(): void
    {
        $account = $this->createAccount();
        $other = $this->createAccount();
        $operation = new AccountOperation(
            $account,
            new Contractor('Edited Contractor'),
            new \DateTimeImmutable(),
            -1000,
            $account->getCurrency()
        );

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get('form.factory');
        $form = $formFactory->create(AccountOperationType::class, options: ['existingOperation' => $operation]);
        $form->submit(['account' => (string) $other->getId(), 'amount' => '10.00']);

        self::assertTrue($form->get('account')->isDisabled());
        self::assertSame($account, $form->get('account')->getData());
    }

    private function createOperationForm(): FormInterface
    {
        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get('form.factory');

        return $formFactory->create(AccountOperationType::class, options: ['csrf_protection' => false]);
    }

    private function createAccount(): Account
    {
        $bank = new Bank('Test Bank ' . uniqid());
        $this->em()->persist($bank);

        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->find('PLN');
        if (null === $currency) {
            $currency = new Currency('PLN');
            $this->em()->persist($currency);
        }

        $accountType = new AccountType('Test Type ' . uniqid());
        $this->em()->persist($accountType);

        $household = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => true]);
        if (null === $household) {
            throw new \RuntimeException('Fixtures must provide exactly one own-funds household.');
        }

        $account = new Account($bank, $currency, $accountType, 'ACC-' . uniqid(), $household);
        $this->em()->persist($account);
        $this->em()->flush();

        return $account;
    }
}
