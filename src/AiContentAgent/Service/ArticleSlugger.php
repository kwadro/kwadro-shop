<?php

namespace App\AiContentAgent\Service;

use App\Routing\ShopRoutes;

final class ArticleSlugger
{
    public function slugify(string $value): string
    {
        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'h', 'ґ' => 'g', 'д' => 'd', 'е' => 'e', 'є' => 'ye',
            'ж' => 'zh', 'з' => 'z', 'и' => 'y', 'і' => 'i', 'ї' => 'yi', 'й' => 'y', 'к' => 'k', 'л' => 'l',
            'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ь' => '', 'ю' => 'yu',
            'я' => 'ya', 'ы' => 'y', 'э' => 'e', 'ъ' => '', 'ё' => 'e',
        ];

        $value = mb_strtolower(trim($value));
        $value = str_replace(['ий', 'ій'], ['yi', 'iy'], $value);
        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
        $value = trim($value, '-_');
        $value = preg_replace('/-{2,}/', '-', $value) ?? $value;

        if ($value === '' || !preg_match('#^(?:'.ShopRoutes::SLUG_REQUIREMENTS.')$#', $value)) {
            $value = 'article';
        }

        if (mb_strlen($value) > 80) {
            $value = rtrim(mb_substr($value, 0, 80), '-_');
        }

        return $value !== '' ? $value : 'article';
    }
}
