-- ============================================
-- Base de données : sante1 (sans accent, pour Render)
-- Schéma complet + Orange Money + Notifications
-- À importer après création de la base MySQL sur Render
-- ============================================

USE `sante1`;

-- ============================================
-- Tables du schéma principal (ProjetClinique)
-- ============================================

CREATE TABLE IF NOT EXISTS `PATIENTS` (
    `id_patient` INT AUTO_INCREMENT PRIMARY KEY,
    `Matricule_patient` VARCHAR(50) UNIQUE NOT NULL,
    `Nom_patient` VARCHAR(100) NOT NULL,
    `Prénom_patient` VARCHAR(100) NOT NULL,
    `Date_naissance_patient` DATE NOT NULL,
    `Tel_patient` VARCHAR(20),
    `Email_patient` VARCHAR(100) UNIQUE,
    `Adresse_patient` TEXT,
    `Photo_profil` VARCHAR(255) NULL,
    `Date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_matricule` (`Matricule_patient`),
    INDEX `idx_nom_prenom` (`Nom_patient`, `Prénom_patient`),
    UNIQUE INDEX `idx_email_patient` (`Email_patient`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `MEDECINS` (
    `id_med` INT AUTO_INCREMENT PRIMARY KEY,
    `Matricule_med` VARCHAR(50) UNIQUE NOT NULL,
    `Nom_med` VARCHAR(100) NOT NULL,
    `Prénom_med` VARCHAR(100) NOT NULL,
    `Spécialisation_med` VARCHAR(100) NOT NULL,
    `Tel_med` VARCHAR(20),
    `Email_med` VARCHAR(100) UNIQUE,
    `Photo_profil` VARCHAR(255) NULL,
    `statut` ENUM('en_attente', 'approuvé', 'refusé') DEFAULT 'en_attente',
    `Date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_matricule_med` (`Matricule_med`),
    INDEX `idx_specialisation` (`Spécialisation_med`),
    UNIQUE INDEX `idx_email_med` (`Email_med`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `CARNETS` (
    `Num_carnet` INT AUTO_INCREMENT PRIMARY KEY,
    `Libellé` VARCHAR(200) NOT NULL,
    `id_patient` INT NOT NULL,
    `Date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_patient`) REFERENCES `PATIENTS`(`id_patient`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_patient` (`id_patient`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `SERVICES` (
    `id_service` INT AUTO_INCREMENT PRIMARY KEY,
    `Nom_service` VARCHAR(100) NOT NULL,
    `Tarif` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `Description` TEXT,
    `Date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_nom_service` (`Nom_service`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `RENDEZ_VOUS` (
    `id_rdv` INT AUTO_INCREMENT PRIMARY KEY,
    `Date_rdv` DATETIME NOT NULL,
    `Notification` TINYINT(1) DEFAULT 0,
    `Statut` ENUM('planifié', 'confirmé', 'annulé', 'terminé') DEFAULT 'planifié',
    `id_patient` INT NOT NULL,
    `id_med` INT NOT NULL,
    `id_service` INT,
    `Motif` TEXT,
    `Date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_patient`) REFERENCES `PATIENTS`(`id_patient`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`id_med`) REFERENCES `MEDECINS`(`id_med`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`id_service`) REFERENCES `SERVICES`(`id_service`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_date_rdv` (`Date_rdv`),
    INDEX `idx_patient_rdv` (`id_patient`),
    INDEX `idx_med_rdv` (`id_med`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `CONSULTATION` (
    `id_consultation` INT AUTO_INCREMENT PRIMARY KEY,
    `Date_consultation` DATETIME NOT NULL,
    `Motif_diagnostic` TEXT NOT NULL,
    `Note` TEXT,
    `Statut` ENUM('en_cours', 'terminée', 'annulée') DEFAULT 'en_cours',
    `id_patient` INT NOT NULL,
    `id_med` INT NOT NULL,
    `Num_carnet` INT NOT NULL,
    `Date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_patient`) REFERENCES `PATIENTS`(`id_patient`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`id_med`) REFERENCES `MEDECINS`(`id_med`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`Num_carnet`) REFERENCES `CARNETS`(`Num_carnet`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_date_consultation` (`Date_consultation`),
    INDEX `idx_patient_consult` (`id_patient`),
    INDEX `idx_med_consult` (`id_med`),
    INDEX `idx_carnet` (`Num_carnet`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PAIEMENT` (
    `id_paiement` INT AUTO_INCREMENT PRIMARY KEY,
    `Montant` DECIMAL(10, 2) NOT NULL,
    `Date_paiement` DATETIME NOT NULL,
    `Statut` ENUM('en_attente', 'payé', 'remboursé', 'annulé') DEFAULT 'en_attente',
    `Méthode_paiement` ENUM('espèces', 'carte', 'chèque', 'virement', 'orange_money') DEFAULT 'espèces',
    `id_facture` VARCHAR(50) UNIQUE,
    `orange_order_id` VARCHAR(100) NULL,
    `chemin_reçu` VARCHAR(255) NULL,
    `id_patient` INT NOT NULL,
    `id_consultation` INT UNIQUE,
    `Date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_patient`) REFERENCES `PATIENTS`(`id_patient`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`id_consultation`) REFERENCES `CONSULTATION`(`id_consultation`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_date_paiement` (`Date_paiement`),
    INDEX `idx_patient_paiement` (`id_patient`),
    INDEX `idx_facture` (`id_facture`),
    INDEX `idx_orange_order_id` (`orange_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ORDONNANCES` (
    `id_ordonnance` INT AUTO_INCREMENT PRIMARY KEY,
    `Médicament` VARCHAR(200) NOT NULL,
    `Dosage` VARCHAR(100) NOT NULL,
    `Date_émission` DATE NOT NULL,
    `Durée_traitement` VARCHAR(50),
    `Instructions` TEXT,
    `id_consultation` INT NOT NULL,
    `Date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_consultation`) REFERENCES `CONSULTATION`(`id_consultation`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_date_emission` (`Date_émission`),
    INDEX `idx_consultation_ordo` (`id_consultation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `CONSULTATION_SERVICES` (
    `id_consultation` INT NOT NULL,
    `id_med` INT NOT NULL,
    `id_service` INT NOT NULL,
    `Date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_consultation`, `id_med`, `id_service`),
    FOREIGN KEY (`id_consultation`) REFERENCES `CONSULTATION`(`id_consultation`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`id_med`) REFERENCES `MEDECINS`(`id_med`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`id_service`) REFERENCES `SERVICES`(`id_service`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_consultation` (`id_consultation`),
    INDEX `idx_med` (`id_med`),
    INDEX `idx_service` (`id_service`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `telephone` VARCHAR(20),
    `password` VARCHAR(255) NOT NULL,
    `photo_profil` VARCHAR(255) NULL,
    `role` ENUM('patient', 'medecin', 'admin', 'accueil') DEFAULT 'patient',
    `id_patient` INT NULL,
    `id_med` INT NULL,
    `Date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_patient`) REFERENCES `PATIENTS`(`id_patient`) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (`id_med`) REFERENCES `MEDECINS`(`id_med`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_email` (`email`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PATIENT_SERVICES` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_patient` INT NOT NULL,
    `id_service` INT NOT NULL,
    `Date_inscription` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `Statut` ENUM('inscrit', 'en_attente', 'traité') DEFAULT 'inscrit',
    FOREIGN KEY (`id_patient`) REFERENCES `PATIENTS`(`id_patient`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`id_service`) REFERENCES `SERVICES`(`id_service`) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY `unique_patient_service` (`id_patient`, `id_service`),
    INDEX `idx_patient` (`id_patient`),
    INDEX `idx_service` (`id_service`),
    INDEX `idx_date_inscription` (`Date_inscription`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `NOTIFICATIONS` (
    `id_notification` INT AUTO_INCREMENT PRIMARY KEY,
    `id_patient` INT NOT NULL,
    `titre` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('paiement', 'rendez_vous', 'consultation', 'autre') DEFAULT 'autre',
    `lien` VARCHAR(500) NULL,
    `lu` TINYINT(1) DEFAULT 0,
    `Date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_patient`) REFERENCES `PATIENTS`(`id_patient`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_patient` (`id_patient`),
    INDEX `idx_lu` (`lu`),
    INDEX `idx_date_creation` (`Date_creation`),
    INDEX `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Données de base
INSERT INTO `SERVICES` (`Nom_service`, `Tarif`, `Description`) VALUES
('Consultation générale', 5000.00, 'Consultation médicale générale'),
('Maternité', 10000.00, 'Suivi de grossesse et accouchement'),
('Chirurgie', 50000.00, 'Interventions chirurgicales'),
('Ophtalmologie', 8000.00, 'Examens et soins oculaires')
ON DUPLICATE KEY UPDATE Nom_service=Nom_service;

INSERT INTO `MEDECINS` (`Matricule_med`, `Nom_med`, `Prénom_med`, `Spécialisation_med`, `Tel_med`, `Email_med`) VALUES
('MED001', 'Laurent', 'Sophie', 'Gynécologie-Obstétrique', '01 23 45 67 01', 's.laurent@medico.fr'),
('MED002', 'Dubois', 'Marc', 'Chirurgie générale', '01 23 45 67 02', 'm.dubois@medico.fr'),
('MED003', 'Moreau', 'Julie', 'Médecine générale', '01 23 45 67 03', 'j.moreau@medico.fr'),
('MED004', 'Renaud', 'Thomas', 'Ophtalmologie', '01 23 45 67 04', 't.renaud@medico.fr')
ON DUPLICATE KEY UPDATE Matricule_med=Matricule_med;
