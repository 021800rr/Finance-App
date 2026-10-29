<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\FixedExpense;
use App\Entity\Household;
use App\Repository\AccountRepository;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FixedExpensePlanApiTest extends WebTestCase
{
    use DatabaseTestTrait;
    use ReadsJsonTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->initEntityManager($this->client->getKernel());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testPlanRequiresAuthentication(): void
    {
        $this->client->request('POST', '/api/fixed_expenses/plan');

        self::assertResponseStatusCodeSame(401);
    }

    public function testPlanCreatesOperationsOnTheMainAccountForTheNextMonth(): void
    {
        $main = $this->createMainAccount();
        $fixedExpense = $this->createFixedExpense(day: 12, amount: -15000);
        $expectedCount = $this->em()->getRepository(FixedExpense::class)->count([]);
        $operationsBefore = $this->em()->getRepository(AccountOperation::class)->count(['account' => $main]);

        $this->client->request('POST', '/api/fixed_expenses/plan', server: $this->authHeaders());

        self::assertResponseStatusCodeSame(201);
        $data = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame($main->getId(), self::intAt($data, 'accountId'));
        self::assertSame(new \DateTimeImmutable('first day of next month')->format('Y-m'), self::stringAt($data, 'month'));
        self::assertSame($expectedCount, self::intAt($data, 'createdCount'));

        $this->em()->clear();
        self::assertSame(
            $operationsBefore + $expectedCount,
            $this->em()->getRepository(AccountOperation::class)->count(['account' => $main->getId()]),
        );

        $operations = $this->em()->getRepository(AccountOperation::class)->findBy([
            'contractor' => $fixedExpense->getContractor()->getId(),
        ]);
        self::assertCount(1, $operations);
        self::assertSame($main->getId(), $operations[0]->getAccount()->getId());
        self::assertSame(-15000, $operations[0]->getAmount());
        self::assertSame(
            new \DateTimeImmutable('first day of next month')->format('Y-m').'-12',
            $operations[0]->getOperationDate()->format('Y-m-d'),
        );
    }

    public function testPlanTwiceRespondsWithConflictAndCreatesNothingTheSecondTime(): void
    {
        $this->createMainAccount();
        $this->createFixedExpense(day: 12, amount: -15000);

        $this->client->request('POST', '/api/fixed_expenses/plan', server: $this->authHeaders());
        self::assertResponseStatusCodeSame(201);
        $operationsAfterFirstCall = $this->em()->getRepository(AccountOperation::class)->count([]);

        $this->client->request('POST', '/api/fixed_expenses/plan', server: $this->authHeaders());

        $this->assertConflict('fixed_expense.plan_duplicate', ['contractor', 'amount', 'date']);
        self::assertSame($operationsAfterFirstCall, $this->em()->getRepository(AccountOperation::class)->count([]));
    }

    public function testPlanWithoutMainAccountRespondsWithConflict(): void
    {
        foreach ($this->em()->getRepository(Account::class)->findBy(['isMain' => true]) as $account) {
            $account->setMain(false);
        }
        $this->em()->flush();

        $this->client->request('POST', '/api/fixed_expenses/plan', server: $this->authHeaders());

        $this->assertConflict('fixed_expense.plan_no_main_account', []);
    }

    public function testPlanWithAForeignCurrencyMainAccountRespondsWithConflictAndCreatesNothing(): void
    {
        $this->createMainAccount(currencyCode: 'CHF');
        $this->createFixedExpense(day: 12, amount: -15000);
        $operationsBefore = $this->em()->getRepository(AccountOperation::class)->count([]);

        $this->client->request('POST', '/api/fixed_expenses/plan', server: $this->authHeaders());

        $params = $this->assertConflict(
            'fixed_expense.plan_currency_mismatch',
            ['expenseCurrency', 'account', 'accountCurrency'],
        );
        self::assertSame('PLN', $params['expenseCurrency']);
        self::assertSame('CHF', $params['accountCurrency']);
        self::assertSame($operationsBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
    }

    public function testPlanWithAMainAccountOfAnotherHouseholdRespondsWithConflictAndCreatesNothing(): void
    {
        $this->createMainAccount(ownFunds: false);
        $this->createFixedExpense(day: 12, amount: -15000);
        $operationsBefore = $this->em()->getRepository(AccountOperation::class)->count([]);

        $this->client->request('POST', '/api/fixed_expenses/plan', server: $this->authHeaders());

        $this->assertConflict('fixed_expense.plan_main_not_own', ['account', 'household']);
        self::assertSame($operationsBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
    }

    public function testPlanWithAHiddenMainAccountRespondsWithConflictAndCreatesNothing(): void
    {
        $main = $this->createMainAccount();
        $main->setHidden(true);
        $this->em()->flush();
        $this->createFixedExpense(day: 12, amount: -15000);
        $operationsBefore = $this->em()->getRepository(AccountOperation::class)->count([]);

        $this->client->request('POST', '/api/fixed_expenses/plan', server: $this->authHeaders());

        $params = $this->assertConflict('fixed_expense.plan_main_hidden', ['account']);
        self::assertSame($main->getAccountNumber(), $params['account']);
        self::assertSame($operationsBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
    }

    /**
     * Asserts a 409 problem with the given code and parameter names; returns the parameters.
     *
     * @param list<string> $paramNames
     *
     * @return array<string, mixed>
     */
    private function assertConflict(string $errorCode, array $paramNames): array
    {
        self::assertResponseStatusCodeSame(409);
        $problem = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame($errorCode, $problem['errorCode'] ?? null);
        self::assertSame('urn:finance-app:problem:'.$errorCode, $problem['type'] ?? null);
        self::assertNotSame('', self::stringAt($problem, 'detail'));
        $params = self::objectAt($problem, 'params');
        self::assertSame($paramNames, array_keys($params));

        return $params;
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken()];
    }

    private function obtainJwtToken(): string
    {
        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'test@example.com', 'password' => 'test1234'], JSON_THROW_ON_ERROR),
        );

        $data = self::decodeObject((string) $this->client->getResponse()->getContent());

        return self::stringAt($data, 'token');
    }

    private function createMainAccount(string $currencyCode = 'PLN', bool $ownFunds = true): Account
    {
        $bank = new Bank('Test Bank '.uniqid());
        $this->em()->persist($bank);

        $currency = $this->em()->getRepository(Currency::class)->find($currencyCode);
        if (null === $currency) {
            $currency = new Currency($currencyCode);
            $this->em()->persist($currency);
        }

        $accountType = new AccountType('Test Type '.uniqid());
        $this->em()->persist($accountType);

        $household = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => $ownFunds]);
        if (null === $household) {
            throw new \RuntimeException('Fixtures must provide the household for this test.');
        }

        $account = new Account($bank, $currency, $accountType, 'ACC-'.uniqid(), $household);
        $this->em()->persist($account);
        $this->em()->flush();

        /** @var AccountRepository $accountRepository */
        $accountRepository = self::getContainer()->get(AccountRepository::class);
        $accountRepository->setAsMainAccount($account);

        return $account;
    }

    private function createFixedExpense(int $day, int $amount): FixedExpense
    {
        $contractor = new Contractor('Plan Contractor '.uniqid());
        $this->em()->persist($contractor);

        $currency = $this->em()->getRepository(Currency::class)->find('PLN');
        if (null === $currency) {
            throw new \RuntimeException('PLN currency must exist.');
        }

        $fixedExpense = new FixedExpense($contractor, $day, $amount, $currency);
        $this->em()->persist($fixedExpense);
        $this->em()->flush();

        return $fixedExpense;
    }
}
