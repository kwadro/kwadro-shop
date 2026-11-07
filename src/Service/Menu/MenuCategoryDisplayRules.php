<?php

namespace App\Service\Menu;

use App\Entity\ProductImport;

/**
 * Storefront menu display overrides. Stored beside the import, not on the category,
 * so the next file import can keep writing parent and position.
 */
final class MenuCategoryDisplayRules
{
    /**
     * @param list<int> $topCategoryIds
     * @param array<int, int> $displayParentByCategoryId
     * @param array<int, bool> $showInMenuByCategoryId Explicit menu visibility. Missing ids stay visible.
     */
    public function __construct(
        public readonly array $topCategoryIds = [],
        public readonly array $displayParentByCategoryId = [],
        public readonly array $showInMenuByCategoryId = [],
    ) {
    }

    public function isShownInMenu(int $categoryId): bool
    {
        return $this->showInMenuByCategoryId[$categoryId] ?? true;
    }

    /**
     * @param iterable<ProductImport> $imports
     */
    public static function fromImports(iterable $imports): self
    {
        /** @var list<array{id: int, position: int, order: int}> $ranked */
        $ranked = [];
        /** @var array<int, true> $seenTop */
        $seenTop = [];
        /** @var array<int, int> $moves */
        $moves = [];
        /** @var array<int, bool> $visibility */
        $visibility = [];
        $order = 0;

        foreach ($imports as $import) {
            if (!$import instanceof ProductImport || !$import->isActive()) {
                continue;
            }

            foreach ($import->getMenuTopCategories() as $row) {
                $id = (int) ($row['category_id'] ?? 0);
                if ($id < 1 || isset($seenTop[$id])) {
                    continue;
                }
                $seenTop[$id] = true;
                $ranked[] = [
                    'id' => $id,
                    'position' => (int) ($row['position'] ?? 0),
                    'order' => $order++,
                ];
            }

            foreach ($import->getMenuCategoryMoves() as $row) {
                $id = (int) ($row['category_id'] ?? 0);
                $parentId = (int) ($row['parent_id'] ?? 0);
                if ($id < 1 || $parentId < 1 || $id === $parentId) {
                    continue;
                }
                $moves[$id] = $parentId;
            }

            foreach ($import->getMenuCategoryVisibility() as $row) {
                $id = (int) ($row['category_id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $visibility[$id] = (bool) ($row['show'] ?? true);
            }
        }

        usort($ranked, static function (array $a, array $b): int {
            $byPosition = $a['position'] <=> $b['position'];
            if ($byPosition !== 0) {
                return $byPosition;
            }

            return $a['order'] <=> $b['order'];
        });

        return new self(
            array_map(static fn (array $row): int => $row['id'], $ranked),
            $moves,
            $visibility,
        );
    }
}
