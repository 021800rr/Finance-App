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

    #[DataProvider('daysOutOfRange')]
    public function testDayOutOfRangeIsAViolationOnCreateAndUpdate(int $day): void
    {
        $expected = ['code' => 'fixed_expense.day_out_of_range', 'params' => ['min' => '1', 'max' => '28']];
        $valid = ['contractorName' => 'Day test', 'amount' => '-1.00'];

        $this->send('POST', '/api/fixed_expenses', ['day' => $day] + $valid);
        self::assertSame(['day' => $expected], $this->codesAndParams());

        $this->send('POST', '/api/fixed_expenses', ['day' => 5] + $valid);
        self::assertResponseStatusCodeSame(201);
        $id = self::intAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'id');

        $this->send('PATCH', '/api/fixed_expenses/'.$id, ['day' => $day]);
        self::assertSame(['day' => $expected], $this->codesAndParams());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function daysOutOfRange(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'above the maximum' => [29];
    }

    /**
     * Also amounts that are rounded to zero when stored.
     */
    #[DataProvider('zeroAmounts')]
    public function testZeroAmountIsAViolationOnCreateAndUpdate(string $amount): void
    {
        $expected = ['amount' => ['code' => 'common.amount_cannot_be_zero', 'params' => []]];
        $valid = $this->validOperation();
        $countBefore = $this->rowCounts();

        $this->send('POST', '/api/account_operations', ['amount' => $amount] + $valid);
        self::assertSame($expected, $this->codesAndParams());
        self::assertSame($countBefore, $this->rowCounts());

        $this->send('POST', '/api/account_operations', ['amount' => '-1.00'] + $valid);
        self::assertResponseStatusCodeSame(201);
        $id = self::intAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'id');

        $this->send('PATCH', '/api/account_operations/'.$id, ['amount' => $amount]);
        self::assertSame($expected, $this->codesAndParams());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function zeroAmounts(): iterable
    {
        yield 'zero' => ['0'];
        yield 'with decimals' => ['0.00'];
        yield 'negative zero' => ['-0'];
        yield 'rounded down to zero' => ['0.004'];
        yield 'negative, rounded to zero' => ['-0.004'];
    }

    public function testSmallestAmountIsNotTreatedAsZero(): void
    {
        $this->send('POST', '/api/account_operations', ['amount' => '0.005'] + $this->validOperation());

        self::assertResponseStatusCodeSame(201);
        self::assertSame(
            '0.01',
            self::stringAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'amount'),
        );
    }

    /**
     * @param array<string, mixed> $contractor
     */
    #[DataProvider('missingContractors')]
    public function testMissingContractorIsAViolationAndNothingIsStored(array $contractor): void
    {
        $expected = ['contractorName' => ['code' => 'contractor.id_or_name_required', 'params' => []]];
        $countBefore = $this->rowCounts();

        $operation = $this->validOperation();
        unset($operation['contractorName']);

        $this->send('POST', '/api/account_operations', $contractor + $operation);
        self::assertSame($expected, $this->codesAndParams());

        $this->send('POST', '/api/fixed_expenses', $contractor + ['day' => 5, 'amount' => '-1.00']);
        self::assertSame($expected, $this->codesAndParams());

        self::assertSame($countBefore, $this->rowCounts());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function missingContractors(): iterable
    {
        yield 'no fields' => [[]];
        yield 'both null' => [['contractorId' => null, 'contractorName' => null]];
        yield 'empty name' => [['contractorName' => '']];
        yield 'name of spaces' => [['contractorName' => '   ']];
    }

    public function testMissingContractorIsReportedTogetherWithOtherFields(): void
    {
        $this->send('POST', '/api/fixed_expenses', ['day' => 29, 'amount' => '1.00']);

        self::assertSame(
            ['contractorName', 'day', 'amount'],
            array_keys($this->codesAndParams()),
        );
    }

    /**
     * @param array<string, mixed> $contractor
     */
    #[DataProvider('clearedContractors')]
    public function testClearingTheContractorIsAViolationOnUpdate(array $contractor): void
    {
        $valid = ['contractorName' => 'Clear test', 'day' => 5, 'amount' => '-1.00'];
        $this->send('POST', '/api/fixed_expenses', $valid);
        self::assertResponseStatusCodeSame(201);
        $id = self::intAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'id');

        $this->send('PATCH', '/api/fixed_expenses/'.$id, $contractor);

        self::assertSame(
            ['contractorName' => ['code' => 'contractor.id_or_name_required', 'params' => []]],
            $this->codesAndParams(),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function clearedContractors(): iterable
    {
        yield 'both null' => [['contractorId' => null, 'contractorName' => null]];
        yield 'blank name' => [['contractorName' => '   ']];
    }

    public function testUpdateWithoutContractorFieldsKeepsTheContractor(): void
    {
        $this->send('POST', '/api/fixed_expenses', ['contractorName' => 'Keep test', 'day' => 5, 'amount' => '-1.00']);
        self::assertResponseStatusCodeSame(201);
        $id = self::intAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'id');

        $this->send('PATCH', '/api/fixed_expenses/'.$id, ['day' => 6]);

        self::assertResponseIsSuccessful();
        self::assertSame(
            'Keep test',
            self::stringAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'contractorName'),
        );
    }

    public function testHiddenNullIsAViolationAndTheAccountStaysUnchanged(): void
    {
        $account = $this->em()->getRepository(Account::class)->findOneBy(['accountNumber' => 'Bank1-ROR-PLN']);
        self::assertNotNull($account);
        $hidden = $account->isHidden();

        $this->send('PATCH', '/api/accounts/'.$account->getId(), ['hidden' => null]);

        self::assertSame(
            ['hidden' => ['code' => 'account.hidden_not_null', 'params' => []]],
            $this->codesAndParams(),
        );
        $this->em()->clear();
        self::assertSame($hidden, $this->em()->find(Account::class, $account->getId())?->isHidden());
    }

    #[DataProvider('isMainValuesOtherThanTrue')]
    public function testIsMainOtherThanTrueIsAViolationOnAnyAccount(mixed $isMain): void
    {
        $main = $this->accountId('Bank1-ROR-PLN');
        $other = $this->accountId('Bank1-WAK-PLN');
        $this->send('PATCH', '/api/accounts/'.$main, ['isMain' => true]);
        self::assertResponseIsSuccessful();
        $expected = ['isMain' => ['code' => 'account.is_main_must_be_true', 'params' => []]];

        foreach ([$main, $other] as $id) {
            $this->send('PATCH', '/api/accounts/'.$id, ['isMain' => $isMain]);
            self::assertSame($expected, $this->codesAndParams());
        }

        $this->em()->clear();
        self::assertTrue($this->em()->find(Account::class, $main)?->isMain());
        self::assertFalse($this->em()->find(Account::class, $other)?->isMain());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function isMainValuesOtherThanTrue(): iterable
    {
        yield 'false' => [false];
        yield 'null' => [null];
        yield 'text' => ['x'];
        yield 'text true' => ['true'];
        yield 'one' => [1];
        yield 'zero' => [0];
        yield 'list' => [[true]];
        yield 'object' => [new \stdClass()];
    }

    public function testIsMainTrueMovesTheMainAccount(): void
    {
        $previous = $this->accountId('Bank1-ROR-PLN');
        $next = $this->accountId('Bank1-WAK-PLN');
        $this->send('PATCH', '/api/accounts/'.$previous, ['isMain' => true]);
        self::assertResponseIsSuccessful();

        $this->send('PATCH', '/api/accounts/'.$next, ['isMain' => true]);

        self::assertResponseIsSuccessful();
        $this->em()->clear();
        self::assertFalse($this->em()->find(Account::class, $previous)?->isMain());
        self::assertTrue($this->em()->find(Account::class, $next)?->isMain());
    }

    public function testLeavingIsMainOutKeepsItOnEveryAccount(): void
    {
        $main = $this->accountId('Bank1-ROR-PLN');
        $other = $this->accountId('Bank1-WAK-PLN');
        $this->send('PATCH', '/api/accounts/'.$main, ['isMain' => true]);
        self::assertResponseIsSuccessful();

        foreach ([$main, $other] as $id) {
            $this->send('PATCH', '/api/accounts/'.$id, ['hidden' => false]);
            self::assertResponseIsSuccessful();
        }

        $this->em()->clear();
        self::assertTrue($this->em()->find(Account::class, $main)?->isMain());
        self::assertFalse($this->em()->find(Account::class, $other)?->isMain());
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
        yield 'null character' => ['ab%00cd', ['code' => 'common.text_contains_null_character', 'params' => []]];
    }

    public function testTooLongSearchTextIsAViolationWithTheLimitsOfEachFilter(): void
    {
        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'HTTP_ACCEPT' => 'application/ld+json',
        ];
        $tooLong = str_repeat('a', 256);

        $this->client->request('GET', '/api/contractors?name='.$tooLong, server: $headers);
        self::assertSame(
            ['code' => 'validation.too_long', 'params' => ['min' => '1', 'limit' => '255', 'max' => '255']],
            $this->codesAndParams()['name'] ?? null,
        );

        $this->client->request('GET', '/api/account_operations?q='.$tooLong, server: $headers);
        self::assertSame(
            ['code' => 'validation.too_long', 'params' => ['min' => '1', 'limit' => '255', 'max' => '255']],
            $this->codesAndParams()['q'] ?? null,
        );
    }

    public function testOneCharacterContractorCanBeCreatedAndFoundFirst(): void
    {
        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'HTTP_ACCEPT' => 'application/ld+json',
        ];

        $this->client->request('POST', '/api/contractors', server: $headers + [
            'CONTENT_TYPE' => 'application/ld+json',
        ], content: json_encode(['name' => 'X'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', '/api/contractors?name=%20x%20', server: $headers);
        self::assertResponseIsSuccessful();
        $members = self::rowsAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'member');
        self::assertSame('X', $members[0]['name'] ?? null);
    }

    public function testBlankContractorNameIsAViolation(): void
    {
        $this->client->request('POST', '/api/contractors', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'HTTP_ACCEPT' => 'application/ld+json',
            'CONTENT_TYPE' => 'application/ld+json',
        ], content: json_encode(['name' => ' '], JSON_THROW_ON_ERROR));

        self::assertSame(
            ['name' => ['code' => 'contractor.name_not_blank', 'params' => []]],
            $this->codesAndParams(),
        );
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
            ['name' => ['code' => 'validation.too_short', 'params' => ['max' => '255', 'limit' => '1', 'min' => '1']]],
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
        $this->client->request('GET', '/api/contractors?name=', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        self::assertSame(
            [
                'name' => [
                    'code' => 'validation.too_short',
                    'params' => [
                        'max' => '255',
                        'limit' => '1',
                        'min' => '1'
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
     * @param array<string, mixed> $payload
     */
    private function send(string $method, string $uri, array $payload): void
    {
        $type = 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json';
        $this->client->request($method, $uri, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'HTTP_ACCEPT' => 'application/ld+json',
            'CONTENT_TYPE' => $type,
        ], content: json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function validOperation(): array
    {
        $account = $this->em()->getRepository(Account::class)->findOneBy(['accountNumber' => 'Bank1-ROR-PLN']);
        self::assertNotNull($account);

        return [
            'accountId' => $account->getId(),
            'contractorName' => 'Valid operation',
            'operationDate' => '2026-05-05',
            'amount' => '-1.00',
        ];
    }

    private function accountId(string $accountNumber): int
    {
        $account = $this->em()->getRepository(Account::class)->findOneBy(['accountNumber' => $accountNumber]);
        self::assertNotNull($account);

        return (int) $account->getId();
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
