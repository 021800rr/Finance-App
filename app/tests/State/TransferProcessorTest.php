<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Post;
use App\ApiResource\TransferResource;
use App\Constant\Contractors;
use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\Household;
use App\Repository\ContractorRepository;
use App\State\TransferProcessor;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class TransferProcessorTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private TransferProcessor $processor;
    private ContractorRepository $contractorRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var TransferProcessor $processor */
        $processor = self::getContainer()->get(TransferProcessor::class);
        $this->processor = $processor;

        /** @var ContractorRepository $contractorRepository */
        $contractorRepository = self::getContainer()->get(ContractorRepository::class);
        $this->contractorRepository = $contractorRepository;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testProcessCreatesTransferBetweenSameCurrencyAccountsWithoutAmountTo(): void
    {
        $this->ensureFixedContractor();
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('PLN');

        $resource = $this->buildResource(
            fromAccountId: $from->getId(),
            toAccountId: $to->getId(),
            amountFrom: '100.00',
        );

        $result = $this->processor->process($resource, new Post());

        self::assertNotNull($result->transactionUuid);
        self::assertNotNull($result->outOperationId);
        self::assertNotNull($result->inOperationId);
        self::assertSame('-100.00', $result->outAmount);
        self::assertSame('100.00', $result->inAmount);
        self::assertSame('PLN', $result->outCurrencyCode);
        self::assertSame('PLN', $result->inCurrencyCode);

        $outOperation = $this->em()->getRepository(AccountOperation::class)->find($result->outOperationId);
        self::assertInstanceOf(AccountOperation::class, $outOperation);
        self::assertSame(Contractors::SELF_TRANSFER_NAME, $outOperation->getContractor()->getName());
    }

    public function testProcessCreatesTransferBetweenDifferentCurrenciesWithAmountTo(): void
    {
        $this->ensureFixedContractor();
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('EUR');

        $resource = $this->buildResource(
            fromAccountId: $from->getId(),
            toAccountId: $to->getId(),
            amountFrom: '100.00',
            amountTo: '23.00',
        );

        $result = $this->processor->process($resource, new Post());

        self::assertSame('-100.00', $result->outAmount);
        self::assertSame('23.00', $result->inAmount);
        self::assertSame('PLN', $result->outCurrencyCode);
        self::assertSame('EUR', $result->inCurrencyCode);
    }

    public function testProcessThrowsWhenDifferentCurrenciesAndAmountToMissing(): void
    {
        $this->ensureFixedContractor();
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('EUR');

        $resource = $this->buildResource(fromAccountId: $from->getId(), toAccountId: $to->getId(), amountFrom: '100.00');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsWhenTransferringToSameAccount(): void
    {
        $this->ensureFixedContractor();
        $account = $this->createAccount('PLN');

        $resource = $this->buildResource(fromAccountId: $account->getId(), toAccountId: $account->getId(), amountFrom: '100.00');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsWhenAmountFromIsZero(): void
    {
        $this->ensureFixedContractor();
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('PLN');

        $resource = $this->buildResource(fromAccountId: $from->getId(), toAccountId: $to->getId(), amountFrom: '0.00');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    #[DataProvider('provideNonPositiveAmounts')]
    public function testProcessRejectsNonPositiveAmountsWith422AndNoOperations(string $amountFrom, string $amountTo): void
    {
        $this->ensureFixedContractor();
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('CHF');
        $resource = $this->buildResource(
            fromAccountId: $from->getId(),
            toAccountId: $to->getId(),
            amountFrom: $amountFrom,
            amountTo: $amountTo,
        );
        $operations = $this->em()->getRepository(AccountOperation::class);
        $countBefore = $operations->count([]);

        try {
            $this->processor->process($resource, new Post());
            self::fail('A non-positive transfer amount must be rejected.');
        } catch (UnprocessableEntityHttpException $exception) {
            self::assertSame(422, $exception->getStatusCode());
            self::assertSame('Transfer amounts must be positive.', $exception->getMessage());
        }

        self::assertSame($countBefore, $operations->count([]));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function provideNonPositiveAmounts(): array
    {
        return [
            'negative debit' => ['-100.00', '25.00'],
            'negative credit' => ['100.00', '-25.00'],
            'zero credit' => ['100.00', '0.00'],
            'credit rounded to zero' => ['100.00', '0.004'],
            'zero debit' => ['0.00', '100.00'],
            'debit rounded to zero' => ['0.004', '100.00'],
        ];
    }

    public function testProcessThrowsUnprocessableWhenFromAccountIdMissing(): void
    {
        $this->ensureFixedContractor();
        $to = $this->createAccount('PLN');

        $resource = $this->buildResource(fromAccountId: null, toAccountId: $to->getId(), amountFrom: '10.00');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsUnprocessableWhenToAccountIdMissing(): void
    {
        $this->ensureFixedContractor();
        $from = $this->createAccount('PLN');

        $resource = $this->buildResource(fromAccountId: $from->getId(), toAccountId: null, amountFrom: '10.00');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsNotFoundWhenFromAccountDoesNotExist(): void
    {
        $this->ensureFixedContractor();
        $to = $this->createAccount('PLN');

        $resource = $this->buildResource(fromAccountId: 999999999, toAccountId: $to->getId(), amountFrom: '10.00');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsNotFoundWhenToAccountDoesNotExist(): void
    {
        $this->ensureFixedContractor();
        $from = $this->createAccount('PLN');

        $resource = $this->buildResource(fromAccountId: $from->getId(), toAccountId: 999999999, amountFrom: '10.00');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsNotFoundWhenFromAccountIdAboveInt4Max(): void
    {
        $this->ensureFixedContractor();
        $to = $this->createAccount('PLN');

        $resource = $this->buildResource(fromAccountId: PHP_INT_MAX, toAccountId: $to->getId(), amountFrom: '10.00');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('fromAccountId');

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsNotFoundWhenToAccountIdAboveInt4Max(): void
    {
        $this->ensureFixedContractor();
        $from = $this->createAccount('PLN');

        $resource = $this->buildResource(fromAccountId: $from->getId(), toAccountId: PHP_INT_MAX, amountFrom: '10.00');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('toAccountId');

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsUnprocessableWhenOperationDateMissing(): void
    {
        $this->ensureFixedContractor();
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('PLN');

        $resource = $this->buildResource(
            fromAccountId: $from->getId(),
            toAccountId: $to->getId(),
            amountFrom: '10.00',
            operationDate: null,
        );

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsUnprocessableWhenOperationDateInvalid(): void
    {
        $this->ensureFixedContractor();
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('PLN');

        $resource = $this->buildResource(
            fromAccountId: $from->getId(),
            toAccountId: $to->getId(),
            amountFrom: '10.00',
            operationDate: 'not-a-date',
        );

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsUnprocessableWhenAmountFromMissing(): void
    {
        $this->ensureFixedContractor();
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('PLN');

        $resource = $this->buildResource(fromAccountId: $from->getId(), toAccountId: $to->getId(), amountFrom: null);

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsWhenDataIsNotTransferResource(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->processor->process(new \stdClass(), new Post());
    }

    /**
     * Sentinel distinguishing "argument omitted" (auto-fill today's date) from an explicit
     * null passed by a test that wants to verify the missing-date validation path.
     */
    private const string AUTO_DATE = '__auto_date__';

    private function buildResource(
        ?int $fromAccountId = null,
        ?int $toAccountId = null,
        ?string $amountFrom = '10.00',
        ?string $amountTo = null,
        ?string $operationDate = self::AUTO_DATE,
        ?string $description = null,
    ): TransferResource {
        $resource = new TransferResource();
        $resource->fromAccountId = $fromAccountId;
        $resource->toAccountId = $toAccountId;
        $resource->amountFrom = $amountFrom;
        $resource->amountTo = $amountTo;
        $resource->operationDate = self::AUTO_DATE === $operationDate
            ? (new DateTimeImmutable())->format('Y-m-d')
            : $operationDate;
        $resource->description = $description;

        return $resource;
    }

    private function ensureFixedContractor(): Contractor
    {
        $contractor = $this->contractorRepository->findOneByName(Contractors::SELF_TRANSFER_NAME);
        if ($contractor) {
            return $contractor;
        }

        $contractor = new Contractor(Contractors::SELF_TRANSFER_NAME);
        $this->em()->persist($contractor);
        $this->em()->flush();

        return $contractor;
    }

    private function createAccount(string $currencyCode): Account
    {
        $bank = new Bank('Test Bank ' . uniqid());
        $this->em()->persist($bank);

        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->find($currencyCode);
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
}
