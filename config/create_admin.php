<?php
/**
 * Script pour créer le compte administrateur
 * À exécuter une seule fois
 */

require_once 'bdd.php';

try {
    $pdo = bdd();
    
    // Vérifier si l'admin existe déjà
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'admin'");
    $check->execute(['kadiatou1541.kb@gmail.com']);
    
    if ($check->rowCount() > 0) {
        echo "Le compte admin existe déjà.\n";
    } else {
        // Créer le compte admin
        $password_hash = password_hash('12345@', PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (nom, email, telephone, password, role) 
                VALUES (?, ?, ?, ?, 'admin')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['Administrateur', 'kadiatou1541.kb@gmail.com', '', $password_hash]);
        
        echo "Compte administrateur créé avec succès !\n";
        echo "Email: kadiatou1541.kb@gmail.com\n";
        echo "Mot de passe: 12345@\n";
    }
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}

?>
