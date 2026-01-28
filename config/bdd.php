<?php

// Protection contre les inclusions multiples
if (!defined('BDD_INCLUDED')) {
    define('BDD_INCLUDED', true);
    
    if (!function_exists('bdd')) {
        function bdd() {
            // Détection automatique : InfinityFree ou local
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $isLocal = stripos($host, 'localhost') !== false || stripos($host, '127.0.0.1') !== false || php_sapi_name() === 'cli';
            $isInfinityFree = stripos($host, 'great-site.net') !== false || stripos($host, 'infinityfree') !== false;
            
            if ($isInfinityFree && !$isLocal) {
                // Configuration InfinityFree
                $server = 'sql302.infinityfree.com';
                $dbname = 'if0_41017295_sante1';
                $port = '3306';
                $username = 'if0_41017295';
                $password = 'MallBzKQE6BiI';
            } else {
                // Configuration locale (XAMPP)
                $server = 'localhost';
                $dbname = 'santé1';
                $port = '3306';
                $username = 'root';
                $password = '';
            }
            
            try {
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
