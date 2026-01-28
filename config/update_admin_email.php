<?php
/**
 * Script pour mettre à jour l'email du compte administrateur
 * Le mot de passe reste inchangé.
 */

require_once __DIR__ . '/bdd.php';

$nouvel_email = 'deve7.wev@gmail.com';

try {
    $pdo = bdd();
    
    // Mettre à jour uniquement l'email du compte admin (le mot de passe n'est pas touché)
    $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE role = 'admin' LIMIT 1");
    $stmt->execute([$nouvel_email]);
    
    if ($stmt->rowCount() > 0) {
        echo "Email du compte admin mis à jour avec succès.\n";
        echo "Nouvel email: $nouvel_email\n";
        echo "Le mot de passe est inchangé.\n";
    } else {
        echo "Aucun compte admin trouvé (ou email déjà identique).\n";
    }
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
