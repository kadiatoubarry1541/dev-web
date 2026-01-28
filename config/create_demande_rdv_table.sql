-- Table des demandes de rendez-vous (dossier non reconnu) : l'accueil les traite puis le médecin confirme
CREATE TABLE IF NOT EXISTS `DEMANDE_RENDEZ_VOUS` (
    `id_demande` INT AUTO_INCREMENT PRIMARY KEY,
    `Date_rdv_souhaitee` DATETIME NOT NULL,
    `email_demandeur` VARCHAR(255) NULL,
    `nom_demandeur` VARCHAR(200) NULL,
    `matricule_demandeur` VARCHAR(100) NULL,
    `id_service` INT NULL,
    `motif` TEXT NULL,
    `id_user` INT NULL,
    `statut` ENUM('en_attente_accueil', 'traitee', 'annulee') DEFAULT 'en_attente_accueil',
    `Date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_service`) REFERENCES `SERVICES`(`id_service`) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (`id_user`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_statut` (`statut`),
    INDEX `idx_date_souhaitee` (`Date_rdv_souhaitee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
