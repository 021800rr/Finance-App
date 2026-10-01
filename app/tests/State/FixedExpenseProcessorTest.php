<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\ApiResource\FixedExpenseResource;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\FixedExpense;
use App\Repository\ContractorRepository;
use App\State\FixedExpenseProcessor;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class FixedExpenseProcessorTest extends KernelTestCase
{
    use DatabaseTestTrait;

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

        $this->ensurePlnCurrency();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testProcessCreatesNewFixedExpenseWithContractorId(): void
    {
        $contractor = $this->createContractor('Existing Contractor');

        $resource = $this->buildResource(contractorId: $contractor->getId(), day: 10, amount: '-200.00');

        $result = $this->processor->process($resource, new Post());

        self::assertInstanceOf(FixedExpenseResource::class, $result);
        self::assertNotNull($result->id);
        self::assertSame($contractor->getId(), $result->contractorId);
        self::assertSame(10, $result->day);
        self::assertSame('-200.00', $result->amount);
        self::assertSame('PLN', $result->currencyCode);
    }

    public function testProcessCreatesContractorFromNameWhenNotFound(): void
    {
        $newName = 'Brand New Contractor ' . uniqid();

        $resource = $this->buildResource(contractorName: $newName, day: 5, amount: '-30.00');

        $result = $this->processor->process($resource, new Post());

        self::assertInstanceOf(FixedExpenseResource::class, $result);
        self::assertNotNull($result->contractorId);
        self::assertSame($newName, $result->contractorName);
        self::assertNotNull($this->contractorRepository->find($result->contractorId));
    }

    public function testProcessReusesExistingContractorFoundByName(): void
    {
        $contractor = $this->createContractor('Reused Contractor ' . uniqid());

        $resource = $this->buildResource(contractorName: $contractor->getName(), day: 5, amount: '-30.00');

        $result = $this->processor->process($resource, new Post());

        self::assertInstanceOf(FixedExpenseResource::class, $result);
        self::assertSame($contractor->getId(), $result->contractorId);
    }

    public function testProcessThrowsUnprocessableWhenNeitherContractorIdNorNameGiven(): void
    {
        $resource = $this->buildResource(day: 5, amount: '-30.00');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsNotFoundWhenContractorIdDoesNotExist(): void
    {
        $resource = $this->buildResource(contractorId: 999999999, day: 5, amount: '-30.00');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsUnprocessableWhenDayMissing(): void
    {
        $contractor = $this->createContractor('Contractor');
        $resource = $this->buildResource(contractorId: $contractor->getId(), day: null, amount: '-30.00');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsUnprocessableWhenDayOutOfRange(): void
    {
        $contractor = $this->createContractor('Contractor');
        $resource = $this->buildResource(contractorId: $contractor->getId(), day: 29, amount: '-30.00');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsUnprocessableWhenAmountMissing(): void
    {
        $contractor = $this->createContractor('Contractor');
        $resource = $this->buildResource(contractorId: $contractor->getId(), day: 5, amount: null);

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsUnprocessableWhenAmountNotDecimal(): void
    {
        $contractor = $this->createContractor('Contractor');
        $resource = $this->buildResource(contractorId: $contractor->getId(), day: 5, amount: 'string');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsUnprocessableWhenAmountPositive(): void
    {
        $contractor = $this->createContractor('Contractor');
        $resource = $this->buildResource(contractorId: $contractor->getId(), day: 5, amount: '30.00');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsUnprocessableWhenAmountZero(): void
    {
        $contractor = $this->createContractor('Contractor');
        $resource = $this->buildResource(contractorId: $contractor->getId(), day: 5, amount: '0.00');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessUpdatesExistingFixedExpenseOnPatch(): void
    {
        $contractor = $this->createContractor('Original Contractor');
        $created = $this->processor->process(
            $this->buildResource(contractorId: $contractor->getId(), day: 5, amount: '-30.00'),
            new Post(),
        );
        self::assertInstanceOf(FixedExpenseResource::class, $created);

        $newContractor = $this->createContractor('New Contractor');
        $update = $this->buildResource(
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

    public function testProcessThrowsNotFoundOnPatchWhenFixedExpenseMissing(): void
    {
        $contractor = $this->createContractor('Contractor');
        $resource = $this->buildResource(contractorId: $contractor->getId(), day: 5, amount: '-30.00');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process($resource, new Patch(), ['id' => 999999999]);
    }

    public function testProcessDeletesExistingFixedExpenseOnDelete(): void
    {
        $contractor = $this->createContractor('Contractor');
        $created = $this->processor->process(
            $this->buildResource(contractorId: $contractor->getId(), day: 5, amount: '-30.00'),
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

    public function testProcessThrowsWhenDataIsNotFixedExpenseResource(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->processor->process(new \stdClass(), new Post());
    }

    private function buildResource(
        ?int $contractorId = null,
        ?string $contractorName = null,
        ?int $day = 5,
        ?string $amount = '-10.00',
        ?string $description = null,
    ): FixedExpenseResource {
        $resource = new FixedExpenseResource();
        $resource->contractorId = $contractorId;
        $resource->contractorName = $contractorName;
        $resource->day = $day;
        $resource->amount = $amount;
        $resource->description = $description;

        return $resource;
    }

    private function createContractor(string $name): Contractor
    {
        $contractor = new Contractor($name);
        $this->em()->persist($contractor);
        $this->em()->flush();

        return $contractor;
    }

    private function ensurePlnCurrency(): void
    {
        $currencyRepository = $this->em()->getRepository(Currency::class);
        if (null === $currencyRepository->find('PLN')) {
            $this->em()->persist(new Currency('PLN'));
            $this->em()->flush();
        }
    }
}
