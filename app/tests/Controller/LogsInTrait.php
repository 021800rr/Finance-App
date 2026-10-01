<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use LogicException;

/**
 * Loguje testowego użytkownika z fixtures, żeby żądania przechodziły przez firewall Security.
 * Wymaga, aby klasa używająca traitu ma właściwość KernelBrowser $client oraz DatabaseTestTrait (metoda em()).
 */
trait LogsInTrait
{
    protected function logIn(): void
    {
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        if (null === $user) {
            throw new LogicException('Testowy użytkownik "test@example.com" nie istnieje — sprawdź AppFixtures.');
        }

        $this->client->loginUser($user);
    }
}
