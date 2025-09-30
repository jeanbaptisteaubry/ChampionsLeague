#!/usr/bin/env php
<?php
declare(strict_types=1);

// Afficher toutes les erreurs pendant l'installation (mode CLI)
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

function println(string $s=''){ echo $s.PHP_EOL; }
function prompt(string $label, ?string $default=null): string {
    $suffix = $default !== null ? " [$default]" : "";
    $v = readline("$label$suffix: ");
    if ($v === '' && $default !== null) return $default;
    return $v !== '' ? $v : prompt($label, $default);
}
function promptHidden(string $label, ?string $default=null): string {
    if (stripos(PHP_OS,'WIN')===0) {
        $v = readline("$label".($default?" [Entrée pour garder]":"").": ");
        return ($v==='' && $default!==null) ? $default : $v;
    }
    echo "$label".($default?" [Entrée pour garder]":"").": ";
    shell_exec('stty -echo');
    $v = trim(fgets(STDIN));
    shell_exec('stty echo'); echo PHP_EOL;
    return ($v==='' && $default!==null) ? $default : $v;
}
function confirm(string $msg): bool {
    $ans = readline("$msg (O/n): ");
    if ($ans === '') return true; // défaut = Oui
    return in_array(strtolower($ans), ['o','y','oui','yes'], true);
}
function ok($s){ println("✅ $s"); }
function err($s){ fwrite(STDERR,"❌ $s".PHP_EOL); }

// Charger defaults depuis paramBDD.txt si présent
$DEFAULT_IPBDD=$DEFAULT_BDD=$DEFAULT_USERBDD=$DEFAULT_MDPBDD=null;
if (is_file('paramBDD.txt')) {
    foreach (file('paramBDD.txt') as $line) {
        if (preg_match('/^IPBDD\s+(.+)$/',$line,$m)) $DEFAULT_IPBDD=$m[1];
        if (preg_match('/^BDD\s+(.+)$/',$line,$m)) $DEFAULT_BDD=$m[1];
        if (preg_match('/^USERBDD\s+(.+)$/',$line,$m)) $DEFAULT_USERBDD=$m[1];
        if (preg_match('/^MDPBDD\s+(.+)$/',$line,$m)) $DEFAULT_MDPBDD=$m[1];
    }
}

println("=== Installation BDD MySQL ===");
$ipbdd = prompt("Hôte/IP",$DEFAULT_IPBDD ?? "127.0.0.1");
$port  = prompt("Port","3306");
$bdd   = prompt("Nom de la base",$DEFAULT_BDD ?? "BDDCAFE2025");
$user  = prompt("Utilisateur",$DEFAULT_USERBDD ?? "BDDCAFE2025_user");
$pass  = promptHidden("Mot de passe",$DEFAULT_MDPBDD ?? "secret");

// Écrire paramBDD.txt
file_put_contents("paramBDD.txt","IPBDD $ipbdd\nBDD $bdd\nUSERBDD $user\nMDPBDD $pass\n");
ok("paramBDD.txt écrit.");

// Connexion PDO
try {
    $dsn="mysql:host=$ipbdd;port=$port;dbname=$bdd;charset=utf8mb4";
    $pdo=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->query("SELECT 1");
    ok("Connexion OK.");
} catch(PDOException $e){
    err("Connexion échouée: ".$e->getMessage()); exit(1);
}

// Normaliser la collation de session pour éviter les mixes (use DB default)
function pickCollation(PDO $pdo, string $db): string {
    // 1) Collation par défaut de la base si disponible
    $stmt = $pdo->prepare("SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?");
    if ($stmt->execute([$db])) {
        $c = $stmt->fetchColumn();
        if (is_string($c) && $c !== '') return $c;
    }
    // 2) Collation serveur
    $row = $pdo->query("SHOW VARIABLES LIKE 'collation_server'")->fetch(PDO::FETCH_ASSOC);
    if (!empty($row['Value'])) return (string)$row['Value'];
    // 3) Fallback raisonnable
    $prefs = ['utf8mb4_unicode_ci','utf8mb4_general_ci'];
    foreach($prefs as $c){
        $stmt = $pdo->prepare("SHOW COLLATION LIKE ?");
        $stmt->execute([$c]);
        if ($stmt->fetch()) return $c;
    }
    return 'utf8mb4_general_ci';
}
$collation = pickCollation($pdo, $bdd);

