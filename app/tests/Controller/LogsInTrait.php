<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\User;

/**
 * Logs in the test user from the fixtures, so requests pass through the Security firewall.
 * Requires the using class to have a KernelBrowser $client property and DatabaseTestTrait (the em() method).
 */
trait LogsInTrait
{
    protected function logIn(): void
    {
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => AppFixtures::TEST_USER_EMAIL]);
        if (null === $user) {
            throw new \LogicException('The test user from AppFixtures does not exist.');
        }

        $this->client->loginUser($user);
    }
}
