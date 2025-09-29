-- Données par défaut pour Champions League 2025-2026
SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;

-- Campagne
SET @campagne := 'Champions league 2025-2026';
INSERT INTO `CampagnePari` (`libelle`, `description`)
SELECT @campagne, NULL
WHERE NOT EXISTS (SELECT 1 FROM `CampagnePari` WHERE `libelle` = @campagne);
SET @idCampagne := (SELECT `idCampagnePari` FROM `CampagnePari` WHERE `libelle`=@campagne LIMIT 1);

-- Gain par défaut de la campagne (n'est défini que s'il est vide)
UPDATE `CampagnePari`
SET `gain` = 'Une bouffe payée par le (ou les plus mauvais si égalité) en IG 114, un midi !'
WHERE `idCampagnePari` = @idCampagne AND (`gain` IS NULL OR `gain` = '');

-- Type de phase "Journée" (2 valeurs)
SET @typeLib := 'Journée';
INSERT INTO `TypePhase` (`libelle`, `nbValeurParPari`)
SELECT @typeLib, 2
WHERE NOT EXISTS (SELECT 1 FROM `TypePhase` WHERE `libelle` = @typeLib);
SET @idTypePhase := (SELECT `idTypePhase` FROM `TypePhase` WHERE `libelle`=@typeLib LIMIT 1);
REPLACE INTO `LibelleValeurPhase` (`idTypePhase`, `numeroValeur`, `libelle`) VALUES
(@idTypePhase, 1, 'Domicile'),
(@idTypePhase, 2, 'Extérieur');

-- Types de résultat pour calcul des points
SET @idTR1N2 := (SELECT `idTypeResultat` FROM `TypeResultat` WHERE `libelle`='1N2' LIMIT 1);
SET @idTRScore := (SELECT `idTypeResultat` FROM `TypeResultat` WHERE `libelle`='scoreExact' LIMIT 1);

-- Phases Journée 2 à 8
-- Journée 2
SET @p2 := 'journée 2';
INSERT INTO `PhaseCampagne` (`idCampagnePari`,`idTypePhase`,`ordre`,`libelle`,`dateheureLimite`)
SELECT @idCampagne, @idTypePhase, 2, @p2, '2025-09-30 18:45:00'
WHERE NOT EXISTS (SELECT 1 FROM `PhaseCampagne` WHERE `idCampagnePari`=@idCampagne AND `libelle`=@p2);
SET @idP2 := (SELECT `idPhaseCampagne` FROM `PhaseCampagne` WHERE `idCampagnePari`=@idCampagne AND `libelle`=@p2 LIMIT 1);
-- Calculs de points par défaut pour J2
INSERT INTO `PhaseCalculPoint` (`idPhaseCampagne`,`idTypeResultat`,`nbPoint`)
SELECT @idP2, @idTR1N2, 2
WHERE @idP2 IS NOT NULL AND NOT EXISTS (
  SELECT 1 FROM `PhaseCalculPoint` WHERE `idPhaseCampagne`=@idP2 AND `idTypeResultat`=@idTR1N2
);
INSERT INTO `PhaseCalculPoint` (`idPhaseCampagne`,`idTypeResultat`,`nbPoint`)
SELECT @idP2, @idTRScore, 3
WHERE @idP2 IS NOT NULL AND NOT EXISTS (
  SELECT 1 FROM `PhaseCalculPoint` WHERE `idPhaseCampagne`=@idP2 AND `idTypeResultat`=@idTRScore
);
-- AParier J2
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP2, 'Atalanta - Club Brugge' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP2 AND `libellePari`='Atalanta - Club Brugge');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP2, 'Kairat Almaty - Real Madrid' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP2 AND `libellePari`='Kairat Almaty - Real Madrid');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP2, 'Atlético Madrid - Eintracht Francfort' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP2 AND `libellePari`='Atlético Madrid - Eintracht Francfort');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP2, 'Chelsea - Benfica' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP2 AND `libellePari`='Chelsea - Benfica');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP2, 'Inter Milan - Slavia Prague' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP2 AND `libellePari`='Inter Milan - Slavia Prague');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP2, 'Bodø/Glimt - Tottenham' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP2 AND `libellePari`='Bodø/Glimt - Tottenham');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP2, 'Galatasaray - Liverpool' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP2 AND `libellePari`='Galatasaray - Liverpool');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP2, 'Marseille - Ajax' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP2 AND `libellePari`='Marseille - Ajax');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP2, 'Pafos - Bayern Munich' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP2 AND `libellePari`='Pafos - Bayern Munich');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP2, 'Qarabağ - FC Copenhagen' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP2 AND `libellePari`='Qarabağ - FC Copenhagen');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP2, 'Union Saint-Gilloise - Newcastle United' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP2 AND `libellePari`='Union Saint-Gilloise - Newcastle United');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP2, 'Arsenal - Olympiacos' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP2 AND `libellePari`='Arsenal - Olympiacos');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP2, 'AS Monaco - Manchester City' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP2 AND `libellePari`='AS Monaco - Manchester City');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP2, 'Bayer Leverkusen - PSV' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP2 AND `libellePari`='Bayer Leverkusen - PSV');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP2, 'Borussia Dortmund - Athletic Club' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP2 AND `libellePari`='Borussia Dortmund - Athletic Club');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP2, 'FC Barcelona - Paris Saint-Germain' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP2 AND `libellePari`='FC Barcelona - Paris Saint-Germain');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP2, 'Napoli - Sporting CP' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP2 AND `libellePari`='Napoli - Sporting CP');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP2, 'Villarreal - Juventus' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP2 AND `libellePari`='Villarreal - Juventus');

