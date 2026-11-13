<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CreateUserCommand;
use App\Repository\UserRepository;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateUserCommandTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private CommandTester $tester;
    private UserRepository $users;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        $command = self::getContainer()->get(CreateUserCommand::class);
        self::assertInstanceOf(CreateUserCommand::class, $command);
        $this->tester = new CommandTester($command);

        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);
        $this->users = $users;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testItCreatesAUserWithANormalizedEmailAndAHashedPassword(): void
    {
        $status = $this->tester->execute(['email' => ' New.User@Example.com ', 'password' => 'secret-123']);

        self::assertSame(Command::SUCCESS, $status);
        $user = $this->users->findOneByEmail('new.user@example.com');
        self::assertNotNull($user);
        self::assertNotSame('secret-123', $user->getPassword());
    }

    public function testItRefusesAnAddressThatExistsInAnotherCapitalisation(): void
    {
        $this->tester->execute(['email' => 'dup@example.com', 'password' => 'secret-123']);

        $status = $this->tester->execute(['email' => 'DUP@example.com', 'password' => 'secret-123']);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('already exists', $this->tester->getDisplay());
    }

    public function testItRefusesAMalformedAddress(): void
    {
        $status = $this->tester->execute(['email' => 'not an address', 'password' => 'secret-123']);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('is not a valid address', $this->tester->getDisplay());
        self::assertNull($this->users->findOneByEmail('not an address'));
    }
}
