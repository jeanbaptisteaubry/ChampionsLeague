<?php
declare(strict_types=1);

namespace App\Service;

final class BetDisplayFormatter
{
    public static function html(array $values, array $labels, string $itemLabel): string
    {
        if (!self::hasQualifierColumn($labels)) {
            return self::escape(self::plain($values, $labels, $itemLabel));
        }

        $score = self::score($values);
        if ($score === null) {
            return '';
        }

        [$homeScore, $awayScore] = $score;
        $qualified = self::qualifiedSide($values);
        $home = self::escape((string)$homeScore);
        $away = self::escape((string)$awayScore);

        if ($homeScore !== $awayScore && $qualified === 1) {
            $home = '<strong>' . $home . '</strong>';
        } elseif ($homeScore !== $awayScore && $qualified === 2) {
            $away = '<strong>' . $away . '</strong>';
        }

        $html = $home . ' - ' . $away;
        if ($homeScore === $awayScore && $qualified !== null) {
            $team = self::teamName($itemLabel, $qualified);
            $html .= ' (<strong>' . self::escape($team) . ' qualifi&eacute;</strong>)';
        }

        return $html;
    }

    public static function plain(array $values, array $labels, string $itemLabel): string
    {
        if (!self::hasQualifierColumn($labels)) {
            $ordered = [];
            foreach ($values as $value) {
                if (trim((string)$value) !== '') {
                    $ordered[] = (string)$value;
                }
            }
            return implode(' | ', $ordered);
        }

        $score = self::score($values);
        if ($score === null) {
            return '';
        }

        [$homeScore, $awayScore] = $score;
        $qualified = self::qualifiedSide($values);
        $text = (string)$homeScore . ' - ' . (string)$awayScore;
        if ($homeScore === $awayScore && $qualified !== null) {
            $text .= ' (' . self::teamName($itemLabel, $qualified) . ' qualifie)';
        }

        return $text;
    }

    public static function hasQualifierColumn(array $labels): bool
    {
        foreach ($labels as $label) {
            if (str_contains(mb_strtolower((string)$label), 'qualifi')) {
                return true;
            }
        }
        return false;
    }

    private static function score(array $values): ?array
    {
        if (!isset($values[1], $values[2]) || trim((string)$values[1]) === '' || trim((string)$values[2]) === '') {
            return null;
        }

        return [(int)$values[1], (int)$values[2]];
    }

    private static function qualifiedSide(array $values): ?int
    {
        $score = self::score($values);
        if ($score === null) {
            return null;
        }

        [$homeScore, $awayScore] = $score;
        if ($homeScore > $awayScore) {
            return 1;
        }
        if ($awayScore > $homeScore) {
            return 2;
        }

        $value = trim((string)($values[3] ?? ''));
        if ($value === '1') {
            return 1;
        }
        if ($value === '2') {
            return 2;
        }

        return null;
    }

    private static function teamName(string $itemLabel, int $side): string
    {
        $parts = preg_split('/\s+-\s+/', $itemLabel) ?: [];
        $name = trim((string)($parts[$side - 1] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return $side === 1 ? 'Domicile' : 'Exterieur';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
