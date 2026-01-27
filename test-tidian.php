<?php
/**
 * Script de test pour vérifier si le docteur Tidian existe dans la base de données
 */
require_once 'config/database_functions.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Test - Recherche Docteur Tidian</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #002939; }
        .section { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #002939; color: white; }
        tr:hover { background: #f5f5f5; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: #0c5460; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Test de Recherche - Docteur Tidian</h1>
        
        <?php
        try {
            $pdo = bdd();
            
            // Test 1: Vérifier la connexion
            echo '<div class="section">';
            echo '<h2>1. Test de connexion à la base de données</h2>';
            echo '<p class="success">✓ Connexion réussie à la base de données</p>';
            echo '</div>';
            
            // Test 2: Vérifier si la table MEDECINS existe
            echo '<div class="section">';
            echo '<h2>2. Vérification de la table MEDECINS</h2>';
            $stmt = $pdo->query("SHOW TABLES LIKE 'MEDECINS'");
            if ($stmt->rowCount() > 0) {
                echo '<p class="success">✓ La table MEDECINS existe</p>';
            } else {
                echo '<p class="error">✗ La table MEDECINS n\'existe pas</p>';
            }
            echo '</div>';
            
            // Test 3: Compter tous les médecins
            echo '<div class="section">';
            echo '<h2>3. Nombre total de médecins dans la base de données</h2>';
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM MEDECINS");
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            echo '<p class="info">Total de médecins: <strong>' . $count['total'] . '</strong></p>';
            echo '</div>';
            
            // Test 4: Lister tous les médecins
            echo '<div class="section">';
            echo '<h2>4. Liste de TOUS les médecins (tous statuts)</h2>';
            $stmt = $pdo->query("SELECT * FROM MEDECINS ORDER BY Nom_med, Prénom_med");
            $all_medecins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($all_medecins)) {
                echo '<p class="error">✗ Aucun médecin trouvé dans la base de données</p>';
            } else {
                echo '<table>';
                echo '<tr><th>ID</th><th>Matricule</th><th>Nom</th><th>Prénom</th><th>Spécialisation</th><th>Email</th><th>Téléphone</th><th>Statut</th></tr>';
                foreach ($all_medecins as $med) {
                    $highlight = (stripos($med['Nom_med'], 'Tidian') !== false || stripos($med['Prénom_med'], 'Tidian') !== false) ? 'style="background: #fff3cd;"' : '';
                    echo '<tr ' . $highlight . '>';
                    echo '<td>' . htmlspecialchars($med['id_med']) . '</td>';
                    echo '<td>' . htmlspecialchars($med['Matricule_med'] ?? 'N/A') . '</td>';
                    echo '<td><strong>' . htmlspecialchars($med['Nom_med']) . '</strong></td>';
                    echo '<td><strong>' . htmlspecialchars($med['Prénom_med']) . '</strong></td>';
                    echo '<td>' . htmlspecialchars($med['Spécialisation_med'] ?? 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($med['Email_med'] ?? 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($med['Tel_med'] ?? 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($med['statut'] ?? 'N/A') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
            echo '</div>';
            
            // Test 5: Recherche spécifique de Tidian
            echo '<div class="section">';
            echo '<h2>5. Recherche spécifique du docteur "Tidian"</h2>';
            $stmt = $pdo->prepare("SELECT * FROM MEDECINS WHERE Nom_med LIKE ? OR Prénom_med LIKE ? OR Nom_med LIKE ? OR Prénom_med LIKE ?");
            $search_term = '%Tidian%';
            $stmt->execute([$search_term, $search_term, '%tidian%', '%tidian%']);
            $tidian_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($tidian_results)) {
                echo '<p class="error">✗ Aucun médecin nommé "Tidian" trouvé dans la base de données</p>';
                echo '<p class="info">Vérifiez l\'orthographe du nom ou assurez-vous que le médecin a bien été enregistré.</p>';
            } else {
                echo '<p class="success">✓ ' . count($tidian_results) . ' médecin(s) nommé(s) "Tidian" trouvé(s):</p>';
                echo '<table>';
                echo '<tr><th>ID</th><th>Matricule</th><th>Nom</th><th>Prénom</th><th>Spécialisation</th><th>Email</th><th>Téléphone</th><th>Statut</th></tr>';
                foreach ($tidian_results as $med) {
                    echo '<tr style="background: #d4edda;">';
                    echo '<td>' . htmlspecialchars($med['id_med']) . '</td>';
                    echo '<td>' . htmlspecialchars($med['Matricule_med'] ?? 'N/A') . '</td>';
                    echo '<td><strong>' . htmlspecialchars($med['Nom_med']) . '</strong></td>';
                    echo '<td><strong>' . htmlspecialchars($med['Prénom_med']) . '</strong></td>';
                    echo '<td>' . htmlspecialchars($med['Spécialisation_med'] ?? 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($med['Email_med'] ?? 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($med['Tel_med'] ?? 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($med['statut'] ?? 'N/A') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
            echo '</div>';
            
            // Test 6: Vérifier la fonction getAllMedecins()
            echo '<div class="section">';
            echo '<h2>6. Test de la fonction getAllMedecins()</h2>';
            $medecins_func = getAllMedecins();
            echo '<p class="info">La fonction getAllMedecins() retourne <strong>' . count($medecins_func) . '</strong> médecin(s)</p>';
            if (!empty($medecins_func)) {
                echo '<p>Médecins retournés par la fonction:</p>';
                echo '<ul>';
                foreach ($medecins_func as $med) {
                    $tidian_marker = (stripos($med['Nom_med'], 'Tidian') !== false || stripos($med['Prénom_med'], 'Tidian') !== false) ? ' <strong style="color: green;">[TIDIAN TROUVÉ]</strong>' : '';
                    echo '<li>' . htmlspecialchars($med['Nom_med'] . ' ' . $med['Prénom_med']) . $tidian_marker . '</li>';
                }
                echo '</ul>';
            }
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="section">';
            echo '<h2 style="color: red;">Erreur</h2>';
            echo '<p class="error">' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        ?>
        
        <div class="section">
            <h2>Actions</h2>
            <p><a href="docteurs.php">← Retour à la page Docteurs</a></p>
            <p><a href="admin/approuver-medecins.php">Voir les médecins en attente d'approbation</a></p>
        </div>
    </div>
</body>
</html>
