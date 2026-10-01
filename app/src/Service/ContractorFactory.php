<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contractor;
use Symfony\Component\HttpFoundation\Request;

final readonly class ContractorFactory
{
    public function createFromJsonRequest(Request $request): Contractor
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON body.');
        }

        $rawName = $data['name'] ?? null;
        $name = trim(is_string($rawName) ? $rawName : '');

        if ('' === $name) {
            throw new \InvalidArgumentException('Name cannot be empty.');
        }

        return new Contractor($name);
    }
}
