<?php
/**
 * Backup MySQL vers .sql
 * 1) Essaie docker exec/mysqldump dans le conteneur
 * 2) Sinon, fallback en pur PHP (structure + données)
 *
 * PHP 8+ recommandé
 */

// ======================= CONFIG =======================
$containerName = 'mysql_cafe';          // nom du conteneur MySQL (docker ps)
$dbHost        = '127.0.0.1';      // host accessible par PHP (ou 'mysql' si PHP est en conteneur)
$dbPort        = 3306;
$dbName        = 'BDDCAFE2025';
$dbUser        = 'BDDCAFE2025_user';
$dbPass        = 'secret';
$outDir        = __DIR__ . '/sql';   // dossier de sortie
$filename      = $dbName . '_' . date('Y-m-d_His') . '.sql';
$outFile       = rtrim($outDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
// ======================================================

@ini_set('memory_limit', '-1');
@set_time_limit(0);

// Prépare le dossier
if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    die("ERREUR: impossible de créer le dossier $outDir\n");
}

logln("== Backup MySQL: base=$dbName, sortie=$outFile");

// 1) Tentative via Docker + mysqldump dans le conteneur
if (hasDocker()) {
    logln("Docker détecté. Tentative d'export via le conteneur: $containerName ...");
    [$ok, $err] = dumpViaDockerExec($containerName, $dbName, $dbUser, $dbPass, $outFile);
    if ($ok) {
        logln("✔ Dump via docker exec OK: $outFile");
        exit(0);
    }
    logln("⚠ Échec docker exec: " . trim($err));
} else {
    logln("Docker non détecté ou non accessible.");
}

// 2) Fallback en pur PHP
logln("Fallback: export en PHP pur (structure + données) ...");
try {
    dumpViaPurePHP($dbHost, $dbPort, $dbName, $dbUser, $dbPass, $outFile);
    logln("✔ Dump PHP OK: $outFile");
    exit(0);
} catch (Throwable $e) {
    if (file_exists($outFile) && filesize($outFile) === 0) @unlink($outFile);
    logln("❌ Échec du dump PHP: " . $e->getMessage());
    exit(1);
}

/* ===================== FONCTIONS ===================== */

function logln(string $msg): void {
    echo $msg . PHP_EOL;
}

/**
 * Détecte si Docker est utilisable.
 */
function hasDocker(): bool {
    // Essaye 'docker ps' (Linux/macOS)
    $out = @shell_exec('docker ps 2>/dev/null');
    if (is_string($out)) return true;
    // Essaye 'docker ps' (Windows powershell/cmd)
    $out = @shell_exec('docker ps 2>NUL');
    return is_string($out);
}

/**
 * Lance mysqldump DANS le conteneur via docker exec et écrit directement dans $outFile.
 * Retourne [bool success, string stderr]
 */
function dumpViaDockerExec(string $container, string $dbName, string $user, string $pass, string $outFile): array
{
    // On passe par sh -lc pour permettre la redirection/quoting interne si nécessaire.
    // On n'utilise PAS la redirection > fichier côté conteneur, on récupère stdout côté hôte.
    $inner = "mysqldump --single-transaction --routines --events --triggers --hex-blob -u{$user} -p'{$pass}' {$dbName}";
    $cmd = sprintf(
        'docker exec %s sh -lc %s',
        escapeshellarg($container),
        escapeshellarg($inner)
    );

    $descriptors = [
        1 => ['file', $outFile, 'w'], // stdout -> fichier
        2 => ['pipe', 'w'],           // stderr
    ];

    $proc = @proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($proc)) {
        return [false, "Impossible de lancer docker exec (proc_open)"];
    }

    $stderr = stream_get_contents($pipes[2]);
    if (is_resource($pipes[2])) fclose($pipes[2]);
    $code = proc_close($proc);

    if ($code !== 0) {
        if (file_exists($outFile) && filesize($outFile) === 0) @unlink($outFile);
        return [false, "mysqldump a renvoyé code $code: $stderr"];
    }
    return [true, ""];
}

/**
 * Export "pur PHP" : structure + données, en stream.
 * - Tables (DROP + CREATE + INSERT)
 * - Vues (DROP VIEW + CREATE VIEW)
 * Ne couvre pas triggers/events/routines (limitation PHP pur).
 */
