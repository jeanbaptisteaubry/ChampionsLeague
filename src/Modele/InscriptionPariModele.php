<?php
declare(strict_types=1);

namespace App\Modele;

use App\Utilitaire\Singleton_ConnexionPDO;
use PDO;

final class InscriptionPariModele
{
    private PDO $pdo;
    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo ?? Singleton_ConnexionPDO::getInstance(); }

    public function inscrire(int $idParieur, int $idCampagnePari): bool
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO `InscriptionPari` (`idParieur`, `idCampagnePari`) VALUES (:u, :c)');
        return $stmt->execute([':u' => $idParieur, ':c' => $idCampagnePari]);
    }

    public function desinscrire(int $idParieur, int $idCampagnePari): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM `InscriptionPari` WHERE `idParieur`=:u AND `idCampagnePari`=:c');
        return $stmt->execute([':u' => $idParieur, ':c' => $idCampagnePari]);
    }

    public function estInscrit(int $idParieur, int $idCampagnePari): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM `InscriptionPari` WHERE `idParieur`=:u AND `idCampagnePari`=:c');
        $stmt->execute([':u' => $idParieur, ':c' => $idCampagnePari]);
        return (bool)$stmt->fetchColumn();
    }

    public function listByUser(int $idParieur): array
    {
        $sql = 'SELECT c.`idCampagnePari`, c.`libelle`, c.`description`, c.`gain`
                FROM `InscriptionPari` i JOIN `CampagnePari` c ON i.`idCampagnePari` = c.`idCampagnePari`
                WHERE i.`idParieur` = :u
                ORDER BY c.`idCampagnePari` DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':u' => $idParieur]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listNotEnrolled(int $idParieur): array
    {
        $sql = 'SELECT c.`idCampagnePari`, c.`libelle`, c.`description`, c.`gain`
                FROM `CampagnePari` c
                WHERE c.`idCampagnePari` NOT IN (
                    SELECT i.`idCampagnePari` FROM `InscriptionPari` i WHERE i.`idParieur` = :u
                )
                ORDER BY c.`idCampagnePari` DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':u' => $idParieur]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countByCampagne(int $idCampagnePari): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM `InscriptionPari` WHERE `idCampagnePari`=:c');
        $stmt->execute([':c' => $idCampagnePari]);
        return (int)$stmt->fetchColumn();
    }

    public function listUsersByCampagne(int $idCampagnePari): array
    {
        $sql = 'SELECT u.`idUtilisateur`, u.`pseudo`, u.`mail`
                FROM `InscriptionPari` i
                JOIN `Utilisateur` u ON u.`idUtilisateur` = i.`idParieur`
                WHERE i.`idCampagnePari` = :c
                ORDER BY u.`pseudo`';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':c' => $idCampagnePari]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
