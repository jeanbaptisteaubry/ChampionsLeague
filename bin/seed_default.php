<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Utilitaire\Singleton_ConnexionPDO;

function seed_default(PDO $pdo): void {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Seed TypeResultat
    $stmt = $pdo->prepare('INSERT INTO `TypeResultat` (`libelle`) VALUES (:l) ON DUPLICATE KEY UPDATE `libelle`=VALUES(`libelle`)');
    foreach (['1N2','scoreExact','qualifieSiN'] as $lib) {
        $stmt->execute([':l'=>$lib]);
    }

    // Seed TypePhase "Journée" (2) + labels
    $pdo->prepare('INSERT INTO `TypePhase` (`libelle`,`nbValeurParPari`) VALUES (?,?) ON DUPLICATE KEY UPDATE `nbValeurParPari`=VALUES(`nbValeurParPari`)')
        ->execute(['Journée', 2]);
    $idTypePhase = (int)$pdo->query("SELECT `idTypePhase` FROM `TypePhase` WHERE `libelle`='Journée' LIMIT 1")->fetchColumn();
    $pdo->prepare('REPLACE INTO `LibelleValeurPhase` (`idTypePhase`,`numeroValeur`,`libelle`) VALUES (?,?,?)')->execute([$idTypePhase,1,'Domicile']);
    $pdo->prepare('REPLACE INTO `LibelleValeurPhase` (`idTypePhase`,`numeroValeur`,`libelle`) VALUES (?,?,?)')->execute([$idTypePhase,2,'Extérieur']);

    // Campagne
    $campagneLib = 'Champions league 2025-2026';
    $pdo->prepare('INSERT INTO `CampagnePari` (`libelle`,`description`) VALUES (?,NULL) ON DUPLICATE KEY UPDATE `libelle`=`libelle`')
        ->execute([$campagneLib]);
    $idCampagne = (int)$pdo->prepare('SELECT `idCampagnePari` FROM `CampagnePari` WHERE `libelle`=? LIMIT 1')
        ->execute([$campagneLib]) || true ? (int)$pdo->query("SELECT `idCampagnePari` FROM `CampagnePari` WHERE `libelle`='".str_replace("'","''",$campagneLib)."' LIMIT 1")->fetchColumn() : 0;
    // Set default gain if empty
    $pdo->prepare('UPDATE `CampagnePari` SET `gain`=? WHERE `idCampagnePari`=? AND (`gain` IS NULL OR `gain`="")')
        ->execute(['Une bouffe payée par le (ou les plus mauvais si égalité) en IG 114, un midi !', $idCampagne]);

    // Phases and matches
    $phases = [
        ['libelle'=>'journée 2','ordre'=>2,'date'=>'2025-09-30 18:45:00','matches' => [
            'Atalanta - Club Brugge','Kairat Almaty - Real Madrid','Atlético Madrid - Eintracht Francfort','Chelsea - Benfica','Inter Milan - Slavia Prague','Bodø/Glimt - Tottenham','Galatasaray - Liverpool','Marseille - Ajax','Pafos - Bayern Munich','Qarabağ - FC Copenhagen','Union Saint-Gilloise - Newcastle United','Arsenal - Olympiacos','AS Monaco - Manchester City','Bayer Leverkusen - PSV','Borussia Dortmund - Athletic Club','FC Barcelona - Paris Saint-Germain','Napoli - Sporting CP','Villarreal - Juventus'
        ]],
        ['libelle'=>'journée 3','ordre'=>3,'date'=>'2025-10-21 18:45:00','matches' => [
            'FC Barcelona - Olympiacos','Kairat Almaty - Pafos','Arsenal - Atlético Madrid','Leverkusen - Paris Saint-Germain','Copenhagen - Borussia Dortmund','Newcastle United - Benfica','PSV - Napoli','Union Saint-Gilloise - Inter Milan','Villarreal - Manchester City','Athletic Club - Qarabağ','Galatasaray - Bodø/Glimt','AS Monaco - Tottenham','Atalanta - Slavia Praha','Chelsea - Ajax','Frankfurt - Liverpool','Bayern München - Club Brugge','Real Madrid - Juventus','Sporting CP - Marseille'
        ]],
        ['libelle'=>'journée 4','ordre'=>4,'date'=>'2025-11-04 18:45:00','matches' => [
            'Slavia Praha – Arsenal','Napoli – Eintracht Francfort','Atlético Madrid – Union Saint-Gilloise','Bodø/Glimt – AS Monaco','Juventus – Sporting CP','Liverpool – Real Madrid','Olympiacos – PSV Eindhoven','Paris Saint-Germain – Bayern Munich','Tottenham – Copenhague','Pafos – Villarreal','Qarabağ – Chelsea','Manchester City – Borussia Dortmund','Newcastle United – Athletic Club','Marseille – Atalanta','Club Brugge – FC Barcelone','Inter Milan – Kairat Almaty'
        ]],
        ['libelle'=>'journée 5','ordre'=>5,'date'=>'2025-11-25 18:45:00','matches' => [
            'Ajax - Benfica','Galatasaray - Union Saint-Gilloise','Borussia Dortmund - Villarreal','Chelsea - FC Barcelona','Bodø/Glimt - Juventus','Manchester City - Leverkusen','Marseille - Newcastle United','Slavia Praha - Athletic Club','Napoli - Qarabağ','Copenhagen - Kairat Almaty','Pafos - AS Monaco','Arsenal - Bayern München','Atlético Madrid - Inter','Frankfurt - Atalanta','Liverpool - PSV Eindhoven','Olympiacos - Real Madrid','Paris Saint-Germain - Tottenham','Sporting CP - Club Brugge'
        ]],
        ['libelle'=>'journée 6','ordre'=>6,'date'=>'2025-12-09 16:30:00','matches' => [
            'Kairat Almaty – Olympiacos','Bayern München – Sporting CP','Monaco – Galatasaray','Atalanta – Chelsea','Barcelona – Frankfurt','Inter – Liverpool','PSV Eindhoven – Atlético Madrid','Union Saint-Gilloise – Marseille','Tottenham – Slavia Praha','Qarabağ – Ajax','Villarreal – Copenhagen','Athletic Club – Paris Saint-Germain','Leverkusen – Newcastle United','Borussia Dortmund – Bodø/Glimt','Club Brugge – Arsenal','Juventus – Pafos','Real Madrid – Manchester City','Benfica – Napoli'
        ]],
        ['libelle'=>'journée 7','ordre'=>7,'date'=>'2026-01-20 18:45:00','matches' => [
            'Kairat Almaty – Club Brugge','Bodø/Glimt – Manchester City','Copenhagen – Napoli','Inter – Arsenal','Olympiacos – Bayer Leverkusen','Real Madrid – Monaco','Sporting CP – Paris Saint-Germain','Tottenham – Borussia Dortmund','Villarreal – Ajax','Galatasaray – Atlético Madrid','Qarabağ – Eintracht Francfort','Atalanta – Athletic Club','Chelsea – Pafos','Bayern München – Union Saint-Gilloise','Juventus – Benfica','Newcastle United – PSV Eindhoven','Marseille – Liverpool','Slavia Praha – FC Barcelone'
        ]],
        ['libelle'=>'journée 8','ordre'=>8,'date'=>'2026-01-28 21:00:00','matches' => [
            'Ajax – Olympiacos','Arsenal – Kairat Almaty','Monaco – Juventus','Athletic Club – Sporting CP','Atlético Madrid – Bodø/Glimt','Leverkusen – Villarreal','Borussia Dortmund – Inter Milan','Club Brugge – Marseille','Frankfurt – Tottenham','Barcelona – Copenhagen','Liverpool – Qarabağ','Manchester City – Galatasaray','Pafos – Slavia Praha','Paris Saint-Germain – Newcastle United','PSV – Bayern München','Union Saint-Gilloise – Atalanta','Benfica – Real Madrid','Napoli – Chelsea'
        ]],
    ];

    $insPhase = $pdo->prepare('INSERT INTO `PhaseCampagne` (`idCampagnePari`,`idTypePhase`,`ordre`,`libelle`,`dateheureLimite`) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE `dateheureLimite`=VALUES(`dateheureLimite`)');
    $selPhase = $pdo->prepare('SELECT `idPhaseCampagne` FROM `PhaseCampagne` WHERE `idCampagnePari`=? AND `libelle`=? LIMIT 1');
    $insMatch = $pdo->prepare('INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) VALUES (?,?) ON DUPLICATE KEY UPDATE `libellePari`=`libellePari`');
    $insCalc = $pdo->prepare('INSERT INTO `PhaseCalculPoint` (`idPhaseCampagne`,`idTypeResultat`,`nbPoint`) VALUES (?,?,?) ON DUPLICATE KEY UPDATE `nbPoint`=VALUES(`nbPoint`)');

    $idTR1N2 = (int)$pdo->query("SELECT `idTypeResultat` FROM `TypeResultat` WHERE `libelle`='1N2' LIMIT 1")->fetchColumn();
    $idTRScore = (int)$pdo->query("SELECT `idTypeResultat` FROM `TypeResultat` WHERE `libelle`='scoreExact' LIMIT 1")->fetchColumn();

    foreach ($phases as $ph) {
        $insPhase->execute([$idCampagne, $idTypePhase, $ph['ordre'], $ph['libelle'], $ph['date']]);
        $selPhase->execute([$idCampagne, $ph['libelle']]);
        $idPhase = (int)$selPhase->fetchColumn();
        foreach ($ph['matches'] as $m) {
            $insMatch->execute([$idPhase, $m]);
        }
        // Calculs points
        $insCalc->execute([$idPhase, $idTR1N2, 2]);
        $insCalc->execute([$idPhase, $idTRScore, 3]);
    }

    echo "✅ Seed par défaut appliqué\n";
}

// Si exécuté directement
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['argv'][0] ?? '')) {
    $pdo = Singleton_ConnexionPDO::getInstance();
    seed_default($pdo);
}

