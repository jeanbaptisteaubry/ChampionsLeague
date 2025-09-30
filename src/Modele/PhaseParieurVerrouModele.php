<?php
declare(strict_types=1);

namespace App\Modele;

use App\Utilitaire\Singleton_ConnexionPDO;
use PDO;

final class PhaseParieurVerrouModele
{
    private PDO $pdo;
    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo ?? Singleton_ConnexionPDO::getInstance(); }

    public function isLocked(int $idParieur, int $idPhaseCampagne): bool
    {
        $sql = 'SELECT 1 FROM `PhaseParieurVerrou` WHERE `idParieur`=:u AND `idPhaseCampagne`=:p LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':u' => $idParieur, ':p' => $idPhaseCampagne]);
        return (bool)$stmt->fetchColumn();
    }

    public function lock(int $idParieur, int $idPhaseCampagne): void
    {
        $sql = 'INSERT INTO `PhaseParieurVerrou`(`idPhaseCampagne`, `idParieur`, `dateVerrouillage`) VALUES (:p, :u, NOW())
                ON DUPLICATE KEY UPDATE `dateVerrouillage` = `dateVerrouillage`';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':u' => $idParieur, ':p' => $idPhaseCampagne]);
    }
}

