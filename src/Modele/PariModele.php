<?php
declare(strict_types=1);

namespace App\Modele;

use App\Utilitaire\Singleton_ConnexionPDO;
use PDO;

final class PariModele
{
    private PDO $pdo;
    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo ?? Singleton_ConnexionPDO::getInstance(); }

    private function ensurePari(int $idParieur, int $idAParier): int
    {
        // Create minimal row (valeur1 kept for compatibility but not used)
        $sql = 'INSERT INTO `Pari` (`idParieur`, `idAParier`, `valeur1`) VALUES (:u, :a, :v)
                ON DUPLICATE KEY UPDATE `idPari` = `idPari`';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':u' => $idParieur, ':a' => $idAParier, ':v' => '']);
        $id = (int)$this->pdo->lastInsertId();
        if ($id === 0) {
            $stmt2 = $this->pdo->prepare('SELECT `idPari` FROM `Pari` WHERE `idParieur`=:u AND `idAParier`=:a');
            $stmt2->execute([':u' => $idParieur, ':a' => $idAParier]);
            $id = (int)($stmt2->fetchColumn() ?: 0);
        }
        return $id;
    }

    public function placerValeurs(int $idParieur, int $idAParier, array $valeurs): int
    {
        $idPari = $this->ensurePari($idParieur, $idAParier);
        $sql = 'INSERT INTO `PariValeur` (`idPari`, `numeroValeur`, `valeur`) VALUES (:id, :num, :val)
                ON DUPLICATE KEY UPDATE `valeur` = VALUES(`valeur`)';
        $stmt = $this->pdo->prepare($sql);
        foreach ($valeurs as $num => $val) {
            $num = (int)$num;
            $val = (string)$val;
            $stmt->execute([':id' => $idPari, ':num' => $num, ':val' => $val]);
        }
        return $idPari;
    }

    public function valeursPourPari(int $idPari): array
    {
        $stmt = $this->pdo->prepare('SELECT `numeroValeur`, `valeur` FROM `PariValeur` WHERE `idPari`=:id ORDER BY `numeroValeur`');
        $stmt->execute([':id' => $idPari]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $r) { $out[(int)$r['numeroValeur']] = $r['valeur']; }
        return $out;
    }

    public function findForUserAndPhase(int $idParieur, int $idPhaseCampagne): array
    {
        $sql = 'SELECT pr.`idPari`, pr.`idAParier`, a.`libellePari`
                FROM `Pari` pr JOIN `AParier` a ON pr.`idAParier`=a.`idAParier`
                WHERE pr.`idParieur`=:u AND a.`idPhaseCampagne`=:p
                ORDER BY pr.`idPari`';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':u' => $idParieur, ':p' => $idPhaseCampagne]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            $r['valeurs'] = $this->valeursPourPari((int)$r['idPari']);
        }
        return $rows;
    }

    public function countByUserAndCampagne(int $idParieur, int $idCampagnePari): int
    {
        $sql = 'SELECT COUNT(*)
                FROM `Pari` pr
                JOIN `AParier` a ON pr.`idAParier` = a.`idAParier`
                JOIN `PhaseCampagne` pc ON a.`idPhaseCampagne` = pc.`idPhaseCampagne`
                WHERE pr.`idParieur` = :u AND pc.`idCampagnePari` = :c';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':u' => $idParieur, ':c' => $idCampagnePari]);
        return (int)$stmt->fetchColumn();
    }

    public function listStatusByPhase(int $idCampagnePari, int $idPhaseCampagne, int $expectedValues): array
    {
        $sql = 'SELECT u.`idUtilisateur`, u.`pseudo`,
                       COUNT(CASE WHEN LENGTH(TRIM(pv.`valeur`)) > 0 THEN 1 END) AS filledValues,
                       MAX(v.`dateVerrouillage`) AS lockedAt
                FROM `InscriptionPari` i
                JOIN `Utilisateur` u ON u.`idUtilisateur` = i.`idParieur`
                LEFT JOIN `AParier` a ON a.`idPhaseCampagne` = :phase_items
                LEFT JOIN `Pari` pr
                       ON pr.`idParieur` = u.`idUtilisateur`
                      AND pr.`idAParier` = a.`idAParier`
                LEFT JOIN `PariValeur` pv ON pv.`idPari` = pr.`idPari`
                LEFT JOIN `PhaseParieurVerrou` v
                       ON v.`idParieur` = u.`idUtilisateur`
                      AND v.`idPhaseCampagne` = :phase_lock
                WHERE i.`idCampagnePari` = :campaign
                GROUP BY u.`idUtilisateur`, u.`pseudo`
                ORDER BY u.`pseudo`';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':phase_items' => $idPhaseCampagne,
            ':phase_lock' => $idPhaseCampagne,
            ':campaign' => $idCampagnePari,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $filledValues = (int)$row['filledValues'];
            $row['filledValues'] = $filledValues;
            $row['expectedValues'] = $expectedValues;

            if ($row['lockedAt'] !== null) {
                $row['status'] = 'locked';
            } elseif ($expectedValues > 0 && $filledValues >= $expectedValues) {
                $row['status'] = 'complete';
            } elseif ($filledValues > 0) {
                $row['status'] = 'in_progress';
            } else {
                $row['status'] = 'not_started';
            }
        }
        unset($row);

        return $rows;
    }
}
