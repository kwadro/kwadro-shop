<?php

namespace App\Controller\Admin;

use App\Repository\RequestListRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/{_locale}/report-visitor-ids', name: 'admin_report_visitor_ids', requirements: ['_locale' => 'uk|en'])]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class ReportVisitorIdsController extends AbstractController
{
    public function __construct(
        private readonly RequestListRepository $requestListRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        $stats = $this->requestListRepository->getDailyIpStats(7);

        return $this->render('admin/report_visitor_ids/index.html.twig', [
            'page_title' => $this->translator->trans('menu.link_report_visitor_ids', [], 'messages'),
            'rows' => $stats['rows'],
            'totals' => $stats['totals'],
            'days' => 7,
        ]);
    }
}
