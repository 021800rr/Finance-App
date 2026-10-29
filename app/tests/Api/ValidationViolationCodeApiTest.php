<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * Every API validation violation carries a stable "code" (our translation key, or "validation.<symfony error>")
 * and whitelisted "params", so a client can build its own message without parsing the English "message".
 */
final class ValidationViolationCodeApiTest extends WebTestCase
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

    public function testOwnConstraintMessagesBecomeTheCodes(): void
    {
        $this->postTransfer(['amountFrom' => '0']);

        self::assertSame([
            'fromAccountId' => ['code' => 'transfer.from_account_required', 'params' => []],
            'toAccountId' => ['code' => 'transfer.to_account_required', 'params' => []],
            'operationDate' => ['code' => 'common.date_not_blank', 'params' => []],
            'amountFrom' => [
                'code' => 'transfer.amount_out_of_range',
                'params' => ['min' => '0.01', 'max' => '999999.99'],
            ],
        ], $this->codesAndParams());
    }

    /**
     * The error format is negotiated separately from the resource format: a client preferring Problem JSON for
     * errors gets the same violation codes as a JSON-LD client.
     */
    public function testViolationCodesAreTheSameInEveryErrorFormat(): void
    {
        $this->postTransfer([], 'application/ld+json;q=0.5, application/problem+json');

        self::assertStringStartsWith(
            'application/problem+json',
            (string) $this->client->getResponse()->headers->get('Content-Type'),
        );
        $problem = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertArrayNotHasKey('@context', $problem);
        self::assertSame(
            ['code' => 'transfer.from_account_required', 'params' => []],
            $this->codesAndParams()['fromAccountId'] ?? null,
        );
    }

    public function testEmptyParamsAreAnObjectNotAList(): void
    {
        $this->postTransfer([]);

        self::assertStringContainsString(
            '"code":"transfer.from_account_required","params":{}',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testLengthLimitIsExposedButTheSubmittedValueIsNot(): void
    {
        $tooLong = str_repeat('x', 256);

        $this->postTransfer(['description' => $tooLong]);

        self::assertSame(
            ['code' => 'common.description_max_length', 'params' => ['limit' => '255', 'max' => '255']],
            $this->codesAndParams()['description'] ?? null,
        );
        self::assertStringNotContainsString($tooLong, (string) $this->client->getResponse()->getContent());
    }

    public function testWrongTypeGetsAGenericCode(): void
    {
        $this->postTransfer(['fromAccountId' => 'abc']);

        self::assertSame(
            ['fromAccountId' => ['code' => 'validation.invalid_type', 'params' => []]],
            $this->codesAndParams(),
        );
    }

    public function testConstraintWithoutOwnMessageGetsTheSymfonyErrorName(): void
    {
        $this->client->request('GET', '/api/account_operations?q=a', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        self::assertSame(
            ['q' => ['code' => 'validation.too_short', 'params' => ['limit' => '2', 'min' => '2']]],
            $this->codesAndParams(),
        );
    }

    public function testEveryApiRouteIsEnglishWhateverTheDefaultLocale(): void
    {
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        $apiRoutes = 0;
        foreach ($router->getRouteCollection() as $name => $route) {
            if (!str_starts_with($route->getPath(), '/api')) {
                continue;
            }

            ++$apiRoutes;
            self::assertSame('en', $route->getDefault('_locale'), $name);
        }

        self::assertGreaterThan(10, $apiRoutes);
    }

    /**
     * @return array<string, array{code: mixed, params: mixed}>
     */
    private function codesAndParams(): array
    {
        self::assertResponseStatusCodeSame(422);
        $violations = self::rowsAt(
            self::decodeObject((string) $this->client->getResponse()->getContent()),
            'violations',
        );

        $result = [];
        foreach ($violations as $violation) {
            self::assertIsString($violation['propertyPath'] ?? null);
            $result[$violation['propertyPath']] = [
                'code' => $violation['code'] ?? null,
                'params' => $violation['params'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postTransfer(array $payload, string $accept = 'application/ld+json'): void
    {
        $this->client->request('POST', '/api/transfers', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => $accept,
        ], content: json_encode($payload, JSON_THROW_ON_ERROR));
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
}
