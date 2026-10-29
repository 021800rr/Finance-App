<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\DatabaseLimits;
use App\Dto\ContractorFormResult;
use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Form\AccountOperationType;
use App\Repository\AccountOperationRepository;
use App\Repository\AccountRepository;
use App\Service\AccountOperationFactory;
use App\Service\ContractorFormHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/operation')]
class AccountOperationController extends AbstractController
{
    public function __construct(
        private readonly ContractorFormHandler $contractorFormHandler,
        private readonly AccountOperationFactory $accountOperationFactory,
    ) {
    }

    #[Route(path: '/new', name: 'operation_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        AccountOperationRepository $accountOperationRepository,
        AccountRepository $accountRepository,
    ): Response {
        $preselectedAccount = null;
        $rawAccountId = $request->query->get('account');

        if (null !== $rawAccountId) {
            $preselectedAccount = $accountRepository->findByIdOrNull($rawAccountId);
            if (!$preselectedAccount || $preselectedAccount->isHidden()) {
                $this->addFlash('warning', 'account.not_found');

                return $this->redirectToRoute('home');
            }
        }

        $result = $this->handleOperationForm(
            existingOperation: null,
            request: $request,
            accountOperationRepository: $accountOperationRepository,
            contractorName: '',
            preselectedAccount: $preselectedAccount
        );
        if ($result instanceof Response) {
            return $result;
        }

        return $this->render('operation/new.html.twig', [
            'form' => $result->form->createView(),
            'contractorErrorMessage' => $result->contractorErrorMessage,
            'contractorName' => $result->contractorName,
        ]);
    }

    #[Route(path: '/{id}/edit',
        name: 'operation_edit',
        requirements: ['id' => Requirement::DIGITS],
        methods: ['GET', 'POST'],
        condition: "params['id'] <= ".DatabaseLimits::POSTGRES_INT4_MAX
    )]
    public function edit(
        AccountOperation $operation,
        Request $request,
        AccountOperationRepository $accountOperationRepository,
    ): Response {
        $contractorName = $operation->getContractor()->getName();
        $contractorId = (string) $operation->getContractor()->getId();
        $result = $this->handleOperationForm(
            $operation,
            $request,
            $accountOperationRepository,
            $contractorName,
            $operation->getAccount(),
        );
        if ($result instanceof Response) {
            return $result;
        }

        return $this->render('operation/edit.html.twig', [
            'form' => $result->form->createView(),
            'title' => 'operation.edit_title',
            'contractorErrorMessage' => $result->contractorErrorMessage,
            'contractorName' => $result->contractorName,
            'contractorId' => $result->contractorId ?? $contractorId,
        ]);
    }

    #[Route(
        path: '/{id}/delete',
        name: 'operation_delete',
        requirements: ['id' => Requirement::DIGITS],
        methods: ['POST'],
        condition: "params['id'] <= ".DatabaseLimits::POSTGRES_INT4_MAX
    )]
    public function delete(
        AccountOperation $operation,
        Request $request,
        AccountOperationRepository $accountOperationRepository,
    ): Response {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('operation-delete', is_string($token) ? $token : null)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $accountId = $operation->getAccount()->getId();
        $accountOperationRepository->remove($operation, true);

        return $this->redirectToRoute('account_operations', ['id' => $accountId]);
    }

    #[Route(
        path: '/{id}/toggle-confirmed',
        name: 'operation_toggle_confirmed',
        requirements: ['id' => Requirement::DIGITS],
        methods: ['POST'],
        condition: "params['id'] <= ".DatabaseLimits::POSTGRES_INT4_MAX
    )]
    public function toggleConfirmed(
        AccountOperation $operation,
        Request $request,
        AccountOperationRepository $accountOperationRepository,
    ): JsonResponse {
        $token = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('operation-toggle-confirmed', $token)) {
            return new JsonResponse(['error' => 'csrf'], 403);
        }

        $operation->setConfirmed(!$operation->isConfirmed());
        $accountOperationRepository->save($operation, flush: true);

        return new JsonResponse([
            'id' => $operation->getId(),
            'confirmed' => $operation->isConfirmed(),
        ]);
    }

    #[Route(
        path: '/{id}/go-to-other-side',
        name: 'operation_go_to_other_side',
        requirements: ['id' => Requirement::DIGITS],
        methods: ['GET'],
        condition: "params['id'] <= ".DatabaseLimits::POSTGRES_INT4_MAX
    )]
    public function goToOtherSide(
        AccountOperation $operation,
        AccountOperationRepository $repository,
    ): Response {
        $otherSide = $repository->findOtherSide($operation);

        if (!$otherSide) {
            $this->addFlash('warning', 'operation.related_not_found');

            return $this->redirectToRoute('account_operations', [
                'id' => $operation->getAccount()->getId(),
                'highlight' => $operation->getId(),
            ]);
        }

        return $this->redirectToRoute('account_operations', [
            'id' => $otherSide->getAccount()->getId(),
            'highlight' => $otherSide->getId(),
        ]);
    }

    private function handleOperationForm(
        ?AccountOperation $existingOperation,
        Request $request,
        AccountOperationRepository $accountOperationRepository,
        string $contractorName,
        ?Account $preselectedAccount = null,
    ): ContractorFormResult|Response {
        $form = $this->createForm(AccountOperationType::class, options: [
            'preselectedAccount' => $preselectedAccount,
            'existingOperation' => $existingOperation,
        ]);
        $form->handleRequest($request);

        $contractorId = null;
        $contractorErrorMessage = null;

        if ($form->isSubmitted()) {
            $resolution = $this->contractorFormHandler->resolve($form->get('contractor'), $request);
            $contractorName = $resolution->contractorName;
            $contractorId = $resolution->contractorId;
            $contractorErrorMessage = $resolution->contractorErrorMessage;

            if ($form->isValid() && $resolution->contractor) {
                $operation = $this->accountOperationFactory->createOrUpdateAndPersist(
                    $existingOperation,
                    $form,
                    $resolution->contractor,
                    $accountOperationRepository,
                );

                return $this->redirectToRoute('account_operations', [
                    'id' => $operation->getAccount()->getId(),
                    'highlight' => $operation->getId(),
                ]);
            }
        }

        return new ContractorFormResult(
            form: $form,
            contractorName: $contractorName,
            contractorId: $contractorId,
            contractorErrorMessage: $contractorErrorMessage,
        );
    }
}
