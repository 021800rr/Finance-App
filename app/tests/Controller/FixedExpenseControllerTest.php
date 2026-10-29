<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Constant\Currencies;
use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\FixedExpense;
use App\Entity\Household;
use App\Repository\AccountRepository;
use App\Tests\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('invalidDays')]
    public function testCreateWithInvalidDayShowsFieldErrorWithoutSaving(string $day, string $message): void
    {
        $contractor = new Contractor('Invalid day test '.uniqid());
        $this->em()->persist($contractor);
        $this->em()->flush();
        $countBefore = $this->em()->getRepository(FixedExpense::class)->count([]);

        $crawler = $this->client->request('GET', '/fixed-expense');
        $form = $crawler->filter('#fixed_expense_form button[type="submit"]')->form();
        $this->client->submit($form, [
            'fixed_expense[day]' => $day,
            'fixed_expense[amount]' => '-150.00',
            'fixed_expense[contractor]' => (string) $contractor->getId(),
            'contractor_input' => $contractor->getName(),
        ]);

        self::assertResponseIsSuccessful();
        self::assertInputValueSame('fixed_expense[day]', $day);
        self::assertSelectorExists('#fixed_expense_day.is-invalid');
        self::assertSelectorTextContains('#fixed_expense_day + .invalid-feedback.d-block', $message);
        self::assertSame($countBefore, $this->em()->getRepository(FixedExpense::class)->count([]));
    }

    /**
     * @param array<string, string> $amount
     */
    #[DataProvider('blankAmounts')]
    public function testCreateWithBlankAmountShowsFieldErrorWithoutSaving(array $amount): void
    {
        $contractor = new Contractor('Blank amount test '.uniqid());
        $this->em()->persist($contractor);
        $this->em()->flush();
        $expensesBefore = $this->em()->getRepository(FixedExpense::class)->count([]);
        $contractorsBefore = $this->em()->getRepository(Contractor::class)->count([]);
        $crawler = $this->client->request('GET', '/fixed-expense');
        $token = $crawler->filter('#fixed_expense_form input[name="fixed_expense[_token]"]')->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/fixed-expense', [
            'fixed_expense' => [
                '_token' => $token,
                'day' => '10',
                'contractor' => (string) $contractor->getId(),
                'description' => 'Must not be saved',
            ] + $amount,
            'contractor_input' => $contractor->getName(),
        ]);

        $this->assertAmountRequiredError();
        self::assertSame($expensesBefore, $this->em()->getRepository(FixedExpense::class)->count([]));
        self::assertSame($contractorsBefore, $this->em()->getRepository(Contractor::class)->count([]));
    }

    /**
     * @param array<string, string> $amount
     */
    #[DataProvider('blankAmounts')]
    public function testEditWithBlankAmountShowsFieldErrorWithoutChangingExpense(array $amount): void
    {
        $expense = $this->createFixedExpenseInDatabase(15, -12345, 'Original description');
        $id = $expense->getId();
        $contractorId = $expense->getContractor()->getId();
        $otherContractor = new Contractor('Unapplied contractor '.uniqid());
        $this->em()->persist($otherContractor);
        $this->em()->flush();
        $expensesBefore = $this->em()->getRepository(FixedExpense::class)->count([]);
        $contractorsBefore = $this->em()->getRepository(Contractor::class)->count([]);
        $uri = '/fixed-expense/'.$id.'/edit';
        $crawler = $this->client->request('GET', $uri);
        $token = $crawler->filter('#fixed_expense_form input[name="fixed_expense[_token]"]')->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', $uri, [
            'fixed_expense' => [
                '_token' => $token,
                'day' => '20',
                'contractor' => (string) $otherContractor->getId(),
                'description' => 'Must not be saved',
            ] + $amount,
            'contractor_input' => $otherContractor->getName(),
        ]);

        $this->assertAmountRequiredError();
        self::assertInputValueSame('fixed_expense[day]', '20');
        self::assertInputValueSame('fixed_expense[description]', 'Must not be saved');
        $this->em()->clear();
        $unchanged = $this->em()->find(FixedExpense::class, $id);
        self::assertNotNull($unchanged);
        self::assertSame(15, $unchanged->getDay());
        self::assertSame(-12345, $unchanged->getAmount());
        self::assertSame('Original description', $unchanged->getDescription());
        self::assertSame($contractorId, $unchanged->getContractor()->getId());
        self::assertSame($expensesBefore, $this->em()->getRepository(FixedExpense::class)->count([]));
        self::assertSame($contractorsBefore, $this->em()->getRepository(Contractor::class)->count([]));
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function blankAmounts(): array
    {
        return [
            'empty' => [['amount' => '']],
            'spaces' => [['amount' => '   ']],
            'missing' => [[]],
        ];
    }

    public function testCreateWithTooLongDescriptionShowsFieldErrorWithoutSaving(): void
    {
        $contractor = new Contractor('Long description test '.uniqid());
        $this->em()->persist($contractor);
        $this->em()->flush();
        $expensesBefore = $this->em()->getRepository(FixedExpense::class)->count([]);
        $crawler = $this->client->request('GET', '/fixed-expense');
        $token = $crawler->filter('#fixed_expense_form input[name="fixed_expense[_token]"]')->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/fixed-expense', [
            'fixed_expense' => [
                '_token' => $token,
                'day' => '10',
                'contractor' => (string) $contractor->getId(),
                'amount' => '-20.00',
                'description' => str_repeat('a', 256),
            ],
            'contractor_input' => $contractor->getName(),
        ]);

        $this->assertDescriptionTooLongError();
        self::assertSame($expensesBefore, $this->em()->getRepository(FixedExpense::class)->count([]));
    }

    public function testCreateSavesDescriptionOfMaximumLengthAfterTrimming(): void
    {
        $contractor = new Contractor('Long description test '.uniqid());
        $this->em()->persist($contractor);
        $this->em()->flush();
        $description = str_repeat('ą', 255);
        $crawler = $this->client->request('GET', '/fixed-expense');
        $token = $crawler->filter('#fixed_expense_form input[name="fixed_expense[_token]"]')->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/fixed-expense', [
            'fixed_expense' => [
                '_token' => $token,
                'day' => '10',
                'contractor' => (string) $contractor->getId(),
                'amount' => '-20.00',
                'description' => '  '.$description.'  ',
            ],
            'contractor_input' => $contractor->getName(),
        ]);

        self::assertResponseRedirects();
        $saved = $this->em()->getRepository(FixedExpense::class)->findOneBy(['contractor' => $contractor]);
        self::assertNotNull($saved);
        self::assertSame($description, $saved->getDescription());
    }

    public function testEditWithTooLongDescriptionShowsFieldErrorWithoutChangingExpense(): void
    {
        $expense = $this->createFixedExpenseInDatabase(15, -12345, 'Original description');
        $id = $expense->getId();
        $uri = '/fixed-expense/'.$id.'/edit';
        $crawler = $this->client->request('GET', $uri);
        $token = $crawler->filter('#fixed_expense_form input[name="fixed_expense[_token]"]')->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', $uri, [
            'fixed_expense' => [
                '_token' => $token,
                'day' => '20',
                'contractor' => (string) $expense->getContractor()->getId(),
                'amount' => '-20.00',
                'description' => str_repeat('a', 256),
            ],
            'contractor_input' => $expense->getContractor()->getName(),
        ]);

        $this->assertDescriptionTooLongError();
        $this->em()->clear();
        $unchanged = $this->em()->find(FixedExpense::class, $id);
        self::assertNotNull($unchanged);
        self::assertSame(15, $unchanged->getDay());
        self::assertSame(-12345, $unchanged->getAmount());
        self::assertSame('Original description', $unchanged->getDescription());
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

    public function testEditPrefillsExistingValues(): void
    {
        $fixedExpense = $this->createFixedExpenseInDatabase(15, -12345, 'Internet subscription');

        $this->client->request('GET', '/fixed-expense/' . $fixedExpense->getId() . '/edit');

        self::assertResponseIsSuccessful();
        self::assertInputValueSame('fixed_expense[day]', '15');
        self::assertInputValueSame('fixed_expense[amount]', '-123.45');
        self::assertInputValueSame('fixed_expense[description]', 'Internet subscription');
        self::assertInputValueSame('fixed_expense[contractor]', (string) $fixedExpense->getContractor()->getId());
        self::assertInputValueSame('contractor_input', $fixedExpense->getContractor()->getName());
    }

    public function testEditWithoutChangesPreservesExistingValues(): void
    {
        $fixedExpense = $this->createFixedExpenseInDatabase(15, -12345, 'Internet subscription');
        $fixedExpenseId = $fixedExpense->getId();
        $contractorId = $fixedExpense->getContractor()->getId();
        $countBefore = $this->em()->getRepository(FixedExpense::class)->count([]);

        $crawler = $this->client->request('GET', '/fixed-expense/' . $fixedExpenseId . '/edit');
        $form = $crawler->filter('#fixed_expense_form button[type="submit"]')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/fixed-expense');

        $this->em()->clear();
        $updated = $this->em()->find(FixedExpense::class, $fixedExpenseId);

        self::assertNotNull($updated);
        self::assertSame(15, $updated->getDay());
        self::assertSame(-12345, $updated->getAmount());
        self::assertSame('Internet subscription', $updated->getDescription());
        self::assertSame($contractorId, $updated->getContractor()->getId());
        self::assertSame(Currencies::SETTLEMENT_CURRENCY_CODE, $updated->getCurrency()->getCode());
        self::assertSame($countBefore, $this->em()->getRepository(FixedExpense::class)->count([]));
    }

    public function testEditChangingOnlyDayPreservesAmountAndDescription(): void
    {
        $fixedExpense = $this->createFixedExpenseInDatabase(15, -12345, 'Internet subscription');
        $fixedExpenseId = $fixedExpense->getId();

        $crawler = $this->client->request('GET', '/fixed-expense/' . $fixedExpenseId . '/edit');
        $form = $crawler->filter('#fixed_expense_form button[type="submit"]')->form();
        $this->client->submit($form, ['fixed_expense[day]' => '20']);

        self::assertResponseRedirects('/fixed-expense');

        $this->em()->clear();
        $updated = $this->em()->find(FixedExpense::class, $fixedExpenseId);

        self::assertNotNull($updated);
        self::assertSame(20, $updated->getDay());
        self::assertSame(-12345, $updated->getAmount());
        self::assertSame('Internet subscription', $updated->getDescription());
    }

    public function testEditInvalidSubmissionKeepsSubmittedValuesAndDoesNotUpdateExpense(): void
    {
        $fixedExpense = $this->createFixedExpenseInDatabase(15, -12345, 'Internet subscription');
        $fixedExpenseId = $fixedExpense->getId();

        $crawler = $this->client->request('GET', '/fixed-expense/' . $fixedExpenseId . '/edit');
        $form = $crawler->filter('#fixed_expense_form button[type="submit"]')->form();
        $this->client->submit($form, [
            'fixed_expense[day]' => '20',
            'fixed_expense[amount]' => 'invalid',
            'fixed_expense[description]' => 'Updated description',
        ]);

        self::assertResponseIsSuccessful();
        self::assertInputValueSame('fixed_expense[day]', '20');
        self::assertInputValueSame('fixed_expense[amount]', 'invalid');
        self::assertInputValueSame('fixed_expense[description]', 'Updated description');
        self::assertSelectorExists('#fixed_expense_amount.is-invalid');

        $this->em()->clear();
        $unchanged = $this->em()->find(FixedExpense::class, $fixedExpenseId);

        self::assertNotNull($unchanged);
        self::assertSame(15, $unchanged->getDay());
        self::assertSame(-12345, $unchanged->getAmount());
        self::assertSame('Internet subscription', $unchanged->getDescription());
    }

    #[DataProvider('invalidDays')]
    public function testEditWithInvalidDayShowsFieldErrorWithoutChangingExpense(string $day, string $message): void
    {
        $fixedExpense = $this->createFixedExpenseInDatabase(15, -12345, 'Internet subscription');
        $id = $fixedExpense->getId();
        $countBefore = $this->em()->getRepository(FixedExpense::class)->count([]);

        $crawler = $this->client->request('GET', '/fixed-expense/'.$id.'/edit');
        $form = $crawler->filter('#fixed_expense_form button[type="submit"]')->form();
        $this->client->submit($form, [
            'fixed_expense[day]' => $day,
            'fixed_expense[amount]' => '-99.99',
            'fixed_expense[description]' => 'Changed description',
        ]);

        self::assertResponseIsSuccessful();
        self::assertInputValueSame('fixed_expense[day]', $day);
        self::assertSelectorExists('#fixed_expense_day.is-invalid');
        self::assertSelectorTextContains('#fixed_expense_day + .invalid-feedback.d-block', $message);

        $this->em()->clear();
        $unchanged = $this->em()->find(FixedExpense::class, $id);
        self::assertNotNull($unchanged);
        self::assertSame(15, $unchanged->getDay());
        self::assertSame(-12345, $unchanged->getAmount());
        self::assertSame('Internet subscription', $unchanged->getDescription());
        self::assertSame($countBefore, $this->em()->getRepository(FixedExpense::class)->count([]));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidDays(): array
    {
        return [
            'below minimum' => ['0', 'Day must be between 1 and 28.'],
            'above maximum' => ['29', 'Day must be between 1 and 28.'],
            'empty day' => ['', 'Please enter the day of the month.'],
        ];
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

    public function testPlanShowsAFlashMessageWhenTheMainAccountIsInAnotherCurrency(): void
    {
        $this->createFixedExpenseInDatabase();

        $chf = $this->em()->getRepository(Currency::class)->find('CHF');
        self::assertNotNull($chf, 'CHF currency must exist in fixtures.');
        $reference = $this->em()->getRepository(Account::class)->findOneBy([]);
        self::assertNotNull($reference);
        $household = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => true]);
        self::assertNotNull($household);

        $foreignAccount = new Account(
            $reference->getBank(),
            $chf,
            $reference->getType(),
            'CHF-MAIN-' . uniqid(),
            $household
        );
        $this->em()->persist($foreignAccount);
        $this->em()->flush();

        /** @var AccountRepository $accountRepository */
        $accountRepository = self::getContainer()->get(AccountRepository::class);
        $accountRepository->setAsMainAccount($foreignAccount);

        $crawler = $this->client->request('GET', '/fixed-expense');
        $csrfToken = $crawler->filter('form[action*="/fixed-expense/plan"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/fixed-expense/plan', ['_token' => $csrfToken]);

        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Fixed expenses are in PLN, but the main account');
    }

    private function assertDescriptionTooLongError(): void
    {
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#fixed_expense_description.is-invalid');
        self::assertSelectorTextContains(
            '#fixed_expense_description + .invalid-feedback.d-block',
            'The description cannot be longer than 255 characters.',
        );
    }

    private function assertAmountRequiredError(): void
    {
        self::assertResponseStatusCodeSame(200);
        self::assertInputValueSame('fixed_expense[amount]', '');
        self::assertSelectorExists('#fixed_expense_amount.is-invalid');
        self::assertSelectorTextContains(
            '#fixed_expense_amount + .invalid-feedback.d-block',
            'Please enter an amount.',
        );
    }

    private function createFixedExpenseInDatabase(int $day = 10, int $amount = -5000, ?string $description = null): FixedExpense
    {
        $contractor = new Contractor('Test Contractor ' . uniqid());
        $this->em()->persist($contractor);

        $pln = $this->em()->getRepository(Currency::class)->find(Currencies::SETTLEMENT_CURRENCY_CODE);
        self::assertNotNull($pln, 'PLN currency must exist in fixtures.');

        $fixedExpense = new FixedExpense($contractor, $day, $amount, $pln);
        $fixedExpense->setDescription($description);
        $this->em()->persist($fixedExpense);
        $this->em()->flush();

        return $fixedExpense;
    }
}
