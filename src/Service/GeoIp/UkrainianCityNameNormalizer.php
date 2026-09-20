<?php

namespace App\Service\GeoIp;

/**
 * Maps English/Latin GeoIP city names to Ukrainian spellings expected by Nova Poshta.
 */
class UkrainianCityNameNormalizer
{
    /** @var array<string, string> */
    private const ALIASES = [
        'ivano frankivsk' => 'Івано-Франківськ',
        'kyiv' => 'Київ',
        'kiev' => 'Київ',
        'lviv' => 'Львів',
        'lvov' => 'Львів',
        'kharkiv' => 'Харків',
        'kharkov' => 'Харків',
        'odesa' => 'Одеса',
        'odessa' => 'Одеса',
        'dnipro' => 'Дніпро',
        'dnipropetrovsk' => 'Дніпро',
        'zaporizhzhia' => 'Запоріжжя',
        'zaporizhia' => 'Запоріжжя',
        'vinnytsia' => 'Вінниця',
        'vinnitsa' => 'Вінниця',
        'mykolaiv' => 'Миколаїв',
        'nikolayev' => 'Миколаїв',
        'poltava' => 'Полтава',
        'chernihiv' => 'Чернігів',
        'chernigov' => 'Чернігів',
        'cherkasy' => 'Черкаси',
        'sumy' => 'Суми',
        'zhytomyr' => 'Житомир',
        'khmelnytskyi' => 'Хмельницький',
        'khmelnitsky' => 'Хмельницький',
        'rivne' => 'Рівне',
        'rovno' => 'Рівне',
        'ternopil' => 'Тернопіль',
        'lutsk' => 'Луцьк',
        'uzhhorod' => 'Ужгород',
        'uzhgorod' => 'Ужгород',
        'kryvyi rih' => 'Кривий Ріг',
        'krivoy rog' => 'Кривий Ріг',
        'mariupol' => 'Маріуполь',
        'sevastopol' => 'Севастополь',
        'simferopol' => 'Сімферополь',
    ];

    public function normalize(string $city, ?string $countryCode = null): string
    {
        $city = trim($city);
        if ($city === '') {
            return '';
        }

        $key = $this->toLookupKey($city);
        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        if ($countryCode !== null && $countryCode !== '' && strtoupper($countryCode) !== 'UA') {
            return $city;
        }

        return $city;
    }

    public function wasAliased(string $original, string $normalized): bool
    {
        return $this->toLookupKey($original) !== $this->toLookupKey($normalized);
    }

    private function toLookupKey(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = str_replace(['-', '_', '’', "'"], ' ', $name);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return $name;
    }
}
