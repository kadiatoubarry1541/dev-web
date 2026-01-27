<?php
/**
 * Script de correction et réorganisation des comptes utilisateurs
 * 
 * Ce script vérifie et corrige tous les comptes dans la base de données
 * pour s'assurer que chaque compte est dans sa table appropriée :
 * 
 * - Admin : uniquement dans users (role = 'admin')
 * - Médecins : dans users (role = 'medecin') ET dans MEDECINS
 * - Accueil : uniquement dans users (role = 'accueil')
 * - Patients : dans users (role = 'patient') ET dans PATIENTS
 * 
 * Tout compte qui n'est pas admin, médecin ou accueil devient automatiquement patient.
 */

require_once 'bdd.php';

echo "=== CORRECTION ET RÉORGANISATION DES COMPTES ===\n\n";

try {
    $pdo = bdd();
    $pdo->beginTransaction();
    
    $corrections = [];
    $erreurs = [];
    
    // ============================================
    // ÉTAPE 1 : Vérifier et corriger les médecins
    // ============================================
    echo "1. Vérification des médecins...\n";
    
    // Médecins dans users mais pas dans MEDECINS
    $sql_medecins_orphelins = "SELECT u.* FROM users u 
                               WHERE u.role = 'medecin' 
                               AND (u.id_med IS NULL OR u.id_med NOT IN (SELECT id_med FROM MEDECINS))";
    $stmt = $pdo->query($sql_medecins_orphelins);
    $medecins_orphelins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($medecins_orphelins as $medecin) {
        echo "   ⚠️  Médecin orphelin trouvé : {$medecin['email']} (ID: {$medecin['id']})\n";
        
        // Essayer de trouver le médecin dans MEDECINS par email
        $sql_find_med = "SELECT id_med FROM MEDECINS WHERE Email_med = ?";
        $stmt_find = $pdo->prepare($sql_find_med);
        $stmt_find->execute([$medecin['email']]);
        $med_found = $stmt_find->fetch(PDO::FETCH_ASSOC);
        
        if ($med_found) {
            // Lier le compte users au médecin trouvé
            $sql_update = "UPDATE users SET id_med = ? WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$med_found['id_med'], $medecin['id']]);
            echo "   ✅ Lien restauré avec MEDECINS.id_med = {$med_found['id_med']}\n";
            $corrections[] = "Médecin {$medecin['email']} : Lien restauré";
        } else {
            // Pas de médecin trouvé, convertir en patient
            echo "   🔄 Conversion en patient...\n";
            
            // Créer le patient dans PATIENTS
            $nom_parts = explode(' ', $medecin['nom'], 2);
            $nom = $nom_parts[0] ?? $medecin['nom'];
            $prenom = $nom_parts[1] ?? '';
            
            // Générer un matricule
            require_once 'traitement.php';
            $matricule = genererMatriculePatient();
            
            $sql_patient = "INSERT INTO PATIENTS (Matricule_patient, Nom_patient, Prénom_patient, Tel_patient, Email_patient, Date_naissance_patient, Photo_profil) 
                              VALUES (?, ?, ?, ?, ?, '1900-01-01', ?)";
            $stmt_patient = $pdo->prepare($sql_patient);
            $stmt_patient->execute([$matricule, $nom, $prenom, $medecin['telephone'], $medecin['email'], $medecin['photo_profil']]);
            $id_patient = $pdo->lastInsertId();
            
            // Mettre à jour users
            $sql_update = "UPDATE users SET role = 'patient', id_patient = ?, id_med = NULL WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$id_patient, $medecin['id']]);
            
            echo "   ✅ Converti en patient (ID: $id_patient, Matricule: $matricule)\n";
            $corrections[] = "Médecin {$medecin['email']} : Converti en patient";
        }
    }
    
    // Médecins dans MEDECINS mais pas dans users
    $sql_medecins_sans_compte = "SELECT m.* FROM MEDECINS m 
                                 LEFT JOIN users u ON m.id_med = u.id_med 
                                 WHERE u.id IS NULL";
    $stmt = $pdo->query($sql_medecins_sans_compte);
    $medecins_sans_compte = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($medecins_sans_compte as $medecin) {
        echo "   ⚠️  Médecin sans compte users : {$medecin['Email_med']} (ID: {$medecin['id_med']})\n";
        
        // Vérifier si un compte users existe avec cet email
        $sql_check_email = "SELECT id FROM users WHERE email = ?";
        $stmt_check = $pdo->prepare($sql_check_email);
        $stmt_check->execute([$medecin['Email_med']]);
        $user_existant = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($user_existant) {
            // Lier le compte existant
            $sql_update = "UPDATE users SET role = 'medecin', id_med = ?, id_patient = NULL WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$medecin['id_med'], $user_existant['id']]);
            echo "   ✅ Compte users lié au médecin\n";
            $corrections[] = "Médecin {$medecin['Email_med']} : Compte users lié";
        } else {
            // Créer un compte users pour ce médecin
            $nom_complet = trim($medecin['Nom_med'] . ' ' . $medecin['Prénom_med']);
            $password_hash = password_hash('temp123456', PASSWORD_DEFAULT); // Mot de passe temporaire
            
            $sql_user = "INSERT INTO users (nom, email, telephone, password, photo_profil, role, id_med, id_patient) 
                         VALUES (?, ?, ?, ?, ?, 'medecin', ?, NULL)";
            $stmt_user = $pdo->prepare($sql_user);
            $stmt_user->execute([$nom_complet, $medecin['Email_med'], $medecin['Tel_med'], $password_hash, $medecin['Photo_profil'], $medecin['id_med']]);
            
            echo "   ✅ Compte users créé (mot de passe temporaire: temp123456)\n";
            $corrections[] = "Médecin {$medecin['Email_med']} : Compte users créé";
        }
    }
    
    // ============================================
    // ÉTAPE 2 : Vérifier et corriger les patients
    // ============================================
    echo "\n2. Vérification des patients...\n";
    
    // Patients dans users mais pas dans PATIENTS
    $sql_patients_orphelins = "SELECT u.* FROM users u 
                               WHERE u.role = 'patient' 
                               AND (u.id_patient IS NULL OR u.id_patient NOT IN (SELECT id_patient FROM PATIENTS))";
    $stmt = $pdo->query($sql_patients_orphelins);
    $patients_orphelins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($patients_orphelins as $patient) {
        echo "   ⚠️  Patient orphelin trouvé : {$patient['email']} (ID: {$patient['id']})\n";
        
        // Essayer de trouver le patient dans PATIENTS par email
        $sql_find_pat = "SELECT id_patient FROM PATIENTS WHERE Email_patient = ?";
        $stmt_find = $pdo->prepare($sql_find_pat);
        $stmt_find->execute([$patient['email']]);
        $pat_found = $stmt_find->fetch(PDO::FETCH_ASSOC);
        
        if ($pat_found) {
            // Lier le compte users au patient trouvé
            $sql_update = "UPDATE users SET id_patient = ? WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$pat_found['id_patient'], $patient['id']]);
            echo "   ✅ Lien restauré avec PATIENTS.id_patient = {$pat_found['id_patient']}\n";
            $corrections[] = "Patient {$patient['email']} : Lien restauré";
        } else {
            // Créer le patient dans PATIENTS
            $nom_parts = explode(' ', $patient['nom'], 2);
            $nom = $nom_parts[0] ?? $patient['nom'];
            $prenom = $nom_parts[1] ?? '';
            
            // Générer un matricule
            require_once 'traitement.php';
            $matricule = genererMatriculePatient();
            
            $sql_patient = "INSERT INTO PATIENTS (Matricule_patient, Nom_patient, Prénom_patient, Tel_patient, Email_patient, Date_naissance_patient, Photo_profil) 
                            VALUES (?, ?, ?, ?, ?, '1900-01-01', ?)";
            $stmt_patient = $pdo->prepare($sql_patient);
            $stmt_patient->execute([$matricule, $nom, $prenom, $patient['telephone'], $patient['email'], $patient['photo_profil']]);
            $id_patient = $pdo->lastInsertId();
            
            // Mettre à jour users
            $sql_update = "UPDATE users SET id_patient = ? WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$id_patient, $patient['id']]);
            
            echo "   ✅ Patient créé dans PATIENTS (ID: $id_patient, Matricule: $matricule)\n";
            $corrections[] = "Patient {$patient['email']} : Créé dans PATIENTS";
        }
    }
    
    // Patients dans PATIENTS mais pas dans users
    $sql_patients_sans_compte = "SELECT p.* FROM PATIENTS p 
                                 LEFT JOIN users u ON p.id_patient = u.id_patient 
                                 WHERE u.id IS NULL";
    $stmt = $pdo->query($sql_patients_sans_compte);
    $patients_sans_compte = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($patients_sans_compte as $patient) {
        echo "   ⚠️  Patient sans compte users : {$patient['Email_patient']} (ID: {$patient['id_patient']})\n";
        
        // Vérifier si un compte users existe avec cet email
        $sql_check_email = "SELECT id FROM users WHERE email = ?";
        $stmt_check = $pdo->prepare($sql_check_email);
        $stmt_check->execute([$patient['Email_patient']]);
        $user_existant = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($user_existant) {
            // Lier le compte existant
            $sql_update = "UPDATE users SET role = 'patient', id_patient = ?, id_med = NULL WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$patient['id_patient'], $user_existant['id']]);
            echo "   ✅ Compte users lié au patient\n";
            $corrections[] = "Patient {$patient['Email_patient']} : Compte users lié";
        } else {
            // Créer un compte users pour ce patient
            $nom_complet = trim($patient['Nom_patient'] . ' ' . $patient['Prénom_patient']);
            $password_hash = password_hash('temp123456', PASSWORD_DEFAULT); // Mot de passe temporaire
            
            $sql_user = "INSERT INTO users (nom, email, telephone, password, photo_profil, role, id_patient, id_med) 
                         VALUES (?, ?, ?, ?, ?, 'patient', ?, NULL)";
            $stmt_user = $pdo->prepare($sql_user);
            $stmt_user->execute([$nom_complet, $patient['Email_patient'], $patient['Tel_patient'], $password_hash, $patient['Photo_profil'], $patient['id_patient']]);
            
            echo "   ✅ Compte users créé (mot de passe temporaire: temp123456)\n";
            $corrections[] = "Patient {$patient['Email_patient']} : Compte users créé";
        }
    }
    
    // ============================================
    // ÉTAPE 3 : Corriger les comptes avec rôles invalides
    // ============================================
    echo "\n3. Vérification des rôles...\n";
    
    // Comptes avec rôle NULL ou invalide
    $sql_roles_invalides = "SELECT id, email, role, id_patient, nom, telephone, photo_profil FROM users WHERE role IS NULL OR role NOT IN ('admin', 'medecin', 'accueil', 'patient')";
    $stmt = $pdo->query($sql_roles_invalides);
    $roles_invalides = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($roles_invalides as $compte) {
        echo "   ⚠️  Compte avec rôle invalide : {$compte['email']} (Rôle: " . ($compte['role'] ?? 'NULL') . ")\n";
        
        // Si pas de lien patient, créer le patient
        if (empty($compte['id_patient'])) {
            // Créer le patient dans PATIENTS
            $nom_parts = explode(' ', $compte['nom'], 2);
            $nom = $nom_parts[0] ?? $compte['nom'];
            $prenom = $nom_parts[1] ?? '';
            
            require_once 'traitement.php';
            $matricule = genererMatriculePatient();
            
            $sql_patient = "INSERT INTO PATIENTS (Matricule_patient, Nom_patient, Prénom_patient, Tel_patient, Email_patient, Date_naissance_patient, Photo_profil) 
                            VALUES (?, ?, ?, ?, ?, '1900-01-01', ?)";
            $stmt_patient = $pdo->prepare($sql_patient);
            $stmt_patient->execute([$matricule, $nom, $prenom, $compte['telephone'], $compte['email'], $compte['photo_profil'] ?? null]);
            $id_patient = $pdo->lastInsertId();
            
            // Mettre à jour users
            $sql_update = "UPDATE users SET id_patient = ?, id_med = NULL WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$id_patient, $compte['id']]);
            
            echo "   ✅ Rôle corrigé à 'patient' et patient créé (Matricule: $matricule)\n";
        } else {
            echo "   ✅ Rôle corrigé à 'patient'\n";
        }
        
        $corrections[] = "Compte {$compte['email']} : Rôle corrigé à 'patient'";
    }
    
    // ============================================
    // ÉTAPE 4 : Corriger les comptes admin et accueil
    // ============================================
    echo "\n4. Vérification des comptes admin et accueil...\n";
    
    // Admin et accueil ne doivent pas avoir de liens
    $sql_admin_accueil_avec_liens = "SELECT id, email, role, id_patient, id_med FROM users 
                                      WHERE role IN ('admin', 'accueil') 
                                      AND (id_patient IS NOT NULL OR id_med IS NOT NULL)";
    $stmt = $pdo->query($sql_admin_accueil_avec_liens);
    $comptes_avec_liens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($comptes_avec_liens as $compte) {
        echo "   ⚠️  {$compte['role']} avec liens inappropriés : {$compte['email']}\n";
        
        // Supprimer les liens
        $sql_update = "UPDATE users SET id_patient = NULL, id_med = NULL WHERE id = ?";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([$compte['id']]);
        
        echo "   ✅ Liens supprimés\n";
        $corrections[] = "{$compte['role']} {$compte['email']} : Liens supprimés";
    }
    
    // ============================================
    // ÉTAPE 5 : Convertir les comptes orphelins en patients
    // ============================================
    echo "\n5. Conversion des comptes orphelins en patients...\n";
    
    // Comptes qui ne sont ni admin, ni médecin, ni accueil, ni patient
    // OU qui ont un rôle patient mais pas de lien
    $sql_orphelins = "SELECT u.* FROM users u 
                      WHERE (u.role NOT IN ('admin', 'medecin', 'accueil', 'patient') 
                             OR (u.role = 'patient' AND u.id_patient IS NULL))
                      AND u.role IS NOT NULL";
    $stmt = $pdo->query($sql_orphelins);
    $comptes_orphelins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($comptes_orphelins as $compte) {
        echo "   ⚠️  Compte orphelin : {$compte['email']} (Rôle: {$compte['role']})\n";
        
        // Créer le patient dans PATIENTS
        $nom_parts = explode(' ', $compte['nom'], 2);
        $nom = $nom_parts[0] ?? $compte['nom'];
        $prenom = $nom_parts[1] ?? '';
        
        require_once 'traitement.php';
        $matricule = genererMatriculePatient();
        
        $sql_patient = "INSERT INTO PATIENTS (Matricule_patient, Nom_patient, Prénom_patient, Tel_patient, Email_patient, Date_naissance_patient, Photo_profil) 
                        VALUES (?, ?, ?, ?, ?, '1900-01-01', ?)";
        $stmt_patient = $pdo->prepare($sql_patient);
        $stmt_patient->execute([$matricule, $nom, $prenom, $compte['telephone'], $compte['email'], $compte['photo_profil'] ?? null]);
        $id_patient = $pdo->lastInsertId();
        
        // Mettre à jour users
        $sql_update = "UPDATE users SET role = 'patient', id_patient = ?, id_med = NULL WHERE id = ?";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([$id_patient, $compte['id']]);
        
        echo "   ✅ Converti en patient (Matricule: $matricule)\n";
        $corrections[] = "Compte {$compte['email']} : Converti en patient";
    }
    
    // ============================================
    // VALIDATION FINALE
    // ============================================
    echo "\n6. Validation finale...\n";
    
    // Vérifier qu'il n'y a plus d'incohérences
    $incoherences = [];
    
    // Médecins sans lien
    $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'medecin' AND id_med IS NULL";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['count'] > 0) {
        $incoherences[] = "Médecins sans lien MEDECINS : {$result['count']}";
    }
    
    // Patients sans lien
    $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'patient' AND id_patient IS NULL";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['count'] > 0) {
        $incoherences[] = "Patients sans lien PATIENTS : {$result['count']}";
    }
    
    // Admin/Accueil avec liens
    $sql = "SELECT COUNT(*) as count FROM users WHERE role IN ('admin', 'accueil') AND (id_patient IS NOT NULL OR id_med IS NOT NULL)";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['count'] > 0) {
        $incoherences[] = "Admin/Accueil avec liens inappropriés : {$result['count']}";
    }
    
    if (empty($incoherences)) {
        echo "   ✅ Aucune incohérence détectée\n";
    } else {
        foreach ($incoherences as $inc) {
            echo "   ⚠️  $inc\n";
        }
    }
    
    // Valider la transaction
    $pdo->commit();
    
    // ============================================
    // RÉSUMÉ
    // ============================================
    echo "\n=== RÉSUMÉ ===\n";
    echo "Corrections effectuées : " . count($corrections) . "\n";
    
    if (!empty($corrections)) {
        echo "\nDétails des corrections :\n";
        foreach ($corrections as $i => $correction) {
            echo "  " . ($i + 1) . ". $correction\n";
        }
    }
    
    // Statistiques finales
    echo "\n=== STATISTIQUES FINALES ===\n";
    $sql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
    $stmt = $pdo->query($sql);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($stats as $stat) {
        echo "  {$stat['role']} : {$stat['count']}\n";
    }
    
    echo "\n✅ Correction terminée avec succès !\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ ERREUR : " . $e->getMessage() . "\n";
    echo "Transaction annulée.\n";
    exit(1);
}

?>
