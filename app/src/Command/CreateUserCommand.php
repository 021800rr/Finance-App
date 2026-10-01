<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-user', description: 'Creates a new user (login access).')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adres e-mail (login)')
            ->addArgument('password', InputArgument::REQUIRED, 'Hasło w postaci czystego tekstu (zostanie zahaszowane)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $email */
        $email = $input->getArgument('email');
        /** @var string $password */
        $password = $input->getArgument('password');

        if (null !== $this->userRepository->findOneByEmail($email)) {
            $io->error(sprintf('Użytkownik o adresie "%s" już istnieje.', $email));

            return Command::FAILURE;
        }

        $user = new User($email, 'temporary');
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->userRepository->save($user, flush: true);

        $io->success(sprintf('Użytkownik "%s" został utworzony.', $email));

        return Command::SUCCESS;
    }
}
