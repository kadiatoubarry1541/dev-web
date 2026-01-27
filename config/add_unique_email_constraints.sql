-- Script pour ajouter des contraintes UNIQUE sur les emails
-- À exécuter si les tables existent déjà

USE `santé1`;

-- Ajouter UNIQUE sur Email_patient dans PATIENTS
ALTER TABLE `PATIENTS` 
ADD UNIQUE INDEX `idx_email_patient` (`Email_patient`);

-- Ajouter UNIQUE sur Email_med dans MEDECINS
ALTER TABLE `MEDECINS` 
ADD UNIQUE INDEX `idx_email_med` (`Email_med`);

-- Vérifier que la contrainte UNIQUE existe sur users.email (devrait déjà exister)
-- Si elle n'existe pas, la créer :
-- ALTER TABLE `users` ADD UNIQUE INDEX `idx_email` (`email`);
