-- Ajouter la colonne chemin_reçu à la table PAIEMENT
ALTER TABLE PAIEMENT 
ADD COLUMN chemin_reçu VARCHAR(255) NULL AFTER id_facture;

-- Créer un index pour améliorer les performances
CREATE INDEX idx_chemin_reçu ON PAIEMENT(chemin_reçu);
