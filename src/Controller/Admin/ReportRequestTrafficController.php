<?php

namespace App\Controller\Admin;

use App\Repository\RequestListRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/{_locale}/report-request-traffic', name: 'admin_report_request_traffic', requirements: ['_locale' => 'ua|en'])]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class ReportRequestTrafficController extends AbstractController
{
    private const PERIOD_TODAY = 'today';
    private const PERIOD_7D = '7d';
    private const PERIOD_30D = '30d';
    private const PERIOD_CUSTOM = 'custom';
    private const MAX_CUSTOM_DAYS = 90;

    public function __construct(
        private readonly RequestListRepository $requestListRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $resolved = $this->resolvePeriod($request);

        return $this->render('admin/report_request_traffic/index.html.twig', [
            'page_title' => $this->translator->trans('menu.link_report_request_traffic', [], 'messages'),
            'period' => $resolved['period'],
            'fromDate' => $resolved['fromDate'],
            'toDate' => $resolved['toDate'],
            'data_url' => $this->generateUrl('admin_report_request_traffic_data', [
                '_locale' => $request->attributes->get('_locale', 'ua'),
            ]),
            'chart_labels' => [
                'requests' => $this->translator->trans('admin.report_request_traffic.series_requests', [], 'messages'),
                'uniqueIps' => $this->translator->trans('admin.report_request_traffic.series_unique_ips', [], 'messages'),
            ],
        ]);
    }

    #[Route('/data', name: '_data', methods: ['GET'])]
    public function data(Request $request): JsonResponse
    {
        $resolved = $this->resolvePeriod($request);
        $series = $this->requestListRepository->getNonBotTrafficSeries(
            $resolved['from'],
            $resolved['to'],
        );

        return $this->json([
            'period' => $resolved['period'],
            'fromDate' => $resolved['fromDate'],
            'toDate' => $resolved['toDate'],
            'mode' => $series['mode'],
            'labels' => $series['labels'],
            'requests' => $series['requests'],
            'uniqueIps' => $series['uniqueIps'],
        ]);
    }

    /**
     * @return array
     */
    private function resolvePeriod(Request $request): array
    {
        $timezone = new \DateTimeZone(date_default_timezone_get() ?: 'Europe/Kyiv');
        $today = new \DateTimeImmutable('today', $timezone);
        $period = trim((string) $request->query->get('period', self::PERIOD_7D));
        if (!\in_array($period, [self::PERIOD_TODAY, self::PERIOD_7D, self::PERIOD_30D, self::PERIOD_CUSTOM], true)) {
            $period = self::PERIOD_7D;
        }

        if ($period === self::PERIOD_TODAY) {
            return [
                'period' => $period,
                'from' => $today,
                'to' => $today->modify('+1 day'),
                'fromDate' => $today->format('Y-m-d'),
                'toDate' => $today->format('Y-m-d'),
            ];
        }

        if ($period === self::PERIOD_7D) {
            $from = $today->modify('-6 days');

            return [
                'period' => $period,
                'from' => $from,
                'to' => $today->modify('+1 day'),
                'fromDate' => $from->format('Y-m-d'),
                'toDate' => $today->format('Y-m-d'),
            ];
        }

        if ($period === self::PERIOD_30D) {
            $from = $today->modify('-29 days');

            return [
                'period' => $period,
                'from' => $from,
                'to' => $today->modify('+1 day'),
                'fromDate' => $from->format('Y-m-d'),
                'toDate' => $today->format('Y-m-d'),
            ];
        }

        $fromDate = $this->parseDate((string) $request->query->get('from', ''), $timezone) ?? $today->modify('-6 days');
        $toDate = $this->parseDate((string) $request->query->get('to', ''), $timezone) ?? $today;
        if ($toDate < $fromDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $maxTo = $fromDate->modify('+'.(self::MAX_CUSTOM_DAYS - 1).' days');
        if ($toDate > $maxTo) {
            $toDate = $maxTo;
        }
        if ($toDate > $today) {
            $toDate = $today;
        }

        return [
            'period' => self::PERIOD_CUSTOM,
            'from' => $fromDate,
            'to' => $toDate->modify('+1 day'),
            'fromDate' => $fromDate->format('Y-m-d'),
            'toDate' => $toDate->format('Y-m-d'),
        ];
    }

    private function parseDate(string $value, \DateTimeZone $timezone): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);

        return $date instanceof \DateTimeImmutable ? $date : null;
    }
}
