-- Verrouillage des paris par phase et par parieur
CREATE TABLE IF NOT EXISTS `PhaseParieurVerrou` (
  `idPhaseCampagne` INT NOT NULL,
  `idParieur` INT NOT NULL,
  `dateVerrouillage` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idPhaseCampagne`, `idParieur`),
  CONSTRAINT `fk_ppv_phase` FOREIGN KEY (`idPhaseCampagne`) REFERENCES `PhaseCampagne` (`idPhaseCampagne`) ON DELETE CASCADE,
  CONSTRAINT `fk_ppv_user` FOREIGN KEY (`idParieur`) REFERENCES `Utilisateur` (`idUtilisateur`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