function dumpViaPurePHP(string $host, int $port, string $db, string $user, string $pass, string $outFile): void
{
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false, // streaming
    ]);

    $fp = @fopen($outFile, 'w');
    if (!$fp) {
        throw new RuntimeException("Impossible d'ouvrir le fichier $outFile en écriture");
    }

    $header = "-- Dump PHP pur\n"
            . "-- Base : `{$db}`\n"
            . "-- Date : " . date('Y-m-d H:i:s') . "\n\n"
            . "SET NAMES utf8mb4;\n"
            . "SET FOREIGN_KEY_CHECKS=0;\n\n";
    fwrite($fp, $header);

    // Sépare tables et vues
    $tables = [];
    $views  = [];

    $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $views[] = $row[0];
    }

    // Dump tables: structure + data
    foreach ($tables as $table) {
        fwrite($fp, "--\n-- Structure de la table `{$table}`\n--\n");
        fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");

        $row = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
        $create = $row['Create Table'] ?? null;
        if (!$create) {
            throw new RuntimeException("SHOW CREATE TABLE échoué pour {$table}");
        }
        fwrite($fp, $create . ";\n\n");

        fwrite($fp, "--\n-- Données de la table `{$table}`\n--\n");

        // Types des colonnes pour gérer BLOB/BIT correctement
        $colTypes = getColumnTypes($pdo, $table);

        $q = $pdo->query("SELECT * FROM `{$table}`", PDO::FETCH_ASSOC);
        $batchSize = 1000;
        $buffer = [];
        $columns = null;

        while ($row = $q->fetch()) {
            if ($columns === null) {
                $columns = array_keys($row);
            }
            $valuesSql = '(' . implode(',', array_map(
                fn($col) => sqlValue($pdo, $row[$col], $colTypes[$col] ?? 'text'),
                $columns
            )) . ')';
            $buffer[] = $valuesSql;

            if (count($buffer) >= $batchSize) {
                writeInsert($fp, $table, $columns, $buffer);
                $buffer = [];
            }
        }
        if (!empty($buffer)) {
            writeInsert($fp, $table, $columns, $buffer);
        }
        fwrite($fp, "\n");
    }

    // Dump views: DROP + CREATE
    foreach ($views as $view) {
        fwrite($fp, "--\n-- Vue `{$view}`\n--\n");
        fwrite($fp, "DROP VIEW IF EXISTS `{$view}`;\n");
        $row = $pdo->query("SHOW CREATE VIEW `{$view}`")->fetch(PDO::FETCH_ASSOC);
        $createView = $row['Create View'] ?? null;
        if (!$createView) {
            throw new RuntimeException("SHOW CREATE VIEW échoué pour {$view}");
        }
        // MySQL retourne CREATE ALGORITHM ... VIEW `v` AS SELECT ...
        fwrite($fp, $createView . ";\n\n");
    }

    fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fp);
}

/**
 * Retourne un tableau [colName => typeCourt] avec détection blob/bit/numeric
 */
function getColumnTypes(PDO $pdo, string $table): array {
    $types = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    while ($c = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $type = strtolower($c['Type'] ?? 'text');
        if (preg_match('/bit\(\d+\)/', $type)) {
            $types[$c['Field']] = 'bit';
        } elseif (preg_match('/blob|binary|varbinary/', $type)) {
            $types[$c['Field']] = 'blob';
        } elseif (preg_match('/int|decimal|double|float|numeric/', $type)) {
            $types[$c['Field']] = 'num';
        } else {
            $types[$c['Field']] = 'text';
        }
    }
    return $types;
}

/**
 * Convertit une valeur PHP vers une valeur SQL sûre selon le type
 */
function sqlValue(PDO $pdo, $v, string $type): string {
    if ($v === null) return "NULL";

    switch ($type) {
        case 'num':
            // numerics: laisser sans quotes si possible
            if (is_bool($v)) return $v ? '1' : '0';
            if (is_numeric($v)) return (string)$v;
            return $pdo->quote($v);

        case 'bit':
            // BIT(x) arrive souvent comme chaîne binaire (ex: "\x01")
            $hex = bin2hex((string)$v);
            $int = $hex === '' ? 0 : hexdec($hex);
            return (string)$int; // ex: 0 ou 1

        case 'blob':
            // écrire en hexadécimal: 0xABCD...
            $hex = bin2hex((string)$v);
            return '0x' . $hex;

        case 'text':
        default:
            return $pdo->quote($v);
    }
}

/**
 * Écrit un INSERT multi-lignes
 */
function writeInsert($fp, string $table, array $columns, array $valuesRows): void {
    if (empty($valuesRows)) return;
    $colsSql = '`' . implode('`,`', $columns) . '`';
    fwrite($fp, "INSERT INTO `{$table}` ({$colsSql}) VALUES\n");
    fwrite($fp, implode(",\n", $valuesRows) . ";\n");
}
