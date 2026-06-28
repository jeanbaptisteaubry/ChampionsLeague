<?php
declare(strict_types=1);

namespace App\Service;

final class PointCalculator
{
    /**
     * Rules:
     * - 1N2 compares only the score tendency.
     * - scoreExact compares only the exact score.
     * - qualifieSiN compares the selected qualified team on every match.
     *   Example: a draw bet with home qualified still earns qualification
     *   points if home wins during regular time.
     * - finalistesChampion gives points for each finalist found, and double
     *   points when the champion is also correct.
     */
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
                $betQualifier = self::selectedQualifier($betVals);
                $resultQualifier = self::qualifier($resultVals);
                if ($betQualifier !== null && $resultQualifier !== null && $betQualifier === $resultQualifier) {
                    $earned += $points;
                }
            } elseif ($lib === 'finalistesChampion') {
                $earned += self::finalistsChampionPoints($betVals, $resultVals, $points);
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

    private static function selectedQualifier(array $values): ?int
    {
        return self::qualifierValue($values[3] ?? null) ?? self::qualifier($values);
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

    private static function finalistsChampionPoints(array $betVals, array $resultVals, int $points): int
    {
        $betFinalists = self::normalizedTeams([$betVals[1] ?? null, $betVals[2] ?? null]);
        $resultFinalists = self::normalizedTeams([$resultVals[1] ?? null, $resultVals[2] ?? null]);
        $earned = 0;

        foreach ($betFinalists as $team) {
            if (in_array($team, $resultFinalists, true)) {
                $earned += $points;
            }
        }

        $betChampion = self::normalizedTeam($betVals[3] ?? null);
        $resultChampion = self::normalizedTeam($resultVals[3] ?? null);
        if ($betChampion !== null && $resultChampion !== null && $betChampion === $resultChampion) {
            $earned += $points * 2;
        }

        return $earned;
    }

    private static function normalizedTeams(array $values): array
    {
        $teams = [];
        foreach ($values as $value) {
            $team = self::normalizedTeam($value);
            if ($team !== null && !in_array($team, $teams, true)) {
                $teams[] = $team;
            }
        }

        return $teams;
    }

    private static function normalizedTeam(mixed $value): ?string
    {
        $team = trim(mb_strtolower((string)$value));
        return $team !== '' ? $team : null;
    }
}
