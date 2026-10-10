<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\ApiResource\AccountOperationResource;
use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\Household;
use App\State\AccountOperationProvider;
use App\State\Pagination\OffsetPaginator;
use App\Tests\DatabaseTestTrait;
use App\ValueObject\Money;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class AccountOperationProviderTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private AccountOperationProvider $provider;
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var AccountOperationProvider $provider */
        $provider = self::getContainer()->get(AccountOperationProvider::class);
        $this->provider = $provider;

        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get(RequestStack::class);
        $this->requestStack = $requestStack;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testProvideReturnsResourceForExistingId(): void
    {
        $account = $this->createAccount();
        $operation = $this->createOperation($account, '10.00');

        $this->pushRequest([]);
        $result = $this->provider->provide(new Get(), ['id' => $operation->getId()]);

        self::assertInstanceOf(AccountOperationResource::class, $result);
        self::assertSame($operation->getId(), $result->id);
        self::assertSame($account->getId(), $result->accountId);
    }

    public function testProvideReturnsNullForNonExistentId(): void
    {
        $this->pushRequest([]);

        $result = $this->provider->provide(new Get(), ['id' => 999999999]);

        self::assertNull($result);
    }

    public function testProvideCollectionReturnsPaginatorOrderedByDateDescending(): void
    {
        $account = $this->createAccount();
        $older = $this->createOperation($account, '10.00', new \DateTimeImmutable('2026-01-01'));
        $newer = $this->createOperation($account, '20.00', new \DateTimeImmutable('2026-06-01'));

        $this->pushRequest([]);
        $result = $this->provider->provide(new GetCollection());

        self::assertInstanceOf(OffsetPaginator::class, $result);
        $items = iterator_to_array($result);
        self::assertGreaterThanOrEqual(2, count($items));
        self::assertSame($newer->getId(), $items[0]->id);
        self::assertContains($older->getId(), array_map(static fn (AccountOperationResource $r): ?int => $r->id, $items));
    }

    public function testProvideCollectionFiltersByAccountId(): void
    {
        $accountOne = $this->createAccount();
        $accountTwo = $this->createAccount();
        $operationOne = $this->createOperation($accountOne, '10.00');
        $this->createOperation($accountTwo, '20.00');

        $this->pushRequest(['accountId' => (string) $accountOne->getId()]);
        $result = $this->provider->provide(new GetCollection());

        self::assertInstanceOf(OffsetPaginator::class, $result);
        $items = iterator_to_array($result);
        self::assertCount(1, $items);
        self::assertSame($operationOne->getId(), $items[0]->id);
    }

    public function testProvideCollectionReturnsEmptyWhenAccountIdDoesNotExist(): void
    {
        $this->pushRequest(['accountId' => '999999999']);

        $result = $this->provider->provide(new GetCollection());

        self::assertInstanceOf(OffsetPaginator::class, $result);
        self::assertCount(0, iterator_to_array($result));
    }

    public function testProvideCollectionFiltersByQuery(): void
    {
        $marker = uniqid();
        $account = $this->createAccount();
        $match = $this->createOperation($account, '10.00', null, "Findable {$marker}");
        $this->createOperation($account, '10.00', null, "Other {$marker}");

        $this->pushRequest(['q' => "Findable {$marker}"]);
        $result = $this->provider->provide(new GetCollection());

        self::assertInstanceOf(OffsetPaginator::class, $result);
        $items = iterator_to_array($result);
        self::assertCount(1, $items);
        self::assertSame($match->getId(), $items[0]->id);
        self::assertSame(1.0, $result->getTotalItems());
    }

    public function testProvideCollectionCombinesQueryWithAccountId(): void
    {
        $marker = uniqid();
        $accountA = $this->createAccount();
        $accountB = $this->createAccount();
        $inA = $this->createOperation($accountA, '10.00', null, "Shared {$marker}");
        $this->createOperation($accountB, '10.00', null, "Shared {$marker}");

        $this->pushRequest(['q' => $marker, 'accountId' => (string) $accountA->getId()]);
        $result = $this->provider->provide(new GetCollection());

        self::assertInstanceOf(OffsetPaginator::class, $result);
        $items = iterator_to_array($result);
        self::assertCount(1, $items);
        self::assertSame($inA->getId(), $items[0]->id);
    }

    public function testProvideCollectionReturnsEmptyForWhitespaceOnlyQuery(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, '10.00');

        $this->pushRequest(['q' => '   ']);
        $result = $this->provider->provide(new GetCollection());

        self::assertInstanceOf(OffsetPaginator::class, $result);
        self::assertCount(0, iterator_to_array($result));
        self::assertSame(0.0, $result->getTotalItems());
    }

    public function testProvideCollectionReturnsEmptyForQueryWithUnknownAccountId(): void
    {
        $marker = uniqid();
        $account = $this->createAccount();
        $this->createOperation($account, '10.00', null, "Orphan {$marker}");

        $this->pushRequest(['q' => $marker, 'accountId' => '999999999']);
        $result = $this->provider->provide(new GetCollection());

        self::assertInstanceOf(OffsetPaginator::class, $result);
        self::assertCount(0, iterator_to_array($result));
    }

    public function testProvideCollectionToleratesAbsurdPageNumber(): void
    {
        $this->pushRequest(['page' => (string) PHP_INT_MAX]);

        $result = $this->provider->provide(new GetCollection());

        self::assertInstanceOf(OffsetPaginator::class, $result);
        self::assertCount(0, iterator_to_array($result));
    }

    /**
     * @param array<string, string> $query
     */
    private function pushRequest(array $query): void
    {
        $this->requestStack->push(new Request(query: $query));
    }

    private function createAccount(): Account
    {
        $bank = new Bank('Test Bank '.uniqid());
        $this->em()->persist($bank);

        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->find('PLN');
        if (null === $currency) {
            $currency = new Currency('PLN');
            $this->em()->persist($currency);
        }

        $accountType = new AccountType('Test Type '.uniqid());
        $this->em()->persist($accountType);

        $household = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => true]);
        if (null === $household) {
            throw new \RuntimeException('Fixtures must provide exactly one own-funds household.');
        }

        $account = new Account($bank, $currency, $accountType, 'ACC-'.uniqid(), $household);
        $this->em()->persist($account);
        $this->em()->flush();

        return $account;
    }

    private function createOperation(Account $account, string $amount, ?\DateTimeImmutable $date = null, ?string $description = null): AccountOperation
    {
        $contractor = new Contractor('Test Contractor '.uniqid());
        $this->em()->persist($contractor);

        $operation = new AccountOperation(
            $account,
            $contractor,
            $date ?? new \DateTimeImmutable(),
            Money::fromDecimalString($amount, $account->getCurrencyCode())->getMinorUnits(),
            $this->em()->getRepository(Currency::class)->find($account->getCurrencyCode()) ?? throw new \RuntimeException('Missing currency.'),
        );
        if (null !== $description) {
            $operation->setDescription($description);
        }
        $this->em()->persist($operation);
        $this->em()->flush();

        return $operation;
    }
}
