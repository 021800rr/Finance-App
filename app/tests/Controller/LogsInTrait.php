<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use LogicException;

/**
 * Logs in the test user from the fixtures, so requests pass through the Security firewall.
 * Requires the using class to have a KernelBrowser $client property and DatabaseTestTrait (the em() method).
 */
trait LogsInTrait
{
    protected function logIn(): void
    {
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        if (null === $user) {
            throw new LogicException('The test user "test@example.com" does not exist — check AppFixtures.');
        }

        $this->client->loginUser($user);
    }
}
