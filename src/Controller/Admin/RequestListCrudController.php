<?php

namespace App\Controller\Admin;

use App\Admin\Filter\EmptyUserAgentFilter;
use App\Entity\RequestList;
use App\Repository\RequestListRepository;
use App\Service\Request\BotUserAgentClassifier;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

class RequestListCrudController extends AbstractCrudController
{
    private const PERIOD_TODAY = 'today';
    private const PERIOD_7D = '7d';
    private const PERIOD_28D = '28d';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RequestListRepository $requestListRepository,
        private readonly BotUserAgentClassifier $botUserAgentClassifier,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return RequestList::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_request_list_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_request_list', [], 'messages'))
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['ip', 'path', 'pathAfterRedirect', 'userAgent'])
            ->setPaginatorPageSize(50)
            ->overrideTemplate('crud/index', 'admin/request_list/index.html.twig');
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addCssFile('lib/admin-request-list.css')
            ->addJsFile('lib/admin-request-list-filters.js');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(DateTimeFilter::new('createdAt', $this->translator->trans('admin.request_list.created_at', [], 'messages')))
            ->add(TextFilter::new('ip'))
            ->add(TextFilter::new('path'))
            ->add(TextFilter::new('pathAfterRedirect'))
            ->add(TextFilter::new('userAgent', $this->translator->trans('admin.request_list.user_agent', [], 'messages')))
            ->add(
                EmptyUserAgentFilter::new(
                    'userAgentEmpty',
                    $this->translator->trans('admin.request_list.user_agent_status', [], 'messages'),
                )->setChoiceLabels(
                    $this->translator->trans('admin.request_list.user_agent_empty', [], 'messages'),
                    $this->translator->trans('admin.request_list.user_agent_filled', [], 'messages'),
                ),
            );
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield DateTimeField::new('createdAt', $this->translator->trans('admin.request_list.created_at', [], 'messages'));
        yield TextField::new('ip', $this->translator->trans('admin.request_list.ip', [], 'messages'));
        yield TextField::new('path', $this->translator->trans('admin.request_list.path', [], 'messages'));
        yield TextField::new('pathAfterRedirect', $this->translator->trans('admin.request_list.path_after_redirect', [], 'messages'));
        yield TextField::new('userAgent', $this->translator->trans('admin.request_list.user_agent', [], 'messages'))
            ->hideOnIndex();
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $alias = $queryBuilder->getRootAliases()[0] ?? 'entity';
        $this->applyQuickFilters($queryBuilder, $alias, $searchDto->getAppliedFilters());

        return $queryBuilder;
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        if ($responseParameters->get('pageName') === Crud::PAGE_INDEX) {
            $quick = $this->readQuickFilters();
            $stats = $this->buildFilteredStats($quick);
            $responseParameters->set('uniqueIpsToday', $stats['uniqueIps']);
            $responseParameters->set('requestsToday', $stats['requests']);
            $responseParameters->set('statsScopedToFilters', $stats['filtered']);
            $paths = $this->requestListRepository->findPopularPaths(500, 90);
            if ($quick['path'] !== '' && !\in_array($quick['path'], $paths, true)) {
                array_unshift($paths, $quick['path']);
            }
            $responseParameters->set('requestListFilterPaths', $paths);
            $responseParameters->set('requestListFilterPath', $quick['path']);
            $responseParameters->set('requestListFilterVisitor', $quick['visitor']);
            $responseParameters->set('requestListFilterPeriod', $quick['period']);
        }

        return $responseParameters;
    }

    /**
     * @param array $quick
     *
     * @return array
     */
    private function buildFilteredStats(array $quick): array
    {
        $context = $this->getContext();
        if (!$context instanceof AdminContext) {
            return [
                'uniqueIps' => $this->requestListRepository->countUniqueIpsToday(),
                'requests' => $this->requestListRepository->countToday(),
                'filtered' => false,
            ];
        }

        $fields = new FieldCollection($this->configureFields(Crud::PAGE_INDEX));
        $filters = $this->container->get(FilterFactory::class)->create(
            $context->getCrud()->getFiltersConfig(),
            $fields,
            $context->getEntity(),
        );
        $queryBuilder = $this->createIndexQueryBuilder(
            $context->getSearch(),
            $context->getEntity(),
            $fields,
            $filters,
        );

        $alias = $queryBuilder->getRootAliases()[0] ?? 'entity';
        $applied = $context->getSearch()->getAppliedFilters();
        $hasQuickFilters = $quick['path'] !== '' || $quick['visitor'] !== '' || $quick['period'] !== '';
        $hasExplicitFilters = $applied !== [] || $hasQuickFilters;

        $requestsQb = clone $queryBuilder;
        $requestsQb->resetDQLPart('orderBy')
            ->select(sprintf('COUNT(DISTINCT %s.id)', $alias))
            ->setFirstResult(null)
            ->setMaxResults(null);

        $uniqueQb = clone $queryBuilder;
        $uniqueQb->resetDQLPart('orderBy')
            ->select(sprintf('COUNT(DISTINCT %s.ip)', $alias))
            ->andWhere(sprintf('%s.ip IS NOT NULL', $alias))
            ->andWhere(sprintf("%s.ip <> ''", $alias))
            ->setFirstResult(null)
            ->setMaxResults(null);

        return [
            'uniqueIps' => (int) $uniqueQb->getQuery()->getSingleScalarResult(),
            'requests' => (int) $requestsQb->getQuery()->getSingleScalarResult(),
            'filtered' => $hasExplicitFilters,
        ];
    }

    /**
     * @return array
     */
    private function readQuickFilters(): array
    {
        $request = $this->getContext()?->getRequest();
        $path = trim((string) ($request?->query->get('rl_path') ?? ''));
        $visitor = trim((string) ($request?->query->get('rl_visitor') ?? ''));
        $period = trim((string) ($request?->query->get('rl_period') ?? ''));

        if ($visitor !== 'bots' && $visitor !== 'humans') {
            $visitor = '';
        }
        if (!\in_array($period, [self::PERIOD_TODAY, self::PERIOD_7D, self::PERIOD_28D], true)) {
            $period = '';
        }

        return [
            'path' => $path,
            'visitor' => $visitor,
            'period' => $period,
        ];
    }

    /**
     * @param array $appliedFilters
     */
    private function applyQuickFilters(QueryBuilder $queryBuilder, string $alias, array $appliedFilters): void
    {
        $quick = $this->readQuickFilters();

        if ($quick['path'] !== '') {
            $queryBuilder
                ->andWhere(sprintf('%s.path = :requestListQuickPath', $alias))
                ->setParameter('requestListQuickPath', $quick['path']);
        }

        // Period select is skipped when the standard createdAt filter is already applied.
        if ($quick['period'] !== '' && !isset($appliedFilters['createdAt'])) {
            $range = $this->resolvePeriodRange($quick['period']);
            if ($range !== null) {
                $queryBuilder
                    ->andWhere(sprintf('%s.createdAt >= :requestListPeriodFrom', $alias))
                    ->andWhere(sprintf('%s.createdAt < :requestListPeriodTo', $alias))
                    ->setParameter('requestListPeriodFrom', $range['from'])
                    ->setParameter('requestListPeriodTo', $range['to']);
            }
        }

        if ($quick['visitor'] === '') {
            return;
        }

        $patterns = $this->botUserAgentClassifier->sqlLikePatterns();
        $uaExpr = sprintf('LOWER(COALESCE(%s.userAgent, \'\'))', $alias);

        if ($patterns === []) {
            if ($quick['visitor'] === 'bots') {
                $queryBuilder->andWhere('1 = 0');
            }

            return;
        }

        $parts = [];
        foreach ($patterns as $i => $pattern) {
            $param = 'requestListBotPattern'.$i;
            $parts[] = sprintf('%s LIKE :%s', $uaExpr, $param);
            $queryBuilder->setParameter($param, $pattern);
        }

        $botCondition = '('.implode(' OR ', $parts).')';
        if ($quick['visitor'] === 'bots') {
            $queryBuilder->andWhere($botCondition);

            return;
        }

        $queryBuilder->andWhere(sprintf('NOT %s', $botCondition));
    }

    /**
     * @return array|null
     */
    private function resolvePeriodRange(string $period): ?array
    {
        $timezone = new \DateTimeZone(date_default_timezone_get() ?: 'Europe/Kyiv');
        $today = new \DateTimeImmutable('today', $timezone);
        $to = $today->modify('+1 day');

        return match ($period) {
            self::PERIOD_TODAY => [
                'from' => $today,
                'to' => $to,
            ],
            self::PERIOD_7D => [
                'from' => $today->modify('-6 days'),
                'to' => $to,
            ],
            self::PERIOD_28D => [
                'from' => $today->modify('-27 days'),
                'to' => $to,
            ],
            default => null,
        };
    }
}
