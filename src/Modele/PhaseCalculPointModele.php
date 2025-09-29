<?php
declare(strict_types=1);

namespace App\Modele;

use App\Utilitaire\Singleton_ConnexionPDO;
use PDO;

final class PhaseCalculPointModele
{
    private PDO $pdo;
    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo ?? Singleton_ConnexionPDO::getInstance(); }

    public function listByPhase(int $idPhaseCampagne): array
    {
        $sql = 'SELECT pcp.`idPhaseCalculPoint`, pcp.`idPhaseCampagne`, pcp.`nbPoint`, tr.`idTypeResultat`, tr.`libelle`
                FROM `PhaseCalculPoint` pcp JOIN `TypeResultat` tr ON pcp.`idTypeResultat` = tr.`idTypeResultat`
                WHERE pcp.`idPhaseCampagne` = :id ORDER BY pcp.`idPhaseCalculPoint`';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $idPhaseCampagne]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function upsert(int $idPhaseCampagne, int $idTypeResultat, int $nbPoint): bool
    {
        $sql = 'INSERT INTO `PhaseCalculPoint` (`idPhaseCampagne`, `idTypeResultat`, `nbPoint`) VALUES (:p, :t, :n)
                ON DUPLICATE KEY UPDATE `nbPoint` = VALUES(`nbPoint`)';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':p' => $idPhaseCampagne, ':t' => $idTypeResultat, ':n' => $nbPoint]);
    }

    public function delete(int $idPhaseCalculPoint): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM `PhaseCalculPoint` WHERE `idPhaseCalculPoint`=:id');
        return $stmt->execute([':id' => $idPhaseCalculPoint]);
    }
}

