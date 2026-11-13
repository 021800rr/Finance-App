<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\ApiResource\FixedExpenseResource;
use App\ApiResource\Input\FixedExpenseInput;
use App\Entity\FixedExpense;
use App\Repository\ContractorRepository;
use App\State\FixedExpenseProcessor;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class FixedExpenseProcessorTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

    private FixedExpenseProcessor $processor;
    private ContractorRepository $contractorRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var FixedExpenseProcessor $processor */
        $processor = self::getContainer()->get(FixedExpenseProcessor::class);
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

    public function testProcessCreatesNewFixedExpenseWithContractorId(): void
    {
        $contractor = $this->createContractor('Existing Contractor');

        $input = $this->buildInput(contractorId: $contractor->getId(), day: 10, amount: '-200.00');

        $result = $this->processor->process($input, new Post());

        self::assertInstanceOf(FixedExpenseResource::class, $result);
        self::assertGreaterThan(0, $result->id);
        self::assertSame($contractor->getId(), $result->contractorId);
        self::assertSame(10, $result->day);
        self::assertSame('-200.00', $result->amount);
        self::assertSame('PLN', $result->currencyCode);
    }

    public function testProcessCreatesContractorFromNameWhenNotFound(): void
    {
        $newName = 'Brand New Contractor '.uniqid();

        $input = $this->buildInput(contractorName: $newName, day: 5, amount: '-30.00');

        $result = $this->processor->process($input, new Post());

        self::assertInstanceOf(FixedExpenseResource::class, $result);
        self::assertSame($newName, $result->contractorName);
        self::assertNotNull($this->contractorRepository->find($result->contractorId));
    }

    public function testProcessReusesExistingContractorFoundByName(): void
    {
        $contractor = $this->createContractor('Reused Contractor '.uniqid());

        $input = $this->buildInput(contractorName: $contractor->getName(), day: 5, amount: '-30.00');

        $result = $this->processor->process($input, new Post());

        self::assertInstanceOf(FixedExpenseResource::class, $result);
        self::assertSame($contractor->getId(), $result->contractorId);
    }

    public function testProcessThrowsUnprocessableWhenNeitherContractorIdNorNameGiven(): void
    {
        $input = $this->buildInput(day: 5, amount: '-30.00');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsNotFoundWhenContractorIdDoesNotExist(): void
    {
        $input = $this->buildInput(contractorId: 999999999, day: 5, amount: '-30.00');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsUnprocessableWhenDayMissing(): void
    {
        $contractor = $this->createContractor('Contractor');
        $input = $this->buildInput(contractorId: $contractor->getId(), day: null, amount: '-30.00');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsUnprocessableWhenDayOutOfRange(): void
    {
        $contractor = $this->createContractor('Contractor');
        $input = $this->buildInput(contractorId: $contractor->getId(), day: 29, amount: '-30.00');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsUnprocessableWhenAmountMissing(): void
    {
        $contractor = $this->createContractor('Contractor');
        $input = $this->buildInput(contractorId: $contractor->getId(), day: 5, amount: null);

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsUnprocessableWhenAmountNotDecimal(): void
    {
        $contractor = $this->createContractor('Contractor');
        $input = $this->buildInput(contractorId: $contractor->getId(), day: 5, amount: 'string');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsUnprocessableWhenAmountPositive(): void
    {
        $contractor = $this->createContractor('Contractor');
        $input = $this->buildInput(contractorId: $contractor->getId(), day: 5, amount: '30.00');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsUnprocessableWhenAmountZero(): void
    {
        $contractor = $this->createContractor('Contractor');
        $input = $this->buildInput(contractorId: $contractor->getId(), day: 5, amount: '0.00');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessUpdatesExistingFixedExpenseOnPatch(): void
    {
        $contractor = $this->createContractor('Original Contractor');
        $created = $this->processor->process(
            $this->buildInput(contractorId: $contractor->getId(), day: 5, amount: '-30.00'),
            new Post(),
        );
        self::assertInstanceOf(FixedExpenseResource::class, $created);

        $newContractor = $this->createContractor('New Contractor');
        $update = $this->buildInput(
            contractorId: $newContractor->getId(),
            day: 15,
            amount: '-99.99',
            description: 'updated',
        );

        $result = $this->processor->process($update, new Patch(), ['id' => $created->id]);

        self::assertInstanceOf(FixedExpenseResource::class, $result);
        self::assertSame($created->id, $result->id);
        self::assertSame($newContractor->getId(), $result->contractorId);
        self::assertSame(15, $result->day);
        self::assertSame('-99.99', $result->amount);
        self::assertSame('updated', $result->description);
    }

    #[DataProvider('writeFailures')]
    public function testWriteFailureRollsBackTheContractorAndExpense(bool $patch, bool $afterExpense): void
    {
        $input = $this->buildInput();
        $uriVariables = [];
        if ($patch) {
            $contractor = $this->createContractor('Original expense contractor '.uniqid());
            $input->contractorId = $contractor->getId();
            $created = $this->processor->process($input, new Post());
            self::assertInstanceOf(FixedExpenseResource::class, $created);
            // As the provider gives it to a PATCH: the values of the record, with its id.
            $input->id = $created->id;
            $uriVariables = ['id' => $created->id];
        }
        $input->contractorName = 'Rollback contractor '.uniqid();
        $input->amount = '-25.50';
        $input->day = 20;
        $input->description = 'Rollback expense '.uniqid();

        $connection = $this->em()->getConnection();
        $nestingBefore = $connection->getTransactionNestingLevel();
        $contractorsBefore = $connection->fetchAllAssociative('SELECT * FROM contractor ORDER BY id');
        $expensesBefore = $connection->fetchAllAssociative('SELECT * FROM fixed_expense ORDER BY id');

        // Fail after SQL has run to verify that the transaction undoes actual database writes.
        $listener = new class($input->contractorName, $input->description, $afterExpense) {
            public bool $triggered = false;

            public function __construct(
                private readonly string $contractorName,
                private readonly string $description,
                private readonly bool $afterExpense,
            ) {
            }

            public function postFlush(PostFlushEventArgs $event): void
            {
                $connection = $event->getObjectManager()->getConnection();
                $contractorWritten = false !== $connection->fetchOne(
                    'SELECT id FROM contractor WHERE name = ?',
                    [$this->contractorName],
                );
                $expenseWritten = false !== $connection->fetchOne(
                    'SELECT id FROM fixed_expense WHERE description = ?',
                    [$this->description],
                );

                if ($contractorWritten && (!$this->afterExpense || $expenseWritten)) {
                    $this->triggered = true;
                    throw new \RuntimeException('Forced failure after database write.');
                }
            }
        };

        $events = $this->em()->getEventManager();
        $events->addEventListener([Events::postFlush], $listener);
        try {
            $this->processor->process($input, $patch ? new Patch() : new Post(), $uriVariables);
            self::fail('The write must fail after reaching the database.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Forced failure after database write.', $exception->getMessage());
        } finally {
            $events->removeEventListener([Events::postFlush], $listener);
        }

        self::assertTrue($listener->triggered);
        // A failed transaction closes the EntityManager, so check the database directly.
        self::assertSame($contractorsBefore, $connection->fetchAllAssociative('SELECT * FROM contractor ORDER BY id'));
        self::assertSame($expensesBefore, $connection->fetchAllAssociative('SELECT * FROM fixed_expense ORDER BY id'));
        self::assertSame($nestingBefore, $connection->getTransactionNestingLevel());
        self::assertFalse($this->em()->isOpen());
    }

    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function writeFailures(): iterable
    {
        yield 'create, failure after contractor insert' => [false, false];
        yield 'create, failure after expense insert' => [false, true];
        yield 'update, failure after contractor insert' => [true, false];
        yield 'update, failure after expense update' => [true, true];
    }

    public function testProcessThrowsNotFoundOnPatchWhenFixedExpenseMissing(): void
    {
        $contractor = $this->createContractor('Contractor');
        $input = $this->buildInput(contractorId: $contractor->getId(), day: 5, amount: '-30.00');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process($input, new Patch(), ['id' => 999999999]);
    }

    public function testProcessDeletesExistingFixedExpenseOnDelete(): void
    {
        $contractor = $this->createContractor('Contractor');
        $created = $this->processor->process(
            $this->buildInput(contractorId: $contractor->getId(), day: 5, amount: '-30.00'),
            new Post(),
        );
        self::assertInstanceOf(FixedExpenseResource::class, $created);

        $result = $this->processor->process(null, new Delete(), ['id' => $created->id]);

        self::assertNull($result);
        self::assertNull($this->em()->getRepository(FixedExpense::class)->find($created->id));
    }

    public function testProcessThrowsNotFoundOnDeleteWhenFixedExpenseMissing(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->processor->process(null, new Delete(), ['id' => 999999999]);
    }

    public function testProcessThrowsWhenDataIsNotFixedExpenseInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->processor->process(new \stdClass(), new Post());
    }

    private function buildInput(
        ?int $contractorId = null,
        ?string $contractorName = null,
        ?int $day = 5,
        ?string $amount = '-10.00',
        ?string $description = null,
    ): FixedExpenseInput {
        $input = new FixedExpenseInput();
        $input->contractorId = $contractorId;
        $input->contractorName = $contractorName;
        $input->day = $day;
        $input->amount = $amount;
        $input->description = $description;

        return $input;
    }
}