-- Journée 3
SET @p3 := 'journée 3';
INSERT INTO `PhaseCampagne` (`idCampagnePari`,`idTypePhase`,`ordre`,`libelle`,`dateheureLimite`) SELECT @idCampagne, @idTypePhase, 3, @p3, '2025-10-21 18:45:00'
WHERE NOT EXISTS (SELECT 1 FROM `PhaseCampagne` WHERE `idCampagnePari`=@idCampagne AND `libelle`=@p3);
SET @idP3 := (SELECT `idPhaseCampagne` FROM `PhaseCampagne` WHERE `idCampagnePari`=@idCampagne AND `libelle`=@p3 LIMIT 1);
-- Calculs de points par défaut pour J3
INSERT INTO `PhaseCalculPoint` (`idPhaseCampagne`,`idTypeResultat`,`nbPoint`)
SELECT @idP3, @idTR1N2, 2
WHERE @idP3 IS NOT NULL AND NOT EXISTS (
  SELECT 1 FROM `PhaseCalculPoint` WHERE `idPhaseCampagne`=@idP3 AND `idTypeResultat`=@idTR1N2
);
INSERT INTO `PhaseCalculPoint` (`idPhaseCampagne`,`idTypeResultat`,`nbPoint`)
SELECT @idP3, @idTRScore, 3
WHERE @idP3 IS NOT NULL AND NOT EXISTS (
  SELECT 1 FROM `PhaseCalculPoint` WHERE `idPhaseCampagne`=@idP3 AND `idTypeResultat`=@idTRScore
);
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP3, 'FC Barcelona - Olympiacos' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP3 AND `libellePari`='FC Barcelona - Olympiacos');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP3, 'Kairat Almaty - Pafos' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP3 AND `libellePari`='Kairat Almaty - Pafos');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP3, 'Arsenal - Atlético Madrid' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP3 AND `libellePari`='Arsenal - Atlético Madrid');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP3, 'Leverkusen - Paris Saint-Germain' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP3 AND `libellePari`='Leverkusen - Paris Saint-Germain');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP3, 'Copenhagen - Borussia Dortmund' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP3 AND `libellePari`='Copenhagen - Borussia Dortmund');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP3, 'Newcastle United - Benfica' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP3 AND `libellePari`='Newcastle United - Benfica');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP3, 'PSV - Napoli' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP3 AND `libellePari`='PSV - Napoli');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP3, 'Union Saint-Gilloise - Inter Milan' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP3 AND `libellePari`='Union Saint-Gilloise - Inter Milan');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP3, 'Villarreal - Manchester City' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP3 AND `libellePari`='Villarreal - Manchester City');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP3, 'Athletic Club - Qarabağ' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP3 AND `libellePari`='Athletic Club - Qarabağ');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP3, 'Galatasaray - Bodø/Glimt' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP3 AND `libellePari`='Galatasaray - Bodø/Glimt');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP3, 'AS Monaco - Tottenham' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP3 AND `libellePari`='AS Monaco - Tottenham');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP3, 'Atalanta - Slavia Praha' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP3 AND `libellePari`='Atalanta - Slavia Praha');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP3, 'Chelsea - Ajax' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP3 AND `libellePari`='Chelsea - Ajax');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP3, 'Frankfurt - Liverpool' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP3 AND `libellePari`='Frankfurt - Liverpool');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP3, 'Bayern München - Club Brugge' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP3 AND `libellePari`='Bayern München - Club Brugge');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP3, 'Real Madrid - Juventus' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP3 AND `libellePari`='Real Madrid - Juventus');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP3, 'Sporting CP - Marseille' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP3 AND `libellePari`='Sporting CP - Marseille');

