<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Account;
use App\Entity\Contractor;
use App\Service\Paginator;
use App\Tests\Controller\LogsInTrait;
use App\Tests\DatabaseTestTrait;
use App\Tests\JwtTrait;
use App\Tests\TestDataTrait;
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
    use TestDataTrait;
    use JwtTrait;

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
        self::assertStringContainsString(
            $this->asWebAmount(self::stringAt($api, 'saldoToday', 'amount')),
            $balanceLine,
        );
        self::assertNotSame(self::stringAt($api, 'saldo', 'amount'), self::stringAt($api, 'saldoToday', 'amount'));
    }

    public function testEveryPageOfOperationsMatchesTheWebPage(): void
    {
        $account = $this->createAccountWithHistory();

        foreach ([1, 2] as $page) {
            $webRows = $this->webRows($this->webPage($account, $page));
            $apiRows = self::rowsAt(
                $this->getJson('/api/accounts/'.$account->getId().'/operations?page='.$page),
                'member',
            );

            self::assertNotEmpty($webRows);
            self::assertCount(count($webRows), $apiRows, "page {$page}: number of rows");
            foreach ($webRows as $i => $web) {
                $api = $apiRows[$i];
                self::assertSame($web['id'], $api['id'], "page {$page}, row {$i}: id");
                self::assertSame($web['date'], $api['operationDate'], "page {$page}, row {$i}: date");
                self::assertSame($web['contractor'], $api['contractorName'], "page {$page}, row {$i}: contractor");
                self::assertSame($web['description'], $api['description'] ?? '', "page {$page}, row {$i}: description");
                self::assertSame(
                    $web['amount'],
                    $this->asWebAmount(self::stringAt($api, 'amount')),
                    "page {$page}, row {$i}: amount",
                );
                self::assertSame($web['confirmed'], $api['confirmed'], "page {$page}, row {$i}: confirmed");
                self::assertSame(
                    $web['linked'],
                    isset($api['transactionUuid']),
                    "page {$page}, row {$i}: transfer link",
                );
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
        self::assertStringContainsString(
            '1 / '.$totalPages,
            $this->normalizedWithSpaces($webPage->filter('.pagination')->text()),
        );
        self::assertStringEndsWith('page='.$totalPages, self::stringAt($api, 'view', 'last'));
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $uri): array
    {
        $this->client->request(
            'GET',
            $uri,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt(), 'HTTP_ACCEPT' => 'application/ld+json'],
        );
        self::assertResponseIsSuccessful();

        return self::decodeObject((string) $this->client->getResponse()->getContent());
    }

    private function webPage(Account $account, int $page): Crawler
    {
        $crawler = $this->client->request('GET', '/account/'.$account->getId().'/operations?page='.$page);
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    /**
     * @return list<array{
     *     id: int,
     *     date: string,
     *     contractor: string,
     *     description: string,
     *     amount: string,
     *     confirmed: bool,
     *     linked: bool
     * }>
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
        $account = $this->createAccount();
        $contractor = $this->createContractor();
        for ($i = 0; $i < self::OPERATIONS; ++$i) {
            $this->createOperation(
                $account,
                100000 + $i * 111 - ($i % 3) * 250000,
                // The two newest operations are dated in the future ("balance today" must differ from "balance").
                new \DateTimeImmutable(sprintf('%+d days', 2 - $i)),
                $contractor,
                description: 0 === $i % 4 ? null : "Operation {$i}",
                confirmed: 0 === $i % 2,
                transactionUuid: 0 === $i % 5 ? Uuid::v4() : null,
            );
        }

        return $account;
    }
}
