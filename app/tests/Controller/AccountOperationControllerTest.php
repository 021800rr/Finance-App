<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Constant\Currencies;
use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\Household;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Uid\Uuid;

final class AccountOperationControllerTest extends WebTestCase
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
        $this->client->request('GET', '/operation/new');

        self::assertResponseIsSuccessful();
    }

    public function testNewFormWithAccountIdRendersSuccessfully(): void
    {
        $account = $this->createAccount();

        $this->client->request('GET', '/operation/new?account=' . $account->getId());

        self::assertResponseIsSuccessful();
    }

    public function testNewSubmittingWithoutContractorShowsError(): void
    {
        $account = $this->createAccount();

        $crawler = $this->client->request('GET', '/operation/new?account=' . $account->getId());
        $form = $crawler->filter('#operation_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'account_operation[amount]' => '100',
            'contractor_input' => '',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#contractor_error.d-block');
    }

    public function testNewSubmittingWithValidContractorCreatesOperationAndRedirects(): void
    {
        $account = $this->createAccount();

        $contractor = new Contractor('Test Contractor ' . uniqid());
        $this->em()->persist($contractor);
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/operation/new?account=' . $account->getId());
        $form = $crawler->filter('#operation_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'account_operation[amount]' => '250',
            'account_operation[contractor]' => (string) $contractor->getId(),
            'contractor_input' => $contractor->getName(),
        ]);

        self::assertResponseRedirects();
        self::assertStringStartsWith(
            '/account/' . $account->getId() . '/operations?highlight=',
            (string) $this->client->getResponse()->headers->get('Location')
        );
    }

    /**
     * @param array<string, string> $amount
     */
    #[DataProvider('blankAmounts')]
    public function testNewWithBlankAmountShowsFieldErrorWithoutSaving(array $amount): void
    {
        $account = $this->createAccount();
        $contractor = new Contractor('Blank amount test '.uniqid());
        $this->em()->persist($contractor);
        $this->em()->flush();
        $countBefore = $this->em()->getRepository(AccountOperation::class)->count([]);
        $crawler = $this->client->request('GET', '/operation/new');
        $token = $crawler->filter('#operation_form input[name="account_operation[_token]"]')->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/operation/new', [
            'account_operation' => [
                '_token' => $token,
                'account' => (string) $account->getId(),
                'contractor' => (string) $contractor->getId(),
                'operationDate' => '2026-01-15',
            ] + $amount,
            'contractor_input' => $contractor->getName(),
        ]);

        self::assertResponseIsSuccessful();
        self::assertInputValueSame('account_operation[amount]', '');
        self::assertSelectorExists('#account_operation_amount.is-invalid');
        self::assertSelectorTextContains(
            '#account_operation_amount + .invalid-feedback.d-block',
            'Please enter an amount.',
        );
        self::assertSame($countBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
    }

    public function testNewRedirectsWhenAccountIdIsInvalid(): void
    {
        $this->client->request('GET', '/operation/new?account=alamakota');

        self::assertResponseRedirects('/');
    }

    public function testNewRedirectsWhenAccountDoesNotExist(): void
    {
        $this->client->request('GET', '/operation/new?account=999999999');

        self::assertResponseRedirects('/');
    }

    public function testNewRedirectsWhenAccountIdExceedsIntegerRange(): void
    {
        $this->client->request('GET', '/operation/new?account=99999999999');

        self::assertResponseRedirects('/');
    }

    public function testEditReturnsNotFoundForNonNumericId(): void
    {
        $this->client->request('GET', '/operation/alamakota/edit');

        self::assertResponseStatusCodeSame(404);
    }

    public function testEditReturnsNotFoundForIdExceedingNineDigits(): void
    {
        $this->client->request('GET', '/operation/9999999999/edit');

        self::assertResponseStatusCodeSame(404);
    }

    public function testEditReturnsNotFoundForNonExistentOperation(): void
    {
        $this->client->request('GET', '/operation/999999999/edit');

        self::assertResponseStatusCodeSame(404);
    }

    public function testEditUpdatesOperationAndRedirects(): void
    {
        $operation = $this->createOperationInDatabase();

        $newContractor = new Contractor('New Contractor ' . uniqid());
        $this->em()->persist($newContractor);
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/operation/' . $operation->getId() . '/edit');
        $form = $crawler->filter('#operation_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'account_operation[amount]' => '99999',
            'account_operation[contractor]' => (string) $newContractor->getId(),
            'contractor_input' => $newContractor->getName(),
        ]);

        self::assertResponseRedirects(
            '/account/'
            . $operation->getAccount()->getId()
            . '/operations?highlight='
            . $operation->getId()
        );

        /** @var EntityManagerInterface $freshEm */
        $freshEm = static::getContainer()->get(EntityManagerInterface::class);
        $updated = $freshEm->find(AccountOperation::class, $operation->getId());

        self::assertNotNull($updated);
        self::assertSame(9999900, $updated->getAmount());
        self::assertSame($newContractor->getId(), $updated->getContractor()->getId());
    }

    public function testEditShowsATranslatedMessageForAZeroAmount(): void
    {
        $operation = $this->createOperationInDatabase();

        $crawler = $this->client->request('GET', '/operation/' . $operation->getId() . '/edit');
        $form = $crawler->filter('#operation_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'account_operation[amount]' => '0',
            'account_operation[contractor]' => (string) $operation->getContractor()->getId(),
            'contractor_input' => $operation->getContractor()->getName(),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'The amount cannot be zero.');
        self::assertSelectorTextNotContains('body', 'common.amount_cannot_be_zero');
    }

    /**
     * @param array<string, string> $amount
     */
    #[DataProvider('blankAmounts')]
    public function testEditWithBlankAmountShowsFieldErrorWithoutChangingOperation(array $amount): void
    {
        $operation = $this->createOperationInDatabase();
        $id = $operation->getId();
        $originalDate = $operation->getOperationDate()->format('Y-m-d');
        $countBefore = $this->em()->getRepository(AccountOperation::class)->count([]);
        $uri = '/operation/'.$id.'/edit';
        $crawler = $this->client->request('GET', $uri);
        $token = $crawler->filter('#operation_form input[name="account_operation[_token]"]')->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', $uri, [
            'account_operation' => [
                '_token' => $token,
                'contractor' => (string) $operation->getContractor()->getId(),
                'operationDate' => '2028-02-29',
                'description' => 'Must not be saved',
                'confirmed' => '1',
            ] + $amount,
            'contractor_input' => $operation->getContractor()->getName(),
        ]);

        self::assertResponseIsSuccessful();
        self::assertInputValueSame('account_operation[amount]', '');
        self::assertSelectorExists('#account_operation_amount.is-invalid');
        self::assertSelectorTextContains(
            '#account_operation_amount + .invalid-feedback.d-block',
            'Please enter an amount.',
        );
        $this->em()->clear();
        $unchanged = $this->em()->find(AccountOperation::class, $id);
        self::assertNotNull($unchanged);
        self::assertSame(10000, $unchanged->getAmount());
        self::assertSame($originalDate, $unchanged->getOperationDate()->format('Y-m-d'));
        self::assertNull($unchanged->getDescription());
        self::assertFalse($unchanged->isConfirmed());
        self::assertSame($countBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
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

    public function testDeleteReturnsNotFoundForNonNumericId(): void
    {
        $this->client->request('POST', '/operation/alamakota/delete');

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteRejectsInvalidCsrfToken(): void
    {
        $operation = $this->createOperationInDatabase();

        $this->client->request('POST', '/operation/' . $operation->getId() . '/delete', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteRemovesOperationWithValidCsrfToken(): void
    {
        $operation = $this->createOperationInDatabase();
        $operationId = $operation->getId();
        $accountId = $operation->getAccount()->getId();

        $crawler = $this->client->request('GET', '/account/' . $accountId . '/operations');
        $csrfToken = $crawler->filter('form[action*="' . $operationId . '/delete"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/operation/' . $operationId . '/delete', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/account/' . $operation->getAccount()->getId() . '/operations');

        /** @var EntityManagerInterface $freshEm */
        $freshEm = static::getContainer()->get(EntityManagerInterface::class);
        $deleted = $freshEm->find(AccountOperation::class, $operationId);

        self::assertNull($deleted);
    }

    public function testToggleConfirmedReturnsNotFoundForNonNumericId(): void
    {
        $this->client->request('POST', '/operation/alamakota/toggle-confirmed');

        self::assertResponseStatusCodeSame(404);
    }

    public function testToggleConfirmedReturnsNotFoundForIdExceedingNineDigits(): void
    {
        $this->client->request('POST', '/operation/9999999999/toggle-confirmed');

        self::assertResponseStatusCodeSame(404);
    }

    public function testToggleConfirmedRejectsInvalidCsrfToken(): void
    {
        $operation = $this->createOperationInDatabase();

        $this->client->request(
            'POST',
            '/operation/' . $operation->getId() . '/toggle-confirmed',
            [],
            [],
            ['HTTP_X-CSRF-Token' => 'invalid-token']
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testToggleConfirmedTogglesStateWithValidCsrfToken(): void
    {
        $operation = $this->createOperationInDatabase();
        self::assertFalse($operation->isConfirmed());

        $crawler = $this->client->request('GET', '/');
        $csrfToken = $crawler->filter('meta[name="csrf-token-operation-toggle-confirmed"]')->attr('content');

        $this->client->request(
            'POST',
            '/operation/' . $operation->getId() . '/toggle-confirmed',
            [],
            [],
            ['HTTP_X-CSRF-Token' => $csrfToken]
        );

        self::assertResponseIsSuccessful();

        /** @var EntityManagerInterface $freshEm */
        $freshEm = static::getContainer()->get(EntityManagerInterface::class);
        $updated = $freshEm->find(AccountOperation::class, $operation->getId());

        self::assertNotNull($updated);
        self::assertTrue($updated->isConfirmed());
    }

    public function testGoToOtherSideReturnsNotFoundForNonNumericId(): void
    {
        $this->client->request('GET', '/operation/alamakota/go-to-other-side');

        self::assertResponseStatusCodeSame(404);
    }

    public function testGoToOtherSideReturnsNotFoundForIdExceedingNineDigits(): void
    {
        $this->client->request('GET', '/operation/9999999999/go-to-other-side');

        self::assertResponseStatusCodeSame(404);
    }

    public function testGoToOtherSideReturnsNotFoundForNonExistentOperation(): void
    {
        $this->client->request('GET', '/operation/999999999/go-to-other-side');

        self::assertResponseStatusCodeSame(404);
    }

    public function testGoToOtherSideShowsFlashWhenNoRelatedOperationExists(): void
    {
        $operation = $this->createOperationInDatabase();

        $this->client->request('GET', '/operation/' . $operation->getId() . '/go-to-other-side');

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Related operation not found.');
    }

    public function testGoToOtherSideRedirectsToRelatedOperationAccount(): void
    {
        $account = $this->createAccount();

        $contractor = new Contractor('Test Contractor ' . uniqid());
        $this->em()->persist($contractor);

        $transactionUuid = Uuid::v7();

        $pln = $this->em()->getRepository(Currency::class)->find(Currencies::SETTLEMENT_CURRENCY_CODE);
        self::assertNotNull($pln, 'PLN currency must exist in fixtures.');

        $opOut = new AccountOperation($account, $contractor, new DateTimeImmutable(), -10000, $pln, transactionUuid: $transactionUuid);
        $this->em()->persist($opOut);

        $opIn = new AccountOperation($account, $contractor, new DateTimeImmutable(), 10000, $pln, transactionUuid: $transactionUuid);
        $this->em()->persist($opIn);

        $this->em()->flush();

        $this->client->request('GET', '/operation/' . $opOut->getId() . '/go-to-other-side');

        self::assertResponseRedirects('/account/' . $account->getId() . '/operations?highlight=' . $opIn->getId());
    }

    private function createAccount(): Account
    {
        $bank = new Bank('Test Bank ' . uniqid());
        $this->em()->persist($bank);

        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->findOneBy(['code' => 'PLN']);
        if ($currency === null) {
            $currency = new Currency('PLN');
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

    private function createOperationInDatabase(): AccountOperation
    {
        $account = $this->createAccount();

        $contractor = new Contractor('Test Contractor ' . uniqid());
        $this->em()->persist($contractor);

        $pln = $this->em()->getRepository(Currency::class)->find(Currencies::SETTLEMENT_CURRENCY_CODE);
        self::assertNotNull($pln, 'PLN currency must exist in fixtures.');

        $operation = new AccountOperation($account, $contractor, new DateTimeImmutable(), 10000, $pln);
        $this->em()->persist($operation);

        $this->em()->flush();

        return $operation;
    }
}
