<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Failed logins are counted per IP in a persistent cache pool that `cache:clear` keeps,
// so start every run with a clean limiter: earlier runs must not lock the fixture user out.
$kernel = new App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$kernel->getContainer()->get('test.service_container')->get('cache.rate_limiter')->clear();
$kernel->shutdown();
