<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\Household;
use App\Repository\AccountOperationRepository;
use App\Service\AccountOperationFactory;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class AccountOperationFactoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private AccountOperationFactory $factory;
    private AccountOperationRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var AccountOperationFactory $factory */
        $factory = self::getContainer()->get(AccountOperationFactory::class);
        $this->factory = $factory;

        /** @var AccountOperationRepository $repository */
        $repository = $this->em()->getRepository(AccountOperation::class);
        $this->repository = $repository;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testCreateOrUpdateAndPersistCreatesNewOperationWithCorrectAmount(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor();

        $form = $this->buildSubmittedForm($account, 150.50);
        $operation = $this->factory->createOrUpdateAndPersist(null, $form, $contractor, $this->repository);

        self::assertSame(15050, $operation->getAmount());
        self::assertSame($account->getId(), $operation->getAccount()->getId());
        self::assertSame($contractor->getId(), $operation->getContractor()->getId());
        self::assertSame($account->getCurrency()->getCode(), $operation->getCurrency()->getCode());
        self::assertGreaterThan(0, $operation->getId());
    }

    public function testCreateOrUpdateAndPersistUpdatesExistingOperation(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor();
        $existing = $this->createExistingOperation($account, $contractor, 10000);

        $newContractor = $this->createContractor();
        $form = $this->buildSubmittedForm($account, 75.00);

        $updated = $this->factory->createOrUpdateAndPersist($existing, $form, $newContractor, $this->repository);

        self::assertSame($existing->getId(), $updated->getId());
        self::assertSame(7500, $updated->getAmount());
        self::assertSame($newContractor->getId(), $updated->getContractor()->getId());
    }

    public function testCreateOrUpdateAndPersistKeepsTheAccountOfTheExistingOperation(): void
    {
        $plnAccount = $this->createAccount();
        $chfAccount = $this->createAccount('CHF');
        $contractor = $this->createContractor();
        $existing = $this->createExistingOperation($plnAccount, $contractor, 10000);

        $form = $this->buildSubmittedForm($chfAccount, 20.00);
        $updated = $this->factory->createOrUpdateAndPersist($existing, $form, $contractor, $this->repository);

        self::assertSame($plnAccount->getId(), $updated->getAccount()->getId());
        self::assertSame('PLN', $updated->getCurrency()->getCode());
        self::assertSame(2000, $updated->getAmount());
    }

    public function testCreateOrUpdateAndPersistThrowsWhenAccountIsMissing(): void
    {
        $contractor = $this->createContractor();
        $form = $this->buildSubmittedFormWithoutAccount();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Account is required.');

        $this->factory->createOrUpdateAndPersist(null, $form, $contractor, $this->repository);
    }

    public function testCreateOrUpdateAndPersistDefaultsDescriptionToNullWhenBlank(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor();
        $form = $this->buildSubmittedForm($account, 50.00, description: '');

        $operation = $this->factory->createOrUpdateAndPersist(null, $form, $contractor, $this->repository);

        self::assertSame('', $operation->getDescription());
    }

    public function testCreateOrUpdateAndPersistThrowsWhenOperationDateIsMissing(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor();
        $form = $this->buildSubmittedFormWithoutOperationDate($account);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operation date is required.');

        $this->factory->createOrUpdateAndPersist(null, $form, $contractor, $this->repository);
    }

    public function testStringCastOfBoundaryFloatValuesNeverProducesScientificNotation(): void
    {
        $boundaryValues = [
            0.01,
            -0.01,
            999999.99,
            -999999.99,
            1000000.0,
            -1000000.0,
            0.10,
            0.001,
        ];

        foreach ($boundaryValues as $value) {
            $stringified = (string) $value;

            self::assertMatchesRegularExpression(
                '/^-?\d+(\.\d+)?$/',
                $stringified,
                "String cast of {$value} produced non-decimal format: {$stringified}"
            );
        }
    }

    private function buildSubmittedForm(
        ?Account $account,
        ?float $amount,
        bool $invalidAmount = false,
        ?string $description = null,
    ): FormInterface {
        /** @var FormFactoryInterface $formFactory */
        $formFactory = AccountOperationFactoryTest::getContainer()->get('form.factory');

        $builder = $formFactory->createBuilder();
        $builder->add('account', HiddenType::class, ['mapped' => false]);
        $builder->add('operationDate', HiddenType::class, ['mapped' => false]);
        $builder->add('amount', HiddenType::class, ['mapped' => false]);
        $builder->add('amount', MoneyType::class, [
            'mapped' => false,
            'currency' => false,
            'input' => 'string',
        ]);
        $builder->add('description', HiddenType::class, ['mapped' => false, 'required' => false]);
        $builder->add('confirmed', HiddenType::class, ['mapped' => false]);
        $form = $builder->getForm();
        $form->get('account')->setData($account);
        $form->get('operationDate')->setData(new DateTimeImmutable());
        $form->get('amount')->setData($invalidAmount ? [] : (string) $amount);
        $form->get('description')->setData($description);
        $form->get('confirmed')->setData(false);

        return $form;
    }

    private function buildSubmittedFormWithoutAccount(): FormInterface
    {
        return $this->buildSubmittedForm(null, 10.00);
    }

    private function createExistingOperation(Account $account, Contractor $contractor, int $amount): AccountOperation
    {
        $operation = new AccountOperation($account, $contractor, new DateTimeImmutable(), $amount, $account->getCurrency());
        $this->em()->persist($operation);
        $this->em()->flush();

        return $operation;
    }

    private function createAccount(string $currencyCode = 'PLN'): Account
    {
        $bank = new Bank('Test Bank ' . uniqid());
        $this->em()->persist($bank);

        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->findOneBy(['code' => $currencyCode]);
        if (null === $currency) {
            $currency = new Currency($currencyCode);
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

    private function createContractor(): Contractor
    {
        $contractor = new Contractor('Test Contractor ' . uniqid());
        $this->em()->persist($contractor);
        $this->em()->flush();

        return $contractor;
    }

    private function buildSubmittedFormWithoutOperationDate(Account $account): FormInterface
    {
        /** @var FormFactoryInterface $formFactory */
        $formFactory = AccountOperationFactoryTest::getContainer()->get('form.factory');

        $builder = $formFactory->createBuilder();
        $builder->add('account', HiddenType::class, ['mapped' => false]);
        $builder->add('operationDate', HiddenType::class, ['mapped' => false]);
        $builder->add('amount', MoneyType::class, ['mapped' => false, 'currency' => false, 'input' => 'string']);
        $builder->add('description', HiddenType::class, ['mapped' => false, 'required' => false]);
        $builder->add('confirmed', HiddenType::class, ['mapped' => false]);
        $form = $builder->getForm();

        $form->get('account')->setData($account);
        $form->get('operationDate')->setData(null);
        $form->get('amount')->setData('100.0');
        $form->get('description')->setData(null);
        $form->get('confirmed')->setData(false);

        return $form;
    }
}
