<?php
declare(strict_types=1);

namespace App\Service;

final class TheSportsDbClient
{
    private const DEFAULT_BASE_URL = 'https://www.thesportsdb.com/api/v1/json';
    private const DEFAULT_FREE_KEY = '123';

    private string $baseUrl;
    private string $apiKey;

    public function __construct(?string $apiKey = null, ?string $baseUrl = null)
    {
        $this->apiKey = trim($apiKey ?? (getenv('THESPORTSDB_KEY') ?: self::DEFAULT_FREE_KEY));
        $this->baseUrl = rtrim(
            $baseUrl ?? (getenv('THESPORTSDB_BASE_URL') ?: self::DEFAULT_BASE_URL),
            '/'
        );
    }

    public function seasonEvents(int $leagueId, string $season, string $from, string $to): array
    {
        $payload = $this->request('/eventsseason.php', [
            'id' => $leagueId,
            's' => $season,
        ]);
        $events = is_array($payload['events'] ?? null) ? $payload['events'] : [];

        return array_values(array_filter(
            $events,
            static function (array $event) use ($from, $to): bool {
                $date = (string)($event['dateEvent'] ?? '');
                return $date !== '' && $date >= $from && $date <= $to;
            }
        ));
    }

    public function matchItems(array $items, array $events): array
    {
        $matches = [];
        foreach ($items as $item) {
            [$expectedHome, $expectedAway] = $this->splitLabel((string)$item['libellePari']);
            $bestEvent = null;
            $bestScore = 0.0;

            foreach ($events as $event) {
                $home = (string)($event['strHomeTeam'] ?? '');
                $away = (string)($event['strAwayTeam'] ?? '');
                $score = (
                    $this->similarity($expectedHome, $home)
                    + $this->similarity($expectedAway, $away)
                ) / 2;
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestEvent = $event;
                }
            }

            $matches[(int)$item['idAParier']] = [
                'event' => $bestScore >= 0.58 ? $bestEvent : null,
                'confidence' => (int)round($bestScore * 100),
            ];
        }

        return $matches;
    }

    public static function hasScore(array $event): bool
    {
        return isset($event['intHomeScore'], $event['intAwayScore'])
            && trim((string)$event['intHomeScore']) !== ''
            && trim((string)$event['intAwayScore']) !== '';
    }

    private function request(string $path, array $query): array
    {
        $url = $this->baseUrl . '/' . rawurlencode($this->apiKey) . $path
            . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('Impossible d initialiser TheSportsDB.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
        ];
        if (defined('CURLSSLOPT_NATIVE_CA')) {
            $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        }
        curl_setopt_array($curl, $options);

        $body = curl_exec($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false || $error !== '') {
            throw new \RuntimeException('Connexion TheSportsDB impossible: ' . $error);
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException("TheSportsDB a repondu avec le statut HTTP $httpCode.");
        }

        $payload = json_decode((string)$body, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Reponse TheSportsDB invalide.');
        }
        return $payload;
    }

    private function splitLabel(string $label): array
    {
        $parts = preg_split('/\s+[-–—]\s+/u', trim($label), 2);
        return is_array($parts) && count($parts) === 2
            ? [trim($parts[0]), trim($parts[1])]
            : ['', ''];
    }

    private function similarity(string $left, string $right): float
    {
        $left = $this->normalizeName($left);
        $right = $this->normalizeName($right);
        if ($left === '' || $right === '') {
            return 0.0;
        }
        if ($left === $right) {
            return 1.0;
        }
        similar_text($left, $right, $percent);
        if (str_contains($left, $right) || str_contains($right, $left)) {
            $percent = max($percent, 85.0);
        }
        return $percent / 100;
    }

    private function normalizeName(string $name): string
    {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $name = strtolower($converted !== false ? $converted : $name);
        $name = preg_replace('/[^a-z0-9]+/', ' ', $name) ?? $name;
        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }
}