-- Journée 4
SET @p4 := 'journée 4';
INSERT INTO `PhaseCampagne` (`idCampagnePari`,`idTypePhase`,`ordre`,`libelle`,`dateheureLimite`) SELECT @idCampagne, @idTypePhase, 4, @p4, '2025-11-04 18:45:00'
WHERE NOT EXISTS (SELECT 1 FROM `PhaseCampagne` WHERE `idCampagnePari`=@idCampagne AND `libelle`=@p4);
SET @idP4 := (SELECT `idPhaseCampagne` FROM `PhaseCampagne` WHERE `idCampagnePari`=@idCampagne AND `libelle`=@p4 LIMIT 1);
-- Calculs de points par défaut pour J4
INSERT INTO `PhaseCalculPoint` (`idPhaseCampagne`,`idTypeResultat`,`nbPoint`)
SELECT @idP4, @idTR1N2, 2
WHERE @idP4 IS NOT NULL AND NOT EXISTS (
  SELECT 1 FROM `PhaseCalculPoint` WHERE `idPhaseCampagne`=@idP4 AND `idTypeResultat`=@idTR1N2
);
INSERT INTO `PhaseCalculPoint` (`idPhaseCampagne`,`idTypeResultat`,`nbPoint`)
SELECT @idP4, @idTRScore, 3
WHERE @idP4 IS NOT NULL AND NOT EXISTS (
  SELECT 1 FROM `PhaseCalculPoint` WHERE `idPhaseCampagne`=@idP4 AND `idTypeResultat`=@idTRScore
);
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP4, 'Slavia Praha – Arsenal' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP4 AND `libellePari`='Slavia Praha – Arsenal');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP4, 'Napoli – Eintracht Francfort' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP4 AND `libellePari`='Napoli – Eintracht Francfort');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP4, 'Atlético Madrid – Union Saint-Gilloise' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP4 AND `libellePari`='Atlético Madrid – Union Saint-Gilloise');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP4, 'Bodø/Glimt – AS Monaco' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP4 AND `libellePari`='Bodø/Glimt – AS Monaco');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP4, 'Juventus – Sporting CP' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP4 AND `libellePari`='Juventus – Sporting CP');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP4, 'Liverpool – Real Madrid' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP4 AND `libellePari`='Liverpool – Real Madrid');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP4, 'Olympiacos – PSV Eindhoven' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP4 AND `libellePari`='Olympiacos – PSV Eindhoven');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP4, 'Paris Saint-Germain – Bayern Munich' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP4 AND `libellePari`='Paris Saint-Germain – Bayern Munich');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP4, 'Tottenham – Copenhague' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP4 AND `libellePari`='Tottenham – Copenhague');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP4, 'Pafos – Villarreal' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP4 AND `libellePari`='Pafos – Villarreal');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP4, 'Qarabağ – Chelsea' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP4 AND `libellePari`='Qarabağ – Chelsea');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP4, 'Manchester City – Borussia Dortmund' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP4 AND `libellePari`='Manchester City – Borussia Dortmund');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP4, 'Newcastle United – Athletic Club' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP4 AND `libellePari`='Newcastle United – Athletic Club');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP4, 'Marseille – Atalanta' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP4 AND `libellePari`='Marseille – Atalanta');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP4, 'Club Brugge – FC Barcelone' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP4 AND `libellePari`='Club Brugge – FC Barcelone');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP4, 'Inter Milan – Kairat Almaty' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP4 AND `libellePari`='Inter Milan – Kairat Almaty');

