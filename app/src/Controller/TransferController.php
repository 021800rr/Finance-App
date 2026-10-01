<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\TransferType;
use App\Service\TransferHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/transfer')]
class TransferController extends AbstractController
{
    /**
     * @throws \Throwable
     */
    #[Route(path: '/new', name: 'transfer_new')]
    public function new(Request $request, TransferHandler $transferHandler): Response
    {
        $form = $this->createForm(TransferType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $transferHandler->handle($form);

            return $this->redirectToRoute('account_operations', [
                'id' => $result['out']->getAccount()->getId(),
                'highlight' => $result['out']->getId(),
            ]);
        }

        return $this->render('transfer/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
