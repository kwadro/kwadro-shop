<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\Supplier;
use App\Repository\ProductRepository;
use App\Repository\SiteRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ProductListingService
{
    public const DEFAULT_PER_PAGE = 12;

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly SiteRepository $siteRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param array{
     *   category?: Category|null,
     *   supplier?: Supplier|null,
     *   query?: string|null
     * } $scope
     * @return array{
     *   products: list<array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   perPage: int,
     *   pages: int,
     *   facets: array<string, list<string>>,
     *   filters: array<string, list<string>>,
     *   query: string,
     *   showFilters: bool
     * }
     */
    public function list(array $scope, Request $request, bool $showFilters = true): array
    {
        $query = trim((string) $request->query->get('q', $scope['query'] ?? ''));
        $filters = $this->parseFilters($request);
        $perPage = $this->resolveProductsPerPage();
        $page = max(1, (int) $request->query->get('page', 1));

        $criteria = [
            'category' => $scope['category'] ?? null,
            'supplier' => $scope['supplier'] ?? null,
            'query' => $query !== '' ? $query : null,
            'filters' => $filters,
        ];

        $facetCriteria = [
            'category' => $criteria['category'],
            'supplier' => $criteria['supplier'],
            'query' => $criteria['query'],
            'filters' => $filters,
        ];

        $pageResult = $this->productRepository->findCatalogPage($criteria, $page, $perPage);
        $total = $pageResult['total'];
        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
            $pageResult = $this->productRepository->findCatalogPage($criteria, $page, $perPage);
        }

        $products = array_map(
            static fn ($product): array => $product->toCatalogArray(),
            $pageResult['items'],
        );

        $facets = $showFilters
            ? $this->productRepository->findAttributeFacets($facetCriteria)
            : ['brand' => [], 'color' => [], 'type' => [], 'model' => []];

        // Hide attribute groups that have only one (or zero) options.
        foreach ($facets as $attribute => $options) {
            if (\count($options) < 2) {
                $facets[$attribute] = [];
            }
        }

        return [
            'products' => $products,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => $pages,
            'facets' => $facets,
            'filters' => $filters,
            'query' => $query,
            'showFilters' => $showFilters,
            'paginationQuery' => $this->buildPaginationQuery($query, $filters),
            'activeFilters' => $this->buildActiveFilters($query, $filters),
        ];
    }

    /**
     * @param array<string, list<string>> $filters
     * @return list<array{attr: string, value: string, query: array<string, mixed>}>
     */
    private function buildActiveFilters(string $query, array $filters): array
    {
        $items = [];
        foreach ($filters as $attribute => $values) {
            foreach ($values as $value) {
                $remaining = $filters;
                $remaining[$attribute] = array_values(array_filter(
                    $values,
                    static fn (string $candidate): bool => $candidate !== $value,
                ));
                $items[] = [
                    'attr' => $attribute,
                    'value' => $value,
                    'query' => $this->buildPaginationQuery($query, $remaining),
                ];
            }
        }

        return $items;
    }

    /**
     * @param array<string, list<string>> $filters
     * @return array<string, mixed>
     */
    private function buildPaginationQuery(string $query, array $filters): array
    {
        $params = [];
        if ($query !== '') {
            $params['q'] = $query;
        }
        foreach ($filters as $attribute => $values) {
            if ($values !== []) {
                $params[$attribute] = $values;
            }
        }

        return $params;
    }

    /**
     * @return array<string, list<string>>
     */
    public function parseFilters(Request $request): array
    {
        $filters = [];
        foreach (ProductRepository::FILTER_ATTRIBUTES as $attribute) {
            $raw = $request->query->all($attribute);
            if (!\is_array($raw)) {
                $single = $request->query->get($attribute);
                $raw = $single !== null && $single !== '' ? [$single] : [];
            }
            $values = [];
            foreach ($raw as $value) {
                $value = trim((string) $value);
                if ($value !== '') {
                    $values[] = $value;
                }
            }
            $filters[$attribute] = array_values(array_unique($values));
        }

        return $filters;
    }

    public function resolveProductsPerPage(): int
    {
        $request = $this->requestStack->getCurrentRequest();
        $host = $request?->getHost();
        if ($host === null || $host === '') {
            return self::DEFAULT_PER_PAGE;
        }

        $site = $this->siteRepository->findOneBy(['domain' => $host]);

        return $site?->getProductsPerPage() ?? self::DEFAULT_PER_PAGE;
    }
}
