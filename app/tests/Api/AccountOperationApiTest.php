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
use App\Tests\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountOperationApiTest extends WebTestCase
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

    public function testQueryParameterFiltersTheCollection(): void
    {
        $marker = uniqid();
        $account = $this->createAccount();
        $this->createOperation($account, "Findable {$marker}");
        $this->createOperation($account, "Other {$marker}");

        $descriptions = $this->collectionDescriptions('/api/account_operations?q='.rawurlencode("Findable {$marker}"));

        self::assertSame(["Findable {$marker}"], $descriptions);
    }

    public function testQueryParameterTreatsPercentAsLiteral(): void
    {
        $marker = uniqid();
        $account = $this->createAccount();
        $this->createOperation($account, "Discount 100% {$marker}");
        $this->createOperation($account, "Discount 1000 {$marker}");

        $descriptions = $this->collectionDescriptions('/api/account_operations?q='.rawurlencode('100%'));

        self::assertContains("Discount 100% {$marker}", $descriptions);
        self::assertNotContains("Discount 1000 {$marker}", $descriptions);
    }

    public function testLongNumericQueryFindsMatchingDescription(): void
    {
        $query = str_repeat('9', 33);
        $description = 'Reference '.$query;
        $this->createOperation($this->createAccount(), $description);

        $descriptions = $this->collectionDescriptions('/api/account_operations?q='.$query);

        self::assertSame([$description], $descriptions);
    }

    public function testAccountOperationsRouteListsOnlyThatAccountsOperations(): void
    {
        $marker = uniqid();
        $accountA = $this->createAccount();
        $accountB = $this->createAccount();
        $this->createOperation($accountA, "In A {$marker}");
        $this->createOperation($accountB, "In B {$marker}");

        $descriptions = $this->collectionDescriptions('/api/accounts/'.$accountA->getId().'/operations');

        self::assertSame(["In A {$marker}"], $descriptions);
    }

    public function testAccountOperationsRoutePaginatesWithTotalItemsOfThatAccount(): void
    {
        $account = $this->createAccount();
        $other = $this->createAccount();
        for ($i = 0; $i < Paginator::DEFAULT_PER_PAGE + 2; ++$i) {
            $this->createOperation($account, "Paged {$i}");
        }
        $this->createOperation($other, 'Not counted');

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
        $this->createOperation($this->createAccount(), "In A {$marker}");
        $this->createOperation($this->createAccount(), "In B {$marker}");

        $descriptions = $this->collectionDescriptions('/api/account_operations?q='.rawurlencode($marker));

        self::assertEqualsCanonicalizing(["In A {$marker}", "In B {$marker}"], $descriptions);
    }

    public function testQueryParameterShorterThanTwoCharactersIsRejected(): void
    {
        $this->client->request('GET', '/api/account_operations?q=a', server: $this->authHeaders());

        self::assertResponseStatusCodeSame(422);
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
        $operation = $this->createOperation($account, 'Stays put '.uniqid());

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
        $operation = $this->createOperation($account, 'Keeps its description');
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
        $operation = $this->createOperation($account, 'Reassigned');
        $other = $this->createOperation($account, 'Owner of the other contractor')->getContractor();

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
        $operation = $this->createOperation($account, 'Reassigned by id');
        $other = $this->createOperation($account, 'Owner of the other contractor')->getContractor();

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
        $violations = self::rowsAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'violations');
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
        $violations = self::rowsAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'violations');
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
        $operation = $this->createOperation($account, 'Before '.uniqid());
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
        $operation = $patch ? $this->createOperation($account, 'Original') : null;
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
        $operation = $patch ? $this->createOperation($account, 'Original') : null;

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
        $operation = $this->createOperation($account, 'Before '.uniqid());

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
        $operation = $this->createOperation($this->createAccount(), 'Original');
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
        $operation = $this->createOperation($this->createAccount(), 'Keeps its amount');

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

        $data = self::decodeObject((string) $this->client->getResponse()->getContent());
        $data['member'] ??= $data['hydra:member'] ?? null;
        $data['view'] ??= $data['hydra:view'] ?? null;

        return $data;
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

        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data) || !is_string($data['token'] ?? null)) {
            throw new \RuntimeException('Could not obtain a JWT token in the test.');
        }

        return $data['token'];
    }

    private function createAccount(): Account
    {
        $bank = new Bank('Test Bank '.uniqid());
        $this->em()->persist($bank);

        $currency = $this->em()->getRepository(Currency::class)->find('PLN');
        if (null === $currency) {
            $currency = new Currency('PLN');
            $this->em()->persist($currency);
        }

        $accountType = new AccountType('Test Type '.uniqid());
        $this->em()->persist($accountType);

        $household = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => true]);
        if (null === $household) {
            throw new \RuntimeException('Fixtures must provide exactly one own-funds household.');
        }

        $account = new Account($bank, $currency, $accountType, 'ACC-'.uniqid(), $household);
        $this->em()->persist($account);
        $this->em()->flush();

        return $account;
    }

    private function createOperation(Account $account, string $description): AccountOperation
    {
        $contractor = new Contractor('Test Contractor '.uniqid());
        $this->em()->persist($contractor);

        $operation = new AccountOperation(
            $account,
            $contractor,
            new \DateTimeImmutable(),
            1000,
            $account->getCurrency(),
            description: $description
        );
        $this->em()->persist($operation);
        $this->em()->flush();

        return $operation;
    }
}
