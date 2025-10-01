<?php
declare(strict_types=1);

namespace App\Modele;

use App\Utilitaire\Singleton_ConnexionPDO;
use PDO;

final class ParametreModele
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Singleton_ConnexionPDO::getInstance();
    }

    public function get(string $cle): ?string
    {
        $stmt = $this->pdo->prepare('SELECT `valeur` FROM `Parametre` WHERE `cle` = :k');
        $stmt->execute([':k' => $cle]);
        $v = $stmt->fetchColumn();
        return $v !== false ? (string)$v : null;
    }

    public function set(string $cle, string $valeur): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO `Parametre`(`cle`,`valeur`) VALUES (:k,:v)
            ON DUPLICATE KEY UPDATE `valeur` = VALUES(`valeur`)');
        $stmt->execute([':k' => $cle, ':v' => $valeur]);
    }
}

