<?php
/**
 * Test de la fonction EmailExist
 */

require_once 'config/traitement.php';

$email_a_tester = 'cisse@gmail.com';

echo "=== TEST DE LA FONCTION EmailExist ===\n\n";
echo "Email testé : {$email_a_tester}\n";
echo "Email normalisé : " . trim(strtolower($email_a_tester)) . "\n\n";

// Test 1 : Sans rôle spécifié
echo "1. Test EmailExist(\$email, null) :\n";
$result1 = EmailExist($email_a_tester, null);
echo "   Résultat : " . ($result1 ? "TRUE (email existe)" : "FALSE (email n'existe pas)") . "\n\n";

// Test 2 : Avec rôle 'medecin'
echo "2. Test EmailExist(\$email, 'medecin') :\n";
$result2 = EmailExist($email_a_tester, 'medecin');
echo "   Résultat : " . ($result2 ? "TRUE (email existe)" : "FALSE (email n'existe pas)") . "\n\n";

// Test 3 : Avec rôle 'patient'
echo "3. Test EmailExist(\$email, 'patient') :\n";
$result3 = EmailExist($email_a_tester, 'patient');
echo "   Résultat : " . ($result3 ? "TRUE (email existe)" : "FALSE (email n'existe pas)") . "\n\n";

echo "=== CONCLUSION ===\n";
if ($result1 || $result2 || $result3) {
    echo "❌ PROBLÈME DÉTECTÉ : La fonction EmailExist retourne TRUE alors que l'email n'existe pas dans la base de données !\n";
    echo "C'est un BUG dans la fonction EmailExist.\n";
} else {
    echo "✅ La fonction EmailExist fonctionne correctement et retourne FALSE.\n";
    echo "Le problème doit venir d'ailleurs dans le code.\n";
}

// Vérification directe dans la base de données
echo "\n=== VÉRIFICATION DIRECTE DANS LA BASE DE DONNÉES ===\n";
try {
    require_once 'config/bdd.php';
    $pdo = bdd();
    $email_normalized = trim(strtolower($email_a_tester));
    
    // Vérifier dans users
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE LOWER(TRIM(email)) = ?");
    $stmt->execute([$email_normalized]);
    $count_users = $stmt->fetch()['count'];
    echo "Nombre d'occurrences dans USERS : {$count_users}\n";
    
    // Vérifier dans PATIENTS
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM PATIENTS WHERE LOWER(TRIM(Email_patient)) = ? AND Email_patient IS NOT NULL AND Email_patient != ''");
    $stmt->execute([$email_normalized]);
    $count_patients = $stmt->fetch()['count'];
    echo "Nombre d'occurrences dans PATIENTS : {$count_patients}\n";
    
    // Vérifier dans MEDECINS
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM MEDECINS WHERE LOWER(TRIM(Email_med)) = ? AND Email_med IS NOT NULL AND Email_med != ''");
    $stmt->execute([$email_normalized]);
    $count_medecins = $stmt->fetch()['count'];
    echo "Nombre d'occurrences dans MEDECINS : {$count_medecins}\n";
    
    $total = $count_users + $count_patients + $count_medecins;
    echo "\nTotal d'occurrences : {$total}\n";
    
    if ($total == 0) {
        echo "✅ CONFIRMATION : L'email n'existe vraiment PAS dans la base de données.\n";
    } else {
        echo "❌ ATTENTION : L'email existe dans la base de données !\n";
    }
    
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
?>
