<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Account;
use App\Exception\BusinessError;
use App\Tests\DatabaseTestTrait;
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
        $this->client->request('GET', '/api/accounts/999999999', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
        ]);

        $problem = $this->assertProblem(404, 'http.not_found', []);
        self::assertSame('Not Found', $problem['title'] ?? null);
    }

    public function testMalformedJsonIsABadRequest(): void
    {
        $this->postTransfer('{bad');

        $this->assertProblem(400, 'http.bad_request', []);
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
        yield 'JSON' => ['application/json'];
        yield 'Problem JSON only' => ['application/problem+json'];
    }

    public function testUnsupportedRequestFormatIsAProblemWithACode(): void
    {
        $this->client->request('POST', '/api/transfers', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'CONTENT_TYPE' => 'application/json',
        ], content: '{}');

        $this->assertProblem(415, 'http.unsupported_media_type', []);
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
