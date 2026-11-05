<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Contractor;
use App\Tests\DatabaseTestTrait;
use App\Tests\JwtTrait;
use App\Tests\LosesContractorRaceTrait;
use App\Tests\TestDataTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContractorApiTest extends WebTestCase
{
    use DatabaseTestTrait;
    use LosesContractorRaceTrait;
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

    #[DataProvider('duplicateNameWhitespace')]
    public function testRepeatedPostReturns409AndPreservesExistingContractor(string $whitespace): void
    {
        $token = $this->jwt();
        $name = 'API contractor '.uniqid();
        $countBefore = $this->em()->getRepository(Contractor::class)->count([]);

        $this->postContractor($name, $token);
        self::assertResponseStatusCodeSame(201);

        $created = self::decodeObject((string) $this->client->getResponse()->getContent());
        $id = self::intAt($created, 'id');
        self::assertSame($name, self::stringAt($created, 'name'));

        $this->postContractor($whitespace.$name.$whitespace, $token);

        $this->assertDuplicateResponse();
        self::assertSame(
            $countBefore + 1,
            $this->em()->getRepository(Contractor::class)->count([]));
        self::assertSame(
            $id,
            $this->em()->getRepository(Contractor::class)->findOneBy(['name' => $name])?->getId()
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function duplicateNameWhitespace(): iterable
    {
        yield 'exact name' => [''];
        yield 'name with surrounding spaces' => ['  '];
    }

    public function testDuplicateCaughtOnlyByTheDatabaseIsTheSameConflict(): void
    {
        $token = $this->jwt();
        $name = 'API race '.uniqid();
        $this->loseTheNextContractorRace($this->client);

        $this->postContractor($name, $token);

        $this->assertDuplicateResponse();
        self::assertSame(
            1,
            $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM contractor WHERE name = ?', [$name]),
        );
    }

    /**
     * @param array<string, mixed> $record
     */
    #[DataProvider('recordsCreatingAContractor')]
    public function testLosingTheContractorRaceRefusesTheRecordWithoutSql(string $uri, array $record): void
    {
        if ('/api/account_operations' === $uri) {
            $account = $this->account('Bank1-ROR-PLN');
            $record['accountId'] = $account->getId();
        }
        $token = $this->jwt();
        $name = 'Race in a record '.uniqid();
        $this->loseTheNextContractorRace($this->client);

        $this->client->request('POST', $uri, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/ld+json',
        ], content: json_encode(['contractorName' => $name] + $record, JSON_THROW_ON_ERROR));

        $this->assertDuplicateResponse();
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function recordsCreatingAContractor(): iterable
    {
        yield 'operation' => ['/api/account_operations', ['operationDate' => '2026-05-05', 'amount' => '-1.00']];
        yield 'fixed expense' => ['/api/fixed_expenses', ['day' => 5, 'amount' => '-1.00']];
    }

    private function postContractor(string $name, string $token): void
    {
        $this->client->request('POST', '/api/contractors', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/ld+json',
        ], content: json_encode(['name' => $name], JSON_THROW_ON_ERROR));
    }

    private function assertDuplicateResponse(): void
    {
        self::assertResponseStatusCodeSame(409);
        $error = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame('contractor.name_not_unique', self::stringAt($error, 'errorCode'));
        self::assertSame('urn:finance-app:problem:contractor.name_not_unique', self::stringAt($error, 'type'));
        self::assertSame([], $error['params'] ?? null);
        self::assertSame('A contractor with this name already exists.', self::stringAt($error, 'detail'));
        self::assertStringNotContainsString('SQLSTATE', self::stringAt($error, 'detail'));
    }
}
