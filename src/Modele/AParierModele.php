<?php
declare(strict_types=1);

namespace App\Modele;

use App\Utilitaire\Singleton_ConnexionPDO;
use PDO;

final class AParierModele
{
    private PDO $pdo;
    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo ?? Singleton_ConnexionPDO::getInstance(); }

    public function create(int $idPhaseCampagne, string $libellePari): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO `AParier` (`idPhaseCampagne`, `libellePari`) VALUES (:p, :l)');
        $stmt->execute([':p' => $idPhaseCampagne, ':l' => $libellePari]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findByPhase(int $idPhaseCampagne): array
    {
        $stmt = $this->pdo->prepare('SELECT `idAParier`, `idPhaseCampagne`, `libellePari` FROM `AParier` WHERE `idPhaseCampagne`=:p ORDER BY `idAParier`');
        $stmt->execute([':p' => $idPhaseCampagne]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT `idAParier`, `idPhaseCampagne`, `libellePari` FROM `AParier` WHERE `idAParier`=:id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function countByPhase(int $idPhaseCampagne): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM `AParier` WHERE `idPhaseCampagne`=:p');
        $stmt->execute([':p' => $idPhaseCampagne]);
        return (int)$stmt->fetchColumn();
    }
}
