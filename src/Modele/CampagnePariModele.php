<?php
declare(strict_types=1);

namespace App\Modele;

use App\Utilitaire\Singleton_ConnexionPDO;
use PDO;

final class CampagnePariModele
{
    private PDO $pdo;
    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo ?? Singleton_ConnexionPDO::getInstance(); }

    public function create(string $libelle, ?string $description = null): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO `CampagnePari` (`libelle`, `description`) VALUES (:lib, :desc)');
        $stmt->execute([':lib' => $libelle, ':desc' => $description]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT `idCampagnePari`, `libelle`, `description`, `gain` FROM `CampagnePari` ORDER BY `idCampagnePari` DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT `idCampagnePari`, `libelle`, `description`, `gain` FROM `CampagnePari` WHERE `idCampagnePari` = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function update(int $id, string $libelle, ?string $description = null): bool
    {
        $stmt = $this->pdo->prepare('UPDATE `CampagnePari` SET `libelle`=:l, `description`=:d WHERE `idCampagnePari`=:id');
        return $stmt->execute([':l' => $libelle, ':d' => $description, ':id' => $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM `CampagnePari` WHERE `idCampagnePari`=:id');
        return $stmt->execute([':id' => $id]);
    }

    public function setGain(int $id, ?string $gain): bool
    {
        $stmt = $this->pdo->prepare('UPDATE `CampagnePari` SET `gain` = :g WHERE `idCampagnePari` = :id');
        return $stmt->execute([':g' => $gain, ':id' => $id]);
    }
}
