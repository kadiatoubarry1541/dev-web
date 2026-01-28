<?php
/**
 * Unifier les tables patient / PATIENTS : n'utiliser que PATIENTS (avec S).
 *
 * Problème : deux tables peuvent exister (patient sans S et PATIENTS avec S),
 * ce qui provoque "patient introuvable en base" car l'app lit dans PATIENTS.
 *
 * Solution : migrer les données de patient → PATIENTS si besoin, puis supprimer patient.
 * À exécuter une seule fois. Sauvegardez la BDD avant.
 *
 * Usage :
 * - Navigateur : config/unifier_table_patients.php
 * - Avec exécution : config/unifier_table_patients.php?confirmer=oui
 */
require_once __DIR__ . '/bdd.php';

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Unifier table PATIENTS</title>";
echo "<style>body{font-family:sans-serif;margin:20px;max-width:700px;} .ok{color:green;} .err{color:red;} .warn{color:orange;} pre{background:#f5f5f5;padding:10px;}</style></head><body>";
echo "<h1>Unifier patient → PATIENTS</h1>";
echo "<p>Ce script assure qu’une seule table patient existe : <strong>PATIENTS</strong> (avec S).</p>";

$confirmer = isset($_GET['confirmer']) && $_GET['confirmer'] === 'oui';
$pdo = null;

try {
    $pdo = bdd();

    // Tables à vérifier (MySQL peut retourner patient ou PATIENT selon la config)
    $noms_possibles_singulier = ['patient', 'PATIENT'];
    $table_singulier_trouvee = null;
    foreach ($noms_possibles_singulier as $nom) {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($nom));
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_NUM);
            $table_singulier_trouvee = $row[0]; // nom réel retourné par MySQL
            break;
        }
    }

    $stmt = $pdo->query("SHOW TABLES LIKE 'PATIENTS'");
    $table_patients_existe = $stmt->rowCount() > 0;

    if (!$table_patients_existe && !$table_singulier_trouvee) {
        echo "<p class='warn'>Aucune table patient ou PATIENTS trouvée. Créez la base avec sante1_database.sql ou init_database.</p>";
        echo "</body></html>";
        exit;
    }

    if ($table_singulier_trouvee && !$table_patients_existe) {
        echo "<p class='warn'>Seule la table <code>" . htmlspecialchars($table_singulier_trouvee) . "</code> existe. Le code utilise <code>PATIENTS</code>.</p>";
        echo "<p>Renommez la table en PATIENTS : <code>RENAME TABLE `" . htmlspecialchars($table_singulier_trouvee) . "` TO `PATIENTS`;</code></p>";
        if ($confirmer) {
            try {
                $pdo->exec("RENAME TABLE `" . str_replace('`', '``', $table_singulier_trouvee) . "` TO `PATIENTS`");
                echo "<p class='ok'>Table renommée en PATIENTS.</p>";
            } catch (Exception $e) {
                echo "<p class='err'>Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
        echo "</body></html>";
        exit;
    }

    if (!$table_singulier_trouvee && $table_patients_existe) {
        echo "<p class='ok'>Configuration correcte : seule la table <strong>PATIENTS</strong> existe. Rien à faire.</p>";
        echo "</body></html>";
        exit;
    }

    // Les deux existent : migrer patient → PATIENTS puis supprimer patient
    echo "<p><strong>Les deux tables existent.</strong> On garde <strong>PATIENTS</strong> et on supprime <code>" . htmlspecialchars($table_singulier_trouvee) . "</code> après migration si besoin.</p>";

    $stmt = $pdo->query("SELECT COUNT(*) as n FROM `" . str_replace('`', '``', $table_singulier_trouvee) . "`");
    $n_singulier = (int) $stmt->fetch(PDO::FETCH_ASSOC)['n'];
    $stmt = $pdo->query("SELECT COUNT(*) as n FROM `PATIENTS`");
    $n_patients = (int) $stmt->fetch(PDO::FETCH_ASSOC)['n'];

    echo "<ul><li>Enregistrements dans <code>" . htmlspecialchars($table_singulier_trouvee) . "</code> : <strong>$n_singulier</strong></li>";
    echo "<li>Enregistrements dans <code>PATIENTS</code> : <strong>$n_patients</strong></li></ul>";

    if ($n_singulier === 0) {
        echo "<p>La table singulière est vide → on peut la supprimer sans perte.</p>";
        if ($confirmer) {
            $pdo->exec("DROP TABLE IF EXISTS `" . str_replace('`', '``', $table_singulier_trouvee) . "`");
            echo "<p class='ok'>Table <code>" . htmlspecialchars($table_singulier_trouvee) . "</code> supprimée. Seule <strong>PATIENTS</strong> reste utilisée.</p>";
        } else {
            echo "<p>Pour exécuter : <a href='?confirmer=oui'>Unifier (supprimer la table en double)</a></p>";
        }
        echo "</body></html>";
        exit;
    }

    echo "<p>La table singulière contient des données → on les copie dans PATIENTS puis on supprime la table en double.</p>";
    if (!$confirmer) {
        echo "<p><a href='?confirmer=oui'>Confirmer : migrer vers PATIENTS et supprimer " . htmlspecialchars($table_singulier_trouvee) . "</a></p>";
        echo "</body></html>";
        exit;
    }

    // Colonnes de PATIENTS
    $stmt = $pdo->query("SHOW COLUMNS FROM `PATIENTS`");
    $cols_patients = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $pdo->query("SELECT * FROM `" . str_replace('`', '``', $table_singulier_trouvee) . "`");
    $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $migres = 0;
    foreach ($lignes as $row) {
        $donnee = [];
        $placeholders = [];
        foreach ($cols_patients as $c) {
            if (array_key_exists($c, $row)) {
                $donnee[$c] = $row[$c];
                $placeholders[] = '?';
            }
        }
        if (empty($donnee)) {
            continue;
        }
        $cols = implode(', ', array_map(function ($k) { return "`$k`"; }, array_keys($donnee)));
        $ph = implode(', ', $placeholders);
        $sql = "INSERT IGNORE INTO `PATIENTS` ($cols) VALUES ($ph)";
        try {
            $st = $pdo->prepare($sql);
            $st->execute(array_values($donnee));
            if ($st->rowCount() > 0) {
                $migres++;
            }
        } catch (Exception $e) {
            // ignorer conflits (doublon matricule/email)
        }
    }

    echo "<p class='ok'>$migres enregistrement(s) copié(s) vers PATIENTS.</p>";
    $pdo->exec("DROP TABLE IF EXISTS `" . str_replace('`', '``', $table_singulier_trouvee) . "`");
    echo "<p class='ok'>Table <code>" . htmlspecialchars($table_singulier_trouvee) . "</code> supprimée. Le système utilise désormais uniquement <strong>PATIENTS</strong>.</p>";

} catch (Exception $e) {
    echo "<p class='err'>Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><a href='unifier_table_patients.php'>Recharger la page</a></p>";
echo "</body></html>";
