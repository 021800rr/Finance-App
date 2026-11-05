<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use App\ApiResource\SummaryResource;
use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\Household;
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
        self::assertFalse($visibleRow['hidden']);
        self::assertTrue($hiddenRow['hidden']);
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
        self::assertSame(['amount' => '4.1234', 'currency' => 'PLN'], $row['rate']);
        self::assertFalse($row['missing_rate']);
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
        self::assertNull($row['rate']);
        self::assertFalse($row['missing_rate']);
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
        self::assertNull($row['rate']);
        self::assertTrue($row['missing_rate']);
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
        self::assertTrue($mainRow['is_main']);
        self::assertFalse($otherRow['is_main']);
        self::assertCount(
            1,
            array_filter($result->accounts, static fn (array $account): bool => true === $account['is_main']),
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    private function pushRequest(array $query): void
    {
        $this->requestStack->push(new Request(query: $query));
    }

    /**
     * @return array{
     *     id: int,
     *     bank: string,
     *     type: string,
     *     currency: string,
     *     account_number: string,
     *     hidden: bool,
     *     household: string,
     *     is_own_funds: bool,
     *     is_retirement: bool,
     *     is_main: bool,
     *     saldo: mixed,
     *     rate: mixed,
     *     missing_rate: bool
     * }|null
     */
    private function findAccountRow(SummaryResource $resource, string $accountNumber): ?array
    {
        foreach ($resource->accounts as $account) {
            if ($account['account_number'] === $accountNumber) {
                /** @var array{
                 *     id: int,
                 *     bank: string,
                 *     type: string,
                 *     currency: string,
                 *     account_number: string,
                 *     hidden: bool,
                 *     household: string,
                 *     is_own_funds: bool,
                 *     is_retirement: bool,
                 *     is_main: bool,
                 *     saldo: mixed,
                 *     rate: mixed,
                 *     missing_rate: bool
                 * } $row
                 */
                $row = $account;

                return $row;
            }
        }

        return null;
    }
}