// Appliquer SET NAMES + collation de session pour aligner les comparaisons
try { $pdo->exec("SET NAMES utf8mb4 COLLATE $collation"); } catch (Throwable $e) {}
try { $pdo->exec("SET SESSION collation_connection = '$collation'"); } catch (Throwable $e) {}

// Debug collations et options
function debugCollationState(PDO $pdo, string $db, string $label): void {
    $server = $pdo->query("SHOW VARIABLES LIKE 'collation_server'")->fetch(PDO::FETCH_ASSOC)['Value'] ?? '';
    $conn = $pdo->query("SHOW VARIABLES LIKE 'collation_connection'")->fetch(PDO::FETCH_ASSOC)['Value'] ?? '';
    $stmt = $pdo->prepare("SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?");
    $dbcol = ($stmt->execute([$db])) ? (string)($stmt->fetchColumn() ?: '') : '';
    println("[DEBUG] collation_server=$server | collation_database=$dbcol | collation_connection=$conn");
}
debugCollationState($pdo, $bdd, 'init');

// Option: override collation utilisée pendant l'installation
if (confirm("Forcer une collation spécifique au lieu de '$collation' ?")) {
    $wanted = prompt("Collation (ex: utf8mb4_general_ci)", $collation);
    if ($wanted !== '' && $wanted !== $collation) {
        $check = $pdo->prepare("SHOW COLLATION LIKE ?");
        $check->execute([$wanted]);
        if ($check->fetch()) {
            $collation = $wanted;
            println("[DEBUG] Collation forcée: $collation");
            try { $pdo->exec("SET NAMES utf8mb4 COLLATE $collation"); } catch (Throwable $e) {}
            try { $pdo->exec("SET SESSION collation_connection = '$collation'"); } catch (Throwable $e) {}
            debugCollationState($pdo, $bdd, 'override');
        } else {
            println("[DEBUG] Collation inconnue, on conserve: $collation");
        }
    }
}

// Proposition: harmoniser les SET NAMES présents dans les scripts SQL
$cleanSetNames = confirm("Harmoniser les 'SET NAMES' des scripts SQL avec '$collation' ?");

// Vidage base
println("⚠️ Cette étape supprime toutes les tables de '$bdd'.");
if(confirm("Confirmer vidage ?")){
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    $stmt=$pdo->prepare("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=?");
    $stmt->execute([$bdd]);
    foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $t){
        $pdo->exec("DROP TABLE IF EXISTS `$t`");
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    ok("Base vidée.");
} else {
    println("Vidage ignoré.");
}

// Exécuter le script de structure
$schema = __DIR__ . '/sql/script.sql';
if (!is_file($schema)) { err("Manquant: sql/script.sql"); exit(1); }
println("Script de structure: $schema");
if (confirm("Exécuter le script de structure ?")) {
    $sql = file_get_contents($schema);
    if ($cleanSetNames) {
        $before = $sql;
        $sql = preg_replace('/SET\s+NAMES\s+utf8mb4[^;]*;?/i', "SET NAMES utf8mb4 COLLATE $collation;", $sql);
        if ($before !== $sql) println("[DEBUG] SET NAMES harmonisé dans script.sql");
    }
    // Forcer collation durant l'exécution
    $pdo->exec("SET NAMES utf8mb4 COLLATE $collation");
    $pdo->exec("SET SESSION collation_connection = '$collation'");
    debugCollationState($pdo, $bdd, 'before-structure');
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt === '') continue;
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            err('Erreur SQL (structure): '.$e->getMessage());
            err('Instruction: '.substr($stmt,0,200));
            exit(1);
        }
    }
    ok("Structure appliquée.");
} else {
    println("Structure ignorée.");
}

