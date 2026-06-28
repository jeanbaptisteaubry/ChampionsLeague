-- Nouveau pari: pronostiquer les deux finalistes et le champion.
-- Points: chaque finaliste exact rapporte les points configures, le champion exact rapporte le double.
INSERT INTO `TypeResultat` (`libelle`)
SELECT 'finalistesChampion'
WHERE NOT EXISTS (SELECT 1 FROM `TypeResultat` WHERE `libelle` = 'finalistesChampion');

INSERT INTO `TypePhase` (`libelle`, `nbValeurParPari`)
SELECT 'Qui sera en finale', 3
WHERE NOT EXISTS (
  SELECT 1 FROM `TypePhase` WHERE `libelle` = 'Qui sera en finale'
);

SET @idTypePhaseFinalistes := (
  SELECT `idTypePhase`
  FROM `TypePhase`
  WHERE `libelle` = 'Qui sera en finale'
  LIMIT 1
);

UPDATE `TypePhase`
SET `nbValeurParPari` = 3
WHERE `idTypePhase` = @idTypePhaseFinalistes;

REPLACE INTO `LibelleValeurPhase` (`idTypePhase`, `numeroValeur`, `libelle`) VALUES
(@idTypePhaseFinalistes, 1, 'Finaliste 1'),
(@idTypePhaseFinalistes, 2, 'Finaliste 2'),
(@idTypePhaseFinalistes, 3, 'Champion');
