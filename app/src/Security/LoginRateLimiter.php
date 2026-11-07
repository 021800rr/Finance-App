<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * The login throttling of both login firewalls, the web form (main) and the API (api_login), see security.yaml.
 *
 * One instance for both, so their attempts at an account count together: before, each firewall had its own counter,
 * and after five failed attempts in one the right password still logged in through the other.
 *
 * The counter of an account is found by its address in the form the login compares it in (User::canonicalEmail).
 * Symfony's limiter takes the address as it was typed, and the API login (unlike the web form) does not trim it:
 * " ryszard@example.com" was a counter of its own that still logged in to the same account, so a space at either
 * end, in any number, gave five more attempts, also during the lock.
 *
 * The counting itself is Symfony's DefaultLoginRateLimiter (services.yaml), with the limiters of rate_limiter.yaml.
 */
final readonly class LoginRateLimiter implements RequestRateLimiterInterface
{
    public function __construct(private RequestRateLimiterInterface $inner)
    {
    }

    public function consume(Request $request): RateLimit
    {
        return $this->inner->consume($this->normalized($request));
    }

    public function reset(Request $request): void
    {
        $this->inner->reset($this->normalized($request));
    }

    private function normalized(Request $request): Request
    {
        $username = $request->attributes->get(SecurityRequestAttributes::LAST_USERNAME);
        if (!is_string($username)) {
            return $request;
        }

        // A copy: the request itself keeps the address as typed, e.g. for the login form to show it back.
        $copy = $request->duplicate();
        $copy->attributes->set(SecurityRequestAttributes::LAST_USERNAME, User::canonicalEmail($username));

        return $copy;
    }
}
