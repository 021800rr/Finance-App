<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Constant\Contractors;
use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\Household;
use App\Tests\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TransferControllerTest extends WebTestCase
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

    public function testNewFormRendersSuccessfully(): void
    {
        $this->client->request('GET', '/transfer/new');

        self::assertResponseIsSuccessful();
    }

    public function testNewSubmittingSameCurrencyTransferCreatesOperationsAndRedirects(): void
    {
        $fromAccount = $this->createAccount('PLN');
        $toAccount = $this->createAccount('PLN');
        $repository = $this->em()->getRepository(AccountOperation::class);
        $countBefore = $repository->count([]);

        $crawler = $this->client->request('GET', '/transfer/new');
        $form = $crawler->filter('#transfer_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'transfer[fromAccount]' => (string) $fromAccount->getId(),
            'transfer[toAccount]' => (string) $toAccount->getId(),
            'transfer[amountFrom]' => '100',
        ]);

        self::assertResponseRedirects();
        self::assertStringStartsWith(
            '/account/' . $fromAccount->getId() . '/operations',
            (string) $this->client->getResponse()->headers->get('Location')
        );
        self::assertSame($countBefore + 2, $repository->count([]));
        $out = $repository->findOneBy(['account' => $fromAccount]);
        $in = $repository->findOneBy(['account' => $toAccount]);
        self::assertNotNull($out);
        self::assertNotNull($in);
        self::assertSame(-10000, $out->getAmount());
        self::assertSame(10000, $in->getAmount());
        self::assertNotNull($out->getTransactionUuid());
        self::assertSame($out->getTransactionUuid()->toRfc4122(), $in->getTransactionUuid()?->toRfc4122());
        self::assertSame(Contractors::SELF_TRANSFER_NAME, $out->getContractor()->getName());
        self::assertSame(Contractors::SELF_TRANSFER_NAME, $in->getContractor()->getName());
    }

    public function testNewFormDoesNotOfferContractor(): void
    {
        $this->client->request('GET', '/transfer/new');

        self::assertSelectorNotExists('[name="transfer[contractor]"]');
    }

    public function testSubmittedContractorIsRejectedWithoutCreatingOperations(): void
    {
        $other = new Contractor('Other contractor '.uniqid());
        $this->em()->persist($other);
        $this->em()->flush();
        $countBefore = $this->em()->getRepository(AccountOperation::class)->count([]);
        $crawler = $this->client->request('GET', '/transfer/new');
        $token = $crawler->filter('#transfer_form input[name="transfer[_token]"]')->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/transfer/new', ['transfer' => [
            '_token' => $token,
            'fromAccount' => (string) $this->createAccount('PLN')->getId(),
            'toAccount' => (string) $this->createAccount('PLN')->getId(),
            'contractor' => (string) $other->getId(),
            'amountFrom' => '100',
            'date' => '2026-01-15',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#transfer_form .alert-danger');
        self::assertSame($countBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
    }

    public function testTooLongDescriptionShowsFieldErrorWithoutCreatingOperations(): void
    {
        $fromAccount = $this->createAccount('PLN');
        $toAccount = $this->createAccount('PLN');
        $countBefore = $this->em()->getRepository(AccountOperation::class)->count([]);
        $crawler = $this->client->request('GET', '/transfer/new');
        $form = $crawler->filter('#transfer_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'transfer[fromAccount]' => (string) $fromAccount->getId(),
            'transfer[toAccount]' => (string) $toAccount->getId(),
            'transfer[amountFrom]' => '100',
            'transfer[description]' => str_repeat('a', 256),
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#transfer_description.is-invalid');
        self::assertSelectorTextContains(
            '#transfer_description + .invalid-feedback.d-block',
            'The description cannot be longer than 255 characters.',
        );
        self::assertSame($countBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
    }

    public function testDescriptionOfMaximumLengthIsSavedAfterTrimming(): void
    {
        $fromAccount = $this->createAccount('PLN');
        $toAccount = $this->createAccount('PLN');
        $description = str_repeat('ą', 255);
        $crawler = $this->client->request('GET', '/transfer/new');
        $form = $crawler->filter('#transfer_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'transfer[fromAccount]' => (string) $fromAccount->getId(),
            'transfer[toAccount]' => (string) $toAccount->getId(),
            'transfer[amountFrom]' => '100',
            'transfer[description]' => '  '.$description.'  ',
        ]);

        self::assertResponseRedirects();
        $out = $this->em()->getRepository(AccountOperation::class)->findOneBy(['account' => $fromAccount]);
        self::assertNotNull($out);
        self::assertSame($description, $out->getDescription());
    }

    public function testNewFormOffersOnlyVisibleAccounts(): void
    {
        $visible = $this->createAccount('PLN');
        $hidden = $this->createHiddenAccount();

        $this->client->request('GET', '/transfer/new');

        self::assertResponseIsSuccessful();
        foreach (['fromAccount', 'toAccount'] as $field) {
            self::assertSelectorExists('#transfer_'.$field.' option[value="'.$visible->getId().'"]');
            self::assertSelectorNotExists('#transfer_'.$field.' option[value="'.$hidden->getId().'"]');
        }
    }

    #[DataProvider('unavailableAccountIds')]
    public function testUnavailableAccountShowsFieldErrorWithoutCreatingOperations(
        string $field,
        ?string $accountId,
    ): void {
        $accountId ??= (string) $this->createHiddenAccount()->getId();
        $data = [
            'fromAccount' => (string) $this->createAccount('PLN')->getId(),
            'toAccount' => (string) $this->createAccount('PLN')->getId(),
        ];
        $data[$field] = $accountId;
        $countBefore = $this->em()->getRepository(AccountOperation::class)->count([]);
        $crawler = $this->client->request('GET', '/transfer/new');
        $token = $crawler->filter('#transfer_form input[name="transfer[_token]"]')->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/transfer/new', ['transfer' => $data + [
            '_token' => $token,
            'amountFrom' => '100',
            'date' => '2026-01-15',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#transfer_'.$field.'.is-invalid');
        self::assertSelectorExists('#transfer_'.$field.' + .invalid-feedback.d-block');
        self::assertSame($countBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
    }

    /**
     * Null stands for a hidden account, created inside the test.
     *
     * @return array<string, array{string, ?string}>
     */
    public static function unavailableAccountIds(): array
    {
        $cases = [];
        foreach (['fromAccount', 'toAccount'] as $field) {
            $cases[$field.' hidden'] = [$field, null];
            $cases[$field.' above SQL integer'] = [$field, '2147483648'];
            $cases[$field.' huge'] = [$field, str_repeat('9', 33)];
        }

        return $cases;
    }

    #[DataProvider('blankAccounts')]
    public function testBlankAccountShowsFieldErrorWithoutCreatingOperations(
        string $field,
        ?string $value,
        string $message,
    ): void {
        $countBefore = $this->em()->getRepository(AccountOperation::class)->count([]);
        $crawler = $this->client->request('GET', '/transfer/new');
        $token = $crawler->filter('#transfer_form input[name="transfer[_token]"]')->attr('value');
        self::assertIsString($token);
        $data = [
            '_token' => $token,
            'fromAccount' => (string) $this->createAccount('PLN')->getId(),
            'toAccount' => (string) $this->createAccount('PLN')->getId(),
            'amountFrom' => '100',
            'date' => '2026-01-15',
        ];
        if (null === $value) {
            unset($data[$field]);
        } else {
            $data[$field] = $value;
        }

        $this->client->request('POST', '/transfer/new', ['transfer' => $data]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#transfer_'.$field.'.is-invalid');
        self::assertSelectorTextContains('#transfer_'.$field.' + .invalid-feedback.d-block', $message);
        self::assertSame($countBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
    }

    /**
     * @return array<string, array{string, ?string, string}>
     */
    public static function blankAccounts(): array
    {
        return [
            'fromAccount empty' => ['fromAccount', '', 'Please select the source account.'],
            'fromAccount missing' => ['fromAccount', null, 'Please select the source account.'],
            'toAccount empty' => ['toAccount', '', 'Please select the destination account.'],
            'toAccount missing' => ['toAccount', null, 'Please select the destination account.'],
        ];
    }

    public function testSameAccountReturns422WithFieldErrorWithoutCreatingOperations(): void
    {
        $account = $this->createAccount('PLN');
        $countBefore = $this->em()->getRepository(AccountOperation::class)->count([]);
        $crawler = $this->client->request('GET', '/transfer/new');
        $form = $crawler->filter('#transfer_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'transfer[fromAccount]' => (string) $account->getId(),
            'transfer[toAccount]' => (string) $account->getId(),
            'transfer[amountFrom]' => '100',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#transfer_toAccount.is-invalid');
        self::assertSelectorTextContains(
            '#transfer_toAccount + .invalid-feedback.d-block',
            'Choose a different destination account.',
        );
        self::assertSelectorExists('#transfer_fromAccount option[value="'.$account->getId().'"][selected]');
        self::assertSelectorExists('#transfer_toAccount option[value="'.$account->getId().'"][selected]');
        self::assertInputValueSame('transfer[amountFrom]', '100.00');
        self::assertSame($countBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
    }

    public function testNewSubmittingDifferentCurrencyWithoutAmountToShowsError(): void
    {
        $fromAccount = $this->createAccount('PLN');
        $toAccount = $this->createAccount('CHF');

        $crawler = $this->client->request('GET', '/transfer/new');
        $form = $crawler->filter('#transfer_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'transfer[fromAccount]' => (string) $fromAccount->getId(),
            'transfer[toAccount]' => (string) $toAccount->getId(),
            'transfer[amountFrom]' => '100',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#transfer_amountTo_error1');
    }

    #[DataProvider('provideNonPositiveAmounts')]
    public function testNonPositiveAmountsShowFieldErrorWithoutCreatingOperations(
        string $field,
        string $amountFrom,
        string $amountTo,
        string $toCurrency,
    ): void {
        $fromAccount = $this->createAccount('PLN');
        $toAccount = $this->createAccount($toCurrency);
        $countBefore = $this->em()->getRepository(AccountOperation::class)->count([]);
        $crawler = $this->client->request('GET', '/transfer/new');
        $form = $crawler->filter('#transfer_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'transfer[fromAccount]' => (string) $fromAccount->getId(),
            'transfer[toAccount]' => (string) $toAccount->getId(),
            'transfer[amountFrom]' => $amountFrom,
            'transfer[amountTo]' => $amountTo,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#transfer_'.$field.'_error1', 'The transfer amount must be greater than zero.');
        self::assertSame($countBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
    }

    #[DataProvider('ignoredAmountsTo')]
    public function testSameCurrencyIgnoresTheSecondAmount(string $amountTo): void
    {
        $fromAccount = $this->createAccount('PLN');
        $toAccount = $this->createAccount('PLN');
        $crawler = $this->client->request('GET', '/transfer/new');
        $form = $crawler->filter('#transfer_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'transfer[fromAccount]' => (string) $fromAccount->getId(),
            'transfer[toAccount]' => (string) $toAccount->getId(),
            'transfer[amountFrom]' => '100.00',
            'transfer[amountTo]' => $amountTo,
        ]);

        self::assertResponseRedirects();
        $operations = $this->em()->getRepository(AccountOperation::class);
        self::assertSame(-10000, $operations->findOneBy(['account' => $fromAccount])?->getAmount());
        self::assertSame(10000, $operations->findOneBy(['account' => $toAccount])?->getAmount());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ignoredAmountsTo(): iterable
    {
        yield 'different amount' => ['25'];
        yield 'negative' => ['-25'];
        yield 'not a number' => ['abc'];
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function provideNonPositiveAmounts(): array
    {
        return [
            'negative debit' => ['amountFrom', '-100', '', 'PLN'],
            'zero debit' => ['amountFrom', '0', '', 'PLN'],
            'debit rounded to zero' => ['amountFrom', '0.004', '', 'PLN'],
            'negative credit in exchange' => ['amountTo', '100', '-25', 'CHF'],
            'negative debit in exchange' => ['amountFrom', '-100', '25', 'CHF'],
            'negative debit and credit in exchange (message at from field)' => ['amountFrom', '-100', '-25', 'CHF'],
            'negative debit and credit in exchange (message at to field)' => ['amountTo', '-100', '-25', 'CHF'],

            'zero credit' => ['amountTo', '100', '0', 'CHF'],
            'zero debit in exchange' => ['amountFrom', '0', '25', 'CHF'],
            'credit rounded to zero' => ['amountTo', '100', '0.004', 'CHF'],
            'debit rounded to zero in exchange' => ['amountFrom', '0.004', '0.01', 'CHF'],
        ];
    }

    #[DataProvider('excessiveAmounts')]
    public function testExcessiveAmountShowsFieldErrorWithoutCreatingOperations(string $field, string $amount): void
    {
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('CHF');
        $countBefore = $this->em()->getRepository(AccountOperation::class)->count([]);
        $crawler = $this->client->request('GET', '/transfer/new');
        $form = $crawler->filter('#transfer_form button[type="submit"]')->form();
        $data = [
            'transfer[fromAccount]' => (string) $from->getId(),
            'transfer[toAccount]' => (string) $to->getId(),
            'transfer[amountFrom]' => '100.00',
            'transfer[amountTo]' => '25.00',
        ];
        $data['transfer['.$field.']'] = $amount;

        $this->client->submit($form, $data);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#transfer_'.$field.'_error1');
        self::assertSame($countBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function excessiveAmounts(): iterable
    {
        foreach (['amountFrom', 'amountTo'] as $field) {
            yield $field.' above maximum' => [$field, '1000000.00'];
            yield $field.' 17 digits' => [$field, str_repeat('9', 17)];
            yield $field.' 33 digits' => [$field, str_repeat('9', 33)];
        }
    }

    public function testMaximumTransferAmountsAreAccepted(): void
    {
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('CHF');
        $crawler = $this->client->request('GET', '/transfer/new');
        $form = $crawler->filter('#transfer_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'transfer[fromAccount]' => (string) $from->getId(),
            'transfer[toAccount]' => (string) $to->getId(),
            'transfer[amountFrom]' => '999999.99',
            'transfer[amountTo]' => '999999.99',
        ]);

        self::assertResponseRedirects();
        $repository = $this->em()->getRepository(AccountOperation::class);
        self::assertSame(-99999999, $repository->findOneBy(['account' => $from])?->getAmount());
        self::assertSame(99999999, $repository->findOneBy(['account' => $to])?->getAmount());
    }

    private function createHiddenAccount(): Account
    {
        $account = $this->createAccount('PLN');
        $account->setHidden(true);
        $this->em()->flush();

        return $account;
    }

    private function createAccount(string $currencyCode): Account
    {
        $bank = new Bank('Test Bank ' . uniqid());
        $this->em()->persist($bank);

        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->find($currencyCode);
        if ($currency === null) {
            $currency = new Currency($currencyCode);
            $this->em()->persist($currency);
        }

        $accountType = new AccountType('Test Type ' . uniqid());
        $this->em()->persist($accountType);

        $household = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => true]);
        if (null === $household) {
            throw new \RuntimeException('Fixtures must provide exactly one own-funds household.');
        }

        $account = new Account($bank, $currency, $accountType, 'ACC-' . uniqid(), $household);
        $this->em()->persist($account);
        $this->em()->flush();

        return $account;
    }
}
