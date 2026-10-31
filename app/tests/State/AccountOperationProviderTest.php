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
use App\Service\Paginator;
use App\State\AccountOperationProvider;
use App\State\Pagination\OffsetPaginator;
use App\Tests\DatabaseTestTrait;
use App\ValueObject\Money;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

    public function testProvideThrowsNotFoundForNonExistentId(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Account operation not found.');

        $this->provider->provide(new Get(), ['id' => 999999999]);
    }

    public function testAccountHistoryIsOrderedByDateDescending(): void
    {
        $account = $this->createAccount();
        $older = $this->createOperation($account, '10.00', new \DateTimeImmutable('2026-01-01'));
        $newer = $this->createOperation($account, '20.00', new \DateTimeImmutable('2026-06-01'));

        $this->pushRequest([]);
        $result = $this->provider->provide(new GetCollection(), ['accountId' => $account->getId()]);

        self::assertInstanceOf(OffsetPaginator::class, $result);
        self::assertSame([$newer->getId(), $older->getId()], $this->ids($result));
    }

    public function testAccountHistoryContainsOnlyThatAccountsOperations(): void
    {
        $accountOne = $this->createAccount();
        $accountTwo = $this->createAccount();
        $operationOne = $this->createOperation($accountOne, '10.00');
        $this->createOperation($accountTwo, '20.00');

        $this->pushRequest([]);
        $result = $this->provider->provide(new GetCollection(), ['accountId' => $accountOne->getId()]);

        self::assertInstanceOf(OffsetPaginator::class, $result);
        self::assertSame([$operationOne->getId()], $this->ids($result));
        self::assertSame(1.0, $result->getTotalItems());
    }

    public function testAccountHistoryUsesTheSamePageSizeAsTheWebPage(): void
    {
        $account = $this->createAccount();
        for ($i = 0; $i < Paginator::DEFAULT_PER_PAGE + 1; ++$i) {
            $this->createOperation($account, '10.00');
        }

        $this->pushRequest([]);
        $result = $this->provider->provide(new GetCollection(), ['accountId' => $account->getId()]);

        self::assertInstanceOf(OffsetPaginator::class, $result);
        self::assertSame((float) Paginator::DEFAULT_PER_PAGE, $result->getItemsPerPage());
        self::assertCount(Paginator::DEFAULT_PER_PAGE, iterator_to_array($result));
        self::assertSame((float) (Paginator::DEFAULT_PER_PAGE + 1), $result->getTotalItems());
    }

    public function testAccountHistoryIgnoresTheQueryParameter(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, '10.00', null, 'Alpha');
        $this->createOperation($account, '10.00', null, 'Beta');

        $this->pushRequest(['q' => 'Alpha']);
        $result = $this->provider->provide(new GetCollection(), ['accountId' => $account->getId()]);

        self::assertInstanceOf(OffsetPaginator::class, $result);
        self::assertSame(2.0, $result->getTotalItems());
    }

    public function testAccountHistoryThrowsNotFoundWhenAccountDoesNotExist(): void
    {
        $this->pushRequest([]);

        $this->expectException(NotFoundHttpException::class);

        $this->provider->provide(new GetCollection(), ['accountId' => '999999999']);
    }

    public function testAccountHistoryThrowsNotFoundWhenAccountIdIsAboveInt4Max(): void
    {
        $this->pushRequest([]);

        $this->expectException(NotFoundHttpException::class);

        $this->provider->provide(new GetCollection(), ['accountId' => (string) PHP_INT_MAX]);
    }

    public function testAccountHistoryThrowsNotFoundWhenAccountIdIsNotNumeric(): void
    {
        $this->pushRequest([]);

        $this->expectException(NotFoundHttpException::class);

        $this->provider->provide(new GetCollection(), ['accountId' => 'abc']);
    }

    public function testAccountHistoryToleratesAbsurdPageNumber(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, '10.00');

        $this->pushRequest(['page' => (string) PHP_INT_MAX]);
        $result = $this->provider->provide(new GetCollection(), ['accountId' => $account->getId()]);

        self::assertInstanceOf(OffsetPaginator::class, $result);
        self::assertCount(0, iterator_to_array($result));
    }

    public function testSearchFindsMatchingOperations(): void
    {
        $marker = uniqid();
        $account = $this->createAccount();
        $match = $this->createOperation($account, '10.00', null, "Findable {$marker}");
        $this->createOperation($account, '10.00', null, "Other {$marker}");

        $this->pushRequest(['q' => "Findable {$marker}"]);
        $result = $this->provider->provide(new GetCollection());

        self::assertInstanceOf(OffsetPaginator::class, $result);
        self::assertSame([$match->getId()], $this->ids($result));
        self::assertSame(1.0, $result->getTotalItems());
    }

    public function testSearchSpansAllAccounts(): void
    {
        $marker = uniqid();
        $inA = $this->createOperation($this->createAccount(), '10.00', null, "Shared {$marker}");
        $inB = $this->createOperation($this->createAccount(), '10.00', null, "Shared {$marker}");

        $this->pushRequest(['q' => $marker]);
        $result = $this->provider->provide(new GetCollection());

        self::assertInstanceOf(OffsetPaginator::class, $result);
        self::assertEqualsCanonicalizing([$inA->getId(), $inB->getId()], $this->ids($result));
    }

    public function testSearchIgnoresTheAccountIdQueryParameter(): void
    {
        $marker = uniqid();
        $accountA = $this->createAccount();
        $this->createOperation($accountA, '10.00', null, "Shared {$marker}");
        $this->createOperation($this->createAccount(), '10.00', null, "Shared {$marker}");

        $this->pushRequest(['q' => $marker, 'accountId' => (string) $accountA->getId()]);
        $result = $this->provider->provide(new GetCollection());

        self::assertInstanceOf(OffsetPaginator::class, $result);
        self::assertSame(2.0, $result->getTotalItems());
    }

    public function testSearchWithWhitespaceOnlyQueryFindsNothing(): void
    {
        $this->createOperation($this->createAccount(), '10.00');

        $this->pushRequest(['q' => '   ']);
        $result = $this->provider->provide(new GetCollection());

        self::assertInstanceOf(OffsetPaginator::class, $result);
        self::assertCount(0, iterator_to_array($result));
        self::assertSame(0.0, $result->getTotalItems());
    }

    public function testSearchWithoutQueryFindsNothing(): void
    {
        $this->createOperation($this->createAccount(), '10.00');

        $this->pushRequest([]);
        $result = $this->provider->provide(new GetCollection());

        self::assertInstanceOf(OffsetPaginator::class, $result);
        self::assertSame(0.0, $result->getTotalItems());
    }

    public function testSearchToleratesAbsurdPageNumber(): void
    {
        $this->createOperation($this->createAccount(), '10.00', null, 'Absurd page');

        $this->pushRequest(['q' => 'Absurd page', 'page' => (string) PHP_INT_MAX]);
        $result = $this->provider->provide(new GetCollection());

        self::assertInstanceOf(OffsetPaginator::class, $result);
        self::assertCount(0, iterator_to_array($result));
    }

    /**
     * @param OffsetPaginator<AccountOperationResource> $paginator
     *
     * @return list<int|null>
     */
    private function ids(OffsetPaginator $paginator): array
    {
        return $paginator
                |> iterator_to_array(...)
                |> (fn($x) => array_map(static fn(AccountOperationResource $r): ?int => $r->id, $x))
                |> array_values(...);
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

    private function createOperation(
        Account $account,
        string $amount,
        ?\DateTimeImmutable $date = null,
        ?string $description = null
    ): AccountOperation
    {
        $contractor = new Contractor('Test Contractor '.uniqid());
        $this->em()->persist($contractor);

        $operation = new AccountOperation(
            $account,
            $contractor,
            $date ?? new \DateTimeImmutable(),
            Money::fromDecimalString($amount, $account->getCurrencyCode())->getMinorUnits(),
            $this->em()->getRepository(Currency::class)->find($account->getCurrencyCode()) ??
            throw new \RuntimeException('Missing currency.'),
            description: $description,
        );
        $this->em()->persist($operation);
        $this->em()->flush();

        return $operation;
    }
}
