-- Verrouillage des paris par phase et par parieur
CREATE TABLE IF NOT EXISTS `PhaseParieurVerrou` (
  `idPhaseCampagne` INT UNSIGNED NOT NULL,
  `idParieur` INT UNSIGNED NOT NULL,
  `dateVerrouillage` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idPhaseCampagne`, `idParieur`),
  KEY `idx_ppv_user` (`idParieur`),
  CONSTRAINT `fk_ppv_phase` FOREIGN KEY (`idPhaseCampagne`) REFERENCES `PhaseCampagne` (`idPhaseCampagne`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_ppv_user` FOREIGN KEY (`idParieur`) REFERENCES `Utilisateur` (`idUtilisateur`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
