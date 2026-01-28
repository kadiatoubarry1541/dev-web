<?php

// Protection contre les inclusions multiples
if (!defined('BDD_INCLUDED')) {
    define('BDD_INCLUDED', true);
    
    if (!function_exists('bdd')) {
        function bdd() {
            // Détection automatique si on est sur InfinityFree
            $isInfinityFree = strpos($_SERVER['HTTP_HOST'] ?? '', 'infinityfreeapp.com') !== false || 
                             strpos($_SERVER['HTTP_HOST'] ?? '', 'great-site.net') !== false ||
                             strpos($_SERVER['HTTP_HOST'] ?? '', 'infinityfree.net') !== false;
            
            if ($isInfinityFree) {
                // Configuration InfinityFree
                $server = 'sql302.infinityfree.com';
                $dbname = 'if0_41017295_sante1';
                $port = '3306';
                $username = 'if0_41017295';
                $password = 'MallBzKQE6BiI'; // Mot de passe MySQL InfinityFree
            } else {
                // Variables d'environnement (Render, etc.) ou valeurs par défaut (local XAMPP)
                $server = getenv('MYSQL_HOST') ?: 'localhost';
                $dbname = getenv('MYSQL_DATABASE') ?: 'sante1'; // Utiliser 'sante1' sans accent pour InfinityFree
                $port = getenv('MYSQL_PORT') ?: '3306';
                $username = getenv('MYSQL_USER') ?: 'root';
                $password = getenv('MYSQL_PASSWORD') ?: '';
            }
            
            try {
                // D'abord se connecter sans spécifier la base de données
                $pdo_temp = new PDO("mysql:host=$server;port=$port;charset=utf8mb4", $username, $password);
                $pdo_temp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Vérifier si la base de données existe, sinon la créer
                // Utiliser des backticks pour sécuriser le nom de la base de données
                $dbname_escaped = '`' . str_replace('`', '``', $dbname) . '`';
                $dbname_quoted = $pdo_temp->quote($dbname);
                $check_db = $pdo_temp->query("SHOW DATABASES LIKE $dbname_quoted");
                if ($check_db->rowCount() == 0) {
                    $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS $dbname_escaped CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                }
                
                // Maintenant se connecter à la base de données
                $pdo = new PDO("mysql:host=$server;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                return $pdo;
            } catch (PDOException $e) {
                // Lancer une exception au lieu de die() pour permettre une meilleure gestion des erreurs
                throw new PDOException('La connexion à la base de données a échoué : ' . $e->getMessage() . '. Vérifiez que MySQL est démarré et que les identifiants dans config/bdd.php sont corrects.', 0, $e);
            }
        }
    }
}

?>
