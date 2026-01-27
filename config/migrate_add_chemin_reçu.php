<?php
/**
 * Script de migration pour ajouter la colonne chemin_reçu à la table PAIEMENT
 * Exécuter ce script une seule fois pour mettre à jour la base de données
 * 
 * Accès via navigateur : http://localhost/ProjetClinique/config/migrate_add_chemin_reçu.php
 */

// Définir le type de contenu pour l'affichage dans le navigateur
header('Content-Type: text/html; charset=utf-8');

require_once 'bdd.php';

try {
    $pdo = bdd();
    
    // Vérifier si la colonne existe déjà
    $check_column = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'chemin_reçu'");
    $column_exists = $check_column->rowCount() > 0;
    
    if ($column_exists) {
        echo "<p style='color: green;'>✓ La colonne 'chemin_reçu' existe déjà dans la table PAIEMENT.</p>";
    } else {
        // Ajouter la colonne
        $pdo->exec("ALTER TABLE PAIEMENT ADD COLUMN chemin_reçu VARCHAR(255) NULL AFTER id_facture");
        
        // Créer un index pour améliorer les performances
        try {
            $pdo->exec("CREATE INDEX idx_chemin_reçu ON PAIEMENT(chemin_reçu)");
        } catch (Exception $e) {
            // L'index peut déjà exister, ignorer l'erreur
            error_log("Index peut déjà exister: " . $e->getMessage());
        }
        
        echo "<p style='color: green;'>✓ La colonne 'chemin_reçu' a été ajoutée avec succès à la table PAIEMENT.</p>";
    }
    
    // Créer le dossier pour les reçus s'il n'existe pas
    $receipts_dir = __DIR__ . '/../uploads/reçus';
    if (!file_exists($receipts_dir)) {
        mkdir($receipts_dir, 0755, true);
        // Créer un fichier .htaccess pour protéger le dossier
        file_put_contents($receipts_dir . '/.htaccess', "Options -Indexes\n");
        echo "<p style='color: green;'>✓ Le dossier 'uploads/reçus' a été créé.</p>";
    } else {
        echo "<p style='color: blue;'>ℹ Le dossier 'uploads/reçus' existe déjà.</p>";
    }
    
    echo "<p style='color: green; font-weight: bold; margin-top: 20px;'>✓ Migration terminée avec succès !</p>";
    echo "<p><a href='../admin/index.php'>Retour au tableau de bord</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erreur lors de la migration : " . htmlspecialchars($e->getMessage()) . "</p>";
    error_log("Erreur migration chemin_reçu: " . $e->getMessage());
}

?>
