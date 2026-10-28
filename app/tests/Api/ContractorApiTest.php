<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Contractor;
use App\Tests\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContractorApiTest extends WebTestCase
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

    #[DataProvider('duplicateNameWhitespace')]
    public function testRepeatedPostReturns409AndPreservesExistingContractor(string $whitespace): void
    {
        $token = $this->obtainJwtToken();
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
        self::assertSame('A contractor with this name already exists.', self::stringAt($error, 'detail'));
        self::assertStringNotContainsString('SQLSTATE', self::stringAt($error, 'detail'));
    }

    private function obtainJwtToken(): string
    {
        $this->client->request('POST', '/api/login_check', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'test@example.com', 'password' => 'test1234'], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        return self::stringAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'token');
    }
}
