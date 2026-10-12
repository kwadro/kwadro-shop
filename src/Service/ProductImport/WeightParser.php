<?php

namespace App\Service\ProductImport;

/**
 * Parses supplier weight values ("200 г", "1.1 кг", "Вага: 0,58 кг", "400") into kilograms.
 */
final class WeightParser
{
    /** Product weight param names, most specific first. "Вага каміну" etc. hold materials, not weight. */
    private const PARAM_NAMES = [
        'вага',
        'вага товару',
        'вага виробу',
        'власна вага',
        'вага брутто',
        'вага нетто',
        'вага парасольки',
        'вага підсвічника',
        'вага ялини/сосни',
        'вага столу',
        'вага праски',
        'вес',
        'вес товара',
    ];

    /** Unit-less values at or above this are grams ("200"), below are kilograms ("1.5"). */
    private const UNITLESS_GRAMS_THRESHOLD = 20.0;

    private const MAX_KG = 1000.0;

    /**
     * @param list<array{name: string, value: string}> $params
     */
    public static function fromParams(array $params): ?float
    {
        $byName = [];
        foreach ($params as $param) {
            $name = mb_strtolower(trim((string) ($param['name'] ?? '')));
            if ($name !== '' && !isset($byName[$name])) {
                $byName[$name] = (string) ($param['value'] ?? '');
            }
        }

        foreach (self::PARAM_NAMES as $name) {
            if (isset($byName[$name])) {
                $kg = self::parseKg($byName[$name]);
                if ($kg !== null) {
                    return $kg;
                }
            }
        }

        return null;
    }

    public static function parseKg(string $raw): ?float
    {
        $value = mb_strtolower(trim($raw));
        if ($value === '') {
            return null;
        }

        // Number followed by a known unit or by nothing; "1.3 м" / "1.84 кн" are rejected.
        if (preg_match('/(?<![\d.,])(\d++(?:[.,]\d++)?+)(?:\s*(кг|kg|гр|г|gr|g)\.?)?(?=\s*(?:$|[^\p{L}\s]))/u', $value, $m) !== 1) {
            return null;
        }

        $number = (float) str_replace(',', '.', $m[1]);
        if ($number <= 0) {
            return null;
        }

        $unit = $m[2] ?? '';
        $kg = match (true) {
            \in_array($unit, ['кг', 'kg'], true) => $number,
            $unit !== '' => $number / 1000,
            $number >= self::UNITLESS_GRAMS_THRESHOLD => $number / 1000,
            default => $number,
        };

        return $kg > 0 && $kg <= self::MAX_KG ? round($kg, 3) : null;
    }
}
