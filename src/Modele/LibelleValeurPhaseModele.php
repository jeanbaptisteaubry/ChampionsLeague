<?php
declare(strict_types=1);

namespace App\Modele;

use App\Utilitaire\Singleton_ConnexionPDO;
use PDO;

final class LibelleValeurPhaseModele
{
    private PDO $pdo;
    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo ?? Singleton_ConnexionPDO::getInstance(); }

    public function upsert(int $idTypePhase, int $numeroValeur, string $libelle): bool
    {
        $sql = 'INSERT INTO `LibelleValeurPhase` (`idTypePhase`, `numeroValeur`, `libelle`) VALUES (:id, :num, :lib)
                ON DUPLICATE KEY UPDATE `libelle` = VALUES(`libelle`)';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $idTypePhase, ':num' => $numeroValeur, ':lib' => $libelle]);
    }

    public function delete(int $idTypePhase, int $numeroValeur): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM `LibelleValeurPhase` WHERE `idTypePhase`=:id AND `numeroValeur`=:num');
        return $stmt->execute([':id' => $idTypePhase, ':num' => $numeroValeur]);
    }
}

