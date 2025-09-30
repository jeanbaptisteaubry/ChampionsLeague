-- Script de création de base pour ChampionsLeague
-- Tables: TypeUtilisateur, Utilisateur
-- Note: ajoutez d'autres tables/contraintes selon les besoins



-- Table des types d'utilisateur
-- Drop in FK order (children first)
DROP TABLE IF EXISTS `ReponsePari`;
DROP TABLE IF EXISTS `PariValeur`;
DROP TABLE IF EXISTS `Pari`;
DROP TABLE IF EXISTS `PhaseCalculPoint`;
DROP TABLE IF EXISTS `TypeResultat`;
DROP TABLE IF EXISTS `InscriptionPari`;
DROP TABLE IF EXISTS `AParier`;
DROP TABLE IF EXISTS `PhaseCampagne`;
DROP TABLE IF EXISTS `LibelleValeurPhase`;
DROP TABLE IF EXISTS `TypePhase`;
DROP TABLE IF EXISTS `CampagnePari`;
DROP TABLE IF EXISTS `Utilisateur`;
DROP TABLE IF EXISTS `TypeUtilisateur`;
CREATE TABLE `TypeUtilisateur` (
  `idTypeUtilisateur` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `libelle` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NULL,
  PRIMARY KEY (`idTypeUtilisateur`),
  UNIQUE KEY `uniq_type_libelle` (`libelle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Données initiales: parieur / administrateur
INSERT INTO `TypeUtilisateur` (`idTypeUtilisateur`, `libelle`, `description`) VALUES
  (1, 'parieur', 'Utilisateur pouvant parier'),
  (2, 'administrateur', 'Utilisateur administrateur du système')
ON DUPLICATE KEY UPDATE
  `libelle` = VALUES(`libelle`),
  `description` = VALUES(`description`);

-- Table des utilisateurs
DROP TABLE IF EXISTS `Utilisateur`;
CREATE TABLE `Utilisateur` (
  `idUtilisateur` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pseudo` VARCHAR(50) NOT NULL,
  `motDePasseHasch` VARCHAR(255) NULL,
  `mail` VARCHAR(255) NOT NULL,
  `idTypeUtilisateur` INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`idUtilisateur`),
  UNIQUE KEY `uniq_utilisateur_pseudo` (`pseudo`),
  UNIQUE KEY `uniq_utilisateur_mail` (`mail`),
  KEY `idx_utilisateur_type` (`idTypeUtilisateur`),
  CONSTRAINT `fk_utilisateur_type`
    FOREIGN KEY (`idTypeUtilisateur`) REFERENCES `TypeUtilisateur`(`idTypeUtilisateur`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tokens d'activation / réinitialisation mot de passe
DROP TABLE IF EXISTS `UtilisateurToken`;
CREATE TABLE `UtilisateurToken` (
  `idToken` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `idUtilisateur` INT UNSIGNED NOT NULL,
  `type` VARCHAR(20) NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `expiresAt` DATETIME NOT NULL,
  `usedAt` DATETIME NULL,
  PRIMARY KEY (`idToken`),
  UNIQUE KEY `uniq_token` (`token`),
  KEY `idx_token_user` (`idUtilisateur`),
  CONSTRAINT `fk_token_user`
    FOREIGN KEY (`idUtilisateur`) REFERENCES `Utilisateur`(`idUtilisateur`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Campagnes de pari
CREATE TABLE `CampagnePari` (
  `idCampagnePari` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `libelle` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `gain` VARCHAR(2000) NULL,
  PRIMARY KEY (`idCampagnePari`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Types de phase
CREATE TABLE `TypePhase` (
  `idTypePhase` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `libelle` VARCHAR(100) NOT NULL,
  `nbValeurParPari` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`idTypePhase`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Types de résultat (codés en dur / seed plus bas)
CREATE TABLE `TypeResultat` (
  `idTypeResultat` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `libelle` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`idTypeResultat`),
  UNIQUE KEY `uniq_type_resultat` (`libelle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Libellés pour chaque valeur d'un type de phase (1..n)
CREATE TABLE `LibelleValeurPhase` (
  `idTypePhase` INT UNSIGNED NOT NULL,
  `numeroValeur` TINYINT UNSIGNED NOT NULL,
  `libelle` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`idTypePhase`,`numeroValeur`),
  CONSTRAINT `fk_libelle_typephase`
    FOREIGN KEY (`idTypePhase`) REFERENCES `TypePhase`(`idTypePhase`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Phases d'une campagne
CREATE TABLE `PhaseCampagne` (
  `idPhaseCampagne` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `idCampagnePari` INT UNSIGNED NOT NULL,
  `idTypePhase` INT UNSIGNED NOT NULL,
  `ordre` INT UNSIGNED NOT NULL DEFAULT 1,
  `libelle` VARCHAR(150) NOT NULL,
  `dateheureLimite` DATETIME NOT NULL,
  PRIMARY KEY (`idPhaseCampagne`),
  UNIQUE KEY `uniq_phase_campagne_libelle` (`idCampagnePari`,`libelle`),
  KEY `idx_phase_campagne` (`idCampagnePari`),
  KEY `idx_phase_type` (`idTypePhase`),
  CONSTRAINT `fk_phase_campagne`
    FOREIGN KEY (`idCampagnePari`) REFERENCES `CampagnePari`(`idCampagnePari`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_phase_typephase`
    FOREIGN KEY (`idTypePhase`) REFERENCES `TypePhase`(`idTypePhase`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Calcul des points par phase
CREATE TABLE `PhaseCalculPoint` (
  `idPhaseCalculPoint` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `idPhaseCampagne` INT UNSIGNED NOT NULL,
  `idTypeResultat` INT UNSIGNED NOT NULL,
  `nbPoint` INT NOT NULL DEFAULT 1,
  PRIMARY KEY (`idPhaseCalculPoint`),
  UNIQUE KEY `uniq_phase_type_resultat` (`idPhaseCampagne`, `idTypeResultat`),
  KEY `idx_phasecalc_phase` (`idPhaseCampagne`),
  KEY `idx_phasecalc_typeres` (`idTypeResultat`),
  CONSTRAINT `fk_phasecalc_phase`
    FOREIGN KEY (`idPhaseCampagne`) REFERENCES `PhaseCampagne`(`idPhaseCampagne`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_phasecalc_typeres`
    FOREIGN KEY (`idTypeResultat`) REFERENCES `TypeResultat`(`idTypeResultat`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Éléments à parier pour une phase
CREATE TABLE `AParier` (
  `idAParier` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `idPhaseCampagne` INT UNSIGNED NOT NULL,
  `libellePari` VARCHAR(150) NOT NULL,
  PRIMARY KEY (`idAParier`),
  UNIQUE KEY `uniq_aparier_phase_libelle` (`idPhaseCampagne`,`libellePari`),
  KEY `idx_aparier_phase` (`idPhaseCampagne`),
  CONSTRAINT `fk_aparier_phase`
    FOREIGN KEY (`idPhaseCampagne`) REFERENCES `PhaseCampagne`(`idPhaseCampagne`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Réponses officielles (résultats) pour un élément à parier
CREATE TABLE `ReponsePari` (
  `idAParier` INT UNSIGNED NOT NULL,
  `numeroValeur` TINYINT UNSIGNED NOT NULL,
  `valeurResultat` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`idAParier`, `numeroValeur`),
  CONSTRAINT `fk_result_aparier`
    FOREIGN KEY (`idAParier`) REFERENCES `AParier`(`idAParier`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Inscriptions des parieurs aux campagnes
CREATE TABLE `InscriptionPari` (
  `idParieur` INT UNSIGNED NOT NULL,
  `idCampagnePari` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`idParieur`, `idCampagnePari`),
  CONSTRAINT `fk_insc_user`
    FOREIGN KEY (`idParieur`) REFERENCES `Utilisateur`(`idUtilisateur`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_insc_campagne`
    FOREIGN KEY (`idCampagnePari`) REFERENCES `CampagnePari`(`idCampagnePari`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Paris des utilisateurs (valeur unique pour starter)
CREATE TABLE `Pari` (
  `idPari` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `idParieur` INT UNSIGNED NOT NULL,
  `idAParier` INT UNSIGNED NOT NULL,
  `valeur1` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`idPari`),
  UNIQUE KEY `uniq_pari_user_item` (`idParieur`, `idAParier`),
  KEY `idx_pari_aparier` (`idAParier`),
  CONSTRAINT `fk_pari_user`
    FOREIGN KEY (`idParieur`) REFERENCES `Utilisateur`(`idUtilisateur`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_pari_aparier`
    FOREIGN KEY (`idAParier`) REFERENCES `AParier`(`idAParier`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Valeurs des paris (multi-valeurs)
CREATE TABLE `PariValeur` (
  `idPari` INT UNSIGNED NOT NULL,
  `numeroValeur` TINYINT UNSIGNED NOT NULL,
  `valeur` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`idPari`, `numeroValeur`),
  CONSTRAINT `fk_parivaleur_pari`
    FOREIGN KEY (`idPari`) REFERENCES `Pari`(`idPari`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Fin du script

-- Seed TypeResultat (codé en dur)
INSERT INTO `TypeResultat` (`idTypeResultat`, `libelle`) VALUES
  (1, '1N2'),
  (2, 'scoreExact'),
  (3, 'qualifieSiN')
ON DUPLICATE KEY UPDATE `libelle` = VALUES(`libelle`);
