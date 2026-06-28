<?php
declare(strict_types=1);

namespace App\Service;

final class ApiFootballClient
{
    private const DEFAULT_BASE_URL = 'https://v3.football.api-sports.io';

    private string $apiKey;
    private string $baseUrl;

    public function __construct(?string $apiKey = null, ?string $baseUrl = null)
    {
        $config = self::loadConfig();
        $this->apiKey = trim($apiKey ?? $config['key'] ?? '');
        $this->baseUrl = rtrim($baseUrl ?? $config['base_url'] ?? self::DEFAULT_BASE_URL, '/');

        if ($this->apiKey === '') {
            throw new \RuntimeException(
                'Cle API-Football absente. Configurez API_FOOTBALL_KEY ou paramAPI.txt.'
            );
        }
    }

    public function status(): array
    {
        return $this->request('/status');
    }

    public function fixtures(string $from, string $to, int $league, int $season): array
    {
        $payload = $this->request('/fixtures', [
            'league' => $league,
            'season' => $season,
            'from' => $from,
            'to' => $to,
            'timezone' => 'Europe/Paris',
        ]);

        return is_array($payload['response'] ?? null) ? $payload['response'] : [];
    }

    public function matchItems(array $items, array $fixtures): array
    {
        $matches = [];
        foreach ($items as $item) {
            $bestFixture = null;
            $bestScore = 0.0;
            [$expectedHome, $expectedAway] = $this->splitLabel((string)$item['libellePari']);

            if ($expectedHome !== '' && $expectedAway !== '') {
                foreach ($fixtures as $fixture) {
                    $home = (string)($fixture['teams']['home']['name'] ?? '');
                    $away = (string)($fixture['teams']['away']['name'] ?? '');
                    $score = (
                        $this->similarity($expectedHome, $home)
                        + $this->similarity($expectedAway, $away)
                    ) / 2;
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestFixture = $fixture;
                    }
                }
            }

            $matches[(int)$item['idAParier']] = [
                'fixture' => $bestScore >= 0.58 ? $bestFixture : null,
                'confidence' => (int)round($bestScore * 100),
            ];
        }

        return $matches;
    }

    public static function isFinished(array $fixture): bool
    {
        $status = (string)($fixture['fixture']['status']['short'] ?? '');
        return in_array($status, ['FT', 'AET', 'PEN'], true)
            && isset($fixture['goals']['home'], $fixture['goals']['away']);
    }

    private function request(string $path, array $query = []): array
    {
        $url = $this->baseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('Impossible d initialiser la connexion API-Football.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'x-apisports-key: ' . $this->apiKey,
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
        ];
        if (defined('CURLSSLOPT_NATIVE_CA')) {
            $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        }
        curl_setopt_array($curl, $options);

        $body = curl_exec($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($body === false || $curlError !== '') {
            throw new \RuntimeException('Connexion API-Football impossible: ' . $curlError);
        }

        $payload = json_decode((string)$body, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Reponse API-Football invalide.');
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException("API-Football a repondu avec le statut HTTP $httpCode.");
        }

        $errors = $payload['errors'] ?? [];
        if (is_array($errors) && $errors !== []) {
            $message = implode(', ', array_map(
                static fn($value): string =>
                    is_scalar($value) ? (string)$value : (string)json_encode($value),
                $errors
            ));
            if (str_contains(strtolower($message), 'free plans do not have access to this season')) {
                throw new \RuntimeException(
                    'Votre offre API-Football ne donne pas acces a cette saison. '
                    . 'La cle actuelle est limitee aux saisons 2022 a 2024; '
                    . 'un abonnement compatible est requis pour 2025-2026.'
                );
            }
            throw new \RuntimeException('Erreur API-Football: ' . $message);
        }

        return $payload;
    }

    private static function loadConfig(): array
    {
        $config = [
            'key' => getenv('API_FOOTBALL_KEY') ?: null,
            'base_url' => getenv('API_FOOTBALL_BASE_URL') ?: null,
        ];
        $file = __DIR__ . '/../../paramAPI.txt';
        if (!is_file($file)) {
            return $config;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (!preg_match('/^(\w+)\s+(.*)$/', trim($line), $matches)) {
                continue;
            }
            $key = strtoupper($matches[1]);
            $value = trim($matches[2]);
            if ($key === 'API_FOOTBALL_KEY' && empty($config['key'])) {
                $config['key'] = $value;
            } elseif ($key === 'API_FOOTBALL_BASE_URL' && empty($config['base_url'])) {
                $config['base_url'] = $value;
            }
        }

        return $config;
    }

    private function splitLabel(string $label): array
    {
        $parts = preg_split('/\s+[-–—]\s+/u', trim($label), 2);
        if (!is_array($parts) || count($parts) !== 2) {
            return ['', ''];
        }
        return [trim($parts[0]), trim($parts[1])];
    }

    private function similarity(string $left, string $right): float
    {
        $left = $this->normalizeTeamName($left);
        $right = $this->normalizeTeamName($right);
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

    private function normalizeTeamName(string $name): string
    {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $name = strtolower($converted !== false ? $converted : $name);
        $name = preg_replace('/\b(fc|cf|afc|ac|as|ssc|club|football)\b/', ' ', $name) ?? $name;
        $name = preg_replace('/[^a-z0-9]+/', ' ', $name) ?? $name;
        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }
}
