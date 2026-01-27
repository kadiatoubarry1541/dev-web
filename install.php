<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Base de Données - MediCo.</title>
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
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #002939;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        button {
            background: #002939;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #004d66;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Installation de la Base de Données - MediCo.</h1>
        
        <?php
        $server = 'localhost';
        $dbname = 'santé1';
        $port = '3306';
        $password = "";
        $host = "root";
        
        $errors = [];
        $success = [];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
            try {
                // Se connecter d'abord sans base de données
                $pdo = new PDO("mysql:host=$server;port=$port;charset=utf8mb4", $host, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Créer la base de données si elle n'existe pas
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `$dbname`");
                
                // Lire le fichier SQL
                $sql_file = __DIR__ . '/config/sante1_database.sql';
                
                if (!file_exists($sql_file)) {
                    throw new Exception("Le fichier SQL n'existe pas : " . $sql_file);
                }
                
                $sql_content = file_get_contents($sql_file);
                
                // Supprimer les commentaires et les lignes vides
                $sql_content = preg_replace('/--.*$/m', '', $sql_content);
                $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
                
                // Supprimer la commande CREATE DATABASE et USE car on l'a déjà fait
                $sql_content = preg_replace('/CREATE DATABASE.*?;/is', '', $sql_content);
                $sql_content = preg_replace('/USE\s+.*?;/is', '', $sql_content);
                
                // Diviser en requêtes individuelles
                $queries = array_filter(array_map('trim', explode(';', $sql_content)));
                
                $executed = 0;
                $skipped = 0;
                
                foreach ($queries as $query) {
                    if (!empty($query) && strlen($query) > 10 && 
                        !preg_match('/^(CREATE DATABASE|USE)/i', trim($query))) {
                        try {
                            $pdo->exec($query);
                            $executed++;
                        } catch (PDOException $e) {
                            // Ignorer les erreurs de table déjà existante ou de base de données déjà existante
                            $error_msg = $e->getMessage();
                            if (strpos($error_msg, 'already exists') !== false || 
                                strpos($error_msg, 'Duplicate') !== false ||
                                strpos($error_msg, 'exist') !== false) {
                                $skipped++;
                            } else {
                                // Ne pas afficher toutes les erreurs pour éviter le spam
                                if (count($errors) < 5) {
                                    $errors[] = "Erreur: " . $error_msg . " (Requête: " . substr(trim($query), 0, 80) . "...)";
                                }
                            }
                        }
                    }
                }
                
                $success[] = "Installation terminée ! $executed requêtes exécutées, $skipped ignorées.";
                
                // Vérifier les tables créées
                $tables_created = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                $success[] = "Tables créées : " . count($tables_created) . " (" . implode(', ', $tables_created) . ")";
                
                // Ajouter les contraintes UNIQUE sur les emails si elles n'existent pas
                try {
                    // Vérifier et ajouter UNIQUE sur Email_patient
                    $check_index_patient = $pdo->query("SHOW INDEX FROM PATIENTS WHERE Key_name = 'idx_email_patient'");
                    if ($check_index_patient->rowCount() == 0) {
                        $pdo->exec("ALTER TABLE PATIENTS ADD UNIQUE INDEX idx_email_patient (Email_patient)");
                        $success[] = "Contrainte UNIQUE ajoutée sur Email_patient dans PATIENTS";
                    }
                } catch (PDOException $e) {
                    // Ignorer si l'index existe déjà
                }
                
                try {
                    // Vérifier et ajouter UNIQUE sur Email_med
                    $check_index_med = $pdo->query("SHOW INDEX FROM MEDECINS WHERE Key_name = 'idx_email_med'");
                    if ($check_index_med->rowCount() == 0) {
                        $pdo->exec("ALTER TABLE MEDECINS ADD UNIQUE INDEX idx_email_med (Email_med)");
                        $success[] = "Contrainte UNIQUE ajoutée sur Email_med dans MEDECINS";
                    }
                } catch (PDOException $e) {
                    // Ignorer si l'index existe déjà
                }
                
                // Ajouter les colonnes photo_profil si elles n'existent pas
                try {
                    $pdo->exec("ALTER TABLE PATIENTS ADD COLUMN Photo_profil VARCHAR(255) NULL");
                    $success[] = "Colonne Photo_profil ajoutée dans PATIENTS";
                } catch (PDOException $e) {
                    // Ignorer si la colonne existe déjà
                }
                
                try {
                    $pdo->exec("ALTER TABLE MEDECINS ADD COLUMN Photo_profil VARCHAR(255) NULL");
                    $success[] = "Colonne Photo_profil ajoutée dans MEDECINS";
                } catch (PDOException $e) {
                    // Ignorer si la colonne existe déjà
                }
                
                try {
                    $pdo->exec("ALTER TABLE users ADD COLUMN photo_profil VARCHAR(255) NULL");
                    $success[] = "Colonne photo_profil ajoutée dans users";
                } catch (PDOException $e) {
                    // Ignorer si la colonne existe déjà
                }
                
                // Ajouter la colonne statut dans MEDECINS si elle n'existe pas
                try {
                    $pdo->exec("ALTER TABLE MEDECINS ADD COLUMN statut ENUM('en_attente', 'approuvé', 'refusé') DEFAULT 'en_attente'");
                    $success[] = "Colonne statut ajoutée dans MEDECINS";
                } catch (PDOException $e) {
                    // Ignorer si la colonne existe déjà
                }
                
                // Créer le dossier uploads/profiles s'il n'existe pas
                $uploads_dir = __DIR__ . '/uploads/profiles/';
                if (!file_exists($uploads_dir)) {
                    mkdir($uploads_dir, 0755, true);
                    $success[] = "Dossier uploads/profiles créé";
                }
                
                // Créer le compte administrateur
                try {
                    $check_admin = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'admin'");
                    $check_admin->execute(['kadiatou1541.kb@gmail.com']);
                    
                    if ($check_admin->rowCount() == 0) {
                        $password_hash = password_hash('12345@', PASSWORD_DEFAULT);
                        $sql_admin = "INSERT INTO users (nom, email, telephone, password, role) 
                                     VALUES (?, ?, ?, ?, 'admin')";
                        $stmt_admin = $pdo->prepare($sql_admin);
                        $stmt_admin->execute(['Administrateur', 'kadiatou1541.kb@gmail.com', '', $password_hash]);
                        $success[] = "Compte administrateur créé : kadiatou1541.kb@gmail.com / 12345@";
                    } else {
                        $success[] = "Compte administrateur existe déjà.";
                    }
                } catch (PDOException $e) {
                    // Ignorer si la table users n'existe pas encore
                }
                
            } catch (Exception $e) {
                $errors[] = "Erreur : " . $e->getMessage();
            }
        }
        
        // Vérifier l'état actuel
        try {
            // Se connecter sans base de données d'abord
            $pdo_check = new PDO("mysql:host=$server;port=$port;charset=utf8mb4", $host, $password);
            $pdo_check->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Vérifier si la base existe
            $db_exists = $pdo_check->query("SHOW DATABASES LIKE '$dbname'")->rowCount() > 0;
            
            if ($db_exists) {
                $pdo_check->exec("USE `$dbname`");
                $tables = $pdo_check->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $tables = [];
            }
            
            if (empty($tables)) {
                echo '<div class="info">';
                echo '<strong>État :</strong> Aucune table trouvée dans la base de données "santé1".';
                echo '<br><strong>Action requise :</strong> Cliquez sur le bouton ci-dessous pour créer toutes les tables.';
                echo '</div>';
            } else {
                echo '<div class="success">';
                echo '<strong>État :</strong> ' . count($tables) . ' table(s) trouvée(s) dans la base de données.';
                echo '<br><strong>Tables :</strong> ' . implode(', ', $tables);
                echo '</div>';
            }
        } catch (PDOException $e) {
            echo '<div class="error">';
            echo '<strong>Erreur de connexion :</strong> ' . $e->getMessage();
            echo '<br>Vérifiez votre configuration dans config/bdd.php';
            echo '</div>';
        }
        
        // Afficher les messages
        if (!empty($success)) {
            foreach ($success as $msg) {
                echo '<div class="success">' . htmlspecialchars($msg) . '</div>';
            }
        }
        
        if (!empty($errors)) {
            foreach ($errors as $msg) {
                echo '<div class="error">' . htmlspecialchars($msg) . '</div>';
            }
        }
        ?>
        
        <form method="post" style="margin-top: 30px;">
            <button type="submit" name="install" value="1">Installer / Réinitialiser la Base de Données</button>
        </form>
        
        <div class="info" style="margin-top: 30px;">
            <strong>Instructions :</strong>
            <ol>
                <li>Assurez-vous que MySQL est démarré</li>
                <li>Vérifiez que la configuration dans <code>config/bdd.php</code> est correcte</li>
                <li>Cliquez sur le bouton ci-dessus pour créer toutes les tables</li>
                <li>Une fois terminé, vous pouvez supprimer ce fichier (install.php) pour des raisons de sécurité</li>
            </ol>
        </div>
    </div>
</body>
</html>
