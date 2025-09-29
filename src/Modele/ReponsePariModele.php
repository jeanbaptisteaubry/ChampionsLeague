<?php
declare(strict_types=1);

namespace App\Modele;

use App\Utilitaire\Singleton_ConnexionPDO;
use PDO;

final class ReponsePariModele
{
    private PDO $pdo;
    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo ?? Singleton_ConnexionPDO::getInstance(); }

    public function setResult(int $idAParier, int $numeroValeur, string $valeurResultat): bool
    {
        $sql = 'INSERT INTO `ReponsePari` (`idAParier`, `numeroValeur`, `valeurResultat`) VALUES (:i, :n, :v)
                ON DUPLICATE KEY UPDATE `valeurResultat` = VALUES(`valeurResultat`)';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':i' => $idAParier, ':n' => $numeroValeur, ':v' => $valeurResultat]);
    }

    public function findByAParier(int $idAParier): array
    {
        $stmt = $this->pdo->prepare('SELECT `numeroValeur`, `valeurResultat` FROM `ReponsePari` WHERE `idAParier`=:i ORDER BY `numeroValeur`');
        $stmt->execute([':i' => $idAParier]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

