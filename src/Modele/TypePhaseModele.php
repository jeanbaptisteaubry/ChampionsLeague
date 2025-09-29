<?php
declare(strict_types=1);

namespace App\Modele;

use App\Utilitaire\Singleton_ConnexionPDO;
use PDO;

final class TypePhaseModele
{
    private PDO $pdo;
    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo ?? Singleton_ConnexionPDO::getInstance(); }

    public function create(string $libelle, int $nbValeurParPari, array $labels = []): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO `TypePhase` (`libelle`, `nbValeurParPari`) VALUES (:lib, :nb)');
        $stmt->execute([':lib' => $libelle, ':nb' => $nbValeurParPari]);
        $id = (int)$this->pdo->lastInsertId();
        // Insert labels if provided
        if ($labels) {
            $stmt2 = $this->pdo->prepare('INSERT INTO `LibelleValeurPhase` (`idTypePhase`, `numeroValeur`, `libelle`) VALUES (:id, :num, :lib)');
            $i = 1;
            foreach ($labels as $lib) {
                $stmt2->execute([':id' => $id, ':num' => $i, ':lib' => $lib]);
                $i++;
            }
        }
        return $id;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT `idTypePhase`, `libelle`, `nbValeurParPari` FROM `TypePhase` ORDER BY `idTypePhase`');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT `idTypePhase`, `libelle`, `nbValeurParPari` FROM `TypePhase` WHERE `idTypePhase`=:id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function labels(int $idTypePhase): array
    {
        $stmt = $this->pdo->prepare('SELECT `numeroValeur`, `libelle` FROM `LibelleValeurPhase` WHERE `idTypePhase`=:id ORDER BY `numeroValeur`');
        $stmt->execute([':id' => $idTypePhase]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countUsage(int $idTypePhase): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM `PhaseCampagne` WHERE `idTypePhase` = :id');
        $stmt->execute([':id' => $idTypePhase]);
        return (int)$stmt->fetchColumn();
    }

    public function delete(int $idTypePhase): bool
    {
        // Supprime d'abord les labels (CASCADE existe, mais être explicite)
        $this->pdo->prepare('DELETE FROM `LibelleValeurPhase` WHERE `idTypePhase`=:id')->execute([':id' => $idTypePhase]);
        $stmt = $this->pdo->prepare('DELETE FROM `TypePhase` WHERE `idTypePhase`=:id');
        return $stmt->execute([':id' => $idTypePhase]);
    }
}
