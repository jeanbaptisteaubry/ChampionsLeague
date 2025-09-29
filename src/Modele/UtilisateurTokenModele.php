<?php
declare(strict_types=1);

namespace App\Modele;

use App\Utilitaire\Singleton_ConnexionPDO;
use PDO;

final class UtilisateurTokenModele
{
    private PDO $pdo;
    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo ?? Singleton_ConnexionPDO::getInstance(); }

    public function create(int $idUtilisateur, string $type, int $ttlHours = 24): string
    {
        $token = bin2hex(random_bytes(32));
        $expires = (new \DateTimeImmutable("+{$ttlHours} hours"))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('INSERT INTO `UtilisateurToken` (`idUtilisateur`,`type`,`token`,`expiresAt`) VALUES (:u,:t,:tok,:exp)');
        $stmt->execute([':u' => $idUtilisateur, ':t' => $type, ':tok' => $token, ':exp' => $expires]);
        return $token;
    }

    public function findValid(string $token, string $type): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `UtilisateurToken` WHERE `token`=:tok AND `type`=:t AND `usedAt` IS NULL AND `expiresAt` > NOW()');
        $stmt->execute([':tok' => $token, ':t' => $type]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function markUsed(int $idToken): bool
    {
        $stmt = $this->pdo->prepare('UPDATE `UtilisateurToken` SET `usedAt`=NOW() WHERE `idToken`=:id');
        return $stmt->execute([':id' => $idToken]);
    }
}

