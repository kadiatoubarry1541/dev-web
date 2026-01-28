<?php
/**
 * Export de la base de données locale vers un fichier SQL pour Render.
 * À exécuter UNIQUEMENT en local (XAMPP) : ouvre http://localhost/ProjetClinique/export_local_db.php
 */

// Ne pas exécuter si on est sur Render (sécurité)
$host = getenv('MYSQL_HOST') ?: '';
if ($host !== '' && $host !== 'localhost' && $host !== '127.0.0.1') {
    header('Content-Type: text/plain; charset=utf-8');
    die("Ce script doit être exécuté en local uniquement (XAMPP).\nMYSQL_HOST actuel : " . $host);
}

require_once __DIR__ . '/config/bdd.php';

$outFile = __DIR__ . '/export_for_render.sql';
$fp = fopen($outFile, 'w');
if (!$fp) {
    die("Impossible de créer le fichier export_for_render.sql");
}

try {
    $pdo = bdd();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    fwrite($fp, "-- Export ProjetClinique pour Render - " . date('Y-m-d H:i:s') . "\n");
    fwrite($fp, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $table = trim($table);
        if ($table === '') continue;

        // Structure
        $create = $pdo->query("SHOW CREATE TABLE `" . str_replace('`', '``', $table) . "`")->fetch(PDO::FETCH_NUM);
        if ($create) {
            fwrite($fp, "DROP TABLE IF EXISTS `" . str_replace('`', '``', $table) . "`;\n");
            fwrite($fp, $create[1] . ";\n\n");
        }

        // Données
        $rows = $pdo->query("SELECT * FROM `" . str_replace('`', '``', $table) . "`")->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 0) {
            $columns = array_keys($rows[0]);
            $colList = '`' . implode('`,`', array_map(function ($c) { return str_replace('`', '``', $c); }, $columns)) . '`';
            $batchSize = 50;
            for ($i = 0; $i < count($rows); $i += $batchSize) {
                $batch = array_slice($rows, $i, $batchSize);
                $values = [];
                foreach ($batch as $row) {
                    $v = [];
                    foreach ($columns as $col) {
                        $val = $row[$col];
                        $v[] = $val === null ? 'NULL' : $pdo->quote($val);
                    }
                    $values[] = '(' . implode(',', $v) . ')';
                    unset($v);
                }
                fwrite($fp, "INSERT INTO `" . str_replace('`', '``', $table) . "` ($colList) VALUES\n" . implode(",\n", $values) . ";\n\n");
            }
        }
    }

    fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fp);

    if (php_sapi_name() === 'cli') {
        echo "Export terminé : export_for_render.sql\n";
        exit(0);
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Export BDD</title></head><body>';
    echo '<h1>Export terminé</h1>';
    echo '<p>Le fichier <strong>export_for_render.sql</strong> a été créé à la racine du projet.</p>';
    echo '<p><strong>Prochaine étape :</strong> va sur <a href="import_on_render.php">import_on_render.php</a> sur ton site Render (après déploiement) et envoie ce fichier.</p>';
    echo '<p>Ou ajoute <code>export_for_render.sql</code> au projet, pousse sur GitHub, redéploie sur Render, puis ouvre <code>import_on_render.php?key=TA_CLE</code> sur Render.</p>';
    echo '</body></html>';

} catch (Exception $e) {
    fclose($fp);
    @unlink($outFile);
    if (php_sapi_name() === 'cli') {
        echo "Erreur : " . $e->getMessage() . "\n";
        exit(1);
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><h1>Erreur</h1><p>' . htmlspecialchars($e->getMessage()) . '</p></body></html>';
}
