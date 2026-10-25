<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\ApiResource\AccountOperationResource;
use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\Household;
use App\Repository\ContractorRepository;
use App\State\AccountOperationProcessor;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class AccountOperationProcessorTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private AccountOperationProcessor $processor;
    private ContractorRepository $contractorRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var AccountOperationProcessor $processor */
        $processor = self::getContainer()->get(AccountOperationProcessor::class);
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

    public function testProcessCreatesNewOperationWithContractorId(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor('Existing Contractor');

        $resource = $this->buildResource(
            accountId: $account->getId(),
            contractorId: $contractor->getId(),
            amount: '150.50',
        );

        $result = $this->processor->process($resource, new Post());

        self::assertInstanceOf(AccountOperationResource::class, $result);
        self::assertNotNull($result->id);
        self::assertSame($account->getId(), $result->accountId);
        self::assertSame($contractor->getId(), $result->contractorId);
        self::assertSame('150.50', $result->amount);
        self::assertSame($account->getCurrency()->getCode(), $result->currencyCode);
    }

    public function testProcessCreatesContractorFromNameWhenNotFound(): void
    {
        $account = $this->createAccount();
        $newName = 'Brand New Contractor ' . uniqid();

        $resource = $this->buildResource(
            accountId: $account->getId(),
            contractorName: $newName,
            amount: '10.00',
        );

        $result = $this->processor->process($resource, new Post());

        self::assertInstanceOf(AccountOperationResource::class, $result);
        self::assertNotNull($result->contractorId);
        self::assertSame($newName, $result->contractorName);
        self::assertNotNull($this->contractorRepository->find($result->contractorId));
    }

    public function testProcessReusesExistingContractorFoundByName(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor('Reused Contractor ' . uniqid());

        $resource = $this->buildResource(
            accountId: $account->getId(),
            contractorName: $contractor->getName(),
            amount: '10.00',
        );

        $result = $this->processor->process($resource, new Post());

        self::assertInstanceOf(AccountOperationResource::class, $result);
        self::assertSame($contractor->getId(), $result->contractorId);
    }

    public function testProcessThrowsUnprocessableWhenAccountIdMissing(): void
    {
        $resource = $this->buildResource(accountId: null, contractorId: 1, amount: '10.00');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsNotFoundWhenAccountDoesNotExist(): void
    {
        $resource = $this->buildResource(accountId: 999999999, contractorId: 1, amount: '10.00');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsNotFoundWhenContractorIdDoesNotExist(): void
    {
        $account = $this->createAccount();
        $resource = $this->buildResource(accountId: $account->getId(), contractorId: 999999999, amount: '10.00');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsUnprocessableWhenNeitherContractorIdNorNameGiven(): void
    {
        $account = $this->createAccount();
        $resource = $this->buildResource(accountId: $account->getId(), amount: '10.00');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsUnprocessableWhenOperationDateMissing(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor('Contractor');

        $resource = $this->buildResource(
            accountId: $account->getId(),
            contractorId: $contractor->getId(),
            amount: '10.00',
            operationDate: null,
        );

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsUnprocessableWhenOperationDateInvalid(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor('Contractor');

        $resource = $this->buildResource(
            accountId: $account->getId(),
            contractorId: $contractor->getId(),
            amount: '10.00',
            operationDate: 'not-a-date',
        );

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessUpdatesExistingOperationOnPatch(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor('Original Contractor');
        $created = $this->processor->process(
            $this->buildResource(accountId: $account->getId(), contractorId: $contractor->getId(), amount: '10.00'),
            new Post(),
        );
        self::assertInstanceOf(AccountOperationResource::class, $created);

        $newContractor = $this->createContractor('New Contractor');
        $update = $this->buildResource(
            accountId: $account->getId(),
            contractorId: $newContractor->getId(),
            amount: '99.99',
            description: 'updated',
        );

        $result = $this->processor->process($update, new Patch(), ['id' => $created->id]);

        self::assertInstanceOf(AccountOperationResource::class, $result);
        self::assertSame($created->id, $result->id);
        self::assertSame($newContractor->getId(), $result->contractorId);
        self::assertSame('99.99', $result->amount);
        self::assertSame('updated', $result->description);
    }

    public function testPatchKeepsTheAccountOfTheOperationWhateverAccountIdTheDataCarries(): void
    {
        $plnAccount = $this->createAccount();
        $chfAccount = $this->createAccount('CHF');
        $contractor = $this->createContractor('Mover');
        $created = $this->processor->process(
            $this->buildResource(accountId: $plnAccount->getId(), contractorId: $contractor->getId(), amount: '10.00'),
            new Post(),
        );
        self::assertInstanceOf(AccountOperationResource::class, $created);

        $result = $this->processor->process(
            $this->buildResource(accountId: $chfAccount->getId(), contractorId: $contractor->getId(), amount: '25.50'),
            new Patch(),
            ['id' => $created->id],
        );

        self::assertInstanceOf(AccountOperationResource::class, $result);
        self::assertSame($plnAccount->getId(), $result->accountId);
        self::assertSame('PLN', $result->currencyCode);
        self::assertSame('25.50', $result->amount);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAmounts(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative zero' => ['-0.00'];
        yield 'rounded to zero' => ['0.004'];
        yield 'negative rounded to zero' => ['-0.004'];
        yield 'not a number' => ['abc'];
        yield 'above the upper bound' => ['1000000.00'];
        yield 'below the lower bound' => ['-1000000.00'];
        yield 'far above the upper bound' => ['9999999999'];
    }

    #[DataProvider('invalidAmounts')]
    public function testCreateRejectsAnInvalidAmountAsUnprocessable(string $amount): void
    {
        $account = $this->createAccount();
        $contractorName = 'Rejected contractor '.uniqid();
        $contractorsBefore = $this->contractorRepository->count([]);
        $operations = $this->em()->getRepository(AccountOperation::class);
        $operationsBefore = $operations->count([]);

        try {
            $this->processor->process(
                $this->buildResource(
                    accountId: $account->getId(),
                    contractorName: $contractorName,
                    amount: $amount,
                ),
                new Post(),
            );
            self::fail('An invalid amount must be rejected.');
        } catch (UnprocessableEntityHttpException $exception) {
            self::assertSame(422, $exception->getStatusCode());
        }

        // Validation must happen before the transaction and leave no pending writes.
        self::assertTrue($this->em()->isOpen());
        $this->em()->flush();
        self::assertSame($contractorsBefore, $this->contractorRepository->count([]));
        self::assertNull($this->contractorRepository->findOneByName($contractorName));
        self::assertSame($operationsBefore, $operations->count([]));
    }

    public function testCreateRejectsAMissingAmountAsUnprocessable(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor('Contractor');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Amount is required.');

        $this->processor->process(
            $this->buildResource(accountId: $account->getId(), contractorId: $contractor->getId(), amount: null),
            new Post(),
        );
    }

    #[DataProvider('invalidAmounts')]
    public function testPatchRejectsAnInvalidAmountAndLeavesTheOperationUntouched(string $amount): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor('Original Contractor');
        $created = $this->processor->process(
            $this->buildResource(accountId: $account->getId(), contractorId: $contractor->getId(), amount: '10.00'),
            new Post(),
        );
        self::assertInstanceOf(AccountOperationResource::class, $created);
        $contractorName = 'Rejected contractor '.uniqid();
        $contractorsBefore = $this->contractorRepository->count([]);

        try {
            $this->processor->process(
                $this->buildResource(
                    accountId: $account->getId(),
                    contractorId: $contractor->getId(),
                    contractorName: $contractorName,
                    amount: $amount,
                    operationDate: '2028-02-29',
                    description: 'must not be applied',
                ),
                new Patch(),
                ['id' => $created->id],
            );
            self::fail('An invalid amount must be rejected.');
        } catch (UnprocessableEntityHttpException $exception) {
            self::assertSame(422, $exception->getStatusCode());
        }

        self::assertTrue($this->em()->isOpen());
        $this->em()->flush();
        $this->em()->clear();
        $stored = $this->em()->find(AccountOperation::class, $created->id);
        self::assertNotNull($stored);
        self::assertSame($contractor->getId(), $stored->getContractor()->getId());
        self::assertSame(1000, $stored->getAmount());
        self::assertSame($created->operationDate, $stored->getOperationDate()->format('Y-m-d'));
        self::assertNull($stored->getDescription());
        self::assertSame($contractorsBefore, $this->contractorRepository->count([]));
        self::assertNull($this->contractorRepository->findOneByName($contractorName));
    }

    public function testPatchSavesANewContractorAndTheUpdatedOperationTogether(): void
    {
        $account = $this->createAccount();
        $originalContractor = $this->createContractor('Original Contractor');
        $created = $this->processor->process(
            $this->buildResource(accountId: $account->getId(), contractorId: $originalContractor->getId()),
            new Post(),
        );
        self::assertInstanceOf(AccountOperationResource::class, $created);
        $newName = 'New contractor on update '.uniqid();
        $created->contractorName = $newName;
        $created->amount = '-99.99';
        $created->operationDate = '2028-02-29';
        $created->description = 'Updated';
        $created->confirmed = true;

        $result = $this->processor->process($created, new Patch(), ['id' => $created->id]);

        self::assertInstanceOf(AccountOperationResource::class, $result);
        $this->em()->clear();
        $stored = $this->em()->find(AccountOperation::class, $created->id);
        self::assertNotNull($stored);
        self::assertSame($newName, $stored->getContractor()->getName());
        self::assertNotSame($originalContractor->getId(), $stored->getContractor()->getId());
        self::assertSame(-9999, $stored->getAmount());
        self::assertSame('2028-02-29', $stored->getOperationDate()->format('Y-m-d'));
        self::assertSame('Updated', $stored->getDescription());
        self::assertTrue($stored->isConfirmed());
    }

    // Write means inserting or updating an account operation.
    #[DataProvider('writeFailures')]
    public function testWriteFailureRollsBackTheContractorAndOperation(bool $patch, bool $afterOperation): void
    {
        $account = $this->createAccount();
        $resource = $this->buildResource(accountId: $account->getId());
        $uriVariables = [];
        if ($patch) {
            $contractor = $this->createContractor('Original Contractor');
            $resource->contractorId = $contractor->getId();
            $created = $this->processor->process($resource, new Post());
            self::assertInstanceOf(AccountOperationResource::class, $created);
            $resource = $created;
            $uriVariables = ['id' => $created->id];
        }
        $resource->contractorName = 'Rollback contractor '.uniqid();
        $resource->amount = '25.50';
        $resource->operationDate = '2028-02-29';
        $resource->description = 'Rollback operation '.uniqid();
        $resource->confirmed = true;

        $connection = $this->em()->getConnection();
        $nestingBefore = $connection->getTransactionNestingLevel();
        $contractorsBefore = $connection->fetchAllAssociative('SELECT * FROM contractor ORDER BY id');
        $operationsBefore = $connection->fetchAllAssociative('SELECT * FROM account_operation ORDER BY id');

        // Fail after SQL has run, so the assertions below test database rollback, not just validation.
        $listener = new class($resource->contractorName, $resource->description, $afterOperation) {
            public bool $triggered = false;

            public function __construct(
                private readonly string $contractorName,
                private readonly string $description,
                private readonly bool $afterOperation,
            ) {
            }

            public function postFlush(PostFlushEventArgs $event): void
            {
                $connection = $event->getObjectManager()->getConnection();
                $contractorWritten = false !== $connection->fetchOne(
                    'SELECT id FROM contractor WHERE name = ?',
                    [$this->contractorName],
                );
                $operationWritten = false !== $connection->fetchOne(
                    'SELECT id FROM account_operation WHERE description = ?',
                    [$this->description],
                );

                if ($contractorWritten && (!$this->afterOperation || $operationWritten)) {
                    $this->triggered = true;
                    throw new \RuntimeException('Forced failure after database write.');
                }
            }
        };

        $events = $this->em()->getEventManager();
        $events->addEventListener([Events::postFlush], $listener);
        try {
            $this->processor->process($resource, $patch ? new Patch() : new Post(), $uriVariables);
            self::fail('The write must fail after reaching the database.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Forced failure after database write.', $exception->getMessage());
        } finally {
            $events->removeEventListener([Events::postFlush], $listener);
        }

        self::assertTrue($listener->triggered);
        // Read the database directly: Doctrine closes the EntityManager after a failed transaction.
        self::assertSame($contractorsBefore, $connection->fetchAllAssociative('SELECT * FROM contractor ORDER BY id'));
        self::assertSame(
            $operationsBefore,
            $connection->fetchAllAssociative('SELECT * FROM account_operation ORDER BY id'),
        );
        self::assertSame($nestingBefore, $connection->getTransactionNestingLevel());
        self::assertFalse($this->em()->isOpen());
    }

    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function writeFailures(): iterable
    {
        yield 'create, failure after contractor insert' => [false, false];
        yield 'create, failure after operation insert' => [false, true];
        yield 'update, failure after contractor insert' => [true, false];
        yield 'update, failure after operation update' => [true, true];
    }

    public function testProcessThrowsNotFoundOnPatchWhenOperationMissing(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor('Contractor');
        $resource = $this->buildResource(accountId: $account->getId(), contractorId: $contractor->getId(), amount: '10.00');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process($resource, new Patch(), ['id' => 999999999]);
    }

    public function testProcessDeletesExistingOperationOnDelete(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor('Contractor');
        $created = $this->processor->process(
            $this->buildResource(accountId: $account->getId(), contractorId: $contractor->getId(), amount: '10.00'),
            new Post(),
        );
        self::assertInstanceOf(AccountOperationResource::class, $created);

        $result = $this->processor->process(null, new Delete(), ['id' => $created->id]);

        self::assertNull($result);
        self::assertNull($this->em()->getRepository(\App\Entity\AccountOperation::class)->find($created->id));
    }

    public function testProcessThrowsNotFoundOnDeleteWhenOperationMissing(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->processor->process(null, new Delete(), ['id' => 999999999]);
    }

    public function testProcessThrowsWhenDataIsNotAccountOperationResource(): void
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
        ?int $accountId = null,
        ?int $contractorId = null,
        ?string $contractorName = null,
        ?string $amount = '10.00',
        ?string $operationDate = self::AUTO_DATE,
        ?string $description = null,
    ): AccountOperationResource {
        $resource = new AccountOperationResource();
        $resource->accountId = $accountId;
        $resource->contractorId = $contractorId;
        $resource->contractorName = $contractorName;
        $resource->amount = $amount;
        $resource->operationDate = self::AUTO_DATE === $operationDate
            ? (new DateTimeImmutable())->format('Y-m-d')
            : $operationDate;
        $resource->description = $description;

        return $resource;
    }

    private function createAccount(string $currencyCode = 'PLN'): Account
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

    private function createContractor(string $name): Contractor
    {
        $contractor = new Contractor($name);
        $this->em()->persist($contractor);
        $this->em()->flush();

        return $contractor;
    }
}
