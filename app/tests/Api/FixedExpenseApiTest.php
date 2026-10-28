<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\FixedExpense;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FixedExpenseApiTest extends WebTestCase
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

    public function testPostRejectsDay29WithoutSavingExpense(): void
    {
        $contractor = new Contractor('Invalid day API test '.uniqid());
        $this->em()->persist($contractor);
        $this->em()->flush();
        $countBefore = $this->em()->getRepository(FixedExpense::class)->count([]);

        $this->client->request('POST', '/api/fixed_expenses', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'CONTENT_TYPE' => 'application/ld+json',
        ], content: json_encode([
            'contractorId' => $contractor->getId(),
            'day' => 29,
            'amount' => '-150.00',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
        $error = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame('Day must be between 1 and 28.', self::stringAt($error, 'detail'));
        self::assertSame($countBefore, $this->em()->getRepository(FixedExpense::class)->count([]));
    }

    public function testPatchRejectsDay29WithoutChangingExpense(): void
    {
        $fixedExpense = $this->createFixedExpense();
        $id = $fixedExpense->getId();

        $this->patch($fixedExpense, ['day' => 29, 'amount' => '-99.99', 'description' => 'Changed description']);

        self::assertResponseStatusCodeSame(422);
        $error = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame('Day must be between 1 and 28.', self::stringAt($error, 'detail'));
        $this->em()->clear();
        $unchanged = $this->em()->find(FixedExpense::class, $id);
        self::assertNotNull($unchanged);
        self::assertSame(10, $unchanged->getDay());
        self::assertSame(-15000, $unchanged->getAmount());
        self::assertNull($unchanged->getDescription());
    }

    public function testPatchWithANewContractorNameAssignsTheFixedExpenseToACreatedContractor(): void
    {
        $fixedExpense = $this->createFixedExpense();
        $newName = 'Brand new contractor '.uniqid();

        $this->patch($fixedExpense, ['contractorName' => $newName]);

        self::assertResponseIsSuccessful();
        $this->em()->clear();
        $reloaded = $this->em()->find(FixedExpense::class, $fixedExpense->getId());
        self::assertSame($newName, $reloaded?->getContractor()->getName());
        self::assertSame(-15000, $reloaded->getAmount());
    }

    public function testPatchWithTheNameOfAnExistingContractorAssignsThatContractor(): void
    {
        $fixedExpense = $this->createFixedExpense();
        $existing = new Contractor('Existing '.uniqid());
        $this->em()->persist($existing);
        $this->em()->flush();
        $contractorsBefore = $this->em()->getRepository(Contractor::class)->count([]);

        $this->patch($fixedExpense, ['contractorName' => $existing->getName()]);

        self::assertResponseIsSuccessful();
        $this->em()->clear();
        $reloaded = $this->em()->find(FixedExpense::class, $fixedExpense->getId());
        self::assertSame($existing->getId(), $reloaded?->getContractor()->getId());
        self::assertSame($contractorsBefore, $this->em()->getRepository(Contractor::class)->count([]));
    }

    public function testPatchWithANewContractorIdAssignsThatContractor(): void
    {
        $fixedExpense = $this->createFixedExpense();
        $other = new Contractor('Other '.uniqid());
        $this->em()->persist($other);
        $this->em()->flush();

        $this->patch($fixedExpense, ['contractorId' => $other->getId()]);

        self::assertResponseIsSuccessful();
        $this->em()->clear();
        $reloaded = $this->em()->find(FixedExpense::class, $fixedExpense->getId());
        self::assertSame($other->getId(), $reloaded?->getContractor()->getId());
    }

    public function testPatchWithoutAContractorKeepsTheCurrentOne(): void
    {
        $fixedExpense = $this->createFixedExpense();
        $contractorId = $fixedExpense->getContractor()->getId();

        $this->patch($fixedExpense, ['day' => 20]);

        self::assertResponseIsSuccessful();
        $this->em()->clear();
        $reloaded = $this->em()->find(FixedExpense::class, $fixedExpense->getId());
        self::assertSame($contractorId, $reloaded?->getContractor()->getId());
        self::assertSame(20, $reloaded->getDay());
    }

    /**
     * @param array<string, mixed> $body
     */
    private function patch(FixedExpense $fixedExpense, array $body): void
    {
        $this->client->request(
            'PATCH',
            '/api/fixed_expenses/'.$fixedExpense->getId(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(), 'CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    private function obtainJwtToken(): string
    {
        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'test@example.com', 'password' => 'test1234'], JSON_THROW_ON_ERROR),
        );

        return self::stringAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'token');
    }

    private function createFixedExpense(): FixedExpense
    {
        $contractor = new Contractor('Original '.uniqid());
        $this->em()->persist($contractor);

        $currency = $this->em()->getRepository(Currency::class)->find('PLN');
        self::assertNotNull($currency);

        $fixedExpense = new FixedExpense($contractor, 10, -15000, $currency);
        $this->em()->persist($fixedExpense);
        $this->em()->flush();

        return $fixedExpense;
    }
}
