<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\ApiLogoutListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * The logouts the refresh token bundle answers are covered by the API tests (TokenApiTest); in this application it
 * always answers before this listener runs.
 */
final class ApiLogoutListenerTest extends TestCase
{
    /**
     * The listener only reshapes the answer of a logout; with none, Symfony reports that no logout listener set one.
     */
    public function testLeavesALogoutNobodyAnsweredToSymfony(): void
    {
        $event = new LogoutEvent(Request::create('/api/token/invalidate', 'POST'), null);

        (new ApiLogoutListener())($event);

        self::assertNull($event->getResponse());
    }
}
