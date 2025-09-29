<?php
declare(strict_types=1);

namespace App\Modele;

use App\Utilitaire\Singleton_ConnexionPDO;
use PDO;

final class TypeUtilisateurModele
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Singleton_ConnexionPDO::getInstance();
    }

    public function create(string $libelle, ?string $description = null): int
    {
        $sql = 'INSERT INTO `TypeUtilisateur` (`libelle`, `description`) VALUES (:libelle, :description)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':libelle' => $libelle,
            ':description' => $description,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findById(int $idTypeUtilisateur): ?array
    {
        $stmt = $this->pdo->prepare('SELECT `idTypeUtilisateur`, `libelle`, `description` FROM `TypeUtilisateur` WHERE `idTypeUtilisateur` = :id');
        $stmt->execute([':id' => $idTypeUtilisateur]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT `idTypeUtilisateur`, `libelle`, `description` FROM `TypeUtilisateur` ORDER BY `idTypeUtilisateur`');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByLibelle(string $libelle): ?array
    {
        $stmt = $this->pdo->prepare('SELECT `idTypeUtilisateur`, `libelle`, `description` FROM `TypeUtilisateur` WHERE `libelle` = :lib');
        $stmt->execute([':lib' => $libelle]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function update(int $idTypeUtilisateur, string $libelle, ?string $description = null): bool
    {
        $sql = 'UPDATE `TypeUtilisateur` SET `libelle` = :libelle, `description` = :description WHERE `idTypeUtilisateur` = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':libelle' => $libelle,
            ':description' => $description,
            ':id' => $idTypeUtilisateur,
        ]);
    }

    public function delete(int $idTypeUtilisateur): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM `TypeUtilisateur` WHERE `idTypeUtilisateur` = :id');
        return $stmt->execute([':id' => $idTypeUtilisateur]);
    }
}
