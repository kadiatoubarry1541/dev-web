<?php
/**
 * Script de Migration des Patients
 * 
 * Ce script migre tous les comptes patients de la table users vers la table PATIENTS
 * et crée les liens appropriés.
 * 
 * PROBLÈME RÉSOLU :
 * - Les patients sont dans users mais pas dans PATIENTS
 * - La table PATIENTS est vide
 * - Les rendez-vous échouent car le patient n'existe pas dans PATIENTS
 * 
 * SOLUTION :
 * - Migrer tous les patients de users vers PATIENTS
 * - Générer les matricules automatiquement
 * - Créer les liens id_patient dans users
 */

require_once 'bdd.php';
require_once 'traitement.php';

echo "=== MIGRATION DES PATIENTS DE users VERS PATIENTS ===\n\n";

try {
    $pdo = bdd();
    $pdo->beginTransaction();
    
    $migrations = [];
    $erreurs = [];
    $deja_migres = [];
    
    // ============================================
    // ÉTAPE 1 : Récupérer tous les patients de users
    // ============================================
    echo "1. Récupération des patients depuis users...\n";
    
    $sql_patients = "SELECT id, nom, email, telephone, photo_profil, id_patient, role 
                     FROM users 
                     WHERE role = 'patient' 
                     ORDER BY id";
    $stmt = $pdo->query($sql_patients);
    $patients_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $nombre_patients = count($patients_users);
    echo "   ✅ {$nombre_patients} patient(s) trouvé(s) dans users\n\n";
    
    if ($nombre_patients == 0) {
        echo "   ℹ️  Aucun patient à migrer.\n";
        $pdo->commit();
        echo "\n✅ Migration terminée (rien à faire).\n";
        exit(0);
    }
    
    // ============================================
    // ÉTAPE 2 : Migrer chaque patient
    // ============================================
    echo "2. Migration des patients vers PATIENTS...\n\n";
    
    foreach ($patients_users as $index => $patient_user) {
        $numero = $index + 1;
        echo "   [{$numero}/{$nombre_patients}] Patient : {$patient_user['email']}\n";
        
        try {
            // Vérifier si le patient existe déjà dans PATIENTS
            $patient_existant = null;
            
            // Vérifier par id_patient si le lien existe
            if (!empty($patient_user['id_patient'])) {
                $sql_check_id = "SELECT * FROM PATIENTS WHERE id_patient = ?";
                $stmt_check = $pdo->prepare($sql_check_id);
                $stmt_check->execute([$patient_user['id_patient']]);
                $patient_existant = $stmt_check->fetch(PDO::FETCH_ASSOC);
            }
            
            // Si pas trouvé par id, vérifier par email
            if (!$patient_existant) {
                $sql_check_email = "SELECT * FROM PATIENTS WHERE Email_patient = ?";
                $stmt_check = $pdo->prepare($sql_check_email);
                $stmt_check->execute([$patient_user['email']]);
                $patient_existant = $stmt_check->fetch(PDO::FETCH_ASSOC);
            }
            
            if ($patient_existant) {
                // Le patient existe déjà dans PATIENTS
                echo "      ℹ️  Patient existe déjà dans PATIENTS (ID: {$patient_existant['id_patient']})\n";
                
                // Vérifier et mettre à jour le lien dans users si nécessaire
                if (empty($patient_user['id_patient']) || $patient_user['id_patient'] != $patient_existant['id_patient']) {
                    $sql_update_link = "UPDATE users SET id_patient = ? WHERE id = ?";
                    $stmt_update = $pdo->prepare($sql_update_link);
                    $stmt_update->execute([$patient_existant['id_patient'], $patient_user['id']]);
                    echo "      ✅ Lien restauré dans users (id_patient = {$patient_existant['id_patient']})\n";
                    $migrations[] = "Patient {$patient_user['email']} : Lien restauré";
                } else {
                    echo "      ✅ Lien déjà correct\n";
                    $deja_migres[] = "Patient {$patient_user['email']} : Déjà migré";
                }
                
                // Mettre à jour les informations si nécessaire
                $update_needed = false;
                $updates = [];
                
                // Vérifier le nom
                $nom_parts = explode(' ', $patient_user['nom'], 2);
                $nom = $nom_parts[0] ?? $patient_user['nom'];
                $prenom = $nom_parts[1] ?? '';
                
                if ($patient_existant['Nom_patient'] != $nom || $patient_existant['Prénom_patient'] != $prenom) {
                    $update_needed = true;
                    $updates[] = "Nom/Prénom";
                }
                
                // Vérifier le téléphone
                if ($patient_existant['Tel_patient'] != $patient_user['telephone']) {
                    $update_needed = true;
                    $updates[] = "Téléphone";
                }
                
                // Vérifier la photo
                if ($patient_existant['Photo_profil'] != $patient_user['photo_profil']) {
                    $update_needed = true;
                    $updates[] = "Photo";
                }
                
                if ($update_needed) {
                    $sql_update_patient = "UPDATE PATIENTS SET 
                                           Nom_patient = ?, 
                                           Prénom_patient = ?, 
                                           Tel_patient = ?, 
                                           Email_patient = ?,
                                           Photo_profil = ?
                                           WHERE id_patient = ?";
                    $stmt_update_patient = $pdo->prepare($sql_update_patient);
                    $stmt_update_patient->execute([
                        $nom, 
                        $prenom, 
                        $patient_user['telephone'], 
                        $patient_user['email'],
                        $patient_user['photo_profil'],
                        $patient_existant['id_patient']
                    ]);
                    echo "      ✅ Informations mises à jour : " . implode(', ', $updates) . "\n";
                    $migrations[] = "Patient {$patient_user['email']} : Informations mises à jour";
                }
                
            } else {
                // Le patient n'existe pas dans PATIENTS, le créer
                echo "      🔄 Création du patient dans PATIENTS...\n";
                
                // Séparer le nom et prénom
                $nom_parts = explode(' ', $patient_user['nom'], 2);
                $nom = $nom_parts[0] ?? $patient_user['nom'];
                $prenom = $nom_parts[1] ?? '';
                
                // Générer un matricule
                $matricule = genererMatriculePatient();
                
                // Vérifier que le matricule n'existe pas déjà
                $sql_check_mat = "SELECT id_patient FROM PATIENTS WHERE Matricule_patient = ?";
                $stmt_check_mat = $pdo->prepare($sql_check_mat);
                $stmt_check_mat->execute([$matricule]);
                $tentatives = 0;
                while ($stmt_check_mat->rowCount() > 0 && $tentatives < 10) {
                    $matricule = genererMatriculePatient();
                    $stmt_check_mat->execute([$matricule]);
                    $tentatives++;
                }
                
                // Insérer le patient dans PATIENTS
                $sql_insert_patient = "INSERT INTO PATIENTS (
                    Matricule_patient, 
                    Nom_patient, 
                    Prénom_patient, 
                    Tel_patient, 
                    Email_patient, 
                    Date_naissance_patient, 
                    Photo_profil
                ) VALUES (?, ?, ?, ?, ?, '1900-01-01', ?)";
                
                $stmt_insert = $pdo->prepare($sql_insert_patient);
                $stmt_insert->execute([
                    $matricule,
                    $nom,
                    $prenom,
                    $patient_user['telephone'],
                    $patient_user['email'],
                    $patient_user['photo_profil']
                ]);
                
                $id_patient = $pdo->lastInsertId();
                
                // Mettre à jour users avec le lien id_patient
                $sql_update_user = "UPDATE users SET id_patient = ?, id_med = NULL WHERE id = ?";
                $stmt_update_user = $pdo->prepare($sql_update_user);
                $stmt_update_user->execute([$id_patient, $patient_user['id']]);
                
                echo "      ✅ Patient créé dans PATIENTS (ID: {$id_patient}, Matricule: {$matricule})\n";
                echo "      ✅ Lien créé dans users (id_patient = {$id_patient})\n";
                
                $migrations[] = "Patient {$patient_user['email']} : Migré vers PATIENTS (Matricule: {$matricule})";
            }
            
        } catch (Exception $e) {
            echo "      ❌ ERREUR : " . $e->getMessage() . "\n";
            $erreurs[] = "Patient {$patient_user['email']} : " . $e->getMessage();
        }
        
        echo "\n";
    }
    
    // ============================================
    // ÉTAPE 3 : Vérifier les patients orphelins dans PATIENTS
    // ============================================
    echo "3. Vérification des patients orphelins dans PATIENTS...\n";
    
    $sql_orphelins = "SELECT p.* FROM PATIENTS p 
                      LEFT JOIN users u ON p.id_patient = u.id_patient 
                      WHERE u.id IS NULL";
    $stmt = $pdo->query($sql_orphelins);
    $patients_orphelins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($patients_orphelins) > 0) {
        echo "   ⚠️  " . count($patients_orphelins) . " patient(s) orphelin(s) trouvé(s)\n";
        
        foreach ($patients_orphelins as $orphelin) {
            echo "   - Patient orphelin : {$orphelin['Email_patient']} (ID: {$orphelin['id_patient']})\n";
            
            // Vérifier si un compte users existe avec cet email
            $sql_check_user = "SELECT id FROM users WHERE email = ?";
            $stmt_check = $pdo->prepare($sql_check_user);
            $stmt_check->execute([$orphelin['Email_patient']]);
            $user_existant = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if ($user_existant) {
                // Lier le compte existant
                $sql_link = "UPDATE users SET role = 'patient', id_patient = ?, id_med = NULL WHERE id = ?";
                $stmt_link = $pdo->prepare($sql_link);
                $stmt_link->execute([$orphelin['id_patient'], $user_existant['id']]);
                echo "      ✅ Compte users lié au patient\n";
                $migrations[] = "Patient orphelin {$orphelin['Email_patient']} : Compte users lié";
            } else {
                // Créer un compte users pour ce patient
                $nom_complet = trim($orphelin['Nom_patient'] . ' ' . $orphelin['Prénom_patient']);
                $password_hash = password_hash('temp123456', PASSWORD_DEFAULT);
                
                $sql_create_user = "INSERT INTO users (nom, email, telephone, password, photo_profil, role, id_patient, id_med) 
                                    VALUES (?, ?, ?, ?, ?, 'patient', ?, NULL)";
                $stmt_create = $pdo->prepare($sql_create_user);
                $stmt_create->execute([
                    $nom_complet,
                    $orphelin['Email_patient'],
                    $orphelin['Tel_patient'],
                    $password_hash,
                    $orphelin['Photo_profil'],
                    $orphelin['id_patient']
                ]);
                
                echo "      ✅ Compte users créé (mot de passe temporaire: temp123456)\n";
                $migrations[] = "Patient orphelin {$orphelin['Email_patient']} : Compte users créé";
            }
        }
    } else {
        echo "   ✅ Aucun patient orphelin\n";
    }
    
    // ============================================
    // ÉTAPE 4 : Validation finale
    // ============================================
    echo "\n4. Validation finale...\n";
    
    // Compter les patients dans users
    $sql_count_users = "SELECT COUNT(*) as count FROM users WHERE role = 'patient'";
    $stmt = $pdo->query($sql_count_users);
    $count_users = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Compter les patients dans PATIENTS
    $sql_count_patients = "SELECT COUNT(*) as count FROM PATIENTS";
    $stmt = $pdo->query($sql_count_patients);
    $count_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Compter les patients avec liens corrects
    $sql_count_linked = "SELECT COUNT(*) as count FROM users u 
                         INNER JOIN PATIENTS p ON u.id_patient = p.id_patient 
                         WHERE u.role = 'patient'";
    $stmt = $pdo->query($sql_count_linked);
    $count_linked = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Patients sans lien
    $sql_count_unlinked = "SELECT COUNT(*) as count FROM users WHERE role = 'patient' AND id_patient IS NULL";
    $stmt = $pdo->query($sql_count_unlinked);
    $count_unlinked = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "   📊 Statistiques :\n";
    echo "      - Patients dans users : {$count_users}\n";
    echo "      - Patients dans PATIENTS : {$count_patients}\n";
    echo "      - Patients avec liens corrects : {$count_linked}\n";
    echo "      - Patients sans lien : {$count_unlinked}\n";
    
    if ($count_unlinked > 0) {
        echo "   ⚠️  ATTENTION : {$count_unlinked} patient(s) sans lien vers PATIENTS\n";
    } else {
        echo "   ✅ Tous les patients ont un lien vers PATIENTS\n";
    }
    
    // Valider la transaction
    $pdo->commit();
    
    // ============================================
    // RÉSUMÉ
    // ============================================
    echo "\n=== RÉSUMÉ DE LA MIGRATION ===\n";
    echo "Migrations effectuées : " . count($migrations) . "\n";
    echo "Déjà migrés : " . count($deja_migres) . "\n";
    echo "Erreurs : " . count($erreurs) . "\n\n";
    
    if (!empty($migrations)) {
        echo "Détails des migrations :\n";
        foreach ($migrations as $i => $migration) {
            echo "  " . ($i + 1) . ". $migration\n";
        }
    }
    
    if (!empty($erreurs)) {
        echo "\nErreurs rencontrées :\n";
        foreach ($erreurs as $i => $erreur) {
            echo "  " . ($i + 1) . ". $erreur\n";
        }
    }
    
    echo "\n✅ Migration terminée avec succès !\n";
    echo "\n⚠️  IMPORTANT :\n";
    echo "   - Vérifiez que tous les patients ont bien été migrés\n";
    echo "   - Les comptes créés automatiquement ont le mot de passe : temp123456\n";
    echo "   - Changez ces mots de passe immédiatement !\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ ERREUR CRITIQUE : " . $e->getMessage() . "\n";
    echo "Transaction annulée. Aucune modification n'a été effectuée.\n";
    exit(1);
}

?>