// Option: données par défaut
$seed = __DIR__ . '/sql/data.sql';
if (is_file($seed)) {
    println("Script de données: $seed");
    if (confirm("Charger les données par défaut ?")) {
        $sql = file_get_contents($seed);
        if ($cleanSetNames) {
            $before = $sql;
            $sql = preg_replace('/SET\s+NAMES\s+utf8mb4[^;]*;?/i', "SET NAMES utf8mb4 COLLATE $collation;", $sql);
            if ($before !== $sql) println("[DEBUG] SET NAMES harmonisé dans data.sql");
        }
        // Patch de compat: certaines lignes peuvent omettre la liste de colonnes
        $sql = str_replace(
            "INSERT INTO `AParier` SELECT",
            "INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT",
            $sql
        );
        $sql = str_replace(
            "INSERT INTO `PhaseCampagne` SELECT",
            "INSERT INTO `PhaseCampagne` (`idCampagnePari`,`idTypePhase`,`ordre`,`libelle`,`dateheureLimite`) SELECT",
            $sql
        );
        $pdo->exec("SET NAMES utf8mb4 COLLATE $collation");
        $pdo->exec("SET SESSION collation_connection = '$collation'");
        debugCollationState($pdo, $bdd, 'before-data');
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt === '') continue;
            try {
                $pdo->exec($stmt);
            } catch (PDOException $e) {
                err('Erreur SQL (data): '.$e->getMessage());
                err('Instruction: '.substr($stmt,0,200));
                exit(1);
            }
        }
        ok("Données chargées.");
    } else {
        println("Données ignorées.");
    }
} else {
    println("Aucun script de données trouvé (sql/data.sql).");
}

// Admin par défaut
if (confirm("Créer l'utilisateur administrateur par défaut (admin / adminfoot@jbaubry.fr) ?")) {
    try {
        $pseudo = 'admin';
        $mail = 'adminfoot@jbaubry.fr';
        $hash = password_hash('secret', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('SELECT 1 FROM `Utilisateur` WHERE `mail` = ? OR `pseudo` = ?');
        $stmt->execute([$mail, $pseudo]);
        if (!$stmt->fetchColumn()) {
            $stmt2 = $pdo->prepare('SELECT `idTypeUtilisateur` FROM `TypeUtilisateur` WHERE `libelle` = ?');
            $stmt2->execute(['administrateur']);
            $idType = (int)($stmt2->fetchColumn() ?: 0);
            if ($idType <= 0) { $idType = 2; }
            $ins = $pdo->prepare('INSERT INTO `Utilisateur` (`pseudo`, `motDePasseHasch`, `mail`, `idTypeUtilisateur`) VALUES (?,?,?,?)');
            $ins->execute([$pseudo, $hash, $mail, $idType]);
            ok("Utilisateur administrateur par défaut créé.");
        } else {
            println("Utilisateur administrateur déjà présent, ignoré.");
        }
    } catch (Throwable $e) {
        err('Erreur création administrateur par défaut: ' . $e->getMessage());
    }
}

// Parieur de test par défaut
if (confirm("Créer l'utilisateur parieur de test (jbaubry / jbaubry25@gmail.com) ?")) {
    try {
        $pseudo = 'jbaubry';
        $mail = 'jbaubry25@gmail.com';
        $hash = password_hash('secret', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('SELECT 1 FROM `Utilisateur` WHERE `mail` = ? OR `pseudo` = ?');
        $stmt->execute([$mail, $pseudo]);
        if (!$stmt->fetchColumn()) {
            $stmt2 = $pdo->prepare('SELECT `idTypeUtilisateur` FROM `TypeUtilisateur` WHERE `libelle` = ?');
            $stmt2->execute(['parieur']);
            $idType = (int)($stmt2->fetchColumn() ?: 1);
            if ($idType <= 0) { $idType = 1; }
            $ins = $pdo->prepare('INSERT INTO `Utilisateur` (`pseudo`, `motDePasseHasch`, `mail`, `idTypeUtilisateur`) VALUES (?,?,?,?)');
            $ins->execute([$pseudo, $hash, $mail, $idType]);
            ok("Utilisateur parieur de test créé.");
        } else {
            println("Utilisateur parieur de test déjà présent, ignoré.");
        }
    } catch (Throwable $e) {
        err('Erreur création parieur de test: ' . $e->getMessage());
    }
}

ok("Terminé.");
