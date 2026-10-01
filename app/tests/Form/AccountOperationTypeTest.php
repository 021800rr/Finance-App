<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\Account;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\Household;
use App\Form\AccountOperationType;
use App\Tests\DatabaseTestTrait;
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

    public function testSubmitWithValidAmountIsValid(): void
    {
        $account = $this->createAccount();

        $form = $this->createOperationForm();
        $form->submit([
            'account' => (string) $account->getId(),
            'amount' => '150.50',
        ]);

        self::assertTrue($form->get('amount')->isValid());
    }

    private function createOperationForm(): FormInterface
    {
        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get('form.factory');

        return $formFactory->create(AccountOperationType::class);
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
