<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Post;
use App\ApiResource\Input\TransferInput;
use App\Constant\Contractors;
use App\Entity\AccountOperation;
use App\Entity\Contractor;
use App\Repository\ContractorRepository;
use App\State\TransferProcessor;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

final class TransferProcessorTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

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

        $input = $this->buildInput(
            fromAccountId: $from->getId(),
            toAccountId: $to->getId(),
            amountFrom: '100.00',
        );

        $result = $this->processor->process($input, new Post());

        self::assertTrue(Uuid::isValid($result->transactionUuid));
        self::assertNotSame($result->outOperationId, $result->inOperationId);
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
        $to = $this->createAccount($this->createCurrency('EUR'));

        $input = $this->buildInput(
            fromAccountId: $from->getId(),
            toAccountId: $to->getId(),
            amountFrom: '100.00',
            amountTo: '23.00',
        );

        $result = $this->processor->process($input, new Post());

        self::assertSame('-100.00', $result->outAmount);
        self::assertSame('23.00', $result->inAmount);
        self::assertSame('PLN', $result->outCurrencyCode);
        self::assertSame('EUR', $result->inCurrencyCode);
    }

    public function testProcessThrowsWhenDifferentCurrenciesAndAmountToMissing(): void
    {
        $this->ensureFixedContractor();
        $from = $this->createAccount('PLN');
        $to = $this->createAccount($this->createCurrency('EUR'));

        $input = $this->buildInput(
            fromAccountId: $from->getId(),
            toAccountId: $to->getId(),
            amountFrom: '100.00',
        );

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsWhenTransferringToSameAccount(): void
    {
        $this->ensureFixedContractor();
        $account = $this->createAccount('PLN');

        $input = $this->buildInput(
            fromAccountId: $account->getId(),
            toAccountId: $account->getId(),
            amountFrom: '100.00',
        );

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsWhenAmountFromIsZero(): void
    {
        $this->ensureFixedContractor();
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('PLN');

        $input = $this->buildInput(fromAccountId: $from->getId(), toAccountId: $to->getId(), amountFrom: '0.00');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    #[DataProvider('provideNonPositiveAmounts')]
    public function testProcessRejectsNonPositiveAmountsWith422AndNoOperations(
        string $amountFrom,
        string $amountTo,
    ): void {
        $this->ensureFixedContractor();
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('CHF');
        $input = $this->buildInput(
            fromAccountId: $from->getId(),
            toAccountId: $to->getId(),
            amountFrom: $amountFrom,
            amountTo: $amountTo,
        );
        $operations = $this->em()->getRepository(AccountOperation::class);
        $countBefore = $operations->count([]);

        try {
            $this->processor->process($input, new Post());
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

        $input = $this->buildInput(fromAccountId: null, toAccountId: $to->getId(), amountFrom: '10.00');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsUnprocessableWhenToAccountIdMissing(): void
    {
        $this->ensureFixedContractor();
        $from = $this->createAccount('PLN');

        $input = $this->buildInput(fromAccountId: $from->getId(), toAccountId: null, amountFrom: '10.00');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsNotFoundWhenFromAccountDoesNotExist(): void
    {
        $this->ensureFixedContractor();
        $to = $this->createAccount('PLN');

        $input = $this->buildInput(fromAccountId: 999999999, toAccountId: $to->getId(), amountFrom: '10.00');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsNotFoundWhenToAccountDoesNotExist(): void
    {
        $this->ensureFixedContractor();
        $from = $this->createAccount('PLN');

        $input = $this->buildInput(fromAccountId: $from->getId(), toAccountId: 999999999, amountFrom: '10.00');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsNotFoundWhenFromAccountIdAboveInt4Max(): void
    {
        $this->ensureFixedContractor();
        $to = $this->createAccount('PLN');

        $input = $this->buildInput(fromAccountId: PHP_INT_MAX, toAccountId: $to->getId(), amountFrom: '10.00');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('fromAccountId');

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsNotFoundWhenToAccountIdAboveInt4Max(): void
    {
        $this->ensureFixedContractor();
        $from = $this->createAccount('PLN');

        $input = $this->buildInput(fromAccountId: $from->getId(), toAccountId: PHP_INT_MAX, amountFrom: '10.00');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('toAccountId');

        $this->processor->process($input, new Post());
    }

    public function testProcessTreatsAMissingOperationDateAsABug(): void
    {
        $this->ensureFixedContractor();
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('PLN');

        $input = $this->buildInput(
            fromAccountId: $from->getId(),
            toAccountId: $to->getId(),
            amountFrom: '10.00',
            operationDate: null,
        );

        // The API validates the date first (NotBlank + BusinessDate), so reaching here is a bug.
        $this->expectException(\LogicException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessTreatsAnUnvalidatedOperationDateAsABug(): void
    {
        $this->ensureFixedContractor();
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('PLN');

        $input = $this->buildInput(
            fromAccountId: $from->getId(),
            toAccountId: $to->getId(),
            amountFrom: '10.00',
            operationDate: 'not-a-date',
        );

        // The API validates the date first (NotBlank + BusinessDate), so reaching here is a bug.
        $this->expectException(\LogicException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsUnprocessableWhenAmountFromMissing(): void
    {
        $this->ensureFixedContractor();
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('PLN');

        $input = $this->buildInput(fromAccountId: $from->getId(), toAccountId: $to->getId(), amountFrom: null);

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsWhenDataIsNotTransferInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->processor->process(new \stdClass(), new Post());
    }

    /**
     * Sentinel distinguishing "argument omitted" (auto-fill today's date) from an explicit
     * null passed by a test that wants to verify the missing-date validation path.
     */
    private const string AUTO_DATE = '__auto_date__';

    private function buildInput(
        ?int $fromAccountId = null,
        ?int $toAccountId = null,
        ?string $amountFrom = '10.00',
        ?string $amountTo = null,
        ?string $operationDate = self::AUTO_DATE,
        ?string $description = null,
    ): TransferInput {
        $input = new TransferInput();
        $input->fromAccountId = $fromAccountId;
        $input->toAccountId = $toAccountId;
        $input->amountFrom = $amountFrom;
        $input->amountTo = $amountTo;
        $input->operationDate = self::AUTO_DATE === $operationDate
            ? (new \DateTimeImmutable())->format('Y-m-d')
            : $operationDate;
        $input->description = $description;

        return $input;
    }

    private function ensureFixedContractor(): Contractor
    {
        $contractor = $this->contractorRepository->findOneByName(Contractors::SELF_TRANSFER_NAME);
        if ($contractor) {
            return $contractor;
        }

        return $this->createContractor(Contractors::SELF_TRANSFER_NAME);
    }
}
