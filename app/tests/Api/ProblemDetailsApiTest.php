<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Account;
use App\Entity\Contractor;
use App\Exception\BusinessError;
use App\OpenApi\ProblemDetailsDecorator;
use App\Tests\DatabaseTestTrait;
use App\Tests\LosesMainAccountRaceTrait;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Query;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Every API error is RFC 9457 Problem Details with a stable "errorCode", its "params" and an English "detail";
 * "type" is derived from the code.
 */
final class ProblemDetailsApiTest extends WebTestCase
{
    use DatabaseTestTrait;
    use LosesMainAccountRaceTrait;
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

    public function testValidationErrorHasTheValidationFailedCode(): void
    {
        $this->postTransfer('{"amountFrom":"0"}');

        $problem = $this->assertProblem(422, 'validation.failed', []);
        self::assertSame('Unprocessable Content', $problem['title'] ?? null);
        self::assertNotSame([], self::rowsAt($problem, 'violations'));
    }

    public function testJsonLdErrorKeepsContextFirstAndAddsOurFieldsAfterwards(): void
    {
        $this->postTransfer('{"amountFrom":"0"}');

        $keys = array_keys(self::decodeObject((string) $this->client->getResponse()->getContent()));
        self::assertSame('@context', $keys[0] ?? null);
        self::assertSame(['errorCode', 'params'], array_slice($keys, -2));
    }

    public function testTransferToTheSameAccountHasTheFormsCode(): void
    {
        $id = $this->account('Bank1-ROR-PLN')->getId();

        $this->postTransfer($this->transferJson($id, $id));

        $problem = $this->assertProblem(422, 'transfer.accounts_must_differ', []);
        self::assertSame('Cannot transfer between the same account.', $problem['detail'] ?? null);
    }

    public function testCurrencyExchangeWithoutAmountToNamesBothCurrencies(): void
    {
        $this->postTransfer($this->transferJson(
            $this->account('Bank1-ROR-PLN')->getId(),
            $this->account('Bank1-WAK-CHF')->getId(),
        ));

        $this->assertProblem(
            422,
            'transfer.amount_to_required_for_currency_exchange',
            ['fromCurrency' => 'PLN', 'toCurrency' => 'CHF'],
        );
    }

    public function testDateOutsideTheRangeIsAViolationWithTheRange(): void
    {
        $id = $this->account('Bank1-ROR-PLN')->getId();
        $other = $this->account('Bank1-WAK-PLN')->getId();
        $json = str_replace('2026-01-15', '1999-01-15', $this->transferJson($id, $other));

        $this->postTransfer($json);

        $problem = $this->assertProblem(422, 'validation.failed', []);
        $violation = self::rowsAt($problem, 'violations')[0] ?? null;
        self::assertIsArray($violation);
        self::assertSame('operationDate', $violation['propertyPath'] ?? null);
        self::assertSame('common.date_out_of_range', $violation['code'] ?? null);
        self::assertSame(['min' => '2025-01-01', 'max' => '2070-12-31'], $violation['params'] ?? null);
    }

    public function testErrorWithoutABusinessCodeIsNamedAfterTheHttpStatus(): void
    {
        $this->client->request('GET', '/api/no_such_address/1', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
        ]);

