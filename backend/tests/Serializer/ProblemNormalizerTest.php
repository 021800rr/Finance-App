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

        self::assertSame(
            [
                'type' => 'urn:finance-app:problem:http.internal_server_error',
                'title' => 'Internal Server Error',
                'status' => 500,
                'detail' => 'Internal Server Error',
            ],
            $normalized,
        );
    }

    public function testBusinessErrorIsFoundAnywhereInThePreviousChain(): void
    {
        $chain = new \RuntimeException('wrapper', 0, TransferException::amountToRequired('PLN', 'EUR'));
        $error = new Error('An error occurred', 'amountTo is required.', 422, previous: $chain);

        $normalized = $this->normalize($error, 422);

        self::assertSame(
            'urn:finance-app:problem:transfer.amount_to_required_for_currency_exchange',
            $normalized['type'],
        );
        self::assertSame('Amount to required for a currency exchange', $normalized['title']);
        self::assertSame('PLN', $normalized['fromCurrency'] ?? null);
        self::assertSame('EUR', $normalized['toCurrency'] ?? null);
    }

    public function testServerErrorNeverCarriesABusinessCodeFromItsCauses(): void
    {
        $cause = TransferException::accountsMustDiffer();
        $error = new Error('An error occurred', 'Internal Server Error', 500, previous: $cause);

        $normalized = $this->normalize($error, 500);

        self::assertSame('urn:finance-app:problem:http.internal_server_error', $normalized['type']);
        self::assertArrayNotHasKey('fromCurrency', $normalized);
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

    /**
     * RFC 9457, section 3.1.4: the stack trace stays in the logs, also in debug mode.
     */
    public function testTheStackTraceIsLeftOut(): void
    {
        $normalized = $this->normalizer(['status' => 404, 'detail' => 'x', 'trace' => [['file' => 'a.php']]])
            ->normalize(new Error('An error occurred', 'x', 404));

        self::assertIsArray($normalized);
        self::assertArrayNotHasKey('trace', $normalized);
    }

    public function testAServerErrorNamesNothingOfItsCause(): void
    {
        $normalized = $this->normalizer(['status' => 500, 'detail' => 'SQLSTATE[08006] connection failed'])
            ->normalize(new Error('An error occurred', 'SQLSTATE[08006] connection failed', 500));

        self::assertIsArray($normalized);
        self::assertSame('Internal Server Error', $normalized['detail'] ?? null);
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

        /** @var array<string, mixed> $fields */
        $fields = $normalized;

        return $fields;
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
