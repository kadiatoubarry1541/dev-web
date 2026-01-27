<?php
/**
 * Test de la fonction inscription pour diagnostiquer le problème
 */

require_once 'config/traitement.php';
require_once 'config/bdd.php';

$email_test = 'cisse@gmail.com';
$nom = 'Cisse';
$prenom = 'Houlay';
$telephone = '612232323';
$password = 'test123';
$id_service = 1; // Supposons que le service 1 existe

echo "=== TEST DE LA FONCTION inscription() ===\n\n";
echo "Données de test :\n";
echo "- Email : {$email_test}\n";
echo "- Nom : {$nom}\n";
echo "- Prénom : {$prenom}\n";
echo "- Téléphone : {$telephone}\n";
echo "- Service ID : {$id_service}\n\n";

// D'abord, vérifier que le service existe
try {
    $pdo = bdd();
    $stmt = $pdo->prepare("SELECT id_service, Nom_service FROM SERVICES WHERE id_service = ?");
    $stmt->execute([$id_service]);
    $service = $stmt->fetch();
    
    if ($service) {
        echo "✅ Service trouvé : {$service['Nom_service']}\n";
        $specialisation = $service['Nom_service'];
    } else {
        echo "❌ Service non trouvé, récupération du premier service disponible...\n";
        $stmt = $pdo->query("SELECT id_service, Nom_service FROM SERVICES LIMIT 1");
        $service = $stmt->fetch();
        if ($service) {
            $id_service = $service['id_service'];
            $specialisation = $service['Nom_service'];
            echo "✅ Utilisation du service : {$specialisation} (ID: {$id_service})\n";
        } else {
            echo "❌ Aucun service disponible dans la base de données !\n";
            exit(1);
        }
    }
} catch (Exception $e) {
    echo "❌ Erreur lors de la récupération du service : " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== ÉTAPE 1 : Vérification EmailExist ===\n";
$email_exist = EmailExist($email_test, 'medecin');
echo "EmailExist retourne : " . ($email_exist ? "TRUE" : "FALSE") . "\n";

if ($email_exist) {
    echo "❌ PROBLÈME : EmailExist retourne TRUE alors que l'email n'existe pas !\n";
    exit(1);
}

echo "\n=== ÉTAPE 2 : Vérification directe dans MEDECINS ===\n";
try {
    $email_normalized = trim(strtolower($email_test));
    $stmt = $pdo->prepare("SELECT id_med FROM MEDECINS WHERE LOWER(TRIM(Email_med)) = ?");
    $stmt->execute([$email_normalized]);
    $med_result = $stmt->fetch();
    
    if ($med_result) {
        echo "❌ Email trouvé dans MEDECINS avec ID : {$med_result['id_med']}\n";
    } else {
        echo "✅ Email NON trouvé dans MEDECINS\n";
    }
} catch (Exception $e) {
    echo "⚠️ Erreur : " . $e->getMessage() . "\n";
}

echo "\n=== ÉTAPE 3 : Vérification directe dans USERS ===\n";
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(TRIM(email)) = ?");
    $stmt->execute([$email_normalized]);
    $user_result = $stmt->fetch();
    
    if ($user_result) {
        echo "❌ Email trouvé dans USERS avec ID : {$user_result['id']}\n";
    } else {
        echo "✅ Email NON trouvé dans USERS\n";
    }
} catch (Exception $e) {
    echo "⚠️ Erreur : " . $e->getMessage() . "\n";
}

echo "\n=== ÉTAPE 4 : Tentative d'inscription (SANS COMMIT) ===\n";
echo "⚠️ ATTENTION : Cette tentative va échouer mais nous allons voir où exactement\n\n";

try {
    // Démarrer une transaction pour pouvoir rollback
    $pdo->beginTransaction();
    
    echo "Transaction démarrée...\n";
    
    // Appeler la fonction inscription
    $result = inscription($nom, $prenom, $email_test, $telephone, $password, 'medecin', null, null, null, $id_service, $specialisation, null);
    
    if ($result) {
        echo "✅ Inscription réussie !\n";
        $pdo->rollBack(); // Annuler pour ne pas créer de compte de test
        echo "Transaction annulée (rollback)\n";
    } else {
        echo "❌ Inscription a retourné FALSE\n";
        $pdo->rollBack();
    }
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "\n❌ ERREUR PDO CAPTURÉE :\n";
    echo "Code : {$e->getCode()}\n";
    echo "Message : {$e->getMessage()}\n";
    echo "File : {$e->getFile()}\n";
    echo "Line : {$e->getLine()}\n";
    
    if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false || 
        strpos($e->getMessage(), 'UNIQUE constraint') !== false ||
        strpos($e->getMessage(), 'Duplicate') !== false) {
        echo "\n⚠️ Cette erreur est interprétée comme une duplication d'email !\n";
        echo "Mais nous avons vérifié que l'email n'existe pas...\n";
        echo "Il y a peut-être un problème avec la contrainte UNIQUE ou une autre colonne.\n";
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ EXCEPTION CAPTURÉE :\n";
    echo "Message : {$e->getMessage()}\n";
    echo "File : {$e->getFile()}\n";
    echo "Line : {$e->getLine()}\n";
    echo "\n⚠️ C'est probablement cette exception qui cause le message d'erreur !\n";
}

echo "\n=== FIN DU TEST ===\n";
?>
