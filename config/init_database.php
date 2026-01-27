<?php
/**
 * Script d'initialisation de la base de données
 * Vérifie et crée les tables si elles n'existent pas
 */

require_once 'bdd.php';

function initDatabase() {
    $pdo = bdd();
    
    try {
        // Vérifier si la table MEDECINS existe
        $sql_check = "SHOW TABLES LIKE 'MEDECINS'";
        $stmt = $pdo->query($sql_check);
        
        if ($stmt->rowCount() == 0) {
            // La table n'existe pas, lire et exécuter le script SQL
            $sql_file = __DIR__ . '/sante1_database.sql';
            
            if (file_exists($sql_file)) {
                $sql_content = file_get_contents($sql_file);
                
                // Supprimer les commentaires et diviser en requêtes
                $sql_content = preg_replace('/--.*$/m', '', $sql_content);
                $queries = array_filter(array_map('trim', explode(';', $sql_content)));
                
                foreach ($queries as $query) {
                    if (!empty($query) && strlen($query) > 10) {
                        try {
                            $pdo->exec($query);
                        } catch (PDOException $e) {
                            // Ignorer les erreurs de table déjà existante
                            if (strpos($e->getMessage(), 'already exists') === false) {
                                error_log("Erreur SQL: " . $e->getMessage());
                            }
                        }
                    }
                }
                
                return true;
            } else {
                throw new Exception("Le fichier SQL n'existe pas : " . $sql_file);
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Erreur lors de l'initialisation de la base de données : " . $e->getMessage());
        return false;
    }
}

// Exécuter l'initialisation si appelé directement
if (basename($_SERVER['PHP_SELF']) == 'init_database.php') {
    if (initDatabase()) {
        echo "Base de données initialisée avec succès !";
    } else {
        echo "Erreur lors de l'initialisation de la base de données.";
    }
}

?>
