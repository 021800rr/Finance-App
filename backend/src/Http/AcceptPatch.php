<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The media types a PATCH takes (API Platform's patch_formats), as the Accept-Patch header names them (RFC 5789,
 * section 3.1).
 */
final readonly class AcceptPatch
{
    public string $value;

    /**
     * @param array<string, list<string>> $patchFormats media types by format
     */
    public function __construct(#[Autowire('%api_platform.patch_formats%')] array $patchFormats)
    {
        $this->value = implode(', ', array_merge(...array_values($patchFormats)));
    }
}
