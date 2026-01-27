<?php
/**
 * Script pour créer la table NOTIFICATIONS
 * Cette table stocke les messages/notifications aux patients
 */

require_once 'bdd.php';
require_once 'database_functions.php';

try {
    $pdo = bdd();
    
    // Créer la table NOTIFICATIONS si elle n'existe pas
    $result = createNotificationsTable();
    
    if ($result) {
        echo "✅ Table NOTIFICATIONS créée avec succès !\n";
    } else {
        // Vérifier si la table existe déjà
        if (tableExists('NOTIFICATIONS')) {
            echo "ℹ️ La table NOTIFICATIONS existe déjà.\n";
        } else {
            echo "⚠️ Erreur lors de la création de la table NOTIFICATIONS.\n";
        }
    }
    
    // Afficher la structure de la table
    if (tableExists('NOTIFICATIONS')) {
        echo "\n📋 Structure de la table NOTIFICATIONS :\n";
        $sql = "DESCRIBE NOTIFICATIONS";
        $stmt = $pdo->query($sql);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}

?>
