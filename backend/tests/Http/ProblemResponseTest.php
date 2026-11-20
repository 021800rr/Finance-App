<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\ProblemResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProblemResponseTest extends TestCase
{
    public function testAnExceptionsContentTypeInAnyCaseDoesNotReplaceProblemJson(): void
    {
        $response = ProblemResponse::create(429, 'http.too_many_requests', 'Slow down.', headers: [
            'content-type' => 'text/html',
            'Retry-After' => '60',
        ]);

        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame('60', $response->headers->get('Retry-After'));
    }

    public function testInvalidUtf8InTheDetailIsReplacedInsteadOfFailing(): void
    {
        $response = ProblemResponse::create(400, 'http.bad_request', "Invalid Host \"\xff\".");

        self::assertSame(400, $response->getStatusCode());
        $problem = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($problem);
        self::assertSame("Invalid Host \"\u{FFFD}\".", $problem['detail'] ?? null);
    }

    /**
     * The codes are part of the API contract: they must not follow Symfony's status texts, which change between
     * versions (422 was "Unprocessable Entity", 418 is "I'm a teapot").
     */
    public function testHttpErrorCodesAreFixedAndIndependentOfStatusTexts(): void
    {
        self::assertSame('http.bad_request', ProblemResponse::httpErrorCode(400));
        self::assertSame('http.not_found', ProblemResponse::httpErrorCode(404));
        self::assertSame('http.unprocessable_content', ProblemResponse::httpErrorCode(422));
        self::assertSame('http.content_too_large', ProblemResponse::httpErrorCode(413));
        self::assertSame('http.internal_server_error', ProblemResponse::httpErrorCode(500));
        self::assertSame('http.418', ProblemResponse::httpErrorCode(418));
        self::assertSame('http.599', ProblemResponse::httpErrorCode(599));
    }

    public function testAProblemHasTheMembersOfItsTypeAfterTheStandardOnes(): void
    {
        $response = ProblemResponse::create(
            429,
            'auth.too_many_login_attempts',
            'Locked.',
            ['minutes' => '15'],
            title: 'Locked',
        );

        self::assertSame(
            '{"type":"urn:finance-app:problem:auth.too_many_login_attempts","title":"Locked","status":429,'
            .'"detail":"Locked.","minutes":"15"}',
            $response->getContent(),
        );
    }

    /**
     * RFC 9457, section 4: an extension member starts with a letter, has three characters or more, and cannot take
     * the place of a standard member.
     *
     * @return iterable<string, array{string}>
     */
    public static function namesThatCannotBeMembers(): iterable
    {
        yield 'standard member' => ['detail'];
        yield 'list of a validation problem' => ['errors'];
        yield 'too short' => ['id'];
        yield 'not a letter first' => ['@id'];
    }

    #[DataProvider('namesThatCannotBeMembers')]
    public function testANameThatCannotBeAnExtensionMemberIsRefused(string $name): void
    {
        $this->expectException(\LogicException::class);

        ProblemResponse::fields(422, 'some.problem', [$name => 'x']);
    }

    public function testAnHttpTypeIsTitledWithTheNameOfTheStatus(): void
    {
        self::assertSame(
            ['type' => 'urn:finance-app:problem:http.not_found', 'title' => 'Not Found'],
            ProblemResponse::fields(404, 'http.not_found', []),
        );
    }
}