        $problem = $this->assertProblem(404, 'http.not_found', []);
        self::assertSame('Not Found.', $problem['detail'] ?? null);
        self::assertSame('Not Found', $problem['title'] ?? null);
    }

    #[DataProvider('addressesOfMissingRecords')]
    public function testMissingRecordInTheAddressSaysWhatWasNotFound(
        string $method,
        string $uri,
        string $detail,
        string $errorCode,
    ): void {
        $this->client->request($method, $uri, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'CONTENT_TYPE' => 'application/merge-patch+json',
        ], content: 'PATCH' === $method ? '{}' : null);

        $problem = $this->assertProblem(404, $errorCode, []);
        self::assertSame($detail, $problem['detail'] ?? null);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function addressesOfMissingRecords(): iterable
    {
        $id = '999999999';
        $rateCode = 'currency_exchange_rate.not_found';
        $records = [
            ['GET', '/api/accounts/'.$id, 'Account not found.', 'account.not_found'],
            ['PATCH', '/api/accounts/'.$id, 'Account not found.', 'account.not_found'],
            ['GET', '/api/accounts/abc', 'Account not found.', 'account.not_found'],
            ['GET', '/api/accounts/2147483648', 'Account not found.', 'account.not_found'],
            ['GET', '/api/account_operations/'.$id, 'Account operation not found.', 'account_operation.not_found'],
            ['PATCH', '/api/account_operations/'.$id, 'Account operation not found.', 'account_operation.not_found'],
            ['DELETE', '/api/account_operations/'.$id, 'Account operation not found.', 'account_operation.not_found'],
            ['GET', '/api/account_types/'.$id, 'Account type not found.', 'account_type.not_found'],
            ['GET', '/api/banks/'.$id, 'Bank not found.', 'bank.not_found'],
            ['GET', '/api/contractors/'.$id, 'Contractor not found.', 'contractor.not_found'],
            ['GET', '/api/currency_exchange_rates/'.$id, 'Exchange rate not found.', $rateCode],
            ['DELETE', '/api/currency_exchange_rates/'.$id, 'Exchange rate not found.', $rateCode],
            ['GET', '/api/fixed_expenses/'.$id, 'Fixed expense not found.', 'fixed_expense.not_found'],
            ['PATCH', '/api/fixed_expenses/'.$id, 'Fixed expense not found.', 'fixed_expense.not_found'],
            ['DELETE', '/api/fixed_expenses/'.$id, 'Fixed expense not found.', 'fixed_expense.not_found'],
            ['GET', '/api/gold_assets/'.$id, 'Gold asset not found.', 'gold_asset.not_found'],
            ['PATCH', '/api/gold_assets/'.$id, 'Gold asset not found.', 'gold_asset.not_found'],
            ['DELETE', '/api/gold_assets/'.$id, 'Gold asset not found.', 'gold_asset.not_found'],
            ['GET', '/api/gold_forms/'.$id, 'Gold form not found.', 'gold_form.not_found'],
            ['GET', '/api/gold_prices/'.$id, 'Gold price entry not found.', 'gold_price.not_found'],
            ['DELETE', '/api/gold_prices/'.$id, 'Gold price entry not found.', 'gold_price.not_found'],
            ['GET', '/api/households/'.$id, 'Household not found.', 'household.not_found'],
        ];
        foreach ($records as [$method, $uri, $detail, $errorCode]) {
            yield "{$method} {$uri}" => [$method, $uri, $detail, $errorCode];
        }
    }

    public function testLosingTheMainAccountRaceIsAConflictAndKeepsOneMainAccount(): void
    {
        $accounts = $this->em()->getRepository(Account::class)->findBy(['isMain' => false], ['id' => 'ASC'], 2);
        self::assertCount(2, $accounts);
        [$account, $rival] = $accounts;
        $rivalId = $rival->getId();
        $token = $this->obtainJwtToken();
        $this->loseTheNextMainAccountRace($this->client, $rivalId);

        $this->client->request('PATCH', '/api/accounts/'.$account->getId(), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
        ], content: '{"isMain": true}');

        $problem = $this->assertProblem(409, 'account.main_changed_concurrently', []);
        self::assertStringNotContainsString('SQLSTATE', self::stringAt($problem, 'detail'));
        self::assertSame(
            1,
            $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM account WHERE is_main = true'),
        );
    }

    public function testAnOverlookedDuplicateIsAConflictWithoutSql(): void
    {
        $token = $this->obtainJwtToken();
        $this->client->disableReboot();
        $entityManager = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->getEventManager()->addEventListener(Events::preFlush, new class {
            public function preFlush(): void
            {
                throw new UniqueConstraintViolationException(
                    new class('SQLSTATE[23505]: uniq_secret_index') extends \Exception implements DriverException {
                        public function getSQLState(): string
                        {
                            return '23505';
                        }
                    },
                    new Query('INSERT INTO secret_table', [], []),
                );
            }
        });
        $contractor = $this->em()->getRepository(Contractor::class)->findOneBy([]);
        self::assertNotNull($contractor);

        $this->client->request('POST', '/api/fixed_expenses', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/ld+json',
        ], content: json_encode(
            ['contractorId' => $contractor->getId(), 'day' => 5, 'amount' => '-1.00'],
            JSON_THROW_ON_ERROR,
        ));

        $problem = $this->assertProblem(409, 'http.conflict', []);
        self::assertSame('This record already exists.', $problem['detail'] ?? null);
    }

    public function testValidationErrorIdIsAnAddressThatExists(): void
    {
        $this->postTransfer('{}');

        $problem = $this->assertProblem(422, 'validation.failed', []);
        self::assertSame('/api/errors/422', $problem['@id'] ?? null);

        $this->client->request('GET', '/api/errors/422', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
        ]);
        self::assertResponseIsSuccessful();
    }

    public function testOpenApiDocumentsAuthenticationErrorsOnEveryProtectedOperation(): void
    {
        $docs = $this->openApiDocs();
        $checked = 0;

        foreach (self::objectAt($docs, 'paths') as $path => $methods) {
            self::assertIsArray($methods);
            foreach ($methods as $method => $operation) {
                if (!is_array($operation) || [] === ($operation['security'] ?? [])) {
                    continue;
                }

                $responses = $operation['responses'] ?? null;
                self::assertIsArray($responses, "{$method} {$path}");
                self::assertArrayHasKey('401', $responses, "{$method} {$path}");
                ++$checked;
            }
        }

        self::assertGreaterThan(30, $checked);
        self::assertArrayHasKey('401', self::objectAt($docs, 'paths', '/api/login_check', 'post', 'responses'));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function operationsWithAConflict(): iterable
    {
        yield 'new contractor' => ['/api/contractors', 'post', 'contractor.name_not_unique'];
        yield 'new operation' => ['/api/account_operations', 'post', 'contractor.name_not_unique'];
        yield 'changed operation' => ['/api/account_operations/{id}', 'patch', 'contractor.name_not_unique'];
        yield 'new fixed expense' => ['/api/fixed_expenses', 'post', 'contractor.name_not_unique'];
        yield 'changed fixed expense' => ['/api/fixed_expenses/{id}', 'patch', 'contractor.name_not_unique'];
        yield 'main account' => ['/api/accounts/{id}', 'patch', 'account.main_changed_concurrently'];
        yield 'planning' => ['/api/fixed_expenses/plan', 'post', 'fixed_expense.plan_'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function listsWithValidatedFilters(): iterable
    {
        yield 'search' => ['/api/account_operations'];
        yield 'account history' => ['/api/accounts/{accountId}/operations'];
        yield 'contractors' => ['/api/contractors'];
    }

    #[DataProvider('listsWithValidatedFilters')]
    public function testOpenApiDocumentsThatAListRefusesAnInvalidFilter(string $path): void
    {
        $invalid = self::objectAt($this->openApiDocs(), 'paths', $path, 'get', 'responses', '422');
        $schema = self::objectAt($invalid, 'content', 'application/ld+json', 'schema', 'allOf', '0');

        self::assertStringContainsString('validation.failed', self::stringAt($invalid, 'description'));
        self::assertSame('#/components/schemas/ConstraintViolation.jsonld', $schema['$ref'] ?? null);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function authenticationExamples(): iterable
    {
        yield 'login' => ['/api/login_check', 'post', 'auth.invalid_credentials'];
        yield 'protected address' => ['/api/accounts', 'get', 'auth.token_missing'];
    }

    #[DataProvider('authenticationExamples')]
    public function testOpenApiShowsTheAuthenticationErrorThatFitsTheAddress(
        string $path,
        string $method,
        string $errorCode,
    ): void {
        $schema = self::objectAt(
            $this->openApiDocs(),
            'paths', $path, $method, 'responses', '401', 'content', 'application/json', 'schema',
        );

        $example = self::objectAt($schema, 'allOf', '1', 'properties', 'errorCode');
        self::assertSame([$errorCode], $example['examples'] ?? null);
    }

    public function testErrorAddressExampleDoesNotDependOnOneServer(): void
    {
        $description = self::stringAt($this->openApiDocs(), 'paths', '/api/errors/{status}', 'get', 'description');

        self::assertStringContainsString('curl "$BASE_URL/api/errors/409"', $description);
    }

    /**
     * The decorator names operations as text, so a renamed route would quietly lose its note without this.
     */
    public function testEveryOperationTheDocumentationDecoratorNamesExists(): void
    {
        $docs = $this->openApiDocs();
        $named = [];
        foreach (['CONFLICTS', 'INVALID_QUERIES'] as $constant) {
            $routes = (new \ReflectionClassConstant(ProblemDetailsDecorator::class, $constant))->getValue();
            self::assertIsArray($routes);
            $named = [...$named, ...array_keys($routes)];
        }

        foreach (array_unique($named) as $route) {
            [$method, $path] = explode(' ', (string) $route, 2);
            self::assertIsArray(self::objectAt($docs, 'paths', $path)[strtolower($method)] ?? null, $route);
        }
    }

    #[DataProvider('operationsWithAConflict')]
    public function testOpenApiDocumentsConflicts(string $path, string $method, string $code): void
    {
        $conflict = self::objectAt($this->openApiDocs(), 'paths', $path, $method, 'responses', '409');

        self::assertIsString($conflict['description'] ?? null);
        self::assertStringContainsString($code, $conflict['description']);
    }

    public function testOpenApiDescribesOnlyFieldsTheErrorsHave(): void
    {
        $docs = $this->openApiDocs();
        $schemas = self::objectAt($docs, 'components', 'schemas');

        self::assertArrayNotHasKey('instance', self::objectAt($schemas, 'Error', 'properties'));
        self::assertArrayNotHasKey('instance', self::objectAt($schemas, 'ConstraintViolation', 'properties'));
        foreach (['"instance"', '"hint"', '"payload"'] as $field) {
            self::assertStringNotContainsString($field, (string) json_encode($schemas), $field);
        }
        self::assertStringContainsString('alone gives 406', self::stringAt($docs, 'info', 'description'));
        self::assertStringContainsString('GET /api/errors/{status}', self::stringAt($docs, 'info', 'description'));
    }

    public function testOpenApiDocumentsHowToCallTheErrorAddressAndItWorks(): void
    {
        $operation = self::objectAt($this->openApiDocs(), 'paths', '/api/errors/{status}', 'get');

        self::assertSame(['status'], array_column(self::rowsAt($operation, 'parameters'), 'name'));
        self::assertStringContainsString('curl', self::stringAt($operation, 'description'));
        self::assertArrayHasKey('401', self::objectAt($operation, 'responses'));

        $this->client->request('GET', '/api/errors/409', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        self::assertResponseStatusCodeSame(200);
        $error = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame('http.conflict', self::stringAt($error, 'errorCode'));
    }

    #[DataProvider('badBodies')]
    public function testBodyThatIsNotAJsonObjectIsABadRequest(string $body, string $detail): void
    {
        $this->postTransfer($body);

        $problem = $this->assertProblem(400, 'http.bad_request', []);
        self::assertSame($detail, $problem['detail'] ?? null);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badBodies(): iterable
    {
        $notJson = 'The request body is not valid JSON.';
        $notObject = 'The request body must be a JSON object.';

        yield 'malformed' => ['{bad', $notJson];
        yield 'empty' => ['', $notJson];
        yield 'number' => ['5', $notObject];
        yield 'null' => ['null', $notObject];
        yield 'boolean' => ['true', $notObject];
        yield 'text' => ['"abc"', $notObject];
        yield 'list' => ['[1,2]', $notObject];
        yield 'empty list' => ['[]', $notObject];
    }

    public function testBadBodyOnUpdateIsABadRequest(): void
    {
        $this->client->request('PATCH', '/api/accounts/'.$this->account('Bank1-ROR-PLN')->getId(), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'CONTENT_TYPE' => 'application/merge-patch+json',
        ], content: '5');

        $problem = $this->assertProblem(400, 'http.bad_request', []);
        self::assertSame('The request body must be a JSON object.', $problem['detail'] ?? null);
    }

    public function testEmptyObjectReachesTheFieldValidation(): void
    {
        $this->postTransfer('{}');

        $this->assertProblem(422, 'validation.failed', []);
    }

    public function testMissingRecordStaysNotFoundWhateverTheBody(): void
    {
        $this->client->request('PATCH', '/api/accounts/999999999', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'CONTENT_TYPE' => 'application/merge-patch+json',
        ], content: '5');

        $this->assertProblem(404, 'account.not_found', []);
    }

    public function testUnsupportedContentTypeStaysUnsupportedWhateverTheBody(): void
    {
        $this->client->request('POST', '/api/transfers', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'CONTENT_TYPE' => 'text/plain',
        ], content: '{bad');

        self::assertResponseStatusCodeSame(415);
    }

    public function testOperationWithoutABodyDoesNotRequireOne(): void
    {
        $this->client->request('POST', '/api/fixed_expenses/plan', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'CONTENT_TYPE' => 'application/ld+json',
        ]);

        self::assertResponseStatusCodeSame(201);
    }

    #[DataProvider('unsupportedAcceptHeaders')]
    public function testUnsupportedResponseFormatIsAProblemWithACode(string $accept): void
    {
        $this->client->request('GET', '/api/accounts', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'HTTP_ACCEPT' => $accept,
        ]);

        $this->assertProblem(406, 'http.not_acceptable', []);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedAcceptHeaders(): iterable
    {
        yield 'XML' => ['application/xml'];
        yield 'Problem JSON only' => ['application/problem+json'];
    }

    public function testUnsupportedRequestFormatIsAProblemWithACode(): void
    {
        $this->client->request('POST', '/api/transfers', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'CONTENT_TYPE' => 'application/xml',
        ], content: '{}');

        $this->assertProblem(415, 'http.unsupported_media_type', []);
    }

    public function testPlainJsonIsAnsweredWithoutJsonLdFields(): void
    {
        $this->client->request('GET', '/api/accounts/'.$this->account('Bank1-ROR-PLN')->getId(), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith(
            'application/json',
            (string) $this->client->getResponse()->headers->get('Content-Type'),
        );
        $account = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame('Bank1-ROR-PLN', $account['accountNumber'] ?? null);
        self::assertArrayNotHasKey('@context', $account);
    }

    public function testPlainJsonRequestCreatesARecord(): void
    {
        $name = 'Plain JSON '.uniqid();

        $this->client->request('POST', '/api/contractors', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['name' => $name], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(201);
        self::assertSame(
            $name,
            self::decodeObject((string) $this->client->getResponse()->getContent())['name'] ?? null,
        );
    }

    public function testPlainJsonErrorKeepsCodesAndViolations(): void
    {
        $this->client->request('POST', '/api/contractors', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], content: '{}');

        $problem = $this->assertProblem(422, 'validation.failed', []);
        $violation = self::rowsAt($problem, 'violations')[0] ?? null;
        self::assertIsArray($violation);
        self::assertSame('name', $violation['propertyPath'] ?? null);
        self::assertSame('contractor.name_not_blank', $violation['code'] ?? null);
    }

    public function testOpenApiListsPlainJsonOnlyWhereItWorks(): void
    {
        $docs = $this->openApiDocs();

        self::assertArrayHasKey(
            'application/json',
            self::objectAt($docs, 'paths', '/api/accounts', 'get', 'responses', '200', 'content'),
        );
        self::assertArrayHasKey(
            'application/json',
            self::objectAt($docs, 'paths', '/api/contractors', 'post', 'requestBody', 'content'),
        );
        self::assertSame(
            ['application/merge-patch+json'],
            array_keys(self::objectAt($docs, 'paths', '/api/accounts/{id}', 'patch', 'requestBody', 'content')),
        );
    }

    public function testEmptyParamsAreAnObjectNotAList(): void
    {
        $this->postTransfer('{bad');

        self::assertStringContainsString('"params":{}', (string) $this->client->getResponse()->getContent());
    }

    public function testOpenApiDocumentsErrorCodeAndParamsInEveryErrorSchema(): void
    {
        $docs = $this->openApiDocs();

        foreach (['Error', 'Error.jsonld', 'ConstraintViolation', 'ConstraintViolation.jsonld'] as $name) {
            $schema = self::objectAt($docs, 'components', 'schemas', $name);
            $properties = isset($schema['allOf'])
                ? self::objectAt($schema, 'allOf', '1', 'properties')
                : self::objectAt($schema, 'properties');
            self::assertArrayHasKey('errorCode', $properties, $name);
            self::assertArrayHasKey('params', $properties, $name);
            $objectSchema = isset($schema['allOf']) ? self::objectAt($schema, 'allOf', '1') : $schema;
            $required = $objectSchema['required'] ?? null;
            self::assertIsArray($required, $name);
            self::assertContains('errorCode', $required, $name);
            self::assertContains('params', $required, $name);
            if (str_starts_with($name, 'ConstraintViolation')) {
                self::assertSame(
                    ['propertyPath', 'message', 'code', 'params'],
                    self::objectAt($properties, 'violations', 'items')['required'] ?? null,
                    $name,
                );
            }
        }
    }

    /**
     * All error responses share one schema; without its own example each would show "status": 404 in Swagger UI.
     */
    public function testOpenApiShowsTheResponseStatusInEveryErrorExample(): void
    {
        $docs = $this->openApiDocs();
        $sharedStatus = self::objectAt($docs, 'components', 'schemas', 'Error', 'properties', 'status');
        self::assertArrayNotHasKey('examples', $sharedStatus);
        $sharedErrorCode = self::objectAt($docs, 'components', 'schemas', 'Error', 'properties', 'errorCode');
        self::assertArrayNotHasKey('examples', $sharedErrorCode);

        $checked = [];
        foreach (self::objectAt($docs, 'paths') as $path => $pathItem) {
            self::assertIsArray($pathItem);
            foreach ($pathItem as $method => $operation) {
                if (!is_array($operation) || !is_array($operation['responses'] ?? null)) {
                    continue;
                }
                foreach ($operation['responses'] as $status => $response) {
                    $content = is_array($response) ? ($response['content'] ?? null) : null;
                    if ((int) $status < 400 || !is_array($content)) {
                        continue;
                    }
                    foreach ($content as $mediaType => $media) {
                        self::assertIsArray($media);
                        $where = sprintf('%s %s %s %s', $method, $path, $status, $mediaType);
                        $example = self::objectAt(self::stringKeyed($media), 'schema', 'allOf', '1', 'properties');
                        $statusExample = self::objectAt($example, 'status');
                        self::assertSame([(int) $status], $statusExample['examples'] ?? null, $where);
                        self::assertSame((int) $status, $statusExample['const'] ?? null, $where);
                        $errorCodes = self::objectAt($example, 'errorCode')['examples'] ?? null;
                        self::assertIsArray($errorCodes, $where);
                        self::assertIsString($errorCodes[0] ?? null, $where);
                        $checked[$status] = $errorCodes[0];
                    }
                }
            }
        }

        self::assertSame('http.bad_request', $checked['400'] ?? null);
        self::assertSame('http.forbidden', $checked['403'] ?? null);
        self::assertSame('http.not_found', $checked['404'] ?? null);
        self::assertSame('validation.failed', $checked['422'] ?? null);
    }

    /**
     * Every factory of every BusinessError in src/Exception, found by reflection: a new refusal cannot be added
     * without being listed in the API docs.
     *
     * @return iterable<string, array{BusinessError}>
     */
    public static function businessErrors(): iterable
    {
        foreach (glob(dirname(__DIR__, 2).'/src/Exception/*.php') ?: [] as $file) {
            $className = 'App\\Exception\\'.basename($file, '.php');
            if (!class_exists($className) && !interface_exists($className)) {
                continue;
            }

            $class = new \ReflectionClass($className);
            if (!$class->implementsInterface(BusinessError::class) || $class->isInterface()) {
                continue;
            }

            // The filter flags are OR-ed, so the static check is needed as well.
            foreach ($class->getMethods(\ReflectionMethod::IS_STATIC) as $factory) {
                if (!$factory->isPublic()) {
                    continue;
                }

                $arguments = array_map(static fn (): string => 'X', $factory->getParameters());
                $error = $factory->invoke(null, ...$arguments);
                if ($error instanceof BusinessError) {
                    yield $class->getShortName().'::'.$factory->getName() => [$error];
                }
            }
        }
    }

    #[DataProvider('businessErrors')]
    public function testOpenApiListsEveryBusinessErrorCodeWithItsParams(BusinessError $error): void
    {
        $description = self::stringAt($this->openApiDocs(), 'info', 'description');
        $row = '| `'.$error->getErrorCode().'` |';

        self::assertStringContainsString($row, $description);
        $line = strstr(substr($description, (int) strpos($description, $row)), "\n", true);
        self::assertIsString($line);
        foreach (array_keys($error->getErrorParams()) as $param) {
            self::assertStringContainsString('`'.$param.'`', $line);
        }
    }

    /**
     * Our Swagger UI template override keeps inline code in the descriptions from overlapping in Chrome.
     */
    public function testSwaggerUiPageIncludesTheInlineCodeFix(): void
    {
        $this->client->request('GET', '/api/docs', server: ['HTTP_ACCEPT' => 'text/html']);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('padding: 1px 4px', (string) $this->client->getResponse()->getContent());
    }

    public function testOpenApiListsTheAuthenticationErrorCodes(): void
    {
        $description = self::stringAt($this->openApiDocs(), 'info', 'description');

        foreach ([
            'auth.token_missing',
            'auth.token_invalid',
            'auth.token_expired',
            'auth.invalid_credentials',
            'auth.too_many_login_attempts',
            'auth.login_failed',
        ] as $errorCode) {
            self::assertStringContainsString('| `'.$errorCode.'` |', $description);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function openApiDocs(): array
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');
        self::assertResponseIsSuccessful();

        return self::decodeObject((string) $this->client->getResponse()->getContent());
    }

    /**
     * @param array<string, string> $params
     *
     * @return array<string, mixed>
     */
    private function assertProblem(int $status, string $errorCode, array $params): array
    {
        self::assertResponseStatusCodeSame($status);
        self::assertStringStartsWith(
            'application/problem+json',
            (string) $this->client->getResponse()->headers->get('Content-Type'),
        );
        $problem = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame($status, $problem['status'] ?? null);
        self::assertSame($errorCode, $problem['errorCode'] ?? null);
        self::assertSame('urn:finance-app:problem:'.$errorCode, $problem['type'] ?? null);
        self::assertSame($params, self::objectAt($problem, 'params'));
        self::assertNotSame('', self::stringAt($problem, 'detail'));

        return $problem;
    }

    private function transferJson(?int $fromId, ?int $toId): string
    {
        return json_encode([
            'fromAccountId' => $fromId,
            'toAccountId' => $toId,
            'operationDate' => '2026-01-15',
            'amountFrom' => '10.00',
        ], JSON_THROW_ON_ERROR);
    }

    private function postTransfer(string $json): void
    {
        $this->client->request('POST', '/api/transfers', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'CONTENT_TYPE' => 'application/ld+json',
        ], content: $json);
    }

    private function obtainJwtToken(): string
    {
        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'test@example.com', 'password' => 'test1234'], JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();

        return self::stringAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'token');
    }

    private function account(string $number): Account
    {
        $account = $this->em()->getRepository(Account::class)->findOneBy(['accountNumber' => $number]);
        self::assertNotNull($account);

        return $account;
    }
}
