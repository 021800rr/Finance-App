<?php

namespace App\Tests\Smoke;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

class AllRoutesSmokeTest extends WebTestCase
{
    public function testAllGetRoutesRespondWithoutServerError(): void
    {
        $client = static::createClient();
        /** @var RouterInterface $router */
        $router = static::getContainer()->get('router');

        foreach ($router->getRouteCollection() as $name => $route) {
            // Pomijamy trasy administracyjne/systemowe i te z wymaganymi parametrami
            if (str_starts_with($name, '_')) {
                continue;
            }

            $methods = $route->getMethods();
            if ($methods !== [] && !in_array('GET', $methods, true)) {
                continue;
            }

            // Skip routes with URL parameters (e.g. /account/{id}) — for now
            // only the "flat" ones without required arguments are tested
            if (str_contains($route->getPath(), '{')) {
                continue;
            }

            $client->request('GET', $route->getPath());
            $status = $client->getResponse()->getStatusCode();

            self::assertLessThan(
                500,
                $status,
                sprintf('Route "%s" (%s) returned a server error: %d', $name, $route->getPath(), $status)
            );
        }
    }
}