-- Journée 5
SET @p5 := 'journée 5';
INSERT INTO `PhaseCampagne` SELECT @idCampagne, @idTypePhase, 5, @p5, '2025-11-25 18:45:00'
WHERE NOT EXISTS (SELECT 1 FROM `PhaseCampagne` WHERE `idCampagnePari`=@idCampagne AND `libelle`=@p5);
SET @idP5 := (SELECT `idPhaseCampagne` FROM `PhaseCampagne` WHERE `idCampagnePari`=@idCampagne AND `libelle`=@p5 LIMIT 1);
-- Calculs de points par défaut pour J5
INSERT INTO `PhaseCalculPoint` (`idPhaseCampagne`,`idTypeResultat`,`nbPoint`)
SELECT @idP5, @idTR1N2, 2
WHERE @idP5 IS NOT NULL AND NOT EXISTS (
  SELECT 1 FROM `PhaseCalculPoint` WHERE `idPhaseCampagne`=@idP5 AND `idTypeResultat`=@idTR1N2
);
INSERT INTO `PhaseCalculPoint` (`idPhaseCampagne`,`idTypeResultat`,`nbPoint`)
SELECT @idP5, @idTRScore, 3
WHERE @idP5 IS NOT NULL AND NOT EXISTS (
  SELECT 1 FROM `PhaseCalculPoint` WHERE `idPhaseCampagne`=@idP5 AND `idTypeResultat`=@idTRScore
);
INSERT INTO `AParier` SELECT @idP5, 'Ajax - Benfica' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP5 AND `libellePari`='Ajax - Benfica');
INSERT INTO `AParier` SELECT @idP5, 'Galatasaray - Union Saint-Gilloise' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP5 AND `libellePari`='Galatasaray - Union Saint-Gilloise');
INSERT INTO `AParier` SELECT @idP5, 'Borussia Dortmund - Villarreal' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP5 AND `libellePari`='Borussia Dortmund - Villarreal');
INSERT INTO `AParier` SELECT @idP5, 'Chelsea - FC Barcelona' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP5 AND `libellePari`='Chelsea - FC Barcelona');
INSERT INTO `AParier` SELECT @idP5, 'Bodø/Glimt - Juventus' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP5 AND `libellePari`='Bodø/Glimt - Juventus');
INSERT INTO `AParier` SELECT @idP5, 'Manchester City - Leverkusen' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP5 AND `libellePari`='Manchester City - Leverkusen');
INSERT INTO `AParier` SELECT @idP5, 'Marseille - Newcastle United' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP5 AND `libellePari`='Marseille - Newcastle United');
INSERT INTO `AParier` SELECT @idP5, 'Slavia Praha - Athletic Club' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP5 AND `libellePari`='Slavia Praha - Athletic Club');
INSERT INTO `AParier` SELECT @idP5, 'Napoli - Qarabağ' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP5 AND `libellePari`='Napoli - Qarabağ');
INSERT INTO `AParier` SELECT @idP5, 'Copenhagen - Kairat Almaty' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP5 AND `libellePari`='Copenhagen - Kairat Almaty');
INSERT INTO `AParier` SELECT @idP5, 'Pafos - AS Monaco' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP5 AND `libellePari`='Pafos - AS Monaco');
INSERT INTO `AParier` SELECT @idP5, 'Arsenal - Bayern München' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP5 AND `libellePari`='Arsenal - Bayern München');
INSERT INTO `AParier` SELECT @idP5, 'Atlético Madrid - Inter' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP5 AND `libellePari`='Atlético Madrid - Inter');
INSERT INTO `AParier` SELECT @idP5, 'Frankfurt - Atalanta' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP5 AND `libellePari`='Frankfurt - Atalanta');
INSERT INTO `AParier` SELECT @idP5, 'Liverpool - PSV Eindhoven' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP5 AND `libellePari`='Liverpool - PSV Eindhoven');
INSERT INTO `AParier` SELECT @idP5, 'Olympiacos - Real Madrid' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP5 AND `libellePari`='Olympiacos - Real Madrid');
INSERT INTO `AParier` SELECT @idP5, 'Paris Saint-Germain - Tottenham' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP5 AND `libellePari`='Paris Saint-Germain - Tottenham');
INSERT INTO `AParier` SELECT @idP5, 'Sporting CP - Club Brugge' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP5 AND `libellePari`='Sporting CP - Club Brugge');

