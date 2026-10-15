<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Constant\Currencies;
use App\Entity\AccountOperation;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\FixedExpense;
use App\Repository\AccountRepository;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FixedExpenseControllerTest extends WebTestCase
{
    use DatabaseTestTrait;
    use LogsInTrait;

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

    public function testIndexRendersSuccessfully(): void
    {
        $this->client->request('GET', '/fixed-expense');

        self::assertResponseIsSuccessful();
    }

    public function testSubmittingWithoutContractorShowsError(): void
    {
        $crawler = $this->client->request('GET', '/fixed-expense');
        $form = $crawler->selectButton('Add')->form();

        $this->client->submit($form, [
            'fixed_expense[day]' => '15',
            'fixed_expense[amount]' => '99',
            'contractor_input' => '',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Please select a contractor from the list.');
    }

    public function testSubmittingWithValidContractorCreatesFixedExpenseAndRedirects(): void
    {
        $contractor = new Contractor('Test Contractor ' . uniqid());
        $this->em()->persist($contractor);
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/fixed-expense');
        $form = $crawler->selectButton('Add')->form();

        $this->client->submit($form, [
            'fixed_expense[day]' => '10',
            'fixed_expense[amount]' => '-150',
            'fixed_expense[contractor]' => (string) $contractor->getId(),
            'contractor_input' => $contractor->getName(),
        ]);

        self::assertResponseRedirects('/fixed-expense');
    }

    public function testEditReturnsNotFoundForNonNumericId(): void
    {
        $this->client->request('GET', '/fixed-expense/alamakota/edit');

        self::assertResponseStatusCodeSame(404);
    }

    public function testEditReturnsNotFoundForNonExistentFixedExpense(): void
    {
        $this->client->request('GET', '/fixed-expense/999999999/edit');

        self::assertResponseStatusCodeSame(404);
    }

    public function testEditUpdatesFixedExpenseAndRedirects(): void
    {
        $fixedExpense = $this->createFixedExpenseInDatabase();

        $newContractor = new Contractor('New Contractor ' . uniqid());
        $this->em()->persist($newContractor);
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/fixed-expense/' . $fixedExpense->getId() . '/edit');
        $form = $crawler->filter('#fixed_expense_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'fixed_expense[day]' => '20',
            'fixed_expense[amount]' => '-9999',
            'fixed_expense[contractor]' => (string) $newContractor->getId(),
            'contractor_input' => $newContractor->getName(),
        ]);

        self::assertResponseRedirects('/fixed-expense');

        $updated = $this->em()->find(FixedExpense::class, $fixedExpense->getId());

        self::assertNotNull($updated);
        self::assertSame(20, $updated->getDay());
        self::assertSame(-999900, $updated->getAmount());
        self::assertSame($newContractor->getId(), $updated->getContractor()->getId());
    }

    public function testDeleteReturnsNotFoundForNonNumericId(): void
    {
        $this->client->request('POST', '/fixed-expense/alamakota/delete');

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteRejectsInvalidCsrfToken(): void
    {
        $fixedExpense = $this->createFixedExpenseInDatabase();

        $this->client->request('POST', '/fixed-expense/' . $fixedExpense->getId() . '/delete', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteRemovesFixedExpenseWithValidCsrfToken(): void
    {
        $fixedExpense = $this->createFixedExpenseInDatabase();
        $fixedExpenseId = $fixedExpense->getId();

        $crawler = $this->client->request('GET', '/fixed-expense');
        $csrfToken = $crawler->filter('form[action*="' . $fixedExpenseId . '/delete"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/fixed-expense/' . $fixedExpenseId . '/delete', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/fixed-expense');

        $deleted = $this->em()->find(FixedExpense::class, $fixedExpenseId);

        self::assertNull($deleted);
    }

    public function testPlanRejectsInvalidCsrfToken(): void
    {
        $this->client->request('POST', '/fixed-expense/plan', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testPlanCreatesOperationsForNextMonthWithValidCsrfToken(): void
    {
        $this->createFixedExpenseInDatabase();

        $crawler = $this->client->request('GET', '/fixed-expense');
        $csrfToken = $crawler->filter('form[action*="/fixed-expense/plan"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/fixed-expense/plan', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects();
    }

    public function testPlanShowsFlashMessageWhenDuplicateDetected(): void
    {
        /** @var AccountRepository $accountRepository */
        $accountRepository = self::getContainer()->get(AccountRepository::class);
        $mainAccount = $accountRepository->findMain();
        self::assertNotNull($mainAccount, 'An account must be marked as main in fixtures.');
        $mainAccountId = $mainAccount->getId();

        $fixedExpense = $this->createFixedExpenseInDatabase();

        $targetMonth = new \DateTimeImmutable('first day of this month')->modify('+1 month');
        $duplicateDate = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            sprintf('%04d-%02d-%02d 00:00:00', (int) $targetMonth->format('Y'), (int) $targetMonth->format('m'), $fixedExpense->getDay())
        );
        self::assertNotFalse($duplicateDate);

        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->find(Currencies::SETTLEMENT_CURRENCY_CODE);
        self::assertNotNull($currency);

        $existingOperation = new AccountOperation($mainAccount, $fixedExpense->getContractor(), $duplicateDate, $fixedExpense->getAmount(), $currency);
        $this->em()->persist($existingOperation);
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/fixed-expense');
        $csrfToken = $crawler->filter('form[action*="/fixed-expense/plan"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/fixed-expense/plan', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/account/' . $mainAccountId . '/operations');

        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Suspected duplicate');
    }

    private function createFixedExpenseInDatabase(): FixedExpense
    {
        $contractor = new Contractor('Test Contractor ' . uniqid());
        $this->em()->persist($contractor);

        $pln = $this->em()->getRepository(Currency::class)->find(Currencies::SETTLEMENT_CURRENCY_CODE);
        self::assertNotNull($pln, 'PLN currency must exist in fixtures.');

        $fixedExpense = new FixedExpense($contractor, 10, -5000, $pln);
        $this->em()->persist($fixedExpense);
        $this->em()->flush();

        return $fixedExpense;
    }
}
