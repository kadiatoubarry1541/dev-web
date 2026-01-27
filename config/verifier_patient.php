<?php
/**
 * Script de Vérification d'un Patient
 * 
 * Vérifie si un patient existe dans users ET dans PATIENTS
 * Utile pour diagnostiquer les problèmes de rendez-vous
 */

require_once 'bdd.php';

// Patient à vérifier (depuis l'image : ISMATOU Diallo, Matricule: PAT202601234116)
$matricule_recherche = 'PAT202601234116';
$nom_recherche = 'ISMATOU';
$prenom_recherche = 'Diallo';

echo "=== VÉRIFICATION DU PATIENT ===\n\n";
echo "Recherche pour :\n";
echo "  - Nom : $nom_recherche $prenom_recherche\n";
echo "  - Matricule : $matricule_recherche\n\n";

try {
    $pdo = bdd();
    
    // ============================================
    // VÉRIFICATION 1 : Dans la table PATIENTS
    // ============================================
    echo "1. Vérification dans la table PATIENTS...\n";
    
    // Recherche par matricule
    $sql_patients_matricule = "SELECT * FROM PATIENTS WHERE Matricule_patient = ?";
    $stmt = $pdo->prepare($sql_patients_matricule);
    $stmt->execute([$matricule_recherche]);
    $patient_patients = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($patient_patients) {
        echo "   ✅ Patient TROUVÉ dans PATIENTS par matricule\n";
        echo "      - ID Patient : {$patient_patients['id_patient']}\n";
        echo "      - Matricule : {$patient_patients['Matricule_patient']}\n";
        echo "      - Nom : {$patient_patients['Nom_patient']} {$patient_patients['Prénom_patient']}\n";
        echo "      - Email : {$patient_patients['Email_patient']}\n";
        echo "      - Téléphone : {$patient_patients['Tel_patient']}\n";
    } else {
        echo "   ❌ Patient NON TROUVÉ dans PATIENTS par matricule\n";
    }
    
    // Recherche par nom
    $sql_patients_nom = "SELECT * FROM PATIENTS WHERE Nom_patient LIKE ? AND Prénom_patient LIKE ?";
    $stmt = $pdo->prepare($sql_patients_nom);
    $stmt->execute(["%$nom_recherche%", "%$prenom_recherche%"]);
    $patient_patients_nom = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($patient_patients_nom && !$patient_patients) {
        echo "   ⚠️  Patient trouvé par nom mais matricule différent :\n";
        echo "      - ID Patient : {$patient_patients_nom['id_patient']}\n";
        echo "      - Matricule : {$patient_patients_nom['Matricule_patient']}\n";
        echo "      - Nom : {$patient_patients_nom['Nom_patient']} {$patient_patients_nom['Prénom_patient']}\n";
    }
    
    // ============================================
    // VÉRIFICATION 2 : Dans la table users
    // ============================================
    echo "\n2. Vérification dans la table users...\n";
    
    // Recherche par matricule (via lien avec PATIENTS)
    $sql_users_matricule = "SELECT u.*, p.Matricule_patient 
                            FROM users u 
                            LEFT JOIN PATIENTS p ON u.id_patient = p.id_patient 
                            WHERE p.Matricule_patient = ?";
    $stmt = $pdo->prepare($sql_users_matricule);
    $stmt->execute([$matricule_recherche]);
    $user_matricule = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_matricule) {
        echo "   ✅ Compte TROUVÉ dans users par matricule (via lien PATIENTS)\n";
        echo "      - ID User : {$user_matricule['id']}\n";
        echo "      - Email : {$user_matricule['email']}\n";
        echo "      - Rôle : {$user_matricule['role']}\n";
        echo "      - ID Patient (lien) : " . ($user_matricule['id_patient'] ?? 'NULL') . "\n";
    } else {
        echo "   ❌ Compte NON TROUVÉ dans users par matricule\n";
    }
    
    // Recherche par nom
    $sql_users_nom = "SELECT * FROM users WHERE nom LIKE ?";
    $stmt = $pdo->prepare($sql_users_nom);
    $stmt->execute(["%$nom_recherche%$prenom_recherche%"]);
    $user_nom = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_nom) {
        echo "   ✅ Compte TROUVÉ dans users par nom\n";
        echo "      - ID User : {$user_nom['id']}\n";
        echo "      - Email : {$user_nom['email']}\n";
        echo "      - Rôle : {$user_nom['role']}\n";
        echo "      - ID Patient (lien) : " . ($user_nom['id_patient'] ?? 'NULL') . "\n";
        echo "      - ID Med (lien) : " . ($user_nom['id_med'] ?? 'NULL') . "\n";
        
        // Vérifier si le lien id_patient pointe vers un patient valide
        if (!empty($user_nom['id_patient'])) {
            $sql_check_link = "SELECT * FROM PATIENTS WHERE id_patient = ?";
            $stmt_check = $pdo->prepare($sql_check_link);
            $stmt_check->execute([$user_nom['id_patient']]);
            $patient_linked = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if ($patient_linked) {
                echo "      ✅ Lien id_patient VALIDE → PATIENTS.id_patient = {$patient_linked['id_patient']}\n";
                echo "         Matricule lié : {$patient_linked['Matricule_patient']}\n";
            } else {
                echo "      ❌ Lien id_patient INVALIDE → Le patient n'existe pas dans PATIENTS\n";
            }
        } else {
            echo "      ⚠️  Pas de lien id_patient → Le patient n'est pas dans PATIENTS\n";
        }
    } else {
        echo "   ❌ Compte NON TROUVÉ dans users par nom\n";
    }
    
    // ============================================
    // DIAGNOSTIC
    // ============================================
    echo "\n3. DIAGNOSTIC...\n\n";
    
    $probleme_detecte = false;
    
    // Cas 1 : Patient dans users mais PAS dans PATIENTS
    if ($user_nom && !$patient_patients) {
        echo "   ❌ PROBLÈME DÉTECTÉ :\n";
        echo "      Le patient existe dans users mais PAS dans PATIENTS\n";
        echo "      C'est pour cela que les rendez-vous échouent !\n\n";
        echo "   💡 SOLUTION :\n";
        echo "      Exécutez le script de migration : config/migrer_patients.php\n";
        $probleme_detecte = true;
    }
    
    // Cas 2 : Patient dans PATIENTS mais pas de lien dans users
    if ($patient_patients && (!$user_nom || empty($user_nom['id_patient']))) {
        echo "   ⚠️  PROBLÈME DÉTECTÉ :\n";
        echo "      Le patient existe dans PATIENTS mais le lien dans users est manquant\n";
        echo "      Le lien id_patient doit être restauré\n\n";
        $probleme_detecte = true;
    }
    
    // Cas 3 : Patient dans les deux mais matricules différents
    if ($patient_patients && $user_nom && $patient_patients['Matricule_patient'] != $matricule_recherche) {
        echo "   ⚠️  ATTENTION :\n";
        echo "      Le matricule recherché ne correspond pas\n";
        echo "      Matricule dans PATIENTS : {$patient_patients['Matricule_patient']}\n";
        echo "      Matricule recherché : $matricule_recherche\n\n";
    }
    
    // Cas 4 : Tout est OK
    if ($patient_patients && $user_nom && !empty($user_nom['id_patient']) && $user_nom['id_patient'] == $patient_patients['id_patient']) {
        echo "   ✅ TOUT EST CORRECT :\n";
        echo "      Le patient existe dans users ET dans PATIENTS\n";
        echo "      Le lien id_patient est correct\n";
        echo "      Les rendez-vous devraient fonctionner\n\n";
    }
    
    // ============================================
    // STATISTIQUES GÉNÉRALES
    // ============================================
    echo "4. STATISTIQUES GÉNÉRALES...\n\n";
    
    // Compter les patients dans users
    $sql_count_users = "SELECT COUNT(*) as count FROM users WHERE role = 'patient'";
    $stmt = $pdo->query($sql_count_users);
    $count_users = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   - Patients dans users : $count_users\n";
    
    // Compter les patients dans PATIENTS
    $sql_count_patients = "SELECT COUNT(*) as count FROM PATIENTS";
    $stmt = $pdo->query($sql_count_patients);
    $count_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   - Patients dans PATIENTS : $count_patients\n";
    
    // Compter les patients avec liens corrects
    $sql_count_linked = "SELECT COUNT(*) as count FROM users u 
                         INNER JOIN PATIENTS p ON u.id_patient = p.id_patient 
                         WHERE u.role = 'patient'";
    $stmt = $pdo->query($sql_count_linked);
    $count_linked = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   - Patients avec liens corrects : $count_linked\n";
    
    // Compter les patients sans lien
    $sql_count_unlinked = "SELECT COUNT(*) as count FROM users WHERE role = 'patient' AND (id_patient IS NULL OR id_patient NOT IN (SELECT id_patient FROM PATIENTS))";
    $stmt = $pdo->query($sql_count_unlinked);
    $count_unlinked = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   - Patients SANS lien vers PATIENTS : $count_unlinked\n";
    
    if ($count_unlinked > 0) {
        echo "\n   ⚠️  ATTENTION : $count_unlinked patient(s) ne sont pas dans PATIENTS !\n";
        echo "   💡 SOLUTION : Exécutez config/migrer_patients.php\n";
    }
    
    // ============================================
    // RÉSUMÉ
    // ============================================
    echo "\n=== RÉSUMÉ ===\n";
    
    if ($probleme_detecte || $count_unlinked > 0) {
        echo "❌ PROBLÈME CONFIRMÉ : Le patient n'existe pas dans PATIENTS\n";
        echo "   C'est pour cela que les rendez-vous échouent.\n\n";
        echo "✅ SOLUTION : Exécutez le script de migration\n";
        echo "   → config/migrer_patients.php\n";
    } else {
        echo "✅ Le patient semble correctement configuré.\n";
        echo "   Si les rendez-vous échouent encore, vérifiez les logs d'erreur.\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ ERREUR : " . $e->getMessage() . "\n";
    exit(1);
}

?>
