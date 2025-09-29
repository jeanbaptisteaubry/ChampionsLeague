<?php
declare(strict_types=1);

namespace App\Modele;

use App\Utilitaire\Singleton_ConnexionPDO;
use PDO;

final class PhaseCampagneModele
{
    private PDO $pdo;
    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo ?? Singleton_ConnexionPDO::getInstance(); }

    public function create(int $idCampagnePari, int $idTypePhase, int $ordre, string $libelle, string $dateheureLimite): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO `PhaseCampagne` (`idCampagnePari`, `idTypePhase`, `ordre`, `libelle`, `dateheureLimite`) VALUES (:c, :t, :o, :l, :d)');
        $stmt->execute([':c' => $idCampagnePari, ':t' => $idTypePhase, ':o' => $ordre, ':l' => $libelle, ':d' => $dateheureLimite]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findByCampagne(int $idCampagnePari): array
    {
        $sql = 'SELECT p.`idPhaseCampagne`, p.`ordre`, p.`libelle`, p.`dateheureLimite`, t.`libelle` AS typeLibelle, t.`nbValeurParPari`
                FROM `PhaseCampagne` p JOIN `TypePhase` t ON p.`idTypePhase` = t.`idTypePhase`
                WHERE p.`idCampagnePari`=:c ORDER BY p.`ordre`';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':c' => $idCampagnePari]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `PhaseCampagne` WHERE `idPhaseCampagne`=:id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM `PhaseCampagne` WHERE `idPhaseCampagne`=:id');
        return $stmt->execute([':id' => $id]);
    }
}
