-- Nouveau type de phase pour les phases finales avec qualifie en cas de nul
INSERT INTO `TypeResultat` (`libelle`)
SELECT 'qualifieSiN'
WHERE NOT EXISTS (SELECT 1 FROM `TypeResultat` WHERE `libelle` = 'qualifieSiN');

INSERT INTO `TypePhase` (`libelle`, `nbValeurParPari`)
SELECT 'Phase Finale 1-N(qualifié)-2', 3
WHERE NOT EXISTS (
  SELECT 1 FROM `TypePhase` WHERE `libelle` = 'Phase Finale 1-N(qualifié)-2'
);

SET @idTypePhaseFinale := (
  SELECT `idTypePhase`
  FROM `TypePhase`
  WHERE `libelle` = 'Phase Finale 1-N(qualifié)-2'
  LIMIT 1
);

UPDATE `TypePhase`
SET `nbValeurParPari` = 3
WHERE `idTypePhase` = @idTypePhaseFinale;

REPLACE INTO `LibelleValeurPhase` (`idTypePhase`, `numeroValeur`, `libelle`) VALUES
(@idTypePhaseFinale, 1, 'Domicile'),
(@idTypePhaseFinale, 2, 'Extérieur'),
(@idTypePhaseFinale, 3, 'Qualifié si N');
