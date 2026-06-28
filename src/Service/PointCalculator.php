<?php
declare(strict_types=1);

namespace App\Service;

final class PointCalculator
{
    public static function earned(array $betVals, array $resultVals, array $calculations): int
    {
        $earned = 0;
        $b1 = self::intValue($betVals, 1);
        $b2 = self::intValue($betVals, 2);
        $r1 = self::intValue($resultVals, 1);
        $r2 = self::intValue($resultVals, 2);

        foreach ($calculations as $calculation) {
            $lib = (string)($calculation['libelle'] ?? '');
            $points = (int)($calculation['nbPoint'] ?? 0);
            if ($lib === '1N2') {
                if ($b1 !== null && $b2 !== null && $r1 !== null && $r2 !== null
                    && ($b1 <=> $b2) === ($r1 <=> $r2)) {
                    $earned += $points;
                }
            } elseif ($lib === 'scoreExact') {
                if ($b1 !== null && $b2 !== null && $r1 !== null && $r2 !== null
                    && $b1 === $r1 && $b2 === $r2) {
                    $earned += $points;
                }
            } elseif ($lib === 'qualifieSiN') {
                $betQualifier = self::qualifier($betVals);
                $resultQualifier = self::qualifier($resultVals);
                if ($betQualifier !== null && $resultQualifier !== null && $betQualifier === $resultQualifier) {
                    $earned += $points;
                }
            }
        }

        return $earned;
    }

    public static function qualifier(array $values): ?int
    {
        $home = self::intValue($values, 1);
        $away = self::intValue($values, 2);
        if ($home === null || $away === null) {
            return null;
        }
        if ($home > $away) {
            return 1;
        }
        if ($away > $home) {
            return 2;
        }

        return self::qualifierValue($values[3] ?? null);
    }

    private static function intValue(array $values, int $number): ?int
    {
        if (!isset($values[$number]) || trim((string)$values[$number]) === '') {
            return null;
        }
        return (int)$values[$number];
    }

    private static function qualifierValue(mixed $value): ?int
    {
        $value = trim(mb_strtolower((string)$value));
        if ($value === '') {
            return null;
        }
        if (in_array($value, ['1', 'domicile', 'home', 'd'], true)) {
            return 1;
        }
        if (in_array($value, ['2', 'exterieur', 'extérieur', 'away', 'e'], true)) {
            return 2;
        }
        return null;
    }
}