-- Journée 6
SET @p6 := 'journée 6';
INSERT INTO `PhaseCampagne` SELECT @idCampagne, @idTypePhase, 6, @p6, '2025-12-09 16:30:00'
WHERE NOT EXISTS (SELECT 1 FROM `PhaseCampagne` WHERE `idCampagnePari`=@idCampagne AND `libelle`=@p6);
SET @idP6 := (SELECT `idPhaseCampagne` FROM `PhaseCampagne` WHERE `idCampagnePari`=@idCampagne AND `libelle`=@p6 LIMIT 1);
-- Calculs de points par défaut pour J6
INSERT INTO `PhaseCalculPoint` (`idPhaseCampagne`,`idTypeResultat`,`nbPoint`)
SELECT @idP6, @idTR1N2, 2
WHERE @idP6 IS NOT NULL AND NOT EXISTS (
  SELECT 1 FROM `PhaseCalculPoint` WHERE `idPhaseCampagne`=@idP6 AND `idTypeResultat`=@idTR1N2
);
INSERT INTO `PhaseCalculPoint` (`idPhaseCampagne`,`idTypeResultat`,`nbPoint`)
SELECT @idP6, @idTRScore, 3
WHERE @idP6 IS NOT NULL AND NOT EXISTS (
  SELECT 1 FROM `PhaseCalculPoint` WHERE `idPhaseCampagne`=@idP6 AND `idTypeResultat`=@idTRScore
);
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP6, 'Kairat Almaty – Olympiacos' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP6 AND `libellePari`='Kairat Almaty – Olympiacos');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP6, 'Bayern München – Sporting CP' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP6 AND `libellePari`='Bayern München – Sporting CP');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP6, 'Monaco – Galatasaray' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP6 AND `libellePari`='Monaco – Galatasaray');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP6, 'Atalanta – Chelsea' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP6 AND `libellePari`='Atalanta – Chelsea');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP6, 'Barcelona – Frankfurt' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP6 AND `libellePari`='Barcelona – Frankfurt');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP6, 'Inter – Liverpool' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP6 AND `libellePari`='Inter – Liverpool');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP6, 'PSV Eindhoven – Atlético Madrid' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP6 AND `libellePari`='PSV Eindhoven – Atlético Madrid');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP6, 'Union Saint-Gilloise – Marseille' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP6 AND `libellePari`='Union Saint-Gilloise – Marseille');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP6, 'Tottenham – Slavia Praha' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP6 AND `libellePari`='Tottenham – Slavia Praha');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP6, 'Qarabağ – Ajax' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP6 AND `libellePari`='Qarabağ – Ajax');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP6, 'Villarreal – Copenhagen' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP6 AND `libellePari`='Villarreal – Copenhagen');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP6, 'Athletic Club – Paris Saint-Germain' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP6 AND `libellePari`='Athletic Club – Paris Saint-Germain');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP6, 'Leverkusen – Newcastle United' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP6 AND `libellePari`='Leverkusen – Newcastle United');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP6, 'Borussia Dortmund – Bodø/Glimt' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP6 AND `libellePari`='Borussia Dortmund – Bodø/Glimt');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP6, 'Club Brugge – Arsenal' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP6 AND `libellePari`='Club Brugge – Arsenal');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP6, 'Juventus – Pafos' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP6 AND `libellePari`='Juventus – Pafos');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP6, 'Real Madrid – Manchester City' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP6 AND `libellePari`='Real Madrid – Manchester City');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP6, 'Benfica – Napoli' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP6 AND `libellePari`='Benfica – Napoli');

