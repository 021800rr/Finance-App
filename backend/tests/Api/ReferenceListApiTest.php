<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Household;
use App\Tests\DatabaseTestTrait;
use App\Tests\JwtTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The lists of banks, account types, households and gold forms: every record, sorted by name, with its fields.
 * The fixtures are created in alphabetical order, so each test adds a record last that must come first: only a sort by
 * name puts it there.
 */
final class ReferenceListApiTest extends WebTestCase
{
    use DatabaseTestTrait;
    use JwtTrait;
    use ReadsJsonTrait;
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

    public function testBanksAreListedByName(): void
    {
        $this->createBank('Aaa test bank');

        self::assertSame([
            ['name' => 'Aaa test bank'],
            ['name' => 'Bank1'],
            ['name' => 'Bank2'],
            ['name' => 'Bank3'],
            ['name' => 'Bank4'],
        ], $this->listed('/api/banks', 'name'));
    }

    public function testAccountTypesAreListedByNameWithTheRetirementFlag(): void
    {
        $this->createAccountType('Aaa test type', retirement: true);

        self::assertSame([
            ['name' => 'Aaa test type', 'isRetirement' => true],
            ['name' => 'Emerytura', 'isRetirement' => true],
            ['name' => 'Oszczędności', 'isRetirement' => false],
            ['name' => 'ROR', 'isRetirement' => false],
            ['name' => 'Wakacje', 'isRetirement' => false],
        ], $this->listed('/api/account_types', 'name', 'isRetirement'));
    }

    public function testHouseholdsAreListedByNameWithTheOwnFundsFlag(): void
    {
        $this->em()->persist(new Household('Aaa test household', false));
        $this->em()->flush();

        self::assertSame([
            ['name' => 'Aaa test household', 'isOwnFunds' => false],
            ['name' => 'Matka', 'isOwnFunds' => false],
            ['name' => 'Nasze', 'isOwnFunds' => true],
        ], $this->listed('/api/households', 'name', 'isOwnFunds'));
    }

    public function testGoldFormsAreListedByName(): void
    {
        $this->createGoldForm('aaa test form');

        self::assertSame([
            ['name' => 'aaa test form'],
            ['name' => 'moneta'],
            ['name' => 'sztabka'],
        ], $this->listed('/api/gold_forms', 'name'));
    }

    /**
     * Every listed record with an integer id, reduced to the given fields in their order.
     *
     * @return list<array<string, mixed>>
     */
    private function listed(string $uri, string ...$fields): array
    {
        $this->client->request('GET', $uri, server: $this->authHeaders() + ['HTTP_ACCEPT' => 'application/ld+json']);
        self::assertResponseIsSuccessful();
        $rows = self::rowsAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'member');

        return array_map(static function (array $row) use ($fields): array {
            self::assertIsInt($row['id'] ?? null);

            return array_combine($fields, array_map(static fn (string $field): mixed => $row[$field] ?? null, $fields));
        }, $rows);
    }
}
