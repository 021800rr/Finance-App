<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Tests\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GoldAssetApiTest extends WebTestCase
{
    use DatabaseTestTrait;
    use ReadsJsonTrait;

    private KernelBrowser $client;
    private GoldForm $goldForm;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->initEntityManager($this->client->getKernel());
        $goldForm = $this->em()->getRepository(GoldForm::class)->findOneBy(['name' => 'sztabka']);
        self::assertNotNull($goldForm);
        $this->goldForm = $goldForm;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    /**
     * @param array<string, string|null> $description
     */
    #[DataProvider('blankDescriptions')]
    public function testPostRejectsBlankDescriptionWithFieldErrorWithoutSaving(array $description): void
    {
        $countBefore = $this->em()->getRepository(GoldAsset::class)->count([]);

        $this->post($description);

        self::assertResponseStatusCodeSame(422);
        $error = self::decodeObject((string) $this->client->getResponse()->getContent());
        $violations = self::rowsAt($error, 'violations');
        self::assertCount(1, $violations);
        self::assertSame('description', self::stringAt($violations[0], 'propertyPath'));
        self::assertSame('Please enter a description.', self::stringAt($violations[0], 'message'));
        self::assertSame($countBefore, $this->em()->getRepository(GoldAsset::class)->count([]));
    }

    public function testPostWithDescriptionCreatesAsset(): void
    {
        $countBefore = $this->em()->getRepository(GoldAsset::class)->count([]);

        $this->post(['description' => 'Sztabka 1oz']);

        self::assertResponseStatusCodeSame(201);
        $data = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame('Sztabka 1oz', self::stringAt($data, 'description'));
        $this->em()->clear();
        $stored = $this->em()->find(GoldAsset::class, self::intAt($data, 'id'));
        self::assertNotNull($stored);
        self::assertSame('Sztabka 1oz', $stored->getDescription());
        self::assertSame(10000, $stored->getOunces());
        self::assertSame($this->goldForm->getId(), $stored->getForm()->getId());
        self::assertSame($countBefore + 1, $this->em()->getRepository(GoldAsset::class)->count([]));
    }

    /**
     * @return array<string, array{array<string, string|null>}>
     */
    public static function blankDescriptions(): array
    {
        return [
            'empty' => [['description' => '']],
            'spaces' => [['description' => '   ']],
            'null' => [['description' => null]],
            'missing' => [[]],
        ];
    }

    /**
     * @param array<string, string|null> $description
     */
    private function post(array $description): void
    {
        $this->client->request('POST', '/api/gold_assets', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], content: json_encode([
            'formId' => $this->goldForm->getId(),
            'ounces' => '1.0000',
        ] + $description, JSON_THROW_ON_ERROR));
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
