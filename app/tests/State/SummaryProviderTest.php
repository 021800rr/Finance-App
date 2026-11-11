<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use App\ApiResource\Summary\SummaryAccount;
use App\ApiResource\SummaryResource;
use App\State\SummaryProvider;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class SummaryProviderTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

    private SummaryProvider $provider;
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var SummaryProvider $provider */
        $provider = self::getContainer()->get(SummaryProvider::class);
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

    public function testProvideExcludesHiddenAccountsByDefault(): void
    {
        $visibleNumber = 'VIS-'.uniqid();
        $hiddenNumber = 'HID-'.uniqid();
        $this->createAccount(accountNumber: $visibleNumber);
        $this->createAccount(accountNumber: $hiddenNumber, hidden: true);

        $this->pushRequest([]);

        $result = $this->provider->provide(new Get());

        self::assertInstanceOf(SummaryResource::class, $result);
        self::assertNotNull($this->findAccountRow($result, $visibleNumber));
        self::assertNull($this->findAccountRow($result, $hiddenNumber));
    }

    public function testProvideIncludesHiddenAccountsWhenShowHiddenTrue(): void
    {
        $visibleNumber = 'VIS-'.uniqid();
        $hiddenNumber = 'HID-'.uniqid();
        $this->createAccount(accountNumber: $visibleNumber);
        $this->createAccount(accountNumber: $hiddenNumber, hidden: true);

        $this->pushRequest(['showHidden' => 'true']);

        $result = $this->provider->provide(new Get());

        self::assertInstanceOf(SummaryResource::class, $result);
        $visibleRow = $this->findAccountRow($result, $visibleNumber);
        $hiddenRow = $this->findAccountRow($result, $hiddenNumber);

        self::assertNotNull($visibleRow);
        self::assertNotNull($hiddenRow);
        self::assertFalse($visibleRow->hidden);
        self::assertTrue($hiddenRow->hidden);
    }

    public function testProvideTreatsShowHiddenFalseSameAsOmitted(): void
    {
        $hiddenNumber = 'HID-'.uniqid();
        $this->createAccount(accountNumber: $hiddenNumber, hidden: true);

        $this->pushRequest(['showHidden' => 'false']);

        $result = $this->provider->provide(new Get());

        self::assertInstanceOf(SummaryResource::class, $result);
        self::assertNull($this->findAccountRow($result, $hiddenNumber));
    }

    public function testProvideReturnsTheLatestRateOfTheBankForForeignCurrencyAccounts(): void
    {
        $bank = $this->createBank();
        $accountNumber = 'CHF-'.uniqid();
        $this->createAccount('CHF', bank: $bank, accountNumber: $accountNumber);
        $this->createRate($bank, 'CHF', new \DateTimeImmutable('-10 days'), 40000);
        $this->createRate($bank, 'CHF', new \DateTimeImmutable('-1 day'), 41234);

        $this->pushRequest([]);

        $result = $this->provider->provide(new Get());

        self::assertInstanceOf(SummaryResource::class, $result);
        $row = $this->findAccountRow($result, $accountNumber);
        self::assertNotNull($row);
        self::assertSame(['amount' => '4.1234', 'currency' => 'PLN'], $row->rate);
        self::assertFalse($row->missingRate);
    }

    public function testProvideReturnsNoRateForPlnAccounts(): void
    {
        $accountNumber = 'PLN-'.uniqid();
        $this->createAccount(accountNumber: $accountNumber);

        $this->pushRequest([]);

        $result = $this->provider->provide(new Get());

        self::assertInstanceOf(SummaryResource::class, $result);
        $row = $this->findAccountRow($result, $accountNumber);
        self::assertNotNull($row);
        self::assertNull($row->rate);
        self::assertFalse($row->missingRate);
    }

    public function testProvideReturnsNoRateAndFlagsMissingRateWhenTheBankHasNone(): void
    {
        $accountNumber = 'CHF-'.uniqid();
        $this->createAccount('CHF', accountNumber: $accountNumber);

        $this->pushRequest([]);

        $result = $this->provider->provide(new Get());

        self::assertInstanceOf(SummaryResource::class, $result);
        $row = $this->findAccountRow($result, $accountNumber);
        self::assertNotNull($row);
        self::assertNull($row->rate);
        self::assertTrue($row->missingRate);
    }

    public function testProvideMarksOnlyTheMainAccountAsMain(): void
    {
        $mainNumber = 'MAIN-'.uniqid();
        $otherNumber = 'OTHER-'.uniqid();
        $this->createAccount(accountNumber: $mainNumber, main: true);
        $this->createAccount(accountNumber: $otherNumber);

        $this->pushRequest([]);

        $result = $this->provider->provide(new Get());

        self::assertInstanceOf(SummaryResource::class, $result);
        $mainRow = $this->findAccountRow($result, $mainNumber);
        $otherRow = $this->findAccountRow($result, $otherNumber);
        self::assertNotNull($mainRow);
        self::assertNotNull($otherRow);
        self::assertTrue($mainRow->isMain);
        self::assertFalse($otherRow->isMain);
        self::assertCount(
            1,
            array_filter($result->accounts, static fn (SummaryAccount $account): bool => $account->isMain),
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    private function pushRequest(array $query): void
    {
        $this->requestStack->push(new Request(query: $query));
    }

    private function findAccountRow(SummaryResource $resource, string $accountNumber): ?SummaryAccount
    {
        foreach ($resource->accounts as $account) {
            if ($account->accountNumber === $accountNumber) {
                return $account;
            }
        }

        return null;
    }
}
