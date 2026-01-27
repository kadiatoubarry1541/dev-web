<?php
/**
 * Script pour créer le compte de la gérante à l'accueil
 * À exécuter une seule fois
 */

require_once 'bdd.php';

try {
    $pdo = bdd();
    
    // D'abord, modifier l'ENUM pour ajouter le rôle 'accueil' si nécessaire
    try {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('patient', 'medecin', 'admin', 'accueil') DEFAULT 'patient'");
        echo "Rôle 'accueil' ajouté à la table users.\n";
    } catch (PDOException $e) {
        // Si l'erreur indique que la valeur existe déjà, c'est OK
        if (strpos($e->getMessage(), 'Duplicate') === false && strpos($e->getMessage(), 'already exists') === false) {
            echo "Note: " . $e->getMessage() . "\n";
        }
    }
    
    // Vérifier si le compte existe déjà
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'accueil'");
    $check->execute(['Kadiatou15.kb@gmail.com']);
    
    if ($check->rowCount() > 0) {
        echo "Le compte accueil existe déjà.\n";
        echo "Email: Kadiatou15.kb@gmail.com\n";
    } else {
        // Créer le compte de la gérante
        $password_hash = password_hash('12345@', PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (nom, email, telephone, password, role) 
                VALUES (?, ?, ?, ?, 'accueil')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['Gérante Accueil', 'Kadiatou15.kb@gmail.com', '', $password_hash]);
        
        echo "Compte gérante accueil créé avec succès !\n";
        echo "Email: Kadiatou15.kb@gmail.com\n";
        echo "Mot de passe: 12345@\n";
        echo "Rôle: accueil\n";
    }
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}

?>
