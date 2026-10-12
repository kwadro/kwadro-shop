<?php

namespace App\Service\ProductImport;


use App\Entity\Category;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class GeneratorSlugService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $categoryRepository,
        private ProductRepository $productRepository,
    ) {
    }
    public function transferSkuAndIdToSlug($sku, $id): string
    {
        $value = mb_strtolower(trim($sku)).'-'.$id;
        $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
        $value = trim($value, '-_');
        return $value !== '' ? $value : 'category';
    }
    public function transferCategoryNameToSlug(string $name): string
    {
        $map = [
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'h',
            'ґ' => 'g',
            'д' => 'd',
            'е' => 'e',
            'є' => 'ye',
            'ж' => 'zh',
            'з' => 'z',
            'и' => 'y',
            'і' => 'i',
            'ї' => 'yi',
            'й' => 'y',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'kh',
            'ц' => 'ts',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'shch',
            'ь' => '',
            'ю' => 'yu',
            'я' => 'ya',
            'ы' => 'y',
            'э' => 'e',
            'ъ' => '',
        ];

        $value = mb_strtolower(trim($name));
        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
        $value = trim($value, '-_');

        return $value !== '' ? $value : 'category';
    }

    public function assignUniqueSlug($object, string $baseSlug): void
    {
        $defaultSlug = $repository = null;
        switch ($object) {
            case $object instanceof Category:
                $repository = $this->categoryRepository;
                $defaultSlug = 'category';
                break;
            case $object instanceof Product:
                $repository = $this->productRepository;
                $defaultSlug = 'product';
                break;
        }
        if (!$defaultSlug || !$repository) {
            throw new \RuntimeException(
                'Object has  wrong class.'
            );
        }
        $baseSlug = ($baseSlug !== '') ? $baseSlug : $defaultSlug;
        $candidate = $baseSlug;
        $n = 0;

        while (true) {
            $existing = $repository->findOneBy(['slug' => $candidate]);
            if (!$existing instanceof Category) {
                $object->setSlug($candidate);
                return;
            }

            if ($object->getId() !== null && $existing->getId() === $object->getId()) {
                $object->setSlug($candidate);
                return;
            }

            if (
                $object->getExternalId() !== null
                && $existing->getExternalId() === $object->getExternalId()
            ) {
                $object->setSlug($candidate);
                return;
            }

            ++$n;
            if ($n === 1 && $object->getExternalId() !== null) {
                $candidate = $baseSlug . '-' . $object->getExternalId();
            } else {
                $candidate = $baseSlug . '-' . $n;
            }

            if ($n > 1000) {
                $object->setSlug($baseSlug . '-' . uniqid('', true));
                return;
            }
        }
    }
}