-- Journée 7
SET @p7 := 'journée 7';
INSERT INTO `PhaseCampagne` SELECT @idCampagne, @idTypePhase, 7, @p7, '2026-01-20 18:45:00'
WHERE NOT EXISTS (SELECT 1 FROM `PhaseCampagne` WHERE `idCampagnePari`=@idCampagne AND `libelle`=@p7);
SET @idP7 := (SELECT `idPhaseCampagne` FROM `PhaseCampagne` WHERE `idCampagnePari`=@idCampagne AND `libelle`=@p7 LIMIT 1);
-- Calculs de points par défaut pour J7
INSERT INTO `PhaseCalculPoint` (`idPhaseCampagne`,`idTypeResultat`,`nbPoint`)
SELECT @idP7, @idTR1N2, 2
WHERE @idP7 IS NOT NULL AND NOT EXISTS (
  SELECT 1 FROM `PhaseCalculPoint` WHERE `idPhaseCampagne`=@idP7 AND `idTypeResultat`=@idTR1N2
);
INSERT INTO `PhaseCalculPoint` (`idPhaseCampagne`,`idTypeResultat`,`nbPoint`)
SELECT @idP7, @idTRScore, 3
WHERE @idP7 IS NOT NULL AND NOT EXISTS (
  SELECT 1 FROM `PhaseCalculPoint` WHERE `idPhaseCampagne`=@idP7 AND `idTypeResultat`=@idTRScore
);
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP7, 'Kairat Almaty – Club Brugge' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP7 AND `libellePari`='Kairat Almaty – Club Brugge');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP7, 'Bodø/Glimt – Manchester City' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP7 AND `libellePari`='Bodø/Glimt – Manchester City');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP7, 'Copenhagen – Napoli' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP7 AND `libellePari`='Copenhagen – Napoli');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP7, 'Inter – Arsenal' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP7 AND `libellePari`='Inter – Arsenal');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP7, 'Olympiacos – Bayer Leverkusen' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP7 AND `libellePari`='Olympiacos – Bayer Leverkusen');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP7, 'Real Madrid – Monaco' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP7 AND `libellePari`='Real Madrid – Monaco');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP7, 'Sporting CP – Paris Saint-Germain' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP7 AND `libellePari`='Sporting CP – Paris Saint-Germain');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP7, 'Tottenham – Borussia Dortmund' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP7 AND `libellePari`='Tottenham – Borussia Dortmund');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP7, 'Villarreal – Ajax' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP7 AND `libellePari`='Villarreal – Ajax');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP7, 'Galatasaray – Atlético Madrid' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP7 AND `libellePari`='Galatasaray – Atlético Madrid');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP7, 'Qarabağ – Eintracht Francfort' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP7 AND `libellePari`='Qarabağ – Eintracht Francfort');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP7, 'Atalanta – Athletic Club' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP7 AND `libellePari`='Atalanta – Athletic Club');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP7, 'Chelsea – Pafos' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP7 AND `libellePari`='Chelsea – Pafos');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP7, 'Bayern München – Union Saint-Gilloise' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP7 AND `libellePari`='Bayern München – Union Saint-Gilloise');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP7, 'Juventus – Benfica' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP7 AND `libellePari`='Juventus – Benfica');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP7, 'Newcastle United – PSV Eindhoven' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP7 AND `libellePari`='Newcastle United – PSV Eindhoven');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP7, 'Marseille – Liverpool' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP7 AND `libellePari`='Marseille – Liverpool');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP7, 'Slavia Praha – FC Barcelone' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP7 AND `libellePari`='Slavia Praha – FC Barcelone');

