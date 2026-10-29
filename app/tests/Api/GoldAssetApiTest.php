<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Currency;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Entity\GoldPrice;
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

    public function testPatchDescriptionKeepsOuncesAndForm(): void
    {
        $asset = $this->createAsset();
        $countBefore = $this->em()->getRepository(GoldAsset::class)->count([]);

        $this->patch($asset->getId(), ['description' => 'Updated description']);

        self::assertResponseStatusCodeSame(200);
        $data = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame($asset->getId(), self::intAt($data, 'id'));
        self::assertSame('Updated description', self::stringAt($data, 'description'));
        self::assertSame('1.0000', self::stringAt($data, 'ounces'));
        self::assertSame($this->goldForm->getId(), self::intAt($data, 'formId'));
        $this->em()->clear();
        $stored = $this->em()->find(GoldAsset::class, $asset->getId());
        self::assertNotNull($stored);
        self::assertSame('Updated description', $stored->getDescription());
        self::assertSame(10000, $stored->getOunces());
        self::assertSame($this->goldForm->getId(), $stored->getForm()->getId());
        self::assertSame($countBefore, $this->em()->getRepository(GoldAsset::class)->count([]));
    }

    public function testPatchUpdatesAllEditableFieldsAndPreservesPriceHistory(): void
    {
        $asset = $this->createAsset();
        $otherForm = $this->em()->getRepository(GoldForm::class)->findOneBy(['name' => 'moneta']);
        self::assertNotNull($otherForm);
        $currency = $this->em()->find(Currency::class, 'PLN');
        self::assertNotNull($currency);
        $price = new GoldPrice($asset, new \DateTimeImmutable('2026-01-15'), 123456, $currency);
        $this->em()->persist($price);
        $this->em()->flush();
        $pricesBefore = $this->em()->getConnection()->fetchAllAssociative('SELECT * FROM gold_price ORDER BY id');
        $countBefore = $this->em()->getRepository(GoldAsset::class)->count([]);

        $this->patch($asset->getId(), [
            'formId' => $otherForm->getId(),
            'ounces' => '2.2500',
            'description' => '  Updated coin  ',
            'id' => 999999999,
            'formName' => 'Ignored read-only name',
        ]);

        self::assertResponseStatusCodeSame(200);
        $data = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame($asset->getId(), self::intAt($data, 'id'));
        self::assertSame($otherForm->getId(), self::intAt($data, 'formId'));
        self::assertSame('moneta', self::stringAt($data, 'formName'));
        self::assertSame('Updated coin', self::stringAt($data, 'description'));
        self::assertSame('2.2500', self::stringAt($data, 'ounces'));
        $this->em()->clear();
        $stored = $this->em()->find(GoldAsset::class, $asset->getId());
        self::assertNotNull($stored);
        self::assertSame('Updated coin', $stored->getDescription());
        self::assertSame(22500, $stored->getOunces());
        self::assertSame($otherForm->getId(), $stored->getForm()->getId());
        self::assertSame($countBefore, $this->em()->getRepository(GoldAsset::class)->count([]));
        self::assertSame(
            $pricesBefore,
            $this->em()->getConnection()->fetchAllAssociative('SELECT * FROM gold_price ORDER BY id'),
        );
    }

    public function testPatchOnlyFormKeepsDescriptionAndOunces(): void
    {
        $asset = $this->createAsset();
        $otherForm = $this->em()->getRepository(GoldForm::class)->findOneBy(['name' => 'moneta']);
        self::assertNotNull($otherForm);

        $this->patch($asset->getId(), ['formId' => $otherForm->getId()]);

        self::assertResponseStatusCodeSame(200);
        $this->em()->clear();
        $stored = $this->em()->find(GoldAsset::class, $asset->getId());
        self::assertNotNull($stored);
        self::assertSame($otherForm->getId(), $stored->getForm()->getId());
        self::assertSame('Original asset', $stored->getDescription());
        self::assertSame(10000, $stored->getOunces());
    }

    public function testEmptyPatchKeepsAllEditableFields(): void
    {
        $asset = $this->createAsset();
        $countBefore = $this->em()->getRepository(GoldAsset::class)->count([]);

        $this->patch($asset->getId(), []);

        self::assertResponseStatusCodeSame(200);
        $this->em()->clear();
        $stored = $this->em()->find(GoldAsset::class, $asset->getId());
        self::assertNotNull($stored);
        self::assertSame($this->goldForm->getId(), $stored->getForm()->getId());
        self::assertSame('Original asset', $stored->getDescription());
        self::assertSame(10000, $stored->getOunces());
        self::assertSame($countBefore, $this->em()->getRepository(GoldAsset::class)->count([]));
    }

    #[DataProvider('validOunces')]
    public function testPatchOnlyOuncesAcceptsBoundsAndKeepsOtherFields(string $ounces, int $storedOunces): void
    {
        $asset = $this->createAsset('  Original asset  ');

        $this->patch($asset->getId(), ['ounces' => $ounces]);

        self::assertResponseStatusCodeSame(200);
        $this->em()->clear();
        $stored = $this->em()->find(GoldAsset::class, $asset->getId());
        self::assertNotNull($stored);
        self::assertSame($storedOunces, $stored->getOunces());
        self::assertSame($this->goldForm->getId(), $stored->getForm()->getId());
        self::assertSame('  Original asset  ', $stored->getDescription());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function validOunces(): iterable
    {
        yield 'minimum' => ['0.0001', 1];
        yield 'maximum' => ['999.9999', 9999999];
        yield 'rounded to four places' => ['1.23456', 12346];
    }

    /**
     * @param array<string, mixed> $invalid
     */
    #[DataProvider('invalidWriteData')]
    public function testInvalidWriteReportsFieldErrorWithoutChangingAssets(
        bool $patch,
        array $invalid,
        string $field,
    ): void {
        $asset = $patch ? $this->createAsset() : null;
        $before = $this->em()->getConnection()->fetchAllAssociative('SELECT * FROM gold_asset ORDER BY id');
        $body = $invalid + [
            'formId' => $this->goldForm->getId(),
            'ounces' => '2.2500',
            'description' => 'Must not be saved',
        ];

        if (null !== $asset) {
            $this->patch($asset->getId(), $body);
        } else {
            $this->writePost($body);
        }

        self::assertResponseStatusCodeSame(422);
        $error = self::decodeObject((string) $this->client->getResponse()->getContent());
        $violations = self::rowsAt($error, 'violations');
        self::assertCount(1, $violations);
        self::assertSame($field, self::stringAt($violations[0], 'propertyPath'));
        self::assertSame(
            $before,
            $this->em()->getConnection()->fetchAllAssociative('SELECT * FROM gold_asset ORDER BY id'),
        );
    }

    /**
     * @return iterable<string, array{bool, array<string, mixed>, string}>
     */
    public static function invalidWriteData(): iterable
    {
        $cases = [
            'null form' => ['formId' => null],
            'empty description' => ['description' => ''],
            'spaces description' => ['description' => '   '],
            'null description' => ['description' => null],
            'long description' => ['description' => str_repeat('ą', 256)],
            'empty ounces' => ['ounces' => ''],
            'spaces ounces' => ['ounces' => '   '],
            'null ounces' => ['ounces' => null],
            'non-numeric ounces' => ['ounces' => 'abc'],
            'zero ounces' => ['ounces' => '0'],
            'negative ounces' => ['ounces' => '-1'],
            'below minimum' => ['ounces' => '0.00009'],
            'above maximum' => ['ounces' => '1000'],
            'huge ounces' => ['ounces' => str_repeat('9', 33)],
        ];
        foreach ([false, true] as $patch) {
            foreach ($cases as $name => $body) {
                yield ($patch ? 'PATCH ' : 'POST ').$name => [$patch, $body, (string) key($body)];
            }
        }
    }

    #[DataProvider('writeOperations')]
    public function testDescriptionAllows255CharactersAfterTrimming(bool $patch): void
    {
        $asset = $patch ? $this->createAsset() : null;
        $description = str_repeat('ą', 255);
        if (null !== $asset) {
            $this->patch($asset->getId(), ['description' => '  '.$description.'  ']);
        } else {
            $this->post(['description' => '  '.$description.'  ']);
        }

        self::assertResponseStatusCodeSame($patch ? 200 : 201);
        $data = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame($description, self::stringAt($data, 'description'));
        $this->em()->clear();
        $stored = $this->em()->find(GoldAsset::class, self::intAt($data, 'id'));
        self::assertNotNull($stored);
        self::assertSame($description, $stored->getDescription());
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function writeOperations(): iterable
    {
        yield 'POST' => [false];
        yield 'PATCH' => [true];
    }

    #[DataProvider('invalidFormIds')]
    public function testPatchRejectsUnknownFormWithoutChangingAsset(int $formId): void
    {
        $asset = $this->createAsset();
        $before = $this->em()->getConnection()->fetchAllAssociative('SELECT * FROM gold_asset ORDER BY id');

        $this->patch($asset->getId(), ['formId' => $formId, 'description' => 'Must not be saved']);

        self::assertResponseStatusCodeSame(404);
        self::assertSame(
            $before,
            $this->em()->getConnection()->fetchAllAssociative('SELECT * FROM gold_asset ORDER BY id'),
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidFormIds(): iterable
    {
        yield 'not found' => [999999999];
        yield 'above database limit' => [2147483648];
    }

    public function testPatchReturnsNotFoundForMissingAsset(): void
    {
        $this->patch(999999999, ['description' => 'Must not create an asset']);

        self::assertResponseStatusCodeSame(404);
    }

    public function testPatchRequiresAuthentication(): void
    {
        $asset = $this->createAsset();
        $this->client->request('PATCH', '/api/gold_assets/'.$asset->getId(), server: [
            'CONTENT_TYPE' => 'application/merge-patch+json',
        ], content: '{"description":"Must not be saved"}');

        self::assertResponseStatusCodeSame(401);
        $this->em()->clear();
        self::assertSame('Original asset', $this->em()->find(GoldAsset::class, $asset->getId())?->getDescription());
    }

    public function testOpenApiDocumentsAuthenticatedMergePatch(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');

        self::assertResponseIsSuccessful();
        $document = self::decodeObject((string) $this->client->getResponse()->getContent());
        $patch = self::objectAt($document, 'paths', '/api/gold_assets/{id}', 'patch');
        self::assertSame([['JWT' => []]], $patch['security']);
        self::assertArrayHasKey('application/merge-patch+json', self::objectAt($patch, 'requestBody', 'content'));
        self::assertStringContainsString(
            'Omitted fields keep their current values.',
            self::stringAt($patch, 'description'),
        );
        self::assertStringContainsString('price history is preserved.', self::stringAt($patch, 'description'));
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
        $this->writePost([
            'formId' => $this->goldForm->getId(),
            'ounces' => '1.0000',
        ] + $description);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function writePost(array $body): void
    {
        $this->client->request('POST', '/api/gold_assets', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], content: json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function patch(int $id, array $body): void
    {
        $this->client->request('PATCH', '/api/gold_assets/'.$id, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], content: json_encode((object) $body, JSON_THROW_ON_ERROR));
    }

    private function createAsset(string $description = 'Original asset'): GoldAsset
    {
        $asset = new GoldAsset($this->goldForm, 10000, $description);
        $this->em()->persist($asset);
        $this->em()->flush();

        return $asset;
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
