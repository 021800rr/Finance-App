<?php

declare(strict_types=1);

namespace App\Tests;

use App\DataFixtures\AppFixtures;

/**
 * Logs the test user from the fixtures in to the API once per test and keeps the token for the following requests.
 * Requires the using class to have a KernelBrowser $client property.
 */
trait JwtTrait
{
    private ?string $token = null;

    private function jwt(): string
    {
        if (null === $this->token) {
            $this->client->jsonRequest('POST', '/api/login_check', [
                'email' => AppFixtures::TEST_USER_EMAIL,
                'password' => AppFixtures::TEST_USER_PASSWORD,
            ]);
            $data = json_decode((string) $this->client->getResponse()->getContent(), true);
            $this->token = is_array($data) && is_string($data['token'] ?? null)
                ? $data['token']
                : throw new \LogicException('The test user from the fixtures could not log in to the API.');
        }

        return $this->token;
    }

    /**
     * @return array{HTTP_AUTHORIZATION: string}
     */
    private function authHeaders(): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt()];
    }
}
