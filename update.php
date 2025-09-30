#!/usr/bin/env php
<?php
declare(strict_types=1);

// Outil de mise à jour SQL par versions (updateNN.sql)
// - Lit la version courante dans la table `version` (créée au besoin)
// - Exécute séquentiellement tous les scripts sql/migrations/update{N}.sql dont N > version courante
//
// Options:
//   --dry-run           N'affiche que le plan d'exécution, sans appliquer
//   --target=N          Limite la mise à jour à la version N

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

function println(string $s = ''): void { echo $s, PHP_EOL; }
function errln(string $s): void { fwrite(STDERR, $s . PHP_EOL); }

// Chargement connexion PDO (réutilise le Singleton existant du projet)
require_once __DIR__ . '/src/Utilitaire/Singleton_ConnexionPDO.php';

/** @var PDO $pdo */
$pdo = \App\Utilitaire\Singleton_ConnexionPDO::getInstance();

// Parse des options CLI
$dryRun = false;
$target = null; // ?int
foreach ($argv as $arg) {
    if ($arg === '--dry-run') { $dryRun = true; continue; }
    if (preg_match('/^--target=(\d+)$/', $arg, $m)) {
        $target = (int)$m[1];
        continue;
    }
}

$migrationsDir = __DIR__ . '/sql/migrations';
if (!is_dir($migrationsDir)) {
    // Pas bloquant: on informe seulement
    println("[INFO] Dossier des migrations absent: " . $migrationsDir);
}

// Assure la table version et renvoie la version courante
function ensureVersionAndGet(PDO $pdo): int {
    $pdo->exec("CREATE TABLE IF NOT EXISTS version (version INT NOT NULL)");
    try {
        $v = $pdo->query("SELECT MAX(version) FROM version")->fetchColumn();
    } catch (Throwable $e) {
        // Si la table n'existe pas pour une raison quelconque, on la crée et repart
        $pdo->exec("CREATE TABLE IF NOT EXISTS version (version INT NOT NULL)");
        $v = $pdo->query("SELECT MAX(version) FROM version")->fetchColumn();
    }
    if ($v === null) {
        // initialise à 0 si vide
        $pdo->exec("DELETE FROM version");
        $stmt = $pdo->prepare("INSERT INTO version(version) VALUES (?)");
        $stmt->execute([0]);
        return 0;
    }
    return (int)$v;
}

// Split SQL robuste: découpe par ';' hors guillemets / commentaires
function splitSqlStatements(string $sql): array {
    // Normalisation basique
    $sql = preg_replace("/^\xEF\xBB\xBF/", '', $sql) ?? $sql; // BOM
    // Supprimer directives DELIMITER (non supportées côté PDO)
    $sql = preg_replace('/^\s*DELIMITER\s+.+$/mi', '', $sql) ?? $sql;

    $stmts = [];
    $buf = '';
    $len = strlen($sql);
    $inSingle = false; // '
    $inDouble = false; // "
    $inBack   = false; // `
    $inLineC  = false; // -- ou # jusqu'à fin de ligne
    $inBlockC = false; // /* ... */

    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        $n = $i + 1 < $len ? $sql[$i + 1] : '';

        // Fin de commentaire ligne
        if ($inLineC) {
            if ($c === "\n") { $inLineC = false; }
            continue;
        }
        // Fin de commentaire bloc
        if ($inBlockC) {
            if ($c === '*' && $n === '/') { $inBlockC = false; $i++; }
            continue;
        }

        // Détection commentaires (si hors chaîne/backtick)
        if (!$inSingle && !$inDouble && !$inBack) {
            if ($c === '-' && $n === '-') { $inLineC = true; $i++; continue; }
            if ($c === '#') { $inLineC = true; continue; }
            if ($c === '/' && $n === '*') { $inBlockC = true; $i++; continue; }
        }

        // Gestion des chaînes / quotes
        if (!$inDouble && !$inBack && $c === "'" ) {
            if ($inSingle) {
                // fin si pas échappé par doublage
                $next = $n;
                $buf .= $c;
                if ($next === "'") { // doublage ''
                    $buf .= $next; $i++; // consomme l'échappement
                } else {
                    $inSingle = false;
                }
                continue;
            } else {
                $inSingle = true; $buf .= $c; continue;
            }
        }
        if (!$inSingle && !$inBack && $c === '"') {
            if ($inDouble) {
                $next = $n;
                $buf .= $c;
                if ($next === '"') { $buf .= $next; $i++; } else { $inDouble = false; }
                continue;
            } else { $inDouble = true; $buf .= $c; continue; }
        }
        if (!$inSingle && !$inDouble && $c === '`') {
            if ($inBack) {
                $next = $n;
                $buf .= $c;
                if ($next === '`') { $buf .= $next; $i++; } else { $inBack = false; }
                continue;
            } else { $inBack = true; $buf .= $c; continue; }
        }

        // Délimiteur d'instruction
        if (!$inSingle && !$inDouble && !$inBack && $c === ';') {
            $stmt = trim($buf);
            if ($stmt !== '') { $stmts[] = $stmt; }
            $buf = '';
            continue;
        }

        $buf .= $c;
    }

    $tail = trim($buf);
    if ($tail !== '') { $stmts[] = $tail; }
    return $stmts;
}

function upsertVersion(PDO $pdo, int $v): void {
    // Table avec une seule ligne: on remplace par DELETE + INSERT pour rester simple et robuste
    $pdo->exec("DELETE FROM version");
    $stmt = $pdo->prepare("INSERT INTO version(version) VALUES (?)");
    $stmt->execute([$v]);
}

// Collecte des migrations disponibles
$available = [];
foreach (glob($migrationsDir . DIRECTORY_SEPARATOR . 'update*.sql') ?: [] as $path) {
    $base = basename($path);
    if (preg_match('/^update(\d+)\.sql$/i', $base, $m)) {
        $v = (int)$m[1];
        $available[$v] = $path; // si doublons, le dernier écrase
    }
}
ksort($available, SORT_NUMERIC);

$current = ensureVersionAndGet($pdo);
println("Version actuelle: $current");

// Filtrage à appliquer
$toRun = [];
foreach ($available as $v => $path) {
    if ($v <= $current) continue;
    if ($target !== null && $v > $target) continue;
    $toRun[$v] = $path;
}

if (empty($toRun)) {
    println('[OK] Base déjà à jour.');
    exit(0);
}

println('Migrations à appliquer:');
foreach ($toRun as $v => $p) {
    println(" - $v : $p");
}

if ($dryRun) {
    println('[DRY-RUN] Aucune modification appliquée.');
    exit(0);
}

// Exécution séquentielle
foreach ($toRun as $v => $path) {
    println("\n[RUN] update$v => $path");
    $sql = file_get_contents($path);
    if ($sql === false) {
        errln("Impossible de lire le fichier: $path");
        exit(1);
    }
    $stmts = splitSqlStatements($sql);
    if (empty($stmts)) {
        println('[INFO] Aucun statement à exécuter (fichier vide ou commentaires).');
    }

    try {
        // Les DDL MySQL font des commits implicites; on exécute instruction par instruction
        foreach ($stmts as $idx => $stmt) {
            $pdo->exec($stmt);
        }
        upsertVersion($pdo, $v);
        println("[OK] update$v appliqué.");
    } catch (\PDOException $e) {
        errln("[ERREUR] Échec update$v: " . $e->getMessage());
        errln('[ABANDON] La version n\'a pas été avancée.');
        exit(1);
    }
}

println("\nTerminé. Base à la version " . array_key_last($toRun) . '.');
exit(0);

