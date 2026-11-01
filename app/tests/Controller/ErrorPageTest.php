<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * The test environment runs in debug mode and shows the exception page, so the templates are rendered directly.
 */
final class ErrorPageTest extends KernelTestCase
{
    #[DataProvider('pages')]
    public function testErrorPageNamesTheProblemWithoutTechnicalDetails(
        string $template,
        int $status,
        string $expected,
    ): void {
        self::bootKernel();
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $page = $twig->render($template, ['status_code' => $status, 'status_text' => 'Status']);

        self::assertStringContainsString((string) $status, $page);
        self::assertStringContainsString($expected, $page);
        self::assertStringContainsString('Back to the home page', $page);
        self::assertStringNotContainsString('Oops', $page);
        self::assertStringNotContainsString('error.', $page);
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function pages(): iterable
    {
        yield 'not found' => ['@Twig/Exception/error404.html.twig', 404, 'Page not found'];
        yield 'server error' => ['@Twig/Exception/error.html.twig', 500, 'Something went wrong'];
        yield 'forbidden' => ['@Twig/Exception/error.html.twig', 403, 'Something went wrong'];
    }
}
