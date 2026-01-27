<?php
/**
 * Script de test pour vérifier si un patient existe dans la base de données
 * Utilisez ce script pour déboguer les problèmes de recherche de patient
 */

require_once 'config/bdd.php';

// Matricule à rechercher
$matricule_recherche = 'PAT202601240004'; // Changez ceci selon vos besoins

echo "<h1>Test de recherche de patient dans la base de données</h1>";
echo "<hr>";

try {
    $pdo = bdd();
    
    // 1. Vérifier quelle base de données est utilisée
    $db_name = $pdo->query("SELECT DATABASE()")->fetchColumn();
    echo "<h2>1. Base de données utilisée</h2>";
    echo "<p><strong>Nom de la base :</strong> " . htmlspecialchars($db_name) . "</p>";
    
    // 2. Vérifier si la table PATIENTS existe
    echo "<h2>2. Vérification de la table PATIENTS</h2>";
    $table_exists = $pdo->query("SHOW TABLES LIKE 'PATIENTS'")->rowCount() > 0;
    echo "<p>Table PATIENTS existe : <strong>" . ($table_exists ? 'OUI' : 'NON') . "</strong></p>";
    
    if (!$table_exists) {
        echo "<p style='color: red;'><strong>ERREUR : La table PATIENTS n'existe pas dans la base de données '$db_name' !</strong></p>";
        exit;
    }
    
    // 3. Compter le nombre total de patients
    $total_patients = $pdo->query("SELECT COUNT(*) FROM PATIENTS")->fetchColumn();
    echo "<p>Nombre total de patients dans PATIENTS : <strong>$total_patients</strong></p>";
    
    // 4. Lister tous les patients (limité à 20)
    echo "<h2>3. Liste des patients dans la table PATIENTS</h2>";
    $patients = $pdo->query("SELECT id_patient, Matricule_patient, Nom_patient, Prénom_patient, Email_patient FROM PATIENTS LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($patients)) {
        echo "<p style='color: orange;'><strong>ATTENTION : La table PATIENTS est vide !</strong></p>";
    } else {
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>ID</th><th>Matricule</th><th>Nom</th><th>Prénom</th><th>Email</th>";
        echo "</tr>";
        
        foreach ($patients as $patient) {
            $highlight = ($patient['Matricule_patient'] === $matricule_recherche) ? "style='background: yellow;'" : "";
            echo "<tr $highlight>";
            echo "<td>" . htmlspecialchars($patient['id_patient']) . "</td>";
            echo "<td><strong>" . htmlspecialchars($patient['Matricule_patient']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($patient['Nom_patient'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($patient['Prénom_patient'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($patient['Email_patient'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 5. Recherche exacte du matricule
    echo "<h2>4. Recherche du matricule : " . htmlspecialchars($matricule_recherche) . "</h2>";
    
    // Recherche exacte
    $sql_exact = "SELECT * FROM PATIENTS WHERE Matricule_patient = ? LIMIT 1";
    $stmt_exact = $pdo->prepare($sql_exact);
    $stmt_exact->execute([$matricule_recherche]);
    $patient_exact = $stmt_exact->fetch(PDO::FETCH_ASSOC);
    
    if ($patient_exact) {
        echo "<p style='color: green;'><strong>✅ PATIENT TROUVÉ (recherche exacte)</strong></p>";
        echo "<pre>" . print_r($patient_exact, true) . "</pre>";
    } else {
        echo "<p style='color: red;'><strong>❌ PATIENT NON TROUVÉ (recherche exacte)</strong></p>";
    }
    
    // Recherche avec trim
    $matricule_trimmed = trim($matricule_recherche);
    if ($matricule_trimmed !== $matricule_recherche) {
        echo "<p><strong>Note :</strong> Le matricule avec trim est différent : '$matricule_trimmed'</p>";
        $stmt_trim = $pdo->prepare($sql_exact);
        $stmt_trim->execute([$matricule_trimmed]);
        $patient_trim = $stmt_trim->fetch(PDO::FETCH_ASSOC);
        
        if ($patient_trim) {
            echo "<p style='color: green;'><strong>✅ PATIENT TROUVÉ (avec trim)</strong></p>";
            echo "<pre>" . print_r($patient_trim, true) . "</pre>";
        }
    }
    
    // Recherche avec LIKE
    $sql_like = "SELECT * FROM PATIENTS WHERE Matricule_patient LIKE ? LIMIT 5";
    $stmt_like = $pdo->prepare($sql_like);
    $stmt_like->execute(['%' . $matricule_recherche . '%']);
    $patients_like = $stmt_like->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($patients_like)) {
        echo "<p><strong>Recherche avec LIKE (contient '$matricule_recherche') :</strong></p>";
        echo "<pre>" . print_r($patients_like, true) . "</pre>";
    }
    
    // 6. Vérifier la structure de la table
    echo "<h2>5. Structure de la table PATIENTS</h2>";
    $columns = $pdo->query("DESCRIBE PATIENTS")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th>";
    echo "</tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($col['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 7. Test de la fonction bdd() utilisée dans rendez-vous.php
    echo "<h2>6. Test de la fonction bdd()</h2>";
    $pdo_test = bdd();
    $db_test = $pdo_test->query("SELECT DATABASE()")->fetchColumn();
    echo "<p>Base de données retournée par bdd() : <strong>" . htmlspecialchars($db_test) . "</strong></p>";
    
    if ($db_test !== $db_name) {
        echo "<p style='color: red;'><strong>⚠️ ATTENTION : La base de données retournée par bdd() est différente !</strong></p>";
    }
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>ERREUR</h2>";
    echo "<p style='color: red;'><strong>" . htmlspecialchars($e->getMessage()) . "</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
} catch (Exception $e) {
    echo "<h2 style='color: red;'>ERREUR</h2>";
    echo "<p style='color: red;'><strong>" . htmlspecialchars($e->getMessage()) . "</strong></p>";
}

echo "<hr>";
echo "<p><em>Script de test terminé. Vérifiez les logs PHP pour plus de détails.</em></p>";
?>
