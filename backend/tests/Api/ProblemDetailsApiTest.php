<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Account;
use App\Entity\Contractor;
use App\OpenApi\ProblemDetailsDecorator;
use App\Tests\DatabaseTestTrait;
use App\Tests\JwtTrait;
use App\Tests\LosesMainAccountRaceTrait;
use App\Tests\TestDataTrait;
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
    use JwtTrait;
    use TestDataTrait;

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
        // RFC 9457, section 3.1.3: a summary of the problem type, not the name of the status.
        self::assertSame('Validation failed', $problem['title'] ?? null);
        self::assertNotSame([], self::fieldErrors($problem));
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function acceptHeaders(): iterable
    {
        yield 'none' => [null];
        yield 'any' => ['*/*'];
        yield 'JSON' => ['application/json'];
    }

    /**
     * RFC 9457, section 4: in application/problem+json the names of extension members SHOULD start with a letter, so
     * an error has no JSON-LD members ("@context", "@id", "@type") whatever the client accepts, nor Hydra's
     * "description", which repeated "detail".
     */
    #[DataProvider('acceptHeaders')]
    public function testErrorIsPlainProblemDetailsWhateverTheAccept(?string $accept): void
    {
        $this->client->request('POST', '/api/transfers', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt(),
            'CONTENT_TYPE' => 'application/json',
        ] + (null === $accept ? [] : ['HTTP_ACCEPT' => $accept]), content: '{"amountFrom":"0"}');

        $problem = $this->assertProblem(422, 'validation.failed', []);
        foreach (array_keys($problem) as $name) {
            self::assertMatchesRegularExpression('/^[A-Za-z][A-Za-z0-9_]{2,}$/', (string) $name);
        }
        self::assertArrayNotHasKey('description', $problem);
    }

    public function testTransferToTheSameAccountHasTheFormsCode(): void
    {
        $id = $this->account('Bank1-ROR-PLN')->getId();

        $this->postTransfer($this->transferJson($id, $id));

        $problem = $this->assertProblem(422, 'transfer.accounts_must_differ', []);
        self::assertSame('Cannot transfer between the same account.', $problem['detail'] ?? null);
        self::assertSame('Same account on both sides', $problem['title'] ?? null);
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
        $violation = self::fieldErrors($problem)[0] ?? null;
        self::assertIsArray($violation);
        // RFC 9457's example: a JSON Pointer (RFC 6901) to the refused value, in its URI fragment form.
        self::assertSame('#/operationDate', $violation['pointer'] ?? null);
        self::assertArrayNotHasKey('parameter', $violation);
        self::assertSame('common.date_out_of_range', $violation['code'] ?? null);
        self::assertSame(['min' => '2025-01-01', 'max' => '2070-12-31'], $violation['params'] ?? null);
    }

    public function testErrorWithoutABusinessCodeIsNamedAfterTheHttpStatus(): void
    {
        $this->client->request('GET', '/api/no_such_address/1', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt(),
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
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt(),
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
        $token = $this->jwt();
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
        $token = $this->jwt();
        $this->client->disableReboot();
        $entityManager = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->getEventManager()->addEventListener(Events::preFlush, new class {
            public function preFlush(): void
            {
                $message = 'SQLSTATE[23505]: uniq_secret_index';
                $driverException = new class($message) extends \Exception implements DriverException {
                    public function getSQLState(): string
                    {
                        return '23505';
                    }
                };

                $query = new Query('INSERT INTO secret_table', [], []);

                throw new UniqueConstraintViolationException($driverException, $query);
            }
        });
        $contractor = $this->em()->getRepository(Contractor::class)->findOneBy([]);
        self::assertNotNull($contractor);

        $this->client->request('POST', '/api/fixed_expenses', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(
            ['contractorId' => $contractor->getId(), 'day' => 5, 'amount' => '-1.00'],
            JSON_THROW_ON_ERROR,
        ));

        $problem = $this->assertProblem(409, 'http.conflict', []);
        self::assertSame('This record already exists.', $problem['detail'] ?? null);
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
    }

    /**
     * The login and the refresh refuse their credentials with 400, without the challenge a 401 needs.
     */
    public function testOpenApiDocumentsRefusedCredentialsAsABadRequest(): void
    {
        $docs = $this->openApiDocs();

        foreach (['/api/login_check', '/api/token/refresh'] as $path) {
            $responses = self::objectAt($docs, 'paths', $path, 'post', 'responses');
            self::assertArrayHasKey('400', $responses, $path);
            self::assertArrayNotHasKey('401', $responses, $path);
        }
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
        $invalid = self::objectAt($this->openApiDocs(), 'paths', $path, 'get', 'responses', '400');
        $schema = self::objectAt($invalid, 'content', 'application/problem+json', 'schema', 'allOf', '0');

        self::assertStringContainsString('validation.failed', self::stringAt($invalid, 'description'));
        self::assertSame('#/components/schemas/ConstraintViolation', $schema['$ref'] ?? null);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function authenticationExamples(): iterable
    {
        yield 'login' => ['/api/login_check', 'post', '400', 'auth.invalid_credentials'];
        yield 'locked login' => ['/api/login_check', 'post', '429', 'auth.too_many_login_attempts'];
        yield 'protected address' => ['/api/accounts', 'get', '401', 'auth.token_missing'];
        yield 'refresh' => ['/api/token/refresh', 'post', '400', 'auth.refresh_token_invalid'];
        yield 'logout' => ['/api/token/invalidate', 'post', '400', 'auth.refresh_token_missing'];
    }

    #[DataProvider('authenticationExamples')]
    public function testOpenApiShowsTheAuthenticationErrorThatFitsTheAddress(
        string $path,
        string $method,
        string $status,
        string $errorCode,
    ): void {
        $schema = self::objectAt(
            $this->openApiDocs(),
            'paths', $path, $method, 'responses', $status, 'content', 'application/problem+json', 'schema',
        );

        $example = self::objectAt($schema, 'allOf', '1', 'properties', 'type');
        self::assertSame(['urn:finance-app:problem:'.$errorCode], $example['examples'] ?? null);
    }

    public function testOpenApiDocumentsTheRetryAfterOfALockedLogin(): void
    {
        $header = self::objectAt(
            $this->openApiDocs(),
            'paths', '/api/login_check', 'post', 'responses', '429', 'headers', 'Retry-After',
        );

        self::assertSame(['type' => 'integer'], $header['schema'] ?? null);
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

    /**
     * RFC 9110: every GET answers 304 to a matching If-None-Match, every method 412 to a failing precondition; a 401
     * names its challenge. The JWT comes in the Authorization header or in the BEARER cookie.
     */
    public function testOpenApiDocumentsConditionalRequestsTheChallengeAndTheCookie(): void
    {
        $docs = $this->openApiDocs();
        $get = self::objectAt($docs, 'paths', '/api/accounts/{id}', 'get');
        $patch = self::objectAt($docs, 'paths', '/api/accounts/{id}', 'patch');

        self::assertArrayHasKey('304', self::objectAt($get, 'responses'));
        self::assertArrayHasKey('412', self::objectAt($get, 'responses'));
        self::assertArrayNotHasKey('304', self::objectAt($patch, 'responses'));
        self::assertArrayHasKey('412', self::objectAt($patch, 'responses'));
        self::assertArrayHasKey('WWW-Authenticate', self::objectAt($get, 'responses', '401', 'headers'));
        self::assertSame([['JWT' => []], ['BearerCookie' => []]], $get['security'] ?? null);
        $cookie = self::objectAt($docs, 'components', 'securitySchemes', 'BearerCookie');
        self::assertSame(
            ['apiKey', 'BEARER', 'cookie'],
            [$cookie['type'] ?? null, $cookie['name'] ?? null, $cookie['in'] ?? null],
        );
    }

    /**
     * Every error is application/problem+json, whatever the client accepts: no other media type is documented.
     */
    public function testOpenApiDocumentsErrorsOnlyAsProblemJson(): void
    {
        foreach (self::objectAt($this->openApiDocs(), 'paths') as $path => $pathItem) {
            self::assertIsArray($pathItem);
            foreach ($pathItem as $method => $operation) {
                $responses = is_array($operation) ? ($operation['responses'] ?? null) : null;
                foreach (is_array($responses) ? $responses : [] as $status => $response) {
                    $content = is_array($response) ? ($response['content'] ?? null) : null;
                    if ((int) $status >= 400 && is_array($content)) {
                        $where = sprintf('%s %s %s', $method, $path, $status);
                        self::assertSame(['application/problem+json'], array_keys($content), $where);
                    }
                }
            }
        }
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
    }

    /**
     * Since errors are no JSON-LD, nothing leads to API Platform's description of an HTTP status any more.
     */
    public function testOpenApiDoesNotListTheErrorAddress(): void
    {
        self::assertArrayNotHasKey('/api/errors/{status}', self::objectAt($this->openApiDocs(), 'paths'));
    }

    #[DataProvider('badBodies')]
    public function testBodyThatIsNotAJsonObjectIsRefused(string $body, int $status, string $detail): void
    {
        $this->postTransfer($body);

        $code = 400 === $status ? 'http.bad_request' : 'http.unprocessable_content';
        $problem = $this->assertProblem($status, $code, []);
        self::assertSame($detail, $problem['detail'] ?? null);
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function badBodies(): iterable
    {
        $notJson = 'The request body is not valid JSON.';
        $notObject = 'The request body must be a JSON object.';

        yield 'malformed' => ['{bad', 400, $notJson];
        yield 'empty' => ['', 400, $notJson];
        yield 'number' => ['5', 422, $notObject];
        yield 'null' => ['null', 422, $notObject];
        yield 'boolean' => ['true', 422, $notObject];
        yield 'text' => ['"abc"', 422, $notObject];
        yield 'list' => ['[1,2]', 422, $notObject];
        yield 'empty list' => ['[]', 422, $notObject];
    }

    /**
     * RFC 7396, section 2: a merge patch that is not an object replaces the whole resource, which cannot be a list.
     */
    #[DataProvider('mergePatchesThatAreNotObjects')]
    public function testMergePatchThatIsNotAnObjectCannotBeApplied(string $body): void
    {
        $this->client->request('PATCH', '/api/accounts/'.$this->account('Bank1-ROR-PLN')->getId(), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt(),
            'CONTENT_TYPE' => 'application/merge-patch+json',
        ], content: $body);

        $problem = $this->assertProblem(422, 'http.unprocessable_content', []);
        self::assertSame('The request body must be a JSON object.', $problem['detail'] ?? null);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function mergePatchesThatAreNotObjects(): iterable
    {
        yield 'empty list' => ['[]'];
        yield 'number' => ['5'];
    }

    public function testEmptyObjectReachesTheFieldValidation(): void
    {
        $this->postTransfer('{}');

        $this->assertProblem(422, 'validation.failed', []);
    }

    public function testMissingRecordStaysNotFoundWhateverTheBody(): void
    {
        $this->client->request('PATCH', '/api/accounts/999999999', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt(),
            'CONTENT_TYPE' => 'application/merge-patch+json',
        ], content: '5');

        $this->assertProblem(404, 'account.not_found', []);
    }

    public function testUnsupportedContentTypeStaysUnsupportedWhateverTheBody(): void
    {
        $this->client->request('POST', '/api/transfers', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt(),
            'CONTENT_TYPE' => 'text/plain',
        ], content: '{bad');

        self::assertResponseStatusCodeSame(415);
    }

    public function testOperationWithoutABodyDoesNotRequireOne(): void
    {
        $this->client->request('POST', '/api/fixed_expenses/plan', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt(),
            'CONTENT_TYPE' => 'application/json',
        ]);

        self::assertResponseStatusCodeSame(200);
    }

    #[DataProvider('unsupportedAcceptHeaders')]
    public function testUnsupportedResponseFormatIsAProblemWithACode(string $accept): void
    {
        $this->client->request('GET', '/api/accounts', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt(),
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
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt(),
            'CONTENT_TYPE' => 'application/xml',
        ], content: '{}');

        $this->assertProblem(415, 'http.unsupported_media_type', []);
    }

    public function testPlainJsonIsAnsweredWithoutJsonLdFields(): void
    {
        $this->client->request('GET', '/api/accounts/'.$this->account('Bank1-ROR-PLN')->getId(), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt(),
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
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt(),
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
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt(),
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], content: '{}');

        $problem = $this->assertProblem(422, 'validation.failed', []);
        $violation = self::fieldErrors($problem)[0] ?? null;
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

    public function testEmptyParamsOfAnErrorAreAnObjectNotAList(): void
    {
        $this->postTransfer('{}');

        self::assertStringContainsString('"params":{}', (string) $this->client->getResponse()->getContent());
    }

    /**
     * RFC 9457: the standard members, and extension members of the problem type (strings); a validation problem also
     * has "errors". No "errorCode" or "params": the type is the identifier (section 3.1.1).
     */
    public function testOpenApiDocumentsTheProblemMembersInEveryErrorSchema(): void
    {
        $schemas = self::objectAt($this->openApiDocs(), 'components', 'schemas');

        self::assertArrayNotHasKey('Error.jsonld', $schemas);
        self::assertArrayNotHasKey('ConstraintViolation.jsonld', $schemas);
        foreach (['Error', 'ConstraintViolation'] as $name) {
            $schema = self::objectAt($schemas, $name);
            $properties = self::objectAt($schema, 'properties');
            self::assertArrayNotHasKey('errorCode', $properties, $name);
            self::assertArrayNotHasKey('params', $properties, $name);
            self::assertSame(['type' => 'string'], $schema['additionalProperties'] ?? null, $name);
            $required = $schema['required'] ?? null;
            self::assertIsArray($required, $name);
            foreach (['type', 'title', 'status', 'detail'] as $member) {
                self::assertContains($member, $required, $name);
            }
        }
        self::assertSame(
            ['detail', 'code', 'params'],
            self::objectAt($schemas, 'ConstraintViolation', 'properties', 'errors', 'items')['required'] ?? null,
        );
    }

    /**
     * All error responses share one schema; without its own example each would show "status": 404 in Swagger UI.
     */
    public function testOpenApiShowsTheResponseStatusInEveryErrorExample(): void
    {
        $docs = $this->openApiDocs();
        $sharedStatus = self::objectAt($docs, 'components', 'schemas', 'Error', 'properties', 'status');
        self::assertArrayNotHasKey('examples', $sharedStatus);
        $sharedType = self::objectAt($docs, 'components', 'schemas', 'Error', 'properties', 'type');
        self::assertArrayNotHasKey('examples', $sharedType);

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
                        $types = self::objectAt($example, 'type')['examples'] ?? null;
                        self::assertIsArray($types, $where);
                        self::assertIsString($types[0] ?? null, $where);
                        $checked[$status] = self::problemCode(['type' => $types[0]]);
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
     * Our Swagger UI template override keeps inline code in the descriptions from overlapping in Chrome.
     */
    public function testSwaggerUiPageIncludesTheInlineCodeFix(): void
    {
        $this->client->request('GET', '/api/docs', server: ['HTTP_ACCEPT' => 'text/html']);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('padding: 1px 4px', (string) $this->client->getResponse()->getContent());
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
        self::assertSame($errorCode, self::problemCode($problem));
        self::assertSame('urn:finance-app:problem:'.$errorCode, $problem['type'] ?? null);
        self::assertSame($params, self::problemMembers($problem));
        self::assertNotSame('', self::stringAt($problem, 'detail'));
        self::assertNotSame('', self::stringAt($problem, 'title'));
        // RFC 9457, section 3.1.4: help the client, no debugging information.
        self::assertArrayNotHasKey('trace', $problem);

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
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt(),
            'CONTENT_TYPE' => 'application/json',
        ], content: $json);
    }
}
