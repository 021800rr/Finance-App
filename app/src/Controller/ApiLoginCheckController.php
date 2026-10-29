<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

/**
 * The json_login firewall answers POST /api/login_check itself, but only for a JSON body. Any other request
 * reaches this controller: say what is expected instead of a "route is wrongly configured" 404.
 */
final class ApiLoginCheckController
{
    private const string MESSAGE = 'Send the credentials as JSON with "Content-Type: application/json": '
        .'{"email": "...", "password": "..."}.';

    public function __invoke(): never
    {
        throw new UnsupportedMediaTypeHttpException(self::MESSAGE);
    }
}
