<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Account;
use App\Service\AccountOperationFactory;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class AccountOperationFactoryTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

    private AccountOperationFactory $factory;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var AccountOperationFactory $factory */
        $factory = self::getContainer()->get(AccountOperationFactory::class);
        $this->factory = $factory;
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
        $operation = $this->factory->createOrUpdateAndPersist(null, $form, $contractor);

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
        $existing = $this->createOperation($account, 10000, contractor: $contractor);

        $newContractor = $this->createContractor();
        $form = $this->buildSubmittedForm($account, 75.00);

        $updated = $this->factory->createOrUpdateAndPersist($existing, $form, $newContractor);

        self::assertSame($existing->getId(), $updated->getId());
        self::assertSame(7500, $updated->getAmount());
        self::assertSame($newContractor->getId(), $updated->getContractor()->getId());
    }

    public function testCreateOrUpdateAndPersistKeepsTheAccountOfTheExistingOperation(): void
    {
        $plnAccount = $this->createAccount();
        $chfAccount = $this->createAccount('CHF');
        $contractor = $this->createContractor();
        $existing = $this->createOperation($plnAccount, 10000, contractor: $contractor);

        $form = $this->buildSubmittedForm($chfAccount, 20.00);
        $updated = $this->factory->createOrUpdateAndPersist($existing, $form, $contractor);

        self::assertSame($plnAccount->getId(), $updated->getAccount()->getId());
        self::assertSame('PLN', $updated->getCurrency()->getCode());
        self::assertSame(2000, $updated->getAmount());
    }

    public function testCreateOrUpdateAndPersistThrowsWhenAccountIsMissing(): void
    {
        $contractor = $this->createContractor();
        $form = $this->buildSubmittedFormWithoutAccount();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('account is required.');

        $this->factory->createOrUpdateAndPersist(null, $form, $contractor);
    }

    public function testCreateOrUpdateAndPersistTurnsABlankDescriptionIntoNull(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor();
        $form = $this->buildSubmittedForm($account, 50.00, description: '');

        $operation = $this->factory->createOrUpdateAndPersist(null, $form, $contractor);

        self::assertNull($operation->getDescription());
    }

    public function testCreateOrUpdateAndPersistThrowsWhenOperationDateIsMissing(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor();
        $form = $this->buildSubmittedFormWithoutOperationDate($account);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('operationDate is required.');

        $this->factory->createOrUpdateAndPersist(null, $form, $contractor);
    }

    private function buildSubmittedForm(
        ?Account $account,
        ?float $amount,
        ?string $description = null,
    ): FormInterface {
        /** @var FormFactoryInterface $formFactory */
        $formFactory = self::getContainer()->get('form.factory');

        $builder = $formFactory->createBuilder();
        $builder->add('account', HiddenType::class, ['mapped' => false]);
        $builder->add('operationDate', HiddenType::class, ['mapped' => false]);
        $builder->add('amount', MoneyType::class, [
            'mapped' => false,
            'currency' => false,
            'input' => 'string',
        ]);
        $builder->add('description', HiddenType::class, ['mapped' => false, 'required' => false]);
        $builder->add('confirmed', HiddenType::class, ['mapped' => false]);
        $form = $builder->getForm();
        $form->get('account')->setData($account);
        $form->get('operationDate')->setData(new \DateTimeImmutable());
        $form->get('amount')->setData((string) $amount);
        $form->get('description')->setData($description);
        $form->get('confirmed')->setData(false);

        return $form;
    }

    private function buildSubmittedFormWithoutAccount(): FormInterface
    {
        return $this->buildSubmittedForm(null, 10.00);
    }

    private function buildSubmittedFormWithoutOperationDate(Account $account): FormInterface
    {
        /** @var FormFactoryInterface $formFactory */
        $formFactory = self::getContainer()->get('form.factory');

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
