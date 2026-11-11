<?php

declare(strict_types=1);

namespace App\Tests\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The OpenAPI document is a contract: clients generate their types from it, the React front among them. A field that
 * every answer has must be described so (required, never null), or each client keeps checking for a null that never
 * comes, and a renamed field slips through unnoticed.
 */
final class OpenApiContractTest extends WebTestCase
{
    use ReadsJsonTrait;

    /**
     * The answers described exactly so far, and the fields each of them always has.
     *
     * @return iterable<string, array{string, list<string>}>
     */
    public static function answers(): iterable
    {
        yield 'bank' => ['Bank', ['id', 'name']];
        yield 'currency' => ['Currency', ['code']];
        yield 'gold form' => ['GoldForm', ['id', 'name']];
        yield 'account type' => ['AccountType', ['id', 'name', 'isRetirement']];
        yield 'household' => ['Household', ['id', 'name', 'isOwnFunds']];
        yield 'logged-in user' => ['Me', ['email']];
        yield 'fixed expense' => [
            'FixedExpense',
            ['id', 'contractorId', 'contractorName', 'day', 'amount', 'currencyCode'],
        ];
        yield 'fixed expense plan' => ['FixedExpensePlan', ['accountId', 'month', 'createdCount']];
        yield 'gold asset' => ['GoldAsset', ['id', 'formId', 'formName', 'description', 'ounces']];
        yield 'gold price' => ['GoldPrice', ['id', 'goldAssetId', 'priceDate', 'pricePln', 'currencyCode']];
        yield 'exchange rate' => ['CurrencyExchangeRate', ['id', 'bankId', 'currencyCode', 'rateDate', 'rateToPln']];
        yield 'contractor' => ['Contractor', ['id', 'name']];
        yield 'account' => [
            'Account',
            [
                'id', 'accountNumber', 'hidden', 'isMain', 'bankName', 'currencyCode', 'typeName', 'householdName',
                'displayLabel',
            ],
        ];
    }

    /**
     * @param list<string> $alwaysThere
     */
    #[DataProvider('answers')]
    public function testAnAnswerSaysWhichFieldsItAlwaysHasAndThatTheyAreNeverNull(
        string $schema,
        array $alwaysThere,
    ): void {
        $answer = $this->schema($schema);

        self::assertSame($alwaysThere, $answer['required'] ?? []);
        foreach ($alwaysThere as $field) {
            // One type, not a list of types with "null" in it.
            self::assertIsString(self::objectAt($answer, 'properties', $field)['type'] ?? null, $schema.'.'.$field);
        }
    }

    /**
     * Fields some answers leave out: not required, but never null when they are there.
     *
     * @return iterable<string, array{string, list<string>}>
     */
    public static function fieldsSomeAnswersLeaveOut(): iterable
    {
        // Only GET /api/accounts/{id} works out the balances; the list and the answer to PATCH leave them out.
        yield 'balances of an account' => ['Account', ['saldo', 'saldoToday', 'hasFutureOperations']];
    }

    /**
     * @param list<string> $sometimesThere
     */
    #[DataProvider('fieldsSomeAnswersLeaveOut')]
    public function testAFieldSomeAnswersLeaveOutIsNotRequiredButNeverNull(string $schema, array $sometimesThere): void
    {
        $answer = $this->schema($schema);
        $required = $answer['required'] ?? [];
        self::assertIsArray($required);

        foreach ($sometimesThere as $field) {
            self::assertNotContains($field, $required, $schema.'.'.$field);
            self::assertIsString(self::objectAt($answer, 'properties', $field)['type'] ?? null, $schema.'.'.$field);
        }
    }

    /**
     * One schema of the components of the OpenAPI document, as clients read it.
     *
     * @return array<string, mixed>
     */
    private function schema(string $name): array
    {
        $client = self::createClient();
        $client->request('GET', '/api/docs.jsonopenapi');

        self::assertResponseIsSuccessful();
        $document = self::decodeObject((string) $client->getResponse()->getContent());

        return self::objectAt($document, 'components', 'schemas', $name);
    }
}
