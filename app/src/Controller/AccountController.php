<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\DatabaseLimits;
use App\Entity\Account;
use App\Repository\AccountOperationRepository;
use App\Repository\AccountRepository;
use App\Service\AccountOperationSummaryService;
use App\Service\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/account')]
class AccountController extends AbstractController
{
    // Show / hide a specific account
    #[Route(
        path: '/{id}/toggle-hidden',
        name: 'account_toggle_hidden',
        requirements: ['id' => Requirement::DIGITS],
        methods: ['POST'],
        condition: "params['id'] <= ".DatabaseLimits::POSTGRES_INT4_MAX
    )]
    public function toggleHidden(
        Account $account,
        AccountRepository $accountRepository,
        Request $request,
    ): Response {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('account-toggle-hidden', is_string($token) ? $token : null)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $account->setHidden(!$account->isHidden());
        $accountRepository->save($account, flush: true);

        $showHidden = $request->query->getBoolean('show_hidden') ? '1' : '0';

        return $this->redirectToRoute('home', ['show_hidden' => $showHidden]);
    }

    // Mark a specific account as the main account (used e.g. as the target
    // for "plan next month's fixed expenses"). Setting a new one unsets the
    // previous one — see AccountRepository::setAsMainAccount().
    #[Route(
        path: '/{id}/set-main',
        name: 'account_set_main',
        requirements: ['id' => Requirement::DIGITS],
        methods: ['POST'],
        condition: "params['id'] <= ".DatabaseLimits::POSTGRES_INT4_MAX
    )]
    public function setMain(
        Account $account,
        AccountRepository $accountRepository,
        Request $request,
    ): Response {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('account-set-main', is_string($token) ? $token : null)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $accountRepository->setAsMainAccount($account);

        $showHidden = $request->query->getBoolean('show_hidden') ? '1' : '0';

        return $this->redirectToRoute('home', ['show_hidden' => $showHidden]);
    }

    #[Route(
        path: '/{id}/operations',
        name: 'account_operations',
        requirements: ['id' => Requirement::DIGITS],
        condition: "params['id'] <= ".DatabaseLimits::POSTGRES_INT4_MAX
    )]
    public function operations(
        Account $account,
        Request $request,
        AccountOperationRepository $accountOperationRepository,
        AccountOperationSummaryService $accountOperationSummaryService,
    ): Response {
        $summary = $accountOperationSummaryService->getForAccount($account);

        $total = $accountOperationRepository->countByAccount($account);
        $paginator = Paginator::create((int) $request->query->get('page', 1), $total, Paginator::DEFAULT_PER_PAGE);

        $operations = $accountOperationRepository->findByAccountOrderedPaged($account, $paginator->perPage, $paginator->offset);

        return $this->render('account/operations.html.twig', [
            'account' => $account,
            'operations' => $operations,
            'saldoAll' => $summary['saldoAll'],
            'saldoToday' => $summary['saldoToday'],
            'showSaldoToday' => $summary['showSaldoToday'],
            'highlight' => $request->query->get('highlight'),
            'page' => $paginator->page,
            'totalPages' => $paginator->totalPages,
        ]);
    }
}
