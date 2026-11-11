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
    }

    /**
     * @param list<string> $alwaysThere
     */
    #[DataProvider('answers')]
    public function testAnAnswerSaysWhichFieldsItAlwaysHasAndThatTheyAreNeverNull(
        string $schema,
        array $alwaysThere,
    ): void {
        $client = self::createClient();
        $client->request('GET', '/api/docs.jsonopenapi');

        self::assertResponseIsSuccessful();
        $document = self::decodeObject((string) $client->getResponse()->getContent());
        $answer = self::objectAt($document, 'components', 'schemas', $schema);
        self::assertSame($alwaysThere, $answer['required'] ?? []);
        foreach ($alwaysThere as $field) {
            // One type, not a list of types with "null" in it.
            self::assertIsString(self::objectAt($answer, 'properties', $field)['type'] ?? null, $schema.'.'.$field);
        }
    }
}
