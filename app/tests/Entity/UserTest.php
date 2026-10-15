<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testConstructorStoresTheEmailTrimmedAndInLowerCase(): void
    {
        $user = new User('  Ryszard@Example.COM ', 'hash');

        self::assertSame('ryszard@example.com', $user->getEmail());
        self::assertSame('ryszard@example.com', $user->getUserIdentifier());
    }

    public function testConstructorKeepsTheGivenHashUntouched(): void
    {
        $user = new User('user@example.com', '$2y$13$hashed');

        self::assertSame('$2y$13$hashed', $user->getPassword());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidEmails(): iterable
    {
        yield 'empty' => [''];
        yield 'only spaces' => ['   '];
        yield 'no at sign' => ['example.com'];
        yield 'no domain' => ['user@'];
        yield 'inner space' => ['us er@example.com'];
    }

    #[DataProvider('invalidEmails')]
    public function testConstructorRejectsAnInvalidEmail(string $email): void
    {
        $this->expectException(InvalidArgumentException::class);

        new User($email, 'hash');
    }

    public function testEveryUserHasTheUserRole(): void
    {
        self::assertSame(['ROLE_USER'], new User('user@example.com', 'hash')->getRoles());
    }
}
