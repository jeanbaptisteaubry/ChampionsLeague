<?php
declare(strict_types=1);

namespace App\Modele;

use App\Utilitaire\Singleton_ConnexionPDO;
use PDO;

final class UtilisateurModele
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Singleton_ConnexionPDO::getInstance();
    }

    public function create(string $pseudo, string $plainPassword, string $mail, int $idTypeUtilisateur = 1): int
    {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $sql = 'INSERT INTO `Utilisateur` (`pseudo`, `motDePasseHasch`, `mail`, `idTypeUtilisateur`) VALUES (:pseudo, :hash, :mail, :idType)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':pseudo' => $pseudo,
            ':hash' => $hash,
            ':mail' => $mail,
            ':idType' => $idTypeUtilisateur,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function createWithoutPassword(string $pseudo, string $mail, int $idTypeUtilisateur = 1): int
    {
        $sql = 'INSERT INTO `Utilisateur` (`pseudo`, `motDePasseHasch`, `mail`, `idTypeUtilisateur`) VALUES (:pseudo, NULL, :mail, :idType)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':pseudo' => $pseudo,
            ':mail' => $mail,
            ':idType' => $idTypeUtilisateur,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findById(int $idUtilisateur): ?array
    {
        $stmt = $this->pdo->prepare('SELECT `idUtilisateur`, `pseudo`, `motDePasseHasch`, `mail`, `idTypeUtilisateur` FROM `Utilisateur` WHERE `idUtilisateur` = :id');
        $stmt->execute([':id' => $idUtilisateur]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function findByMail(string $mail): ?array
    {
        $stmt = $this->pdo->prepare('SELECT `idUtilisateur`, `pseudo`, `motDePasseHasch`, `mail`, `idTypeUtilisateur` FROM `Utilisateur` WHERE `mail` = :mail');
        $stmt->execute([':mail' => $mail]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT `idUtilisateur`, `pseudo`, `motDePasseHasch`, `mail`, `idTypeUtilisateur` FROM `Utilisateur` ORDER BY `idUtilisateur`');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function update(int $idUtilisateur, string $pseudo, string $mail): bool
    {
        $sql = 'UPDATE `Utilisateur` SET `pseudo` = :pseudo, `mail` = :mail WHERE `idUtilisateur` = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':pseudo' => $pseudo,
            ':mail' => $mail,
            ':id' => $idUtilisateur,
        ]);
    }

    public function updatePassword(int $idUtilisateur, string $plainPassword): bool
    {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('UPDATE `Utilisateur` SET `motDePasseHasch` = :hash WHERE `idUtilisateur` = :id');
        return $stmt->execute([
            ':hash' => $hash,
            ':id' => $idUtilisateur,
        ]);
    }

    public function delete(int $idUtilisateur): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM `Utilisateur` WHERE `idUtilisateur` = :id');
        return $stmt->execute([':id' => $idUtilisateur]);
    }

    public function assignType(int $idUtilisateur, int $idTypeUtilisateur): bool
    {
        $stmt = $this->pdo->prepare('UPDATE `Utilisateur` SET `idTypeUtilisateur` = :idType WHERE `idUtilisateur` = :id');
        return $stmt->execute([
            ':idType' => $idTypeUtilisateur,
            ':id' => $idUtilisateur,
        ]);
    }

    public function verifyCredentials(string $mail, string $plainPassword): bool
    {
        $user = $this->findByMail($mail);
        if (!$user || empty($user['motDePasseHasch'])) {
            return false;
        }
        return password_verify($plainPassword, $user['motDePasseHasch']);
    }
}
