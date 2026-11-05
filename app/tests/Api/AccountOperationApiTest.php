<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\Contractor;
use App\Service\Paginator;
use App\Tests\DatabaseTestTrait;
use App\Tests\JwtTrait;
use App\Tests\TestDataTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountOperationApiTest extends WebTestCase
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

    public function testQueryParameterFiltersTheCollection(): void
    {
        $marker = uniqid();
        $account = $this->createAccount();
        $this->createOperation($account, description: "Findable {$marker}");
        $this->createOperation($account, description: "Other {$marker}");

        $descriptions = $this->collectionDescriptions('/api/account_operations?q='.rawurlencode("Findable {$marker}"));

        self::assertSame(["Findable {$marker}"], $descriptions);
    }

    public function testQueryParameterTreatsPercentAsLiteral(): void
    {
        $marker = uniqid();
        $account = $this->createAccount();
        $this->createOperation($account, description: "Discount 100% {$marker}");
        $this->createOperation($account, description: "Discount 1000 {$marker}");

        $descriptions = $this->collectionDescriptions('/api/account_operations?q='.rawurlencode('100%'));

        self::assertContains("Discount 100% {$marker}", $descriptions);
        self::assertNotContains("Discount 1000 {$marker}", $descriptions);
    }

    public function testLongNumericQueryFindsMatchingDescription(): void
    {
        $query = str_repeat('9', 33);
        $description = 'Reference '.$query;
        $this->createOperation($this->createAccount(), description: $description);

        $descriptions = $this->collectionDescriptions('/api/account_operations?q='.$query);

        self::assertSame([$description], $descriptions);
    }

    public function testAccountOperationsRouteListsOnlyThatAccountsOperations(): void
    {
        $marker = uniqid();
        $accountA = $this->createAccount();
        $accountB = $this->createAccount();
        $this->createOperation($accountA, description: "In A {$marker}");
        $this->createOperation($accountB, description: "In B {$marker}");

        $descriptions = $this->collectionDescriptions('/api/accounts/'.$accountA->getId().'/operations');

        self::assertSame(["In A {$marker}"], $descriptions);
    }

    public function testAccountOperationsRoutePaginatesWithTotalItemsOfThatAccount(): void
    {
        $account = $this->createAccount();
        $other = $this->createAccount();
        for ($i = 0; $i < Paginator::DEFAULT_PER_PAGE + 2; ++$i) {
            $this->createOperation($account, description: "Paged {$i}");
        }
        $this->createOperation($other, description: 'Not counted');

        $data = $this->collection('/api/accounts/'.$account->getId().'/operations?page=2');

        self::assertSame(Paginator::DEFAULT_PER_PAGE + 2, $data['totalItems']);
        self::assertCount(2, self::rowsAt($data, 'member'));
        self::assertStringEndsWith('/operations?page=1', self::stringAt($data, 'view', 'previous'));
    }

    public function testSearchCollectionDescribesTheQueryParameterInTheHydraSearchTemplate(): void
    {
        $data = $this->collection('/api/account_operations?q=anything');

        self::assertStringEndsWith('{?q}', self::stringAt($data, 'search', 'template'));
    }

    public function testAccountOperationsRouteOfUnknownAccountReturns404(): void
    {
        $this->client->request('GET', '/api/accounts/2147483647/operations', server: $this->authHeaders());

        self::assertResponseStatusCodeSame(404);
    }

    public function testAccountOperationsRouteWithNonNumericAccountIdReturns404(): void
    {
        $this->client->request('GET', '/api/accounts/abc/operations', server: $this->authHeaders());

        self::assertResponseStatusCodeSame(404);
    }

    public function testAccountOperationsRouteWithAccountIdAboveInt4MaxReturns404(): void
    {
        $this->client->request('GET', '/api/accounts/'.PHP_INT_MAX.'/operations', server: $this->authHeaders());

        self::assertResponseStatusCodeSame(404);
    }

    public function testAccountOperationsRouteRequiresAuthentication(): void
    {
        $account = $this->createAccount();

        $this->client->request('GET', '/api/accounts/'.$account->getId().'/operations');

        self::assertResponseStatusCodeSame(401);
    }

    public function testSearchWithoutQueryParameterIsRejected(): void
    {
        $this->client->request('GET', '/api/account_operations', server: $this->authHeaders());

        self::assertResponseStatusCodeSame(422);
    }

    public function testSearchWithBlankQueryParameterIsRejected(): void
    {
        $this->client->request('GET', '/api/account_operations?q=%20%20%20', server: $this->authHeaders());

        self::assertResponseStatusCodeSame(422);
    }

    public function testSearchSpansAllAccounts(): void
    {
        $marker = uniqid();
        $this->createOperation($this->createAccount(), description: "In A {$marker}");
        $this->createOperation($this->createAccount(), description: "In B {$marker}");

        $descriptions = $this->collectionDescriptions('/api/account_operations?q='.rawurlencode($marker));

        self::assertEqualsCanonicalizing(["In A {$marker}", "In B {$marker}"], $descriptions);
    }

    #[DataProvider('likeWildcards')]
    public function testSearchTreatsLikeWildcardsLiterally(string $query, string $matching, string $other): void
    {
        $marker = uniqid();
        $account = $this->createAccount();
        $this->createOperation($account, description: "{$matching} {$marker}");
        $this->createOperation($account, description: "{$other} {$marker}");

        $descriptions = $this->collectionDescriptions('/api/account_operations?q='.rawurlencode($query));

        self::assertContains("{$matching} {$marker}", $descriptions);
        self::assertNotContains("{$other} {$marker}", $descriptions);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function likeWildcards(): iterable
    {
        yield 'percent, also as a one-character search' => ['%', 'Rabat 7%', 'Bez znaku'];
        yield 'underscore' => ['_', 'snake_case', 'snakeXcase'];
        yield 'backslash' => ['\\', 'a\\b', 'ab'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPages(): iterable
    {
        yield 'text' => ['abc'];
        yield 'zero' => ['0'];
        yield 'negative' => ['-1'];
        yield 'fraction' => ['1.5'];
        yield 'empty' => [''];
        yield 'scientific notation' => ['1e3'];
        yield 'leading zero' => ['01'];
        yield 'with a space' => ['1%20'];
    }

    #[DataProvider('invalidPages')]
    public function testInvalidPageIsAViolationOnBothLists(string $page): void
    {
        $account = $this->createAccount();

        $lists = ['/api/accounts/'.$account->getId().'/operations?page=', '/api/account_operations?q=a&page='];
        foreach ($lists as $uri) {
            $this->client->request('GET', $uri.$page, server: $this->authHeaders());

            self::assertResponseStatusCodeSame(422);
            $error = self::decodeObject((string) $this->client->getResponse()->getContent());
            self::assertSame('validation.failed', self::stringAt($error, 'errorCode'));
            self::assertSame(
                ['page' => 'common.page_invalid'],
                array_column(self::rowsAt($error, 'violations'), 'code', 'propertyPath'),
            );
            self::assertSame(
                'page: The page number must be a positive whole number.',
                self::stringAt($error, 'detail'),
            );
        }
    }

    public function testMissingAccountGoesBeforeAnInvalidPage(): void
    {
        $this->client->request('GET', '/api/accounts/2147483647/operations?page=abc', server: $this->authHeaders());

        self::assertResponseStatusCodeSame(404);
        self::assertSame(
            'account.not_found',
            self::stringAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'errorCode'),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validPagesWithoutResults(): iterable
    {
        yield 'beyond the results' => ['999999'];
        yield 'above the cap' => ['9999999999'];
        yield 'above the integer limit' => ['99999999999999999999999'];
    }

    #[DataProvider('validPagesWithoutResults')]
    public function testPageBeyondTheResultsIsAnEmptyList(string $page): void
    {
        $account = $this->createAccount();

        $data = $this->collection('/api/accounts/'.$account->getId().'/operations?page='.$page);

        self::assertSame([], self::rowsAt($data, 'member'));
    }

    public function testOpenApiDocumentsTheAccountOperationsRoute(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');
        self::assertResponseIsSuccessful();

        $docs = self::decodeObject((string) $this->client->getResponse()->getContent());
        $operation = self::objectAt($docs, 'paths', '/api/accounts/{accountId}/operations', 'get');
        $parameters = array_column(self::rowsAt($operation, 'parameters'), 'in', 'name');

        self::assertSame(['accountId' => 'path', 'page' => 'query'], $parameters);
        self::assertArrayHasKey('404', self::objectAt($operation, 'responses'));
    }

    public function testOpenApiDocumentsThatAHiddenAccountIsRejected(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');
        self::assertResponseIsSuccessful();

        $docs = self::decodeObject((string) $this->client->getResponse()->getContent());
        $create = self::objectAt($docs, 'paths', '/api/account_operations', 'post');
        $update = self::objectAt($docs, 'paths', '/api/account_operations/{id}', 'patch');
        $hidden = self::objectAt($docs, 'components', 'schemas', 'Account.jsonMergePatch', 'properties', 'hidden');

        self::assertIsString($create['description'] ?? null);
        self::assertStringContainsString('hidden account cannot be chosen', $create['description']);
        self::assertSame(
            'Validation failed, e.g. accountId points to a hidden account.',
            self::objectAt($create, 'responses', '422')['description'] ?? null,
        );
        self::assertIsString($update['description'] ?? null);
        self::assertStringContainsString('hidden later can still be updated', $update['description']);
        self::assertIsString($hidden['description'] ?? null);
        self::assertStringContainsString('cannot be chosen for a new operation or transfer', $hidden['description']);
    }

    public function testAbsurdPageNumberReturnsAnEmptyPageInsteadOfFailing(): void
    {
        $descriptions = $this->collectionDescriptions('/api/account_operations?q=anything&page='.PHP_INT_MAX);

        self::assertSame([], $descriptions);
    }

    public function testPatchIgnoresAnAccountIdAndKeepsTheOperationOnItsAccount(): void
    {
        $account = $this->createAccount();
        $other = $this->createAccount();
        $operation = $this->createOperation($account, description: 'Stays put '.uniqid());

        $this->client->request(
            'PATCH',
            '/api/account_operations/'.$operation->getId(),
            server: $this->authHeaders() + ['CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['accountId' => $other->getId(), 'description' => 'Edited'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $this->em()->clear();
        $reloaded = $this->em()->find(AccountOperation::class, $operation->getId());
        self::assertNotNull($reloaded);
        self::assertSame($account->getId(), $reloaded->getAccount()->getId());
        self::assertSame('Edited', $reloaded->getDescription());
    }

    public function testPatchWithANewContractorNameAssignsTheOperationToACreatedContractor(): void
    {
        $account = $this->createAccount();
        $operation = $this->createOperation($account, description: 'Keeps its description');
        $newName = 'Brand new contractor '.uniqid();

        $this->client->request(
            'PATCH',
            '/api/account_operations/'.$operation->getId(),
            server: $this->authHeaders() + ['CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['contractorName' => $newName], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $data = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame($newName, self::stringAt($data, 'contractorName'));

        $this->em()->clear();
        $reloaded = $this->em()->find(AccountOperation::class, $operation->getId());
        self::assertSame($newName, $reloaded?->getContractor()->getName());
        self::assertSame('Keeps its description', $reloaded->getDescription());
    }

    public function testPatchWithTheNameOfAnExistingContractorAssignsThatContractor(): void
    {
        $account = $this->createAccount();
        $operation = $this->createOperation($account, description: 'Reassigned');
        $other = $this->createOperation($account, description: 'Owner of the other contractor')->getContractor();

        $this->client->request(
            'PATCH',
            '/api/account_operations/'.$operation->getId(),
            server: $this->authHeaders() + ['CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['contractorName' => $other->getName()], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $this->em()->clear();
        $reloaded = $this->em()->find(AccountOperation::class, $operation->getId());
        self::assertSame($other->getId(), $reloaded?->getContractor()->getId());
    }

    public function testPatchWithANewContractorIdAssignsThatContractor(): void
    {
        $account = $this->createAccount();
        $operation = $this->createOperation($account, description: 'Reassigned by id');
        $other = $this->createOperation($account, description: 'Owner of the other contractor')->getContractor();

        $this->client->request(
            'PATCH',
            '/api/account_operations/'.$operation->getId(),
            server: $this->authHeaders() + ['CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['contractorId' => $other->getId()], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $this->em()->clear();
        $reloaded = $this->em()->find(AccountOperation::class, $operation->getId());
        self::assertSame($other->getId(), $reloaded?->getContractor()->getId());
    }

    public function testMissingRequiredFieldsAreReportedWithTranslatedMessages(): void
    {
        $account = $this->createAccount();

        $this->client->request(
            'POST',
            '/api/account_operations',
            server: $this->authHeaders() + ['CONTENT_TYPE' => 'application/ld+json'],
            content: json_encode(['accountId' => $account->getId(), 'contractorName' => 'Anyone'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        $violations = self::rowsAt(
            self::decodeObject((string) $this->client->getResponse()->getContent()),
            'violations',
        );
        $messages = array_column($violations, 'message', 'propertyPath');
        self::assertSame('Please enter a date.', $messages['operationDate'] ?? null);
        self::assertSame('Please enter an amount.', $messages['amount'] ?? null);
    }

    public function testPostOnHiddenAccountIsRejectedWithoutSaving(): void
    {
        $account = $this->createAccount();
        $account->setHidden(true);
        $this->em()->flush();
        $countBefore = $this->em()->getRepository(AccountOperation::class)->count([]);

        $this->client->request(
            'POST',
            '/api/account_operations',
            server: $this->authHeaders() + ['CONTENT_TYPE' => 'application/ld+json'],
            content: json_encode([
                'accountId' => $account->getId(),
                'contractorName' => 'Anyone',
                'operationDate' => '2026-01-15',
                'amount' => '50.00',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        $violations = self::rowsAt(
            self::decodeObject((string) $this->client->getResponse()->getContent()),
            'violations',
        );
        $messages = array_column($violations, 'message', 'propertyPath');
        self::assertSame(
            'This account is hidden. Please select an account that is not hidden.',
            $messages['accountId'] ?? null,
        );
        self::assertSame($countBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
    }

    public function testPatchOfOperationOnHiddenAccountIsStillAllowed(): void
    {
        $account = $this->createAccount();
        $operation = $this->createOperation($account, description: 'Before '.uniqid());
        $account->setHidden(true);
        $this->em()->flush();

        $this->client->request(
            'PATCH',
            '/api/account_operations/'.$operation->getId(),
            server: $this->authHeaders() + ['CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['description' => 'After'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $this->em()->clear();
        self::assertSame('After', $this->em()->find(AccountOperation::class, $operation->getId())?->getDescription());
    }

    #[DataProvider('descriptionWrites')]
    public function testDescriptionLongerThan255CharactersIsRejectedWithoutSaving(bool $patch): void
    {
        $account = $this->createAccount();
        $operation = $patch ? $this->createOperation($account, description: 'Original') : null;
        $before = $this->em()->getConnection()->fetchAllAssociative('SELECT * FROM account_operation ORDER BY id');

        $this->writeOperation($account, $operation, ['description' => str_repeat('a', 256)]);

        self::assertResponseStatusCodeSame(422);
        $violations = self::rowsAt(
            self::decodeObject((string) $this->client->getResponse()->getContent()),
            'violations',
        );
        $messages = array_column($violations, 'message', 'propertyPath');
        self::assertSame('The description cannot be longer than 255 characters.', $messages['description'] ?? null);
        self::assertSame(
            $before,
            $this->em()->getConnection()->fetchAllAssociative('SELECT * FROM account_operation ORDER BY id'),
        );
    }

    #[DataProvider('storedDescriptions')]
    public function testDescriptionIsStoredTrimmedAndBlankAsNull(bool $patch, string $sent, ?string $stored): void
    {
        $account = $this->createAccount();
        $operation = $patch ? $this->createOperation($account, description: 'Original') : null;

        $this->writeOperation($account, $operation, ['description' => $sent]);

        self::assertResponseStatusCodeSame($patch ? 200 : 201);
        $id = self::intAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'id');
        $this->em()->clear();
        self::assertSame($stored, $this->em()->find(AccountOperation::class, $id)?->getDescription());
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function descriptionWrites(): iterable
    {
        yield 'POST' => [false];
        yield 'PATCH' => [true];
    }

    /**
     * @return iterable<string, array{bool, string, ?string}>
     */
    public static function storedDescriptions(): iterable
    {
        foreach (['POST' => false, 'PATCH' => true] as $method => $patch) {
            yield $method.' 255 characters with surrounding spaces' => [
                $patch, '  '.str_repeat('ą', 255).'  ', str_repeat('ą', 255),
            ];
            yield $method.' only spaces' => [$patch, '   ', null];
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function writeOperation(Account $account, ?AccountOperation $operation, array $body): void
    {
        if (null !== $operation) {
            $this->client->request(
                'PATCH',
                '/api/account_operations/'.$operation->getId(),
                server: $this->authHeaders() + ['CONTENT_TYPE' => 'application/merge-patch+json'],
                content: json_encode($body, JSON_THROW_ON_ERROR),
            );

            return;
        }

        $this->client->request(
            'POST',
            '/api/account_operations',
            server: $this->authHeaders() + ['CONTENT_TYPE' => 'application/ld+json'],
            content: json_encode($body + [
                'accountId' => $account->getId(),
                'contractorName' => 'Anyone',
                'operationDate' => '2026-01-15',
                'amount' => '50.00',
            ], JSON_THROW_ON_ERROR),
        );
    }

    public function testOpenApiOffersTheAccountIdOnCreateButNotOnUpdate(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');
        self::assertResponseIsSuccessful();
        $docs = self::decodeObject((string) $this->client->getResponse()->getContent());

        $createProperties = $this->requestSchemaProperties($docs, '/api/account_operations', 'post');
        $updateProperties = $this->requestSchemaProperties($docs, '/api/account_operations/{id}', 'patch');

        self::assertArrayHasKey('accountId', $createProperties);
        self::assertArrayNotHasKey('accountId', $updateProperties);
        self::assertArrayHasKey('amount', $updateProperties);
    }

    public function testPatchWithTheSameAccountIdUpdatesTheOperation(): void
    {
        $account = $this->createAccount();
        $operation = $this->createOperation($account, description: 'Before '.uniqid());

        $this->client->request(
            'PATCH',
            '/api/account_operations/'.$operation->getId(),
            server: $this->authHeaders() + ['CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['accountId' => $account->getId(), 'description' => 'After'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $this->em()->clear();
        $reloaded = $this->em()->find(AccountOperation::class, $operation->getId());
        self::assertSame('After', $reloaded?->getDescription());
        self::assertSame(1000, $reloaded->getAmount());
    }

    /**
     * @param array<string, string|null> $amount
     */
    #[DataProvider('blankPostAmounts')]
    public function testPostWithBlankAmountShowsFieldErrorWithoutSaving(array $amount): void
    {
        $account = $this->createAccount();
        $operationsBefore = $this->em()->getRepository(AccountOperation::class)->count([]);
        $contractorsBefore = $this->em()->getRepository(Contractor::class)->count([]);

        $this->client->request('POST', '/api/account_operations', server: $this->authHeaders() + [
            'CONTENT_TYPE' => 'application/ld+json',
        ], content: json_encode([
            'accountId' => $account->getId(),
            'contractorName' => 'Rejected contractor '.uniqid(),
            'operationDate' => '2026-01-15',
        ] + $amount, JSON_THROW_ON_ERROR));

        $this->assertAmountRequiredResponse();
        self::assertSame($operationsBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
        self::assertSame($contractorsBefore, $this->em()->getRepository(Contractor::class)->count([]));
    }

    /**
     * @param array<string, string|null> $amount
     */
    #[DataProvider('blankAmounts')]
    public function testPatchWithBlankAmountShowsFieldErrorWithoutChangingOperation(array $amount): void
    {
        $operation = $this->createOperation($this->createAccount(), description: 'Original');
        $id = $operation->getId();
        $originalDate = $operation->getOperationDate()->format('Y-m-d');
        $contractorId = $operation->getContractor()->getId();
        $contractorsBefore = $this->em()->getRepository(Contractor::class)->count([]);

        $this->client->request('PATCH', '/api/account_operations/'.$id, server: $this->authHeaders() + [
            'CONTENT_TYPE' => 'application/merge-patch+json',
        ], content: json_encode([
            'operationDate' => '2028-02-29',
            'description' => 'Must not be saved',
            'confirmed' => true,
            'contractorName' => 'Rejected contractor '.uniqid(),
        ] + $amount, JSON_THROW_ON_ERROR));

        $this->assertAmountRequiredResponse();
        $this->em()->clear();
        $unchanged = $this->em()->find(AccountOperation::class, $id);
        self::assertNotNull($unchanged);
        self::assertSame(1000, $unchanged->getAmount());
        self::assertSame($originalDate, $unchanged->getOperationDate()->format('Y-m-d'));
        self::assertSame('Original', $unchanged->getDescription());
        self::assertFalse($unchanged->isConfirmed());
        self::assertSame($contractorId, $unchanged->getContractor()->getId());
        self::assertSame($contractorsBefore, $this->em()->getRepository(Contractor::class)->count([]));
    }

    #[DataProvider('validAmounts')]
    public function testPostWithValidAmountCreatesOperation(string $amount, int $minorUnits): void
    {
        $account = $this->createAccount();
        $this->client->request('POST', '/api/account_operations', server: $this->authHeaders() + [
            'CONTENT_TYPE' => 'application/ld+json',
        ], content: json_encode([
            'accountId' => $account->getId(),
            'contractorName' => 'Valid amount contractor '.uniqid(),
            'operationDate' => '2026-01-15',
            'amount' => $amount,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(201);
        $data = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame($amount, self::stringAt($data, 'amount'));
        $this->em()->clear();
        $stored = $this->em()->find(AccountOperation::class, self::intAt($data, 'id'));
        self::assertNotNull($stored);
        self::assertSame($minorUnits, $stored->getAmount());
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function validAmounts(): array
    {
        return ['income' => ['150.50', 15050], 'expense' => ['-150.50', -15050]];
    }

    /**
     * @return iterable<string, array{array<string, string|null>}>
     */
    public static function blankPostAmounts(): iterable
    {
        yield from self::blankAmounts();
        yield 'missing' => [[]];
    }

    /**
     * @return iterable<string, array{array<string, string|null>}>
     */
    public static function blankAmounts(): iterable
    {
        yield 'empty' => [['amount' => '']];
        yield 'spaces' => [['amount' => '   ']];
        yield 'null' => [['amount' => null]];
    }

    private function assertAmountRequiredResponse(): void
    {
        self::assertResponseStatusCodeSame(422);
        $error = self::decodeObject((string) $this->client->getResponse()->getContent());
        $violations = self::rowsAt($error, 'violations');
        self::assertCount(1, $violations);
        self::assertSame('amount', self::stringAt($violations[0], 'propertyPath'));
        self::assertSame('Please enter an amount.', self::stringAt($violations[0], 'message'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAmounts(): iterable
    {
        yield 'zero' => ['0'];
        yield 'not a number' => ['abc'];
        yield 'above the upper bound' => ['1000000.00'];
        yield 'far above the upper bound' => ['9999999999'];
        yield '17 digits' => [str_repeat('9', 17)];
        yield '33 digits' => [str_repeat('9', 33)];
        yield 'negative 33 digits' => ['-'.str_repeat('9', 33)];
    }

    #[DataProvider('invalidAmounts')]
    public function testPostWithAnInvalidAmountIsUnprocessable(string $amount): void
    {
        $account = $this->createAccount();

        $this->client->request(
            'POST',
            '/api/account_operations',
            server: $this->authHeaders() + ['CONTENT_TYPE' => 'application/ld+json'],
            content: json_encode([
                'accountId' => $account->getId(),
                'contractorName' => 'Some Contractor',
                'operationDate' => '2026-01-15',
                'amount' => $amount,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
    }

    #[DataProvider('invalidAmounts')]
    public function testPatchWithAnInvalidAmountIsUnprocessable(string $amount): void
    {
        $operation = $this->createOperation($this->createAccount(), description: 'Keeps its amount');

        $this->client->request(
            'PATCH',
            '/api/account_operations/'.$operation->getId(),
            server: $this->authHeaders() + ['CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['amount' => $amount], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        $this->em()->clear();
        $reloaded = $this->em()->find(AccountOperation::class, $operation->getId());
        self::assertSame(1000, $reloaded?->getAmount());
    }

    /**
     * @param array<string, mixed> $docs
     *
     * @return array<string, mixed>
     */
    private function requestSchemaProperties(array $docs, string $path, string $method): array
    {
        $content = self::objectAt($docs, 'paths', $path, $method, 'requestBody', 'content');
        $media = array_values($content)[0] ?? null;
        self::assertIsArray($media);
        $ref = self::stringAt(self::stringKeyed($media), 'schema', '$ref');
        $schema = self::objectAt($docs, 'components', 'schemas', basename($ref));

        return self::objectAt($schema, 'properties');
    }

    /**
     * @return array<string, mixed>
     */
    private function collection(string $uri): array
    {
        $this->client->request('GET', $uri, server: $this->authHeaders() + ['HTTP_ACCEPT' => 'application/ld+json']);
        self::assertResponseIsSuccessful();

        return self::decodeObject((string) $this->client->getResponse()->getContent());
    }

    /**
     * @return list<string>
     */
    private function collectionDescriptions(string $uri): array
    {
        $descriptions = [];
        foreach (self::rowsAt($this->collection($uri), 'member') as $row) {
            if (is_string($row['description'] ?? null)) {
                $descriptions[] = $row['description'];
            }
        }

        return $descriptions;
    }
}
