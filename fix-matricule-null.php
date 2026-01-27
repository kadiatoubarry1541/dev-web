<?php
/**
 * Script pour corriger la structure de la table MEDECINS
 * pour permettre NULL dans Matricule_med
 */

require_once 'config/bdd.php';

echo "=== CORRECTION DE LA STRUCTURE DE LA TABLE MEDECINS ===\n\n";

try {
    $pdo = bdd();
    
    // Vérifier la structure actuelle
    echo "1. Vérification de la structure actuelle...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM MEDECINS WHERE Field = 'Matricule_med'");
    $col = $stmt->fetch();
    
    if ($col) {
        echo "   Colonne Matricule_med :\n";
        echo "   - Type : {$col['Type']}\n";
        echo "   - Null : {$col['Null']}\n";
        echo "   - Key : {$col['Key']}\n";
        echo "   - Default : " . ($col['Default'] ?? 'NULL') . "\n\n";
        
        if ($col['Null'] === 'NO') {
            echo "2. ❌ La colonne Matricule_med est définie comme NOT NULL\n";
            echo "   Correction nécessaire...\n\n";
            
            // Modifier la colonne pour permettre NULL
            echo "3. Modification de la colonne pour permettre NULL...\n";
            $pdo->exec("ALTER TABLE MEDECINS MODIFY COLUMN Matricule_med VARCHAR(50) UNIQUE NULL");
            
            echo "   ✅ Colonne modifiée avec succès !\n\n";
            
            // Vérifier la nouvelle structure
            echo "4. Vérification de la nouvelle structure...\n";
            $stmt = $pdo->query("SHOW COLUMNS FROM MEDECINS WHERE Field = 'Matricule_med'");
            $col_new = $stmt->fetch();
            
            if ($col_new && $col_new['Null'] === 'YES') {
                echo "   ✅ La colonne Matricule_med permet maintenant NULL\n";
                echo "   - Type : {$col_new['Type']}\n";
                echo "   - Null : {$col_new['Null']}\n";
                echo "\n✅ CORRECTION RÉUSSIE !\n";
                echo "Vous pouvez maintenant réessayer l'inscription.\n";
            } else {
                echo "   ❌ Erreur : La modification n'a pas fonctionné\n";
            }
        } else {
            echo "2. ✅ La colonne Matricule_med permet déjà NULL\n";
            echo "   Le problème doit venir d'ailleurs.\n";
        }
    } else {
        echo "   ❌ Erreur : Impossible de trouver la colonne Matricule_med\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR : " . $e->getMessage() . "\n";
    echo "File : " . $e->getFile() . "\n";
    echo "Line : " . $e->getLine() . "\n";
}
?>
