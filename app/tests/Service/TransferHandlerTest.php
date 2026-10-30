<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Constant\Contractors;
use App\Entity\Account;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\Household;
use App\Service\TransferHandler;
use App\Tests\DatabaseTestTrait;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class TransferHandlerTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private TransferHandler $handler;
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        $container = self::getContainer();

        /** @var TransferHandler $handler */
        $handler = $container->get(TransferHandler::class);
        $this->handler = $handler;

        /** @var FormFactoryInterface $formFactory */
        $formFactory = $container->get('form.factory');
        $this->formFactory = $formFactory;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testHandleCreatesOutgoingAndIncomingOperationsInSameCurrency(): void
    {
        $fromAccount = $this->createAccount('PLN');
        $toAccount = $this->createAccount('PLN');

        $form = $this->buildForm(
            $fromAccount,
            $toAccount,
            new \DateTimeImmutable('2026-01-15'),
            100.50,
            null,
            'Test transfer'
        );

        $result = $this->handler->handle($form);

        self::assertSame($fromAccount, $result['out']->getAccount());
        self::assertSame($toAccount, $result['in']->getAccount());
        self::assertSame(-10050, $result['out']->getAmount());
        self::assertSame(10050, $result['in']->getAmount());
        self::assertSame('Test transfer', $result['out']->getDescription());
        self::assertSame(Contractors::SELF_TRANSFER_NAME, $result['out']->getContractor()->getName());
        self::assertSame(Contractors::SELF_TRANSFER_NAME, $result['in']->getContractor()->getName());
    }

    public function testHandleNormalizesBlankDescriptionToNull(): void
    {
        $fromAccount = $this->createAccount('PLN');
        $toAccount = $this->createAccount('PLN');

        $form = $this->buildForm(
            $fromAccount,
            $toAccount,
            new \DateTimeImmutable('2026-01-15'),
            50.0,
            null,
            '   '
        );

        $result = $this->handler->handle($form);

        self::assertNull($result['out']->getDescription());
    }

    public function testHandleThrowsWhenFromAccountIsMissing(): void
    {
        $form = $this->buildForm(
            null,
            $this->createAccount('PLN'),
            new \DateTimeImmutable(),
            50.0,
            null,
            null
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('fromAccount is required.');

        $this->handler->handle($form);
    }

    public function testHandleThrowsWhenDateIsMissing(): void
    {
        $form = $this->buildForm(
            $this->createAccount('PLN'),
            $this->createAccount('PLN'),
            null,
            50.0,
            null,
            null
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Date is required.');

        $this->handler->handle($form);
    }

    public function testHandleThrowsWhenAmountFromIsNotNumeric(): void
    {
        $form = $this->buildFormWithRawAmounts(rawAmountFrom: 'not-a-number', rawAmountTo: null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid decimal string: not-a-number');

        $this->handler->handle($form);
    }

    public function testHandleThrowsWhenAmountToIsNotNumeric(): void
    {
        $form = $this->buildFormWithRawAmounts(rawAmountFrom: '50.0', rawAmountTo: 'not-a-number', toCurrency: 'CHF');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid decimal string: not-a-number');

        $this->handler->handle($form);
    }

    private function buildForm(
        mixed $fromAccount,
        mixed $toAccount,
        mixed $date,
        float $amountFrom,
        ?float $amountTo,
        mixed $description,
    ): FormInterface {
        $form = $this->formFactory->createNamed('', FormType::class, null, ['csrf_protection' => false]);
        $form->add('fromAccount', HiddenType::class, ['mapped' => false]);
        $form->add('toAccount', HiddenType::class, ['mapped' => false]);
        $form->add('date', HiddenType::class, ['mapped' => false]);
        $form->add('amountFrom', MoneyType::class, ['mapped' => false, 'currency' => false, 'input' => 'string']);
        $form->add('amountTo', MoneyType::class, ['mapped' => false, 'currency' => false, 'required' => false, 'input' => 'string']);
        $form->add('description', HiddenType::class, ['mapped' => false, 'required' => false]);

        $form->get('fromAccount')->setData($fromAccount);
        $form->get('toAccount')->setData($toAccount);
        $form->get('date')->setData($date);
        $form->get('amountFrom')->setData((string) $amountFrom);
        $form->get('amountTo')->setData(null === $amountTo ? null : (string) $amountTo);
        $form->get('description')->setData($description);

        return $form;
    }

    /**
     * Used only to exercise the "not numeric" branches: MoneyType's own transformer
     * throws before our validation runs if given a non-numeric value, so amount
     * fields here use HiddenType (no transformer) to let the raw value pass through.
     */
    private function buildFormWithRawAmounts(
        mixed $rawAmountFrom,
        mixed $rawAmountTo,
        string $toCurrency = 'PLN',
    ): FormInterface
    {
        $form = $this->formFactory->createNamed('', FormType::class, null, ['csrf_protection' => false]);
        $form->add('fromAccount', HiddenType::class, ['mapped' => false]);
        $form->add('toAccount', HiddenType::class, ['mapped' => false]);
        $form->add('date', HiddenType::class, ['mapped' => false]);
        $form->add('amountFrom', HiddenType::class, ['mapped' => false]);
        $form->add('amountTo', HiddenType::class, ['mapped' => false, 'required' => false]);
        $form->add('description', HiddenType::class, ['mapped' => false, 'required' => false]);

        $form->get('fromAccount')->setData($this->createAccount('PLN'));
        $form->get('toAccount')->setData($this->createAccount($toCurrency));
        $form->get('date')->setData(new \DateTimeImmutable());
        $form->get('amountFrom')->setData($rawAmountFrom);
        $form->get('amountTo')->setData($rawAmountTo);
        $form->get('description')->setData(null);

        return $form;
    }

    private function createAccount(string $currencyCode): Account
    {
        $bank = new Bank('Test Bank ' . uniqid());
        $this->em()->persist($bank);

        $currency = $this->findOrCreateCurrency($currencyCode);

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

    private function findOrCreateCurrency(string $code): Currency
    {
        $repository = $this->em()->getRepository(Currency::class);
        $currency = $repository->findOneBy(['code' => $code]);
        if (null === $currency) {
            $currency = new Currency($code);
            $this->em()->persist($currency);
        }

        return $currency;
    }
}
