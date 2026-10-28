<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Contractor;
use App\Form\ContractorType;
use App\Repository\ContractorRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
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

    #[Route(path: '/create', name: 'contractor_create', methods: ['POST'], format: 'json')]
    #[Serialize]
    public function create(
        Request $request,
        ContractorRepository $contractorRepository,
    ): Contractor|JsonResponse {
        if (!$this->isCsrfTokenValid('contractor-create', $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['error' => 'CSRF'], 403);
        }

        // CSRF is checked above using the token from the request header.
        $form = $this->createForm(ContractorType::class, options: ['csrf_protection' => false]);
        $form->submit($request->getPayload()->all());

        if (!$form->isValid()) {
            $errors = [];
            /** @var FormError $error */
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $errors], 422);
        }

        /** @var Contractor $contractor */
        $contractor = $form->getData();
        $contractorRepository->save($contractor, flush: true);

        return $contractor;
    }
}
