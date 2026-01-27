-- Script pour modifier la structure des tables PATIENTS et MEDECINS
-- pour permettre NULL temporairement pour les matricules
-- Les matricules seront attribués par l'admin après validation

USE `santé1`;

-- Modifier la table PATIENTS pour permettre NULL pour Matricule_patient
ALTER TABLE `PATIENTS` 
MODIFY COLUMN `Matricule_patient` VARCHAR(50) UNIQUE NULL;

-- Modifier la table MEDECINS pour permettre NULL pour Matricule_med
ALTER TABLE `MEDECINS` 
MODIFY COLUMN `Matricule_med` VARCHAR(50) UNIQUE NULL;

-- Note: Les contraintes UNIQUE restent actives, mais NULL est autorisé
-- Cela signifie qu'un seul NULL est possible par table (selon le moteur MySQL)
-- Pour gérer plusieurs NULL, on peut utiliser un index partiel ou un trigger
-- Pour l'instant, on va utiliser un système où l'admin attribue les matricules immédiatement
