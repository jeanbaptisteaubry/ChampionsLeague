#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Modele\TypeUtilisateurModele;
use App\Modele\UtilisateurModele;

function prompt(string $label): string {
    $v = readline($label . ': ');
    return $v === '' ? prompt($label) : $v;
}

echo "Créer un administrateur\n";
$pseudo = prompt('Pseudo');
$mail = prompt('Email');
echo 'Mot de passe: ';
if (stripos(PHP_OS, 'WIN') === 0) {
    $password = trim(fgets(STDIN));
} else {
    shell_exec('stty -echo');
    $password = trim(fgets(STDIN));
    shell_exec('stty echo');
}
echo "\n";

$types = new TypeUtilisateurModele();
$users = new UtilisateurModele();

$adminType = $types->findByLibelle('administrateur');
$idType = (int)($adminType['idTypeUtilisateur'] ?? 2);

$id = $users->create($pseudo, $password, $mail, $idType);
echo "Administrateur créé avec l'id $id\n";

