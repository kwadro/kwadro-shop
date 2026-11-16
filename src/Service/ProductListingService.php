<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\ProductAttribute;
use App\Entity\Supplier;
use App\Repository\ProductAttributeRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductSearchSettingRepository;
use App\Repository\SiteRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ProductListingService
{
    public const DEFAULT_PER_PAGE = 12;

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductAttributeRepository $productAttributeRepository,
        private readonly ProductSearchSettingRepository $productSearchSettingRepository,
        private readonly SiteRepository $siteRepository,
        private readonly RequestStack $requestStack,
        private readonly CustomerPriceApplier $customerPriceApplier,
    ) {
    }

    /**
     * @param array{
     *   category?: Category|null,
     *   supplier?: Supplier|null,
     *   query?: string|null,
     *   facets?: list<string>
     * } $scope
     * @return array{
     *   products: list<array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   perPage: int,
     *   pages: int,
     *   facets: array<string, list<array{value: string, count: int}>>,
     *   facetLabels: array<string, string>,
     *   filters: array<string, list<string>>,
     *   query: string,
     *   showFilters: bool,
     *   sort: string,
     *   sortDir: string,
     *   priceMin: float|null,
     *   priceMax: float|null,
     *   priceBounds: array{min: float, max: float}|null,
     *   paginationQuery: array<string, mixed>,
     *   activeFilters: list<array{attr: string, value: string, query: array<string, mixed>}>
     * }
     */
    public function list(array $scope, Request $request, bool $showFilters = true): array
    {
        $query = trim((string) $request->query->get('q', $scope['query'] ?? ''));
        $category = ($scope['category'] ?? null) instanceof Category ? $scope['category'] : null;
        // Optional allow-list of built-in filter keys (e.g. ['brand']); dynamic attributes are skipped then.
        $allowedFacets = isset($scope['facets']) && \is_array($scope['facets']) ? array_values($scope['facets']) : null;
        $filterAttributes = $showFilters && $allowedFacets === null
            ? $this->productAttributeRepository->findFilterableForCategory($category)
            : [];
        $filters = $showFilters ? $this->parseFilters($request, $filterAttributes) : [];
        if ($allowedFacets !== null) {
            $filters = array_intersect_key($filters, array_flip($allowedFacets));
        }
        $sort = $this->parseSort($request);
        $sortDir = $this->parseSortDir($request);
        $priceRange = $showFilters ? $this->parsePriceRange($request) : ['min' => null, 'max' => null];
        $perPage = $this->resolveProductsPerPage();
        $page = max(1, (int) $request->query->get('page', 1));

        $searchFields = $this->resolveSearchFields();
        $inStockOnly = $category !== null && !$category->isShowOutOfStock();

        $criteria = [
            'category' => $category,
            'supplier' => $scope['supplier'] ?? null,
            'query' => $query !== '' ? $query : null,
            'filters' => $filters,
            'searchFields' => $searchFields,
            'inStockOnly' => $inStockOnly,
            'filterAttributes' => $filterAttributes,
            'sort' => $sort,
            'sortDir' => $sortDir,
            'priceMin' => $priceRange['min'],
            'priceMax' => $priceRange['max'],
        ];

        $facetCriteria = [
            'category' => $criteria['category'],
            'supplier' => $criteria['supplier'],
            'query' => $criteria['query'],
            'filters' => $filters,
            'searchFields' => $searchFields,
            'inStockOnly' => $inStockOnly,
            'filterAttributes' => $filterAttributes,
            'facetKeys' => $allowedFacets,
            'priceMin' => $priceRange['min'],
            'priceMax' => $priceRange['max'],
        ];

        $priceBounds = $showFilters
            ? $this->productRepository->findCatalogPriceBounds($criteria)
            : null;
        if ($priceBounds !== null) {
            $priceRange = $this->clampPriceRange($priceRange, $priceBounds);
            $criteria['priceMin'] = $priceRange['min'];
            $criteria['priceMax'] = $priceRange['max'];
            $facetCriteria['priceMin'] = $priceRange['min'];
            $facetCriteria['priceMax'] = $priceRange['max'];
        } else {
            $priceRange = ['min' => null, 'max' => null];
            $criteria['priceMin'] = null;
            $criteria['priceMax'] = null;
            $facetCriteria['priceMin'] = null;
            $facetCriteria['priceMax'] = null;
        }

        $pageResult = $this->productRepository->findCatalogPage($criteria, $page, $perPage);
        $total = $pageResult['total'];
        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
            $pageResult = $this->productRepository->findCatalogPage($criteria, $page, $perPage);
        }

        $products = $this->customerPriceApplier->applyToCatalogItems(array_map(
            static fn ($product): array => $product->toCatalogArray(),
            $pageResult['items'],
        ));

        $facets = $showFilters
            ? $this->productRepository->findAttributeFacets($facetCriteria)
            : ['brand' => [], 'color' => [], 'type' => [], 'model' => []];

        if ($allowedFacets !== null) {
            $facets = array_intersect_key($facets, array_flip($allowedFacets));
        }

        // Hide attribute groups that have only one (or zero) options.
        foreach ($facets as $attribute => $options) {
            if (\count($options) < 2) {
                $facets[$attribute] = [];
            }
        }

        $listingState = [
            'sort' => $sort,
            'sortDir' => $sortDir,
            'priceMin' => $priceRange['min'],
            'priceMax' => $priceRange['max'],
        ];

        return [
            'products' => $products,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => $pages,
            'facets' => $facets,
            'facetLabels' => $this->buildFacetLabels($filterAttributes),
            'filters' => $filters,
            'query' => $query,
            'showFilters' => $showFilters,
            'sort' => $sort,
            'sortDir' => $sortDir,
            'priceMin' => $priceRange['min'],
            'priceMax' => $priceRange['max'],
            'priceBounds' => $priceBounds,
            'paginationQuery' => $this->buildPaginationQuery($query, $filters, $listingState),
            'activeFilters' => $this->buildActiveFilters($query, $filters, $listingState, $priceBounds),
        ];
    }

    /**
     * @param list<ProductAttribute> $filterAttributes
     * @return array<string, string>
     */
    private function buildFacetLabels(array $filterAttributes): array
    {
        $labels = [];
        foreach ($filterAttributes as $attribute) {
            $labels[$attribute->getFilterKey()] = $attribute->getName();
        }

        return $labels;
    }

    /**
     * @param array<string, list<string>> $filters
     * @param array{sort: string, sortDir: string, priceMin: float|null, priceMax: float|null} $listingState
     * @param array{min: float, max: float}|null $priceBounds
     * @return list<array{attr: string, value: string, query: array<string, mixed>}>
     */
    private function buildActiveFilters(string $query, array $filters, array $listingState, ?array $priceBounds): array
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
                    'query' => $this->buildPaginationQuery($query, $remaining, $listingState),
                ];
            }
        }

        if (
            $priceBounds !== null
            && (
                ($listingState['priceMin'] !== null && $listingState['priceMin'] > $priceBounds['min'])
                || ($listingState['priceMax'] !== null && $listingState['priceMax'] < $priceBounds['max'])
            )
        ) {
            $minLabel = $listingState['priceMin'] ?? $priceBounds['min'];
            $maxLabel = $listingState['priceMax'] ?? $priceBounds['max'];
            $clearedPrice = $listingState;
            $clearedPrice['priceMin'] = null;
            $clearedPrice['priceMax'] = null;
            $items[] = [
                'attr' => 'price',
                'value' => $this->formatPriceChip($minLabel, $maxLabel),
                'query' => $this->buildPaginationQuery($query, $filters, $clearedPrice),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, list<string>> $filters
     * @param array{sort?: string, sortDir?: string, priceMin?: float|null, priceMax?: float|null} $listingState
     * @return array<string, mixed>
     */
    private function buildPaginationQuery(string $query, array $filters, array $listingState = []): array
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

        $sort = $listingState['sort'] ?? ProductRepository::SORT_POSITION;
        $sortDir = $listingState['sortDir'] ?? ProductRepository::SORT_DIR_ASC;
        if ($sort !== ProductRepository::SORT_POSITION) {
            $params['sort'] = $sort;
        }
        if ($sortDir !== ProductRepository::SORT_DIR_ASC) {
            $params['dir'] = $sortDir;
        }

        if (isset($listingState['priceMin']) && $listingState['priceMin'] !== null) {
            $params['price_min'] = (int) $listingState['priceMin'];
        }
        if (isset($listingState['priceMax']) && $listingState['priceMax'] !== null) {
            $params['price_max'] = (int) $listingState['priceMax'];
        }

        return $params;
    }

    /**
     * @param list<ProductAttribute> $filterAttributes
     * @return array<string, list<string>>
     */
    public function parseFilters(Request $request, array $filterAttributes = []): array
    {
        $keys = ProductRepository::FILTER_ATTRIBUTES;
        foreach ($filterAttributes as $attribute) {
            $keys[] = $attribute->getFilterKey();
        }
        $keys = array_values(array_unique($keys));

        $filters = [];
        foreach ($keys as $attribute) {
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

    public function parseSort(Request $request): string
    {
        $sort = strtolower(trim((string) $request->query->get('sort', ProductRepository::SORT_POSITION)));

        return \in_array($sort, [ProductRepository::SORT_POSITION, ProductRepository::SORT_PRICE], true)
            ? $sort
            : ProductRepository::SORT_POSITION;
    }

    public function parseSortDir(Request $request): string
    {
        $dir = strtolower(trim((string) $request->query->get('dir', ProductRepository::SORT_DIR_ASC)));

        return $dir === ProductRepository::SORT_DIR_DESC
            ? ProductRepository::SORT_DIR_DESC
            : ProductRepository::SORT_DIR_ASC;
    }

    /**
     * @return array{min: float|null, max: float|null}
     */
    public function parsePriceRange(Request $request): array
    {
        return [
            'min' => $this->parsePriceParam($request->query->get('price_min')),
            'max' => $this->parsePriceParam($request->query->get('price_max')),
        ];
    }

    private function parsePriceParam(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }

        $price = (float) $value;
        if ($price < 0) {
            return null;
        }

        return $price;
    }

    /**
     * @param array{min: float|null, max: float|null} $range
     * @param array{min: float, max: float} $bounds
     * @return array{min: float|null, max: float|null}
     */
    private function clampPriceRange(array $range, array $bounds): array
    {
        $min = $range['min'];
        $max = $range['max'];

        if ($min !== null) {
            $min = max($bounds['min'], min($bounds['max'], $min));
        }
        if ($max !== null) {
            $max = max($bounds['min'], min($bounds['max'], $max));
        }
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        // Treat full-range selection as "no price filter".
        if ($min !== null && $min <= $bounds['min']) {
            $min = null;
        }
        if ($max !== null && $max >= $bounds['max']) {
            $max = null;
        }

        return ['min' => $min, 'max' => $max];
    }

    private function formatPriceChip(float $min, float $max): string
    {
        return sprintf(
            '%s – %s ₴',
            number_format($min, 0, ',', ' '),
            number_format($max, 0, ',', ' '),
        );
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

    /**
     * @return list<string>
     */
    public function resolveSearchFields(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $host = $request?->getHost();

        return $this->productSearchSettingRepository->resolveSearchFieldsForDomain($host);
    }
}
