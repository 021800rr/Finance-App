<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\GoldPrice;
use App\Tests\DatabaseTestTrait;
use App\Tests\JwtTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GoldPriceApiTest extends WebTestCase
{
    use DatabaseTestTrait;
    use ReadsJsonTrait;
    use JwtTrait;

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

    #[DataProvider('unknownAssetIds')]
    public function testUnknownAssetIsAViolationWithoutSaving(int $goldAssetId): void
    {
        $countBefore = $this->em()->getRepository(GoldPrice::class)->count([]);

        $this->client->request('POST', '/api/gold_prices', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt(),
            'CONTENT_TYPE' => 'application/ld+json',
        ], content: json_encode([
            'goldAssetId' => $goldAssetId,
            'priceDate' => '2026-01-15',
            'pricePln' => '8000.00',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            ['goldAssetId' => 'gold_asset.not_found'],
            array_column(
                self::rowsAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'violations'),
                'code',
                'propertyPath',
            ),
        );
        self::assertSame($countBefore, $this->em()->getRepository(GoldPrice::class)->count([]));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function unknownAssetIds(): iterable
    {
        yield 'not found' => [999999999];
        yield 'above database limit' => [2147483648];
    }
}
