<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\Household;
use App\Service\Paginator;
use App\Tests\Controller\LogsInTrait;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Uuid;

/**
 * The API must give a client everything the web account history page (/account/{id}/operations) shows:
 * GET /api/accounts/{id} for the header and balances, GET /api/accounts/{id}/operations for the table.
 * Each test renders the Twig page and the API responses for the same account and compares them.
 */
final class AccountViewApiTest extends WebTestCase
{
    use DatabaseTestTrait;
    use LogsInTrait;
    use ReadsJsonTrait;

    private const int OPERATIONS = Paginator::DEFAULT_PER_PAGE + 5;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->initEntityManager($this->client->getKernel());
        $this->logIn();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testHeaderMatchesTheWebPage(): void
    {
        $account = $this->createAccountWithHistory();

        $api = $this->getJson('/api/accounts/'.$account->getId());
        $header = $this->webPage($account, 1)->filter('.card-body .text-muted.small')->text();

        self::assertStringContainsString(self::stringAt($api, 'bankName'), $header);
        self::assertStringContainsString(self::stringAt($api, 'typeName'), $header);
        self::assertStringContainsString(self::stringAt($api, 'currencyCode'), $header);
        self::assertStringContainsString(self::stringAt($api, 'accountNumber'), $header);
    }

    public function testBalancesMatchTheWebPage(): void
    {
        $account = $this->createAccountWithHistory();

        $api = $this->getJson('/api/accounts/'.$account->getId());
        $balanceLine = $this->normalized($this->webPage($account, 1)->filter('.mt-2')->first()->text());

        self::assertTrue($api['hasFutureOperations']);
        self::assertStringContainsString($this->asWebAmount(self::stringAt($api, 'saldo', 'amount')), $balanceLine);
        self::assertStringContainsString($this->asWebAmount(self::stringAt($api, 'saldoToday', 'amount')), $balanceLine);
        self::assertNotSame(self::stringAt($api, 'saldo', 'amount'), self::stringAt($api, 'saldoToday', 'amount'));
    }

    public function testEveryPageOfOperationsMatchesTheWebPage(): void
    {
        $account = $this->createAccountWithHistory();

        foreach ([1, 2] as $page) {
            $webRows = $this->webRows($this->webPage($account, $page));
            $apiRows = self::rowsAt($this->getJson('/api/accounts/'.$account->getId().'/operations?page='.$page), 'member');

            self::assertNotEmpty($webRows);
            self::assertCount(count($webRows), $apiRows, "page {$page}: number of rows");
            foreach ($webRows as $i => $web) {
                $api = $apiRows[$i];
                self::assertSame($web['id'], $api['id'], "page {$page}, row {$i}: id");
                self::assertSame($web['date'], $api['operationDate'], "page {$page}, row {$i}: date");
                self::assertSame($web['contractor'], $api['contractorName'], "page {$page}, row {$i}: contractor");
                self::assertSame($web['description'], $api['description'] ?? '', "page {$page}, row {$i}: description");
                self::assertSame($web['amount'], $this->asWebAmount(self::stringAt($api, 'amount')), "page {$page}, row {$i}: amount");
                self::assertSame($web['confirmed'], $api['confirmed'], "page {$page}, row {$i}: confirmed");
                self::assertSame($web['linked'], isset($api['transactionUuid']), "page {$page}, row {$i}: transfer link");
            }
        }
    }

    public function testNumberOfPagesMatchesTheWebPage(): void
    {
        $account = $this->createAccountWithHistory();

        $api = $this->getJson('/api/accounts/'.$account->getId().'/operations');
        $webPage = $this->webPage($account, 1);

        $totalItems = self::intAt($api, 'totalItems');
        $totalPages = (int) ceil($totalItems / Paginator::DEFAULT_PER_PAGE);

        self::assertSame(self::OPERATIONS, $totalItems);
        self::assertSame(2, $totalPages);
        self::assertStringContainsString('1 / '.$totalPages, $this->normalizedWithSpaces($webPage->filter('.pagination')->text()));
        self::assertStringEndsWith('page='.$totalPages, self::stringAt($api, 'view', 'last'));
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $uri): array
    {
        $this->client->request('GET', $uri, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt(), 'HTTP_ACCEPT' => 'application/ld+json']);
        self::assertResponseIsSuccessful();

        $data = self::decodeObject((string) $this->client->getResponse()->getContent());
        $data['member'] ??= $data['hydra:member'] ?? null;
        $data['view'] ??= $data['hydra:view'] ?? null;

        return $data;
    }

    private function jwt(): string
    {
        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'test@example.com', 'password' => 'test1234'], JSON_THROW_ON_ERROR),
        );
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data) || !is_string($data['token'] ?? null)) {
            throw new \RuntimeException('Could not obtain a JWT token in the test.');
        }

        return $data['token'];
    }

    private function webPage(Account $account, int $page): Crawler
    {
        $crawler = $this->client->request('GET', '/account/'.$account->getId().'/operations?page='.$page);
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    /**
     * @return list<array{id: int, date: string, contractor: string, description: string, amount: string, confirmed: bool, linked: bool}>
     */
    private function webRows(Crawler $page): array
    {
        return $page->filter('table tbody tr')->each(function (Crawler $row): array {
            $cells = $row->filter('td');

            return [
                'id' => (int) $cells->eq(0)->text(),
                'date' => trim($cells->eq(1)->text()),
                'contractor' => trim($cells->eq(2)->text()),
                'description' => trim($cells->eq(3)->text()),
                'amount' => $this->normalized($cells->eq(4)->text()),
                'confirmed' => $row->filter('.op-confirm-toggle.is-confirmed')->count() > 0,
                'linked' => $row->filter('.link-icon-plain')->count() > 0,
            ];
        });
    }

    private function asWebAmount(string $apiAmount): string
    {
        return str_replace('.', ',', $apiAmount);
    }

    private function normalizedWithSpaces(string $text): string
    {
        return trim((string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $text));
    }

    private function normalized(string $text): string
    {
        return (string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $text);
    }

    private function createAccountWithHistory(): Account
    {
        $bank = new Bank('View Bank '.uniqid());
        $type = new AccountType('View Type '.uniqid());
        $household = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => true]);
        $currency = $this->em()->getRepository(Currency::class)->find('PLN') ?? new Currency('PLN');
        if (null === $household) {
            throw new \RuntimeException('Fixtures must provide exactly one own-funds household.');
        }
        $this->em()->persist($bank);
        $this->em()->persist($type);
        $this->em()->persist($currency);
        $account = new Account($bank, $currency, $type, 'ACC-'.uniqid(), $household);
        $this->em()->persist($account);

        $contractor = new Contractor('View Contractor '.uniqid());
        $this->em()->persist($contractor);
        for ($i = 0; $i < self::OPERATIONS; ++$i) {
            // The two newest operations are dated in the future ("balance today" must differ from "balance").
            $date = new \DateTimeImmutable(sprintf('%+d days', 2 - $i));
            $operation = new AccountOperation(
                $account,
                $contractor,
                $date,
                100000 + $i * 111 - ($i % 3) * 250000,
                $currency,
                description: 0 === $i % 4 ? null : "Operation {$i}",
                confirmed: 0 === $i % 2,
                transactionUuid: 0 === $i % 5 ? Uuid::v4() : null,
            );
            $this->em()->persist($operation);
        }
        $this->em()->flush();

        return $account;
    }
}
