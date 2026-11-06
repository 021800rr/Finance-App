<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MeResource;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<MeResource>
 */
final readonly class MeProvider implements ProviderInterface
{
    public function __construct(private Security $security)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MeResource
    {
        $user = $this->security->getUser()
            ?? throw new \LogicException('GET /api/me is open only to logged-in users.');

        $me = new MeResource();
        $me->email = $user->getUserIdentifier();

        return $me;
    }
}
