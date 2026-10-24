<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\DatabaseLimits;
use App\Dto\ContractorFormResult;
use App\Entity\FixedExpense;
use App\Exception\FixedExpensePlanningException;
use App\Form\FixedExpenseType;
use App\Repository\AccountRepository;
use App\Repository\FixedExpenseRepository;
use App\Service\ContractorFormHandler;
use App\Service\FixedExpenseFactory;
use App\Service\FixedExpensePlannerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Translation\TranslatableMessage;

#[Route('/fixed-expense')]
class FixedExpenseController extends AbstractController
{
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly ContractorFormHandler $contractorFormHandler,
        private readonly FixedExpenseFactory $fixedExpenseFactory,
    ) {
    }

    #[Route(path: '', name: 'fixed_expense', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        FixedExpenseRepository $fixedExpenseRepository,
    ): Response {
        $result = $this->handleFixedExpenseForm($request, $fixedExpenseRepository, contractorName: '', existingFixedExpense: null);
        if ($result instanceof Response) {
            return $result;
        }

        return $this->render('fixed_expense/index.html.twig', [
            'form' => $result->form->createView(),
            'fixedExpenses' => $fixedExpenseRepository->findAllOrdered(),
            'contractorErrorMessage' => $result->contractorErrorMessage,
            'contractorName' => $result->contractorName,
            'contractorId' => $result->contractorId,
        ]);
    }

    #[Route(
        path: '/{id}/edit',
        name: 'fixed_expense_edit',
        requirements: ['id' => Requirement::DIGITS],
        methods: ['GET', 'POST'],
        condition: "params['id'] <= ".DatabaseLimits::POSTGRES_INT4_MAX
    )]
    public function edit(
        FixedExpense $fixedExpense,
        Request $request,
        FixedExpenseRepository $fixedExpenseRepository,
    ): Response {
        $contractorName = $fixedExpense->getContractor()->getName();
        $contractorId = (string) $fixedExpense->getContractor()->getId();
        $result = $this->handleFixedExpenseForm(
            $request,
            $fixedExpenseRepository,
            $contractorName,
            $fixedExpense,
        );
        if ($result instanceof Response) {
            return $result;
        }

        return $this->render('fixed_expense/edit.html.twig', [
            'form' => $result->form->createView(),
            'title' => 'fixed_expense.edit_title',
            'contractorErrorMessage' => $result->contractorErrorMessage,
            'contractorName' => $result->contractorName,
            'contractorId' => $result->contractorId ?? $contractorId,
        ]);
    }

    #[Route(
        path: '/{id}/delete',
        name: 'fixed_expense_delete',
        requirements: ['id' => Requirement::DIGITS],
        methods: ['POST'],
        condition: "params['id'] <= ".DatabaseLimits::POSTGRES_INT4_MAX
    )]
    public function delete(
        FixedExpense $fixedExpense,
        Request $request,
        FixedExpenseRepository $fixedExpenseRepository,
    ): Response {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('fixed-expense-delete', is_string($token) ? $token : null)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $fixedExpenseRepository->remove($fixedExpense, true);

        return $this->redirectToRoute('fixed_expense');
    }

    #[Route(path: '/plan', name: 'fixed_expense_plan', methods: ['POST'])]
    public function plan(Request $request, FixedExpensePlannerService $planner): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('fixed-expense-plan', is_string($token) ? $token : null)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $planner->planNextMonth();
        } catch (FixedExpensePlanningException $e) {
            $this->addFlash('warning', new TranslatableMessage($e->translationKey, $e->translationParameters));
        }

        $mainAccount = $this->accountRepository->findMain();
        if (null === $mainAccount) {
            return $this->redirectToRoute('fixed_expense');
        }

        return $this->redirectToRoute('account_operations', [
            'id' => $mainAccount->getId(),
        ]);
    }

    private function handleFixedExpenseForm(
        Request $request,
        FixedExpenseRepository $fixedExpenseRepository,
        string $contractorName,
        ?FixedExpense $existingFixedExpense,
    ): ContractorFormResult|Response {
        $form = $this->createForm(FixedExpenseType::class);
        $form->handleRequest($request);

        $contractorId = null;
        $contractorErrorMessage = null;

        if ($form->isSubmitted()) {
            $resolution = $this->contractorFormHandler->resolve($form->get('contractor'), $request);
            $contractorName = $resolution->contractorName;
            $contractorId = $resolution->contractorId;
            $contractorErrorMessage = $resolution->contractorErrorMessage;

            if ($form->isValid() && $resolution->contractor) {
                if (null === $existingFixedExpense) {
                    $fixedExpense = $this->fixedExpenseFactory->createFromForm($form, $resolution->contractor);
                } else {
                    $this->fixedExpenseFactory->updateFromForm($existingFixedExpense, $form, $resolution->contractor);
                    $fixedExpense = $existingFixedExpense;
                }

                $fixedExpenseRepository->save($fixedExpense, flush: true);

                return $this->redirectToRoute('fixed_expense');
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
