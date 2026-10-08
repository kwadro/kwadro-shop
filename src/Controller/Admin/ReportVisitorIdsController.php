<?php

namespace App\Controller\Admin;

use App\Repository\RequestListRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/{_locale}/report-visitor-ids', name: 'admin_report_visitor_ids', requirements: ['_locale' => 'uk|en'])]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class ReportVisitorIdsController extends AbstractController
{
    /** @var list<int> */
    public const ALLOWED_PERIODS = [7, 14, 30];

    public const DEFAULT_PERIOD = 7;

    public function __construct(
        private readonly RequestListRepository $requestListRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $days = $this->resolveDays($request);
        $stats = $this->requestListRepository->getDailyIpStats($days);

        return $this->render('admin/report_visitor_ids/index.html.twig', [
            'page_title' => $this->translator->trans('menu.link_report_visitor_ids', [], 'messages'),
            'rows' => $stats['rows'],
            'totals' => $stats['totals'],
            'days' => $days,
            'periods' => self::ALLOWED_PERIODS,
            'table_url' => $this->generateUrl('admin_report_visitor_ids_table', [
                '_locale' => $request->attributes->get('_locale', 'uk'),
            ]),
        ]);
    }

    #[Route('/table', name: '_table', methods: ['GET'])]
    public function table(Request $request): Response
    {
        $days = $this->resolveDays($request);
        $stats = $this->requestListRepository->getDailyIpStats($days);

        return $this->render('admin/report_visitor_ids/_table.html.twig', [
            'rows' => $stats['rows'],
            'totals' => $stats['totals'],
            'days' => $days,
        ]);
    }

    private function resolveDays(Request $request): int
    {
        $days = (int) $request->query->get('days', self::DEFAULT_PERIOD);

        return \in_array($days, self::ALLOWED_PERIODS, true) ? $days : self::DEFAULT_PERIOD;
    }
}
