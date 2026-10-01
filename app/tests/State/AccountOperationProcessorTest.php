<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\ApiResource\AccountOperationResource;
use App\Entity\Account;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\Household;
use App\Repository\ContractorRepository;
use App\State\AccountOperationProcessor;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
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

    private function createContractor(string $name): Contractor
    {
        $contractor = new Contractor($name);
        $this->em()->persist($contractor);
        $this->em()->flush();

        return $contractor;
    }
}
