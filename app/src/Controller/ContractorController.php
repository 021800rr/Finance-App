<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Contractor;
use App\Repository\ContractorRepository;
use App\Service\ContractorFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\Serialize;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/contractor')]
class ContractorController extends AbstractController
{
    /**
     * @return array<int, array{id: int, name: string}>
     */
    #[Route(path: '/search', name: 'contractor_search', methods: ['GET'])]
    #[Serialize]
    public function search(Request $request, ContractorRepository $contractorRepository): array
    {
        $q = trim((string) $request->query->get('q'));

        if ('' === $q) {
            return [];
        }

        return $contractorRepository->searchByName($q);
    }

    #[Route(path: '/create', name: 'contractor_create', methods: ['POST'])]
    #[Serialize]
    public function create(
        Request $request,
        ContractorRepository $contractorRepository,
        ContractorFactory $contractorFactory,
    ): Contractor|JsonResponse {
        if (!$this->isCsrfTokenValid('contractor-create', $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['error' => 'CSRF'], 403);
        }

        try {
            $contractor = $contractorFactory->createFromJsonRequest($request);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        $contractorRepository->save($contractor, flush: true);

        return $contractor;
    }
}
