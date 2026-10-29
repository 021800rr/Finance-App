<?php

declare(strict_types=1);

namespace App\Tests\Serializer;

use ApiPlatform\State\ApiResource\Error;
use App\Exception\TransferException;
use App\Serializer\ProblemNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class ProblemNormalizerTest extends TestCase
{
    public function testUnexpectedFailureIsAGenericInternalError(): void
    {
        $normalized = $this->normalize(new Error('An error occurred', 'Internal Server Error', 500), 500);

        self::assertSame('http.internal_server_error', $normalized['errorCode']);
        self::assertSame('urn:finance-app:problem:http.internal_server_error', $normalized['type']);
        self::assertSame('Internal Server Error', $normalized['title']);
        self::assertEquals(new \ArrayObject(), $normalized['params']);
    }

    public function testBusinessErrorIsFoundAnywhereInThePreviousChain(): void
    {
        $chain = new \RuntimeException('wrapper', 0, TransferException::amountToRequired('PLN', 'EUR'));
        $error = new Error('An error occurred', 'amountTo is required.', 422, previous: $chain);

        $normalized = $this->normalize($error, 422);

        self::assertSame('transfer.amount_to_required_for_currency_exchange', $normalized['errorCode']);
        self::assertSame(['fromCurrency' => 'PLN', 'toCurrency' => 'EUR'], $normalized['params']);
    }

    public function testServerErrorNeverCarriesABusinessCodeFromItsCauses(): void
    {
        $cause = TransferException::accountsMustDiffer();
        $error = new Error('An error occurred', 'Internal Server Error', 500, previous: $cause);

        $normalized = $this->normalize($error, 500);

        self::assertSame('http.internal_server_error', $normalized['errorCode']);
        self::assertEquals(new \ArrayObject(), $normalized['params']);
    }

    public function testHandlesOnlyErrorsAndSkipsItsOwnInnerCall(): void
    {
        $normalizer = $this->normalizer([]);
        $error = new Error('An error occurred', 'x', 500);

        self::assertTrue($normalizer->supportsNormalization($error, 'json'));
        self::assertFalse($normalizer->supportsNormalization(new \stdClass(), 'json'));
        self::assertFalse(
            $normalizer->supportsNormalization($error, 'json', ['app_problem_normalizer_called' => true]),
        );
    }

    public function testOutputWithoutAStatusIsLeftAlone(): void
    {
        self::assertSame(['detail' => 'x'], $this->normalizer(['detail' => 'x'])->normalize(new \stdClass()));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(Error $error, int $status): array
    {
        $normalized = $this->normalizer(['status' => $status, 'title' => 'An error occurred'])->normalize($error);
        self::assertIsArray($normalized);

        /** @var array<string, mixed> $normalized */
        return $normalized;
    }

    /**
     * @param array<string, mixed> $output what the serializer (API Platform's own error normalizer) returns
     */
    private function normalizer(array $output): ProblemNormalizer
    {
        $decorated = $this->createStub(NormalizerInterface::class);
        $decorated->method('normalize')->willReturn($output);

        $normalizer = new ProblemNormalizer();
        $normalizer->setNormalizer($decorated);

        return $normalizer;
    }
}
