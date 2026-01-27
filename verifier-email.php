<?php
/**
 * Script de vérification d'email dans la base de données
 * Vérifie si un email existe dans les tables users, PATIENTS et MEDECINS
 */

require_once 'config/bdd.php';

// Email à vérifier
$email_a_verifier = 'cisse@gmail.com';

// Normaliser l'email (comme le fait le code)
$email_normalized = trim(strtolower($email_a_verifier));
$email_normalized = preg_replace('/\s+/', '', $email_normalized);

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Vérification Email</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        .email-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .result {
            margin: 20px 0;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid;
        }
        .found {
            background: #ffebee;
            border-color: #f44336;
        }
        .not-found {
            background: #e8f5e9;
            border-color: #4CAF50;
        }
        .table-info {
            background: #fff3e0;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .data-table th, .data-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .data-table th {
            background: #4CAF50;
            color: white;
        }
        .data-table tr:hover {
            background: #f5f5f5;
        }
        .summary {
            background: #2196F3;
            color: white;
            padding: 20px;
            border-radius: 5px;
            margin-top: 30px;
            font-size: 18px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Vérification de l'email dans la base de données</h1>
        
        <div class='email-info'>
            <strong>Email recherché :</strong> {$email_a_verifier}<br>
            <strong>Email normalisé :</strong> {$email_normalized}
        </div>";

try {
    $pdo = bdd();
    $trouve = false;
    $resultats = [];
    
    // 1. Vérifier dans la table users
    echo "<h2>1. Table USERS</h2>";
    try {
        $stmt_users = $pdo->prepare("SELECT id, nom, email, role, telephone, id_patient, id_med 
                                     FROM users 
                                     WHERE LOWER(TRIM(email)) = ? 
                                     LIMIT 1");
        $stmt_users->execute([$email_normalized]);
        $user_result = $stmt_users->fetch(PDO::FETCH_ASSOC);
        
        if ($user_result) {
            $trouve = true;
            $resultats['users'] = $user_result;
            echo "<div class='result found'>
                    <strong>❌ EMAIL TROUVÉ dans la table USERS</strong>
                    <table class='data-table'>
                        <tr><th>Champ</th><th>Valeur</th></tr>";
            foreach ($user_result as $key => $value) {
                echo "<tr><td><strong>{$key}</strong></td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
            }
            echo "</table>
                  </div>";
        } else {
            echo "<div class='result not-found'>
                    <strong>✅ Email NON trouvé dans la table USERS</strong>
                  </div>";
        }
    } catch (PDOException $e) {
        echo "<div class='result found' style='background: #ffcdd2;'>
                <strong>⚠️ Erreur lors de la vérification dans USERS :</strong> " . htmlspecialchars($e->getMessage()) . "
              </div>";
    }
    
    // 2. Vérifier dans la table PATIENTS
    echo "<h2>2. Table PATIENTS</h2>";
    try {
        $stmt_patient = $pdo->prepare("SELECT id_patient, Matricule_patient, Nom_patient, Prénom_patient, 
                                              Email_patient, Tel_patient, Date_naissance_patient, Adresse_patient
                                       FROM PATIENTS 
                                       WHERE LOWER(TRIM(Email_patient)) = ? 
                                       AND Email_patient IS NOT NULL 
                                       AND Email_patient != '' 
                                       LIMIT 1");
        $stmt_patient->execute([$email_normalized]);
        $patient_result = $stmt_patient->fetch(PDO::FETCH_ASSOC);
        
        if ($patient_result) {
            $trouve = true;
            $resultats['patients'] = $patient_result;
            
            // Vérifier si ce patient a un compte users lié
            $check_linked = $pdo->prepare("SELECT id, nom, role FROM users WHERE id_patient = ? LIMIT 1");
            $check_linked->execute([$patient_result['id_patient']]);
            $linked_user = $check_linked->fetch(PDO::FETCH_ASSOC);
            
            echo "<div class='result found'>
                    <strong>❌ EMAIL TROUVÉ dans la table PATIENTS</strong>
                    <table class='data-table'>
                        <tr><th>Champ</th><th>Valeur</th></tr>";
            foreach ($patient_result as $key => $value) {
                echo "<tr><td><strong>{$key}</strong></td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
            }
            echo "</table>";
            
            if ($linked_user) {
                echo "<div class='table-info'>
                        <strong>ℹ️ Ce patient a un compte USERS lié :</strong><br>
                        ID: {$linked_user['id']}, Nom: {$linked_user['nom']}, Role: {$linked_user['role']}
                      </div>";
            } else {
                echo "<div class='table-info'>
                        <strong>⚠️ Ce patient n'a PAS de compte USERS lié (email orphelin)</strong>
                      </div>";
            }
            echo "</div>";
        } else {
            echo "<div class='result not-found'>
                    <strong>✅ Email NON trouvé dans la table PATIENTS</strong>
                  </div>";
        }
    } catch (PDOException $e) {
        echo "<div class='result found' style='background: #ffcdd2;'>
                <strong>⚠️ Erreur lors de la vérification dans PATIENTS :</strong> " . htmlspecialchars($e->getMessage()) . "
              </div>";
    }
    
    // 3. Vérifier dans la table MEDECINS
    echo "<h2>3. Table MEDECINS</h2>";
    try {
        $stmt_med = $pdo->prepare("SELECT id_med, Matricule_med, Nom_med, Prénom_med, 
                                           Email_med, Tel_med, Spécialisation_med, statut, Photo_profil
                                    FROM MEDECINS 
                                    WHERE LOWER(TRIM(Email_med)) = ? 
                                    AND Email_med IS NOT NULL 
                                    AND Email_med != '' 
                                    LIMIT 1");
        $stmt_med->execute([$email_normalized]);
        $med_result = $stmt_med->fetch(PDO::FETCH_ASSOC);
        
        if ($med_result) {
            $trouve = true;
            $resultats['medecins'] = $med_result;
            
            // Vérifier si ce médecin a un compte users lié
            $check_linked = $pdo->prepare("SELECT id, nom, role FROM users WHERE id_med = ? LIMIT 1");
            $check_linked->execute([$med_result['id_med']]);
            $linked_user = $check_linked->fetch(PDO::FETCH_ASSOC);
            
            echo "<div class='result found'>
                    <strong>❌ EMAIL TROUVÉ dans la table MEDECINS</strong>
                    <table class='data-table'>
                        <tr><th>Champ</th><th>Valeur</th></tr>";
            foreach ($med_result as $key => $value) {
                echo "<tr><td><strong>{$key}</strong></td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
            }
            echo "</table>";
            
            if ($linked_user) {
                echo "<div class='table-info'>
                        <strong>ℹ️ Ce médecin a un compte USERS lié :</strong><br>
                        ID: {$linked_user['id']}, Nom: {$linked_user['nom']}, Role: {$linked_user['role']}
                      </div>";
            } else {
                echo "<div class='table-info'>
                        <strong>⚠️ Ce médecin n'a PAS de compte USERS lié (email orphelin)</strong>
                      </div>";
            }
            echo "</div>";
        } else {
            echo "<div class='result not-found'>
                    <strong>✅ Email NON trouvé dans la table MEDECINS</strong>
                  </div>";
        }
    } catch (PDOException $e) {
        echo "<div class='result found' style='background: #ffcdd2;'>
                <strong>⚠️ Erreur lors de la vérification dans MEDECINS :</strong> " . htmlspecialchars($e->getMessage()) . "
              </div>";
    }
    
    // Résumé final
    echo "<div class='summary'>";
    if ($trouve) {
        echo "❌ <strong>RÉSULTAT : L'EMAIL EXISTE DANS LA BASE DE DONNÉES</strong><br><br>";
        echo "L'email <strong>{$email_normalized}</strong> a été trouvé dans " . count($resultats) . " table(s) :<br>";
        foreach ($resultats as $table => $data) {
            echo "• Table <strong>" . strtoupper($table) . "</strong><br>";
        }
        echo "<br><strong>Conclusion :</strong> Le message d'erreur est CORRECT. Cet email est déjà utilisé et l'inscription doit être refusée.";
    } else {
        echo "✅ <strong>RÉSULTAT : L'EMAIL N'EXISTE PAS DANS LA BASE DE DONNÉES</strong><br><br>";
        echo "L'email <strong>{$email_normalized}</strong> n'a été trouvé dans aucune table.<br><br>";
        echo "<strong>Conclusion :</strong> Le message d'erreur est INCORRECT. Cet email devrait être disponible pour l'inscription.";
    }
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='result found' style='background: #ffcdd2;'>
            <strong>❌ ERREUR DE CONNEXION À LA BASE DE DONNÉES</strong><br>
            " . htmlspecialchars($e->getMessage()) . "
          </div>";
} catch (Exception $e) {
    echo "<div class='result found' style='background: #ffcdd2;'>
            <strong>❌ ERREUR</strong><br>
            " . htmlspecialchars($e->getMessage()) . "
          </div>";
}

echo "    </div>
</body>
</html>";
?>
