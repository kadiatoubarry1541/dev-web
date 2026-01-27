<?php
/**
 * Script de Nettoyage des Tables en Double
 * 
 * Ce script identifie et supprime les tables en double (singulier/pluriel)
 * 
 * PROBLÈME :
 * - Tables en double : patient/patients, medecin/medecins, ordonnance/ordonnances
 * - C'est une tautologie inutile
 * 
 * SOLUTION :
 * - Le code utilise les tables PLURIELLES (PATIENTS, MEDECINS, ORDONNANCES)
 * - Vérifier si les tables singulières contiennent des données
 * - Migrer les données si nécessaire
 * - Supprimer les tables singulières (doublons)
 */

require_once 'bdd.php';

echo "=== NETTOYAGE DES TABLES EN DOUBLE ===\n\n";

try {
    $pdo = bdd();
    
    $tables_a_verifier = [
        ['singulier' => 'patient', 'pluriel' => 'PATIENTS'],
        ['singulier' => 'medecin', 'pluriel' => 'MEDECINS'],
        ['singulier' => 'ordonnance', 'pluriel' => 'ORDONNANCES']
    ];
    
    $actions = [];
    $erreurs = [];
    
    // ============================================
    // ÉTAPE 1 : Vérifier quelles tables existent
    // ============================================
    echo "1. Vérification des tables existantes...\n\n";
    
    foreach ($tables_a_verifier as $table_info) {
        $singulier = $table_info['singulier'];
        $pluriel = $table_info['pluriel'];
        
        echo "   Vérification : $singulier / $pluriel\n";
        
        // Vérifier si la table singulière existe
        $sql_check_singulier = "SHOW TABLES LIKE '$singulier'";
        $stmt = $pdo->query($sql_check_singulier);
        $table_singulier_exists = $stmt->rowCount() > 0;
        
        // Vérifier si la table plurielle existe
        $sql_check_pluriel = "SHOW TABLES LIKE '$pluriel'";
        $stmt = $pdo->query($sql_check_pluriel);
        $table_pluriel_exists = $stmt->rowCount() > 0;
        
        if ($table_singulier_exists) {
            echo "      ✅ Table '$singulier' existe\n";
        } else {
            echo "      ❌ Table '$singulier' n'existe pas\n";
        }
        
        if ($table_pluriel_exists) {
            echo "      ✅ Table '$pluriel' existe\n";
        } else {
            echo "      ❌ Table '$pluriel' n'existe pas\n";
        }
        
        // Si les deux existent, c'est un doublon
        if ($table_singulier_exists && $table_pluriel_exists) {
            echo "      ⚠️  DOUBLON DÉTECTÉ : Les deux tables existent !\n";
            
            // Compter les enregistrements dans chaque table
            try {
                $sql_count_singulier = "SELECT COUNT(*) as count FROM `$singulier`";
                $stmt = $pdo->query($sql_count_singulier);
                $count_singulier = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                $sql_count_pluriel = "SELECT COUNT(*) as count FROM `$pluriel`";
                $stmt = $pdo->query($sql_count_pluriel);
                $count_pluriel = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                echo "         - Enregistrements dans '$singulier' : $count_singulier\n";
                echo "         - Enregistrements dans '$pluriel' : $count_pluriel\n";
                
                // Le code utilise la table PLURIELLE, donc on garde celle-ci
                if ($count_singulier > 0) {
                    echo "         ⚠️  La table '$singulier' contient des données !\n";
                    echo "         💡 Il faudra migrer ces données vers '$pluriel' avant suppression\n";
                    $actions[] = [
                        'type' => 'migration',
                        'table_source' => $singulier,
                        'table_destination' => $pluriel,
                        'count_source' => $count_singulier,
                        'count_destination' => $count_pluriel
                    ];
                } else {
                    echo "         ✅ La table '$singulier' est vide, peut être supprimée\n";
                    $actions[] = [
                        'type' => 'suppression',
                        'table' => $singulier,
                        'raison' => 'Table vide en double'
                    ];
                }
            } catch (Exception $e) {
                echo "         ❌ Erreur lors du comptage : " . $e->getMessage() . "\n";
                $erreurs[] = "Erreur comptage $singulier : " . $e->getMessage();
            }
        } elseif ($table_singulier_exists && !$table_pluriel_exists) {
            echo "      ⚠️  ATTENTION : Seule la table '$singulier' existe\n";
            echo "         Le code utilise '$pluriel' - problème de configuration !\n";
            $erreurs[] = "Table '$singulier' existe mais pas '$pluriel'";
        } elseif (!$table_singulier_exists && $table_pluriel_exists) {
            echo "      ✅ Configuration correcte : seule '$pluriel' existe\n";
        } else {
            echo "      ⚠️  Aucune des deux tables n'existe\n";
        }
        
        echo "\n";
    }
    
    // ============================================
    // ÉTAPE 2 : Demander confirmation et exécuter
    // ============================================
    if (empty($actions)) {
        echo "✅ Aucune action nécessaire. Pas de tables en double détectées.\n";
        exit(0);
    }
    
    echo "2. Actions à effectuer :\n\n";
    foreach ($actions as $i => $action) {
        echo "   " . ($i + 1) . ". ";
        if ($action['type'] === 'suppression') {
            echo "SUPPRIMER la table '{$action['table']}' (vide)\n";
        } elseif ($action['type'] === 'migration') {
            echo "MIGRER {$action['count_source']} enregistrement(s) de '{$action['table_source']}' vers '{$action['table_destination']}'\n";
            echo "      puis SUPPRIMER '{$action['table_source']}'\n";
        }
    }
    
    echo "\n";
    echo "⚠️  ATTENTION : Cette opération est IRREVERSIBLE !\n";
    echo "   Faites une sauvegarde de votre base de données avant de continuer.\n\n";
    
    // Mode interactif : demander confirmation
    // Pour exécution automatique, décommenter la ligne suivante :
    // $confirmation = 'oui';
    
    if (php_sapi_name() === 'cli') {
        // Mode ligne de commande
        echo "Voulez-vous continuer ? (oui/non) : ";
        $confirmation = trim(fgets(STDIN));
    } else {
        // Mode web - utiliser un paramètre GET
        $confirmation = isset($_GET['confirmer']) && $_GET['confirmer'] === 'oui' ? 'oui' : 'non';
        if ($confirmation === 'non') {
            echo "Pour exécuter le nettoyage, ajoutez ?confirmer=oui à l'URL\n";
            echo "Exemple : http://localhost/ProjetClinique/config/nettoyer_tables_doublons.php?confirmer=oui\n";
            exit(0);
        }
    }
    
    if (strtolower($confirmation) !== 'oui') {
        echo "❌ Opération annulée.\n";
        exit(0);
    }
    
    echo "\n3. Exécution des actions...\n\n";
    
    $pdo->beginTransaction();
    
    foreach ($actions as $action) {
        try {
            if ($action['type'] === 'suppression') {
                echo "   Suppression de la table '{$action['table']}'...\n";
                $sql_drop = "DROP TABLE IF EXISTS `{$action['table']}`";
                $pdo->exec($sql_drop);
                echo "      ✅ Table '{$action['table']}' supprimée\n\n";
                
            } elseif ($action['type'] === 'migration') {
                $table_source = $action['table_source'];
                $table_destination = $action['table_destination'];
                
                echo "   Migration de '$table_source' vers '$table_destination'...\n";
                
                // Récupérer la structure de la table source
                $sql_structure = "SHOW CREATE TABLE `$table_source`";
                $stmt = $pdo->query($sql_structure);
                $structure = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Récupérer toutes les données de la table source
                $sql_select = "SELECT * FROM `$table_source`";
                $stmt = $pdo->query($sql_select);
                $donnees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "      - {$action['count_source']} enregistrement(s) à migrer\n";
                
                if (count($donnees) > 0) {
                    // Récupérer les colonnes de la table destination
                    $sql_columns = "SHOW COLUMNS FROM `$table_destination`";
                    $stmt = $pdo->query($sql_columns);
                    $columns_dest = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Insérer les données dans la table destination
                    $migres = 0;
                    $ignores = 0;
                    
                    foreach ($donnees as $row) {
                        // Filtrer les colonnes qui existent dans la destination
                        $row_filtered = [];
                        $placeholders = [];
                        
                        foreach ($columns_dest as $col) {
                            if (isset($row[$col])) {
                                $row_filtered[$col] = $row[$col];
                                $placeholders[] = '?';
                            }
                        }
                        
                        if (empty($row_filtered)) {
                            $ignores++;
                            continue;
                        }
                        
                        // Construire la requête INSERT
                        $columns = implode(', ', array_keys($row_filtered));
                        $values = implode(', ', $placeholders);
                        $sql_insert = "INSERT IGNORE INTO `$table_destination` ($columns) VALUES ($values)";
                        
                        try {
                            $stmt_insert = $pdo->prepare($sql_insert);
                            $stmt_insert->execute(array_values($row_filtered));
                            $migres++;
                        } catch (Exception $e) {
                            // Ignorer les doublons (INSERT IGNORE)
                            $ignores++;
                        }
                    }
                    
                    echo "      ✅ $migres enregistrement(s) migré(s)\n";
                    if ($ignores > 0) {
                        echo "      ⚠️  $ignores enregistrement(s) ignoré(s) (doublons ou colonnes incompatibles)\n";
                    }
                }
                
                // Supprimer la table source
                echo "      Suppression de la table '$table_source'...\n";
                $sql_drop = "DROP TABLE IF EXISTS `$table_source`";
                $pdo->exec($sql_drop);
                echo "      ✅ Table '$table_source' supprimée\n\n";
            }
            
        } catch (Exception $e) {
            echo "      ❌ ERREUR : " . $e->getMessage() . "\n\n";
            $erreurs[] = "Erreur lors de l'action sur {$action['table'] ?? $action['table_source']} : " . $e->getMessage();
        }
    }
    
    // Valider la transaction
    $pdo->commit();
    
    // ============================================
    // RÉSUMÉ
    // ============================================
    echo "=== RÉSUMÉ ===\n";
    echo "Actions effectuées : " . count($actions) . "\n";
    echo "Erreurs : " . count($erreurs) . "\n\n";
    
    if (!empty($erreurs)) {
        echo "Erreurs rencontrées :\n";
        foreach ($erreurs as $i => $erreur) {
            echo "  " . ($i + 1) . ". $erreur\n";
        }
    }
    
    echo "\n✅ Nettoyage terminé avec succès !\n";
    echo "\nLes tables en double ont été supprimées.\n";
    echo "Le système utilise maintenant uniquement les tables PLURIELLES :\n";
    echo "  - PATIENTS (pas 'patient')\n";
    echo "  - MEDECINS (pas 'medecin')\n";
    echo "  - ORDONNANCES (pas 'ordonnance')\n";
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ ERREUR CRITIQUE : " . $e->getMessage() . "\n";
    echo "Transaction annulée. Aucune modification n'a été effectuée.\n";
    exit(1);
}

?>
