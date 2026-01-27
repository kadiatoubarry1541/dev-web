-- Script SQL pour ajouter "orange_money" à la méthode de paiement
-- Exécutez ce script dans votre base de données

-- Pour la colonne Méthode_paiement (avec accent)
ALTER TABLE PAIEMENT 
MODIFY COLUMN Méthode_paiement ENUM('espèces', 'carte', 'chèque', 'virement', 'orange_money') DEFAULT 'espèces';

-- Si vous avez la colonne methode_paiement (sans accent)
-- ALTER TABLE PAIEMENT 
-- MODIFY COLUMN methode_paiement ENUM('espèces', 'carte', 'chèque', 'virement', 'orange_money') DEFAULT 'espèces';

-- Ajouter la colonne orange_order_id si elle n'existe pas
ALTER TABLE PAIEMENT 
ADD COLUMN IF NOT EXISTS orange_order_id VARCHAR(100) NULL AFTER id_facture;

-- Ajouter un index pour améliorer les performances
CREATE INDEX IF NOT EXISTS idx_orange_order_id ON PAIEMENT(orange_order_id);
