<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\FixedExpense;
use App\Tests\DatabaseTestTrait;
use App\Tests\JwtTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FixedExpensePlanApiTest extends WebTestCase
{
    use DatabaseTestTrait;
    use ReadsJsonTrait;
    use TestDataTrait;
    use JwtTrait;

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
        $main = $this->createAccount(main: true);
        $fixedExpense = $this->createFixedExpense(day: 12, amount: -15000);
        $expectedCount = $this->em()->getRepository(FixedExpense::class)->count([]);
        $operationsBefore = $this->em()->getRepository(AccountOperation::class)->count(['account' => $main]);

        $this->client->request('POST', '/api/fixed_expenses/plan', server: $this->authHeaders());

        self::assertResponseStatusCodeSame(201);
        $data = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame($main->getId(), self::intAt($data, 'accountId'));
        self::assertSame(
            new \DateTimeImmutable('first day of next month')->format('Y-m'),
            self::stringAt($data, 'month'),
        );
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
        $this->createAccount(main: true);
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
        $this->createAccount('CHF', main: true);
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
        $this->createAccount(household: $this->household(false), main: true);
        $this->createFixedExpense(day: 12, amount: -15000);
        $operationsBefore = $this->em()->getRepository(AccountOperation::class)->count([]);

        $this->client->request('POST', '/api/fixed_expenses/plan', server: $this->authHeaders());

        $this->assertConflict('fixed_expense.plan_main_not_own', ['account', 'household']);
        self::assertSame($operationsBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
    }

    public function testPlanWithAHiddenMainAccountRespondsWithConflictAndCreatesNothing(): void
    {
        $main = $this->createAccount(main: true);
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
}
