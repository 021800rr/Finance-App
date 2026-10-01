<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use App\ApiResource\CurrencyExchangeRateResource;
use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\CurrencyExchangeRate;
use App\State\CurrencyExchangeRateProcessor;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CurrencyExchangeRateProcessorTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private CurrencyExchangeRateProcessor $processor;
    /** @var EntityRepository<Currency> */
    private EntityRepository $currencyRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var CurrencyExchangeRateProcessor $processor */
        $processor = self::getContainer()->get(CurrencyExchangeRateProcessor::class);
        $this->processor = $processor;

        $this->currencyRepository = $this->em()->getRepository(Currency::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testProcessCreatesRateSuccessfully(): void
    {
        $bank = $this->createBank();
        $currency = $this->createCurrency('CHF');

        $resource = $this->buildResource(bankId: $bank->getId(), currencyCode: 'CHF', rateToPln: '4.3210');

        $result = $this->processor->process($resource, new Post());

        self::assertInstanceOf(CurrencyExchangeRateResource::class, $result);
        self::assertNotNull($result->id);
        self::assertSame($bank->getId(), $result->bankId);
        self::assertSame('CHF', $result->currencyCode);
        self::assertSame('4.3210', $result->rateToPln);
    }

    public function testProcessAllowsMultipleRatesForSameBankCurrencyAndDate(): void
    {
        $bank = $this->createBank();
        $this->createCurrency('CHF');

        $first = $this->processor->process(
            $this->buildResource(bankId: $bank->getId(), currencyCode: 'CHF', rateDate: '2026-09-29', rateToPln: '4.3210'),
            new Post(),
        );

        $second = $this->processor->process(
            $this->buildResource(bankId: $bank->getId(), currencyCode: 'CHF', rateDate: '2026-09-29', rateToPln: '4.4000'),
            new Post(),
        );

        self::assertInstanceOf(CurrencyExchangeRateResource::class, $first);
        self::assertInstanceOf(CurrencyExchangeRateResource::class, $second);
        self::assertNotSame($first->id, $second->id);
        self::assertSame('4.3210', $first->rateToPln);
        self::assertSame('4.4000', $second->rateToPln);
        // The most recently added rate (highest id) is the one that counts as current.
        self::assertGreaterThan($first->id, $second->id);
    }

    public function testProcessThrowsUnprocessableWhenBankIdMissing(): void
    {
        $this->createCurrency('CHF');
        $resource = $this->buildResource(bankId: null, currencyCode: 'CHF', rateToPln: '4.3210');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsNotFoundWhenBankDoesNotExist(): void
    {
        $this->createCurrency('CHF');
        $resource = $this->buildResource(bankId: 999999999, currencyCode: 'CHF', rateToPln: '4.3210');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsUnprocessableWhenCurrencyCodeMissing(): void
    {
        $bank = $this->createBank();
        $resource = $this->buildResource(bankId: $bank->getId(), currencyCode: null, rateToPln: '4.3210');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsNotFoundWhenCurrencyDoesNotExist(): void
    {
        $bank = $this->createBank();
        $resource = $this->buildResource(bankId: $bank->getId(), currencyCode: 'ZZZ', rateToPln: '4.3210');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsUnprocessableWhenRateDateMissing(): void
    {
        $bank = $this->createBank();
        $this->createCurrency('CHF');
        $resource = $this->buildResource(bankId: $bank->getId(), currencyCode: 'CHF', rateToPln: '4.3210', rateDate: null);

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsUnprocessableWhenRateDateInvalid(): void
    {
        $bank = $this->createBank();
        $this->createCurrency('CHF');
        $resource = $this->buildResource(bankId: $bank->getId(), currencyCode: 'CHF', rateToPln: '4.3210', rateDate: 'not-a-date');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsUnprocessableWhenRateToPlnMissing(): void
    {
        $bank = $this->createBank();
        $this->createCurrency('CHF');
        $resource = $this->buildResource(bankId: $bank->getId(), currencyCode: 'CHF', rateToPln: null);

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsUnprocessableWhenRateToPlnNotDecimal(): void
    {
        $bank = $this->createBank();
        $this->createCurrency('CHF');
        $resource = $this->buildResource(bankId: $bank->getId(), currencyCode: 'CHF', rateToPln: 'string');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsUnprocessableWhenRateToPlnOutOfRange(): void
    {
        $bank = $this->createBank();
        $this->createCurrency('CHF');
        $resource = $this->buildResource(bankId: $bank->getId(), currencyCode: 'CHF', rateToPln: '150.0000');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsWhenDataIsNotCurrencyExchangeRateResource(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->processor->process(new \stdClass(), new Post());
    }

    public function testProcessDeletesExistingRateOnDelete(): void
    {
        $bank = $this->createBank();
        $this->createCurrency('CHF');
        $created = $this->processor->process(
            $this->buildResource(bankId: $bank->getId(), currencyCode: 'CHF', rateToPln: '4.3210'),
            new Post(),
        );
        self::assertInstanceOf(CurrencyExchangeRateResource::class, $created);

        $result = $this->processor->process(null, new Delete(), ['id' => $created->id]);

        self::assertNull($result);
        self::assertNull($this->em()->getRepository(CurrencyExchangeRate::class)->find($created->id));
    }

    public function testProcessThrowsNotFoundOnDeleteWhenRateMissing(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->processor->process(null, new Delete(), ['id' => 999999999]);
    }

    /**
     * Sentinel distinguishing "argument omitted" (auto-fill today's date) from an explicit
     * null passed by a test that wants to verify the missing-date validation path.
     */
    private const string AUTO_DATE = '__auto_date__';

    private function buildResource(
        ?int $bankId = null,
        ?string $currencyCode = null,
        ?string $rateToPln = '4.3210',
        ?string $rateDate = self::AUTO_DATE,
    ): CurrencyExchangeRateResource {
        $resource = new CurrencyExchangeRateResource();
        $resource->bankId = $bankId;
        $resource->currencyCode = $currencyCode;
        $resource->rateToPln = $rateToPln;
        $resource->rateDate = self::AUTO_DATE === $rateDate
            ? (new DateTimeImmutable())->format('Y-m-d')
            : $rateDate;

        return $resource;
    }

    private function createBank(): Bank
    {
        $bank = new Bank('Test Bank ' . uniqid());
        $this->em()->persist($bank);
        $this->em()->flush();

        return $bank;
    }

    private function createCurrency(string $code): Currency
    {
        $existing = $this->currencyRepository->find($code);
        if ($existing) {
            return $existing;
        }

        $currency = new Currency($code);
        $this->em()->persist($currency);
        $this->em()->flush();

        return $currency;
    }
}
