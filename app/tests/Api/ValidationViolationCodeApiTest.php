<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\Contractor;
use App\Entity\FixedExpense;
use App\Entity\GoldAsset;
use App\Tests\DatabaseTestTrait;
use App\Validator\NoNullCharacter;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * The contractor is created from this name, so without the limit a too long one fails in the database (500).
     *
     * @param array<string, mixed> $valid
     */
    #[DataProvider('resourcesWithAContractorName')]
    public function testTooLongContractorNameIsAViolationOnCreateAndUpdate(string $uri, array $valid): void
    {
        if ('/api/account_operations' === $uri) {
            $account = $this->em()->getRepository(Account::class)->findOneBy(['accountNumber' => 'Bank1-ROR-PLN']);
            self::assertNotNull($account);
            $valid['accountId'] = $account->getId();
        }

        $tooLong = str_repeat('x', 256);
        $expected = ['code' => 'contractor.name_max_length', 'params' => ['limit' => '255', 'max' => '255']];
        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'HTTP_ACCEPT' => 'application/ld+json',
        ];
        /** @param array<string, mixed> $payload */
        $send = function (string $method, string $target, string $type, array $payload) use ($headers): void {
            $this->client->request(
                $method,
                $target,
                server: $headers + ['CONTENT_TYPE' => $type],
                content: json_encode($payload, JSON_THROW_ON_ERROR),
            );
        };

        $send('POST', $uri, 'application/ld+json', ['contractorName' => $tooLong] + $valid);
        self::assertSame($expected, $this->codesAndParams()['contractorName'] ?? null);

        $send('POST', $uri, 'application/ld+json', ['contractorName' => 'Length test'] + $valid);
        self::assertResponseStatusCodeSame(201);
        $id = self::intAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'id');

        $send('PATCH', $uri.'/'.$id, 'application/merge-patch+json', ['contractorName' => $tooLong]);
        self::assertSame($expected, $this->codesAndParams()['contractorName'] ?? null);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function resourcesWithAContractorName(): iterable
    {
        yield 'operation' => ['/api/account_operations', ['operationDate' => '2026-05-05', 'amount' => '-1.00']];
        yield 'fixed expense' => ['/api/fixed_expenses', ['day' => 5, 'amount' => '-1.00']];
    }

    /**
     * The database driver cuts text at a NUL character: "ab\0cd" would be stored as "ab".
     *
     * @param array<string, mixed> $payload
     */
    #[DataProvider('textFieldsWithANullCharacter')]
    public function testNullCharacterInTextIsAViolationAndNothingIsStored(
        string $uri,
        array $payload,
        string $field,
    ): void {
        $countBefore = $this->rowCounts();

        $this->client->request('POST', $uri, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'HTTP_ACCEPT' => 'application/ld+json',
            'CONTENT_TYPE' => 'application/ld+json',
        ], content: json_encode($payload, JSON_THROW_ON_ERROR));

        self::assertSame(
            ['code' => 'common.text_contains_null_character', 'params' => []],
            $this->codesAndParams()[$field] ?? null,
        );
        self::assertSame($countBefore, $this->rowCounts());
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function textFieldsWithANullCharacter(): iterable
    {
        $text = "ab\0cd";
        yield 'contractor name' => ['/api/contractors', ['name' => $text], 'name'];
        yield 'operation contractor name' => ['/api/account_operations', ['contractorName' => $text], 'contractorName'];
        yield 'operation description' => ['/api/account_operations', ['description' => $text], 'description'];
        yield 'fixed expense contractor name' => ['/api/fixed_expenses', ['contractorName' => $text], 'contractorName'];
        yield 'fixed expense description' => ['/api/fixed_expenses', ['description' => $text], 'description'];
        yield 'transfer description' => ['/api/transfers', ['description' => $text], 'description'];
        yield 'gold asset description' => ['/api/gold_assets', ['description' => $text], 'description'];
    }

    /**
     * @param array{code: string, params: array<string, string>} $expected
     */
    #[DataProvider('invalidSearchTexts')]
    public function testSearchFiltersRejectInvalidText(string $query, array $expected): void
    {
        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'HTTP_ACCEPT' => 'application/ld+json',
        ];

        foreach (['/api/contractors?name=' => 'name', '/api/account_operations?q=' => 'q'] as $uri => $parameter) {
            $this->client->request('GET', $uri.$query, server: $headers);

            self::assertSame($expected, $this->codesAndParams()[$parameter] ?? null, $uri);
        }
    }

    /**
     * @return iterable<string, array{string, array{code: string, params: array<string, string>}}>
     */
    public static function invalidSearchTexts(): iterable
    {
        $tooShort = ['code' => 'validation.too_short', 'params' => ['max' => '255', 'limit' => '2', 'min' => '2']];

        yield 'one character surrounded by spaces' => ['%20a%20', $tooShort];
        yield 'too long' => [
            str_repeat('a', 256),
            ['code' => 'validation.too_long', 'params' => ['min' => '2', 'limit' => '255', 'max' => '255']],
        ];
        yield 'null character' => ['ab%00cd', ['code' => 'common.text_contains_null_character', 'params' => []]];
    }

    public function testBlankSearchFiltersGetOneViolation(): void
    {
        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'HTTP_ACCEPT' => 'application/ld+json',
        ];

        $this->client->request('GET', '/api/account_operations?q=%20%20', server: $headers);
        self::assertSame(['q' => ['code' => 'validation.is_blank', 'params' => []]], $this->codesAndParams());

        $this->client->request('GET', '/api/contractors?name=%20%20', server: $headers);
        self::assertSame(
            ['name' => ['code' => 'validation.too_short', 'params' => ['max' => '255', 'limit' => '2', 'min' => '2']]],
            $this->codesAndParams(),
        );
    }

    public function testContractorListStillWorksWithoutTheOptionalFilter(): void
    {
        $this->client->request('GET', '/api/contractors', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        self::assertResponseIsSuccessful();
    }

    /**
     * A new free-text field must be protected too, or it silently loses everything after a NUL character. Fields
     * listed here accept only a fixed format or an existing code, so a cut value is refused or stores nothing new.
     */
    public function testEveryWritableTextFieldRejectsTheNullCharacter(): void
    {
        $fixedFormat = [
            'amount', 'amountFrom', 'amountTo', 'ounces', 'pricePln', 'rateToPln',
            'operationDate', 'priceDate', 'rateDate', 'currencyCode',
        ];
        $unprotected = [];

        foreach (glob(__DIR__.'/../../src/ApiResource/*.php') ?: [] as $file) {
            $class = 'App\\ApiResource\\'.basename($file, '.php');
            self::assertTrue(class_exists($class));
            $operations = (new \ReflectionClass($class))->getAttributes(ApiResource::class)[0]->newInstance()
                ->getOperations();
            $writes = false;
            foreach ($operations ?? [] as $operation) {
                $writes = $writes || $operation instanceof Post || $operation instanceof Patch;
            }
            if (!$writes) {
                continue;
            }

            foreach ((new \ReflectionClass($class))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                $type = $property->getType();
                $apiProperty = $property->getAttributes(ApiProperty::class)[0] ?? null;
                if (
                    !$type instanceof \ReflectionNamedType
                    || 'string' !== $type->getName()
                    || false === $apiProperty?->newInstance()->isWritable()
                    || in_array($property->getName(), $fixedFormat, true)
                    || [] !== $property->getAttributes(NoNullCharacter::class)
                ) {
                    continue;
                }

                $unprotected[] = $class.'::$'.$property->getName();
            }
        }

        self::assertSame([], $unprotected);
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
            [
                'q' => [
                    'code' => 'validation.too_short',
                    'params' => [
                        'max' => '255',
                        'limit' => '2',
                        'min' => '2'
                    ]
                ]
            ],
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

    /**
     * @return array<class-string, int>
     */
    private function rowCounts(): array
    {
        $counts = [];
        foreach ([Contractor::class, AccountOperation::class, FixedExpense::class, GoldAsset::class] as $class) {
            $counts[$class] = $this->em()->getRepository($class)->count([]);
        }

        return $counts;
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
