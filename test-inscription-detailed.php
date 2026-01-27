<?php
/**
 * Test détaillé pour voir l'erreur PDO exacte
 */

require_once 'config/bdd.php';

$email_test = 'cisse@gmail.com';
$nom = 'Cisse';
$prenom = 'Houlay';
$telephone = '612232323';
$password = 'test123';
$password_hash = password_hash($password, PASSWORD_DEFAULT);
$id_service = 1;

echo "=== TEST DÉTAILLÉ DE L'INSERTION ===\n\n";

try {
    $pdo = bdd();
    
    // Récupérer le service
    $stmt = $pdo->prepare("SELECT id_service, Nom_service FROM SERVICES WHERE id_service = ?");
    $stmt->execute([$id_service]);
    $service = $stmt->fetch();
    $specialisation = $service['Nom_service'];
    
    echo "Service : {$specialisation}\n";
    echo "Email normalisé : " . trim(strtolower($email_test)) . "\n\n";
    
    $pdo->beginTransaction();
    
    echo "=== ÉTAPE 1 : Vérification dans MEDECINS ===\n";
    $check_med_email = $pdo->prepare("SELECT id_med FROM MEDECINS WHERE LOWER(TRIM(Email_med)) = ?");
    $check_med_email->execute([trim(strtolower($email_test))]);
    if ($check_med_email->fetch()) {
        echo "❌ Email trouvé dans MEDECINS\n";
        $pdo->rollBack();
        exit(1);
    }
    echo "✅ Email non trouvé dans MEDECINS\n\n";
    
    echo "=== ÉTAPE 2 : Insertion dans MEDECINS ===\n";
    $sql_medecin = "INSERT INTO MEDECINS (Matricule_med, Nom_med, Prénom_med, Spécialisation_med, Tel_med, Email_med, Photo_profil, statut) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente')";
    $stmt_medecin = $pdo->prepare($sql_medecin);
    
    try {
        $result_medecin = $stmt_medecin->execute([null, $nom, $prenom, $specialisation, $telephone, trim(strtolower($email_test)), null]);
        echo "✅ Insertion dans MEDECINS réussie\n";
        $id_med = $pdo->lastInsertId();
        echo "ID médecin créé : {$id_med}\n\n";
    } catch (PDOException $e) {
        echo "❌ ERREUR lors de l'insertion dans MEDECINS :\n";
        echo "Code : {$e->getCode()}\n";
        echo "Message : {$e->getMessage()}\n";
        echo "SQLState : {$e->errorInfo[0]}\n";
        echo "Error Code : {$e->errorInfo[1]}\n";
        echo "Error Message : {$e->errorInfo[2]}\n";
        $pdo->rollBack();
        exit(1);
    }
    
    echo "=== ÉTAPE 3 : Vérification dans USERS ===\n";
    $check_user_email = $pdo->prepare("SELECT id FROM users WHERE LOWER(TRIM(email)) = ?");
    $check_user_email->execute([trim(strtolower($email_test))]);
    if ($check_user_email->fetch()) {
        echo "❌ Email trouvé dans USERS\n";
        $pdo->rollBack();
        exit(1);
    }
    echo "✅ Email non trouvé dans USERS\n\n";
    
    echo "=== ÉTAPE 4 : Insertion dans USERS ===\n";
    $nom_complet = trim($nom . ' ' . $prenom);
    $sql_user = "INSERT INTO users (nom, email, telephone, password, photo_profil, role, id_patient, id_med) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_user = $pdo->prepare($sql_user);
    
    try {
        $result_user = $stmt_user->execute([$nom_complet, trim(strtolower($email_test)), $telephone, $password_hash, null, 'medecin', null, $id_med]);
        echo "✅ Insertion dans USERS réussie\n";
        $id_user = $pdo->lastInsertId();
        echo "ID utilisateur créé : {$id_user}\n\n";
    } catch (PDOException $e) {
        echo "❌ ERREUR lors de l'insertion dans USERS :\n";
        echo "Code : {$e->getCode()}\n";
        echo "Message : {$e->getMessage()}\n";
        echo "SQLState : {$e->errorInfo[0]}\n";
        echo "Error Code : {$e->errorInfo[1]}\n";
        echo "Error Message : {$e->errorInfo[2]}\n";
        
        // Vérifier si c'est vraiment une duplication
        if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false || 
            strpos($e->getMessage(), 'UNIQUE constraint') !== false ||
            strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "\n⚠️ Cette erreur est interprétée comme une duplication d'email\n";
            echo "Mais nous avons vérifié que l'email n'existe pas !\n";
            echo "Il y a peut-être un problème avec une autre contrainte UNIQUE.\n";
        }
        
        $pdo->rollBack();
        exit(1);
    }
    
    echo "=== SUCCÈS : Toutes les insertions ont réussi ===\n";
    echo "Rollback pour ne pas créer de compte de test...\n";
    $pdo->rollBack();
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ EXCEPTION GÉNÉRALE :\n";
    echo "Message : {$e->getMessage()}\n";
    echo "File : {$e->getFile()}\n";
    echo "Line : {$e->getLine()}\n";
}
?>
