<?php

namespace App\PartnerProgram\Controller\Admin;

use App\PartnerProgram\Repository\PartnerReferralHitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/{_locale}/partner-referral-stats', name: 'admin_partner_referral_stats', requirements: ['_locale' => 'ua|en'])]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class PartnerReferralStatsController extends AbstractController
{
    /** @var list<int> */
    public const ALLOWED_PERIODS = [7, 14, 30];

    public const DEFAULT_PERIOD = 7;

    public function __construct(
        private readonly PartnerReferralHitRepository $hitRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $days = $this->resolveDays($request);
        $stats = $this->hitRepository->getDailyAdSourceStats($days);

        return $this->render('admin/partner_referral_stats/index.html.twig', [
            'page_title' => $this->translator->trans('menu.link_partner_referral_stats', [], 'messages'),
            'rows' => $stats['rows'],
            'totals' => $stats['totals'],
            'days' => $days,
            'periods' => self::ALLOWED_PERIODS,
            'table_url' => $this->generateUrl('admin_partner_referral_stats_table', [
                '_locale' => $request->attributes->get('_locale', 'ua'),
            ]),
        ]);
    }

    #[Route('/table', name: '_table', methods: ['GET'])]
    public function table(Request $request): Response
    {
        $days = $this->resolveDays($request);
        $stats = $this->hitRepository->getDailyAdSourceStats($days);

        return $this->render('admin/partner_referral_stats/_table.html.twig', [
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
