<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use App\ApiResource\SummaryResource;
use App\Entity\Account;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\Household;
use App\State\SummaryProvider;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class SummaryProviderTest extends KernelTestCase
{
    use DatabaseTestTrait;

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
        $visibleNumber = 'VIS-' . uniqid();
        $hiddenNumber = 'HID-' . uniqid();
        $this->createAccount($visibleNumber, hidden: false);
        $this->createAccount($hiddenNumber, hidden: true);

        $this->pushRequest([]);

        $result = $this->provider->provide(new Get());

        self::assertInstanceOf(SummaryResource::class, $result);
        self::assertNotNull($this->findAccountRow($result, $visibleNumber));
        self::assertNull($this->findAccountRow($result, $hiddenNumber));
    }

    public function testProvideIncludesHiddenAccountsWhenShowHiddenTrue(): void
    {
        $visibleNumber = 'VIS-' . uniqid();
        $hiddenNumber = 'HID-' . uniqid();
        $this->createAccount($visibleNumber, hidden: false);
        $this->createAccount($hiddenNumber, hidden: true);

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
        $hiddenNumber = 'HID-' . uniqid();
        $this->createAccount($hiddenNumber, hidden: true);

        $this->pushRequest(['showHidden' => 'false']);

        $result = $this->provider->provide(new Get());

        self::assertInstanceOf(SummaryResource::class, $result);
        self::assertNull($this->findAccountRow($result, $hiddenNumber));
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
     *     saldo: mixed,
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
                 *     saldo: mixed,
                 *     missing_rate: bool
                 * } $account
                 */
                return $account;
            }
        }

        return null;
    }

    private function createAccount(string $accountNumber, bool $hidden): Account
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

        $account = new Account($bank, $currency, $accountType, $accountNumber, $household);
        $account->setHidden($hidden);
        $this->em()->persist($account);
        $this->em()->flush();

        return $account;
    }
}
