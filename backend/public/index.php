<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    /** @var string $environment */
    $environment = $context['APP_ENV'];

    return new Kernel($environment, (bool) $context['APP_DEBUG']);
};