-- Journée 8
SET @p8 := 'journée 8';
INSERT INTO `PhaseCampagne` SELECT @idCampagne, @idTypePhase, 8, @p8, '2026-01-28 21:00:00'
WHERE NOT EXISTS (SELECT 1 FROM `PhaseCampagne` WHERE `idCampagnePari`=@idCampagne AND `libelle`=@p8);
SET @idP8 := (SELECT `idPhaseCampagne` FROM `PhaseCampagne` WHERE `idCampagnePari`=@idCampagne AND `libelle`=@p8 LIMIT 1);
-- Calculs de points par défaut pour J8
INSERT INTO `PhaseCalculPoint` (`idPhaseCampagne`,`idTypeResultat`,`nbPoint`)
SELECT @idP8, @idTR1N2, 2
WHERE @idP8 IS NOT NULL AND NOT EXISTS (
  SELECT 1 FROM `PhaseCalculPoint` WHERE `idPhaseCampagne`=@idP8 AND `idTypeResultat`=@idTR1N2
);
INSERT INTO `PhaseCalculPoint` (`idPhaseCampagne`,`idTypeResultat`,`nbPoint`)
SELECT @idP8, @idTRScore, 3
WHERE @idP8 IS NOT NULL AND NOT EXISTS (
  SELECT 1 FROM `PhaseCalculPoint` WHERE `idPhaseCampagne`=@idP8 AND `idTypeResultat`=@idTRScore
);
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP8, 'Ajax – Olympiacos' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP8 AND `libellePari`='Ajax – Olympiacos');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP8, 'Arsenal – Kairat Almaty' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP8 AND `libellePari`='Arsenal – Kairat Almaty');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP8, 'Monaco – Juventus' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP8 AND `libellePari`='Monaco – Juventus');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP8, 'Athletic Club – Sporting CP' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP8 AND `libellePari`='Athletic Club – Sporting CP');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP8, 'Atlético Madrid – Bodø/Glimt' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP8 AND `libellePari`='Atlético Madrid – Bodø/Glimt');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP8, 'Leverkusen – Villarreal' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP8 AND `libellePari`='Leverkusen – Villarreal');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP8, 'Borussia Dortmund – Inter Milan' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP8 AND `libellePari`='Borussia Dortmund – Inter Milan');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP8, 'Club Brugge – Marseille' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP8 AND `libellePari`='Club Brugge – Marseille');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP8, 'Frankfurt – Tottenham' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP8 AND `libellePari`='Frankfurt – Tottenham');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP8, 'Barcelona – Copenhagen' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP8 AND `libellePari`='Barcelona – Copenhagen');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP8, 'Liverpool – Qarabağ' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP8 AND `libellePari`='Liverpool – Qarabağ');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP8, 'Manchester City – Galatasaray' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP8 AND `libellePari`='Manchester City – Galatasaray');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP8, 'Pafos – Slavia Praha' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP8 AND `libellePari`='Pafos – Slavia Praha');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP8, 'Paris Saint-Germain – Newcastle United' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP8 AND `libellePari`='Paris Saint-Germain – Newcastle United');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP8, 'PSV – Bayern München' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP8 AND `libellePari`='PSV – Bayern München');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP8, 'Union Saint-Gilloise – Atalanta' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP8 AND `libellePari`='Union Saint-Gilloise – Atalanta');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP8, 'Benfica – Real Madrid' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP8 AND `libellePari`='Benfica – Real Madrid');
INSERT INTO `AParier` (`idPhaseCampagne`,`libellePari`) SELECT @idP8, 'Napoli – Chelsea' WHERE NOT EXISTS (SELECT 1 FROM `AParier` WHERE `idPhaseCampagne`=@idP8 AND `libellePari`='Napoli – Chelsea');
