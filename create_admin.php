#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Modele\TypeUtilisateurModele;
use App\Modele\UtilisateurModele;

function println(string $s = ''): void { echo $s . PHP_EOL; }
function prompt(string $label): string {
    if (function_exists('readline')) {
        $v = readline($label . ': ');
    } else {
        echo $label . ': ';
        $v = trim(fgets(STDIN));
    }
    return $v !== '' ? $v : prompt($label);
}
function promptHidden(string $label): string {
    $isWin = stripos(PHP_OS, 'WIN') === 0;
    echo $label . ': ';
    if (!$isWin) @shell_exec('stty -echo');
    $v = trim(fgets(STDIN));
    if (!$isWin) { @shell_exec('stty echo'); echo PHP_EOL; }
    return $v !== '' ? $v : promptHidden($label);
}

println('=== Création d\'un utilisateur administrateur ===');
try {
    $pseudo = prompt('Pseudo');
    $mail = prompt('Email');
    $pwd = promptHidden('Mot de passe');
    $pwd2 = promptHidden('Confirmer le mot de passe');
    if ($pwd !== $pwd2) {
        fwrite(STDERR, "❌ Les mots de passe ne correspondent pas\n");
        exit(1);
    }

    $types = new TypeUtilisateurModele();
    $users = new UtilisateurModele();

    // Vérifier/Créer le type administrateur
    $adminType = $types->findByLibelle('administrateur');
    if (!$adminType) {
        $idType = $types->create('administrateur', 'Utilisateur administrateur du système');
        println('Type "administrateur" créé.');
    } else {
        $idType = (int)$adminType['idTypeUtilisateur'];
    }

    // Vérifie l\'existence par mail
    if ($users->findByMail($mail)) {
        fwrite(STDERR, "❌ Un utilisateur avec cet email existe déjà\n");
        exit(1);
    }

    $id = $users->create($pseudo, $pwd, $mail, $idType);
    println("✅ Administrateur créé avec l'id $id");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '❌ Erreur: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

