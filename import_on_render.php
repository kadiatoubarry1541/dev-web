<?php
/**
 * Import de la base de données sur Render.
 * À utiliser UNE SEULE FOIS après déploiement : uploadez export_for_render.sql
 *
 * Sécurité : ajoutez dans Render > Environment : RENDER_IMPORT_KEY = une phrase secrète
 * Puis ouvrez : https://votre-app.onrender.com/import_on_render.php?key=VOTRE_PHRASE
 */

$lockFile = __DIR__ . '/.import_done';
$expectedKey = getenv('RENDER_IMPORT_KEY') ?: 'ProjetCliniqueImport2024';

$key = $_GET['key'] ?? '';
if ($key !== $expectedKey) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Import BDD Render</title></head><body>';
    echo '<h1>Clé requise</h1>';
    echo '<p>Ajoutez dans Render (Dashboard &rarr; votre Web Service &rarr; Environment) :</p>';
    echo '<pre>RENDER_IMPORT_KEY = une phrase secrète de votre choix</pre>';
    echo '<p>Puis ouvrez : <code>https://votre-app.onrender.com/import_on_render.php?key=VOTRE_PHRASE</code></p>';
    echo '</body></html>';
    exit;
}

if (file_exists($lockFile)) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Import déjà fait</title></head><body>';
    echo '<h1>Import déjà effectué</h1><p>La base a déjà été importée. Vous pouvez vous connecter.</p>';
    echo '<p><a href="login.php">Aller à la connexion</a></p></body></html>';
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sql_file'])) {
    $file = $_FILES['sql_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Erreur upload : ' . $file['error'];
    } elseif ($file['size'] > 50 * 1024 * 1024) {
        $error = 'Fichier trop volumineux (max 50 Mo).';
    } else {
        $sql = file_get_contents($file['tmp_name']);
                $sql = str_replace(["\r\n", "\r"], "\n", $sql);
                if ($sql === false || trim($sql) === '') {
            $error = 'Fichier vide ou illisible.';
        } else {
            try {
                require_once __DIR__ . '/config/bdd.php';
                $pdo = bdd();
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $pdo->exec("SET NAMES utf8mb4");
                $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

                $statements = array_filter(
                    array_map('trim', explode(";\n", $sql)),
                    function ($s) { return $s !== '' && strpos($s, '--') !== 0; }
                );

                foreach ($statements as $stmt) {
                    $stmt = preg_replace('/^--.*$/m', '', $stmt);
                    $stmt = trim($stmt);
                    if ($stmt === '' || $stmt === 'SET NAMES utf8mb4' || $stmt === 'SET FOREIGN_KEY_CHECKS=0' || $stmt === 'SET FOREIGN_KEY_CHECKS=1') {
                        continue;
                    }
                    if (preg_match('/^SET\s+/i', $stmt)) {
                        $pdo->exec($stmt);
                        continue;
                    }
                    try {
                        $pdo->exec($stmt);
                    } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'Duplicate entry') === false && strpos($e->getMessage(), 'already exists') === false) {
                            throw $e;
                        }
                    }
                }

                $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
                file_put_contents($lockFile, date('Y-m-d H:i:s'));
                $message = 'Import terminé. Vous pouvez vous connecter avec votre compte admin.';
            } catch (Exception $e) {
                $error = 'Erreur : ' . htmlspecialchars($e->getMessage());
            }
        }
        @unlink($file['tmp_name']);
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Import BDD sur Render</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; }
        h1 { color: #333; }
        .alert { padding: 12px; border-radius: 6px; margin: 16px 0; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        input[type="file"] { margin: 10px 0; }
        button { background: #4A90E2; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; }
        button:hover { background: #357ABD; }
        a { color: #4A90E2; }
    </style>
</head>
<body>
    <h1>Importer la base de données sur Render</h1>
    <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
        <p><a href="login.php">Aller à la connexion</a></p>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
        <p><a href="import_on_render.php?key=<?= htmlspecialchars($key) ?>">Réessayer</a></p>
    <?php else: ?>
        <p>Envoyez le fichier <strong>export_for_render.sql</strong> généré en local.</p>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="sql_file" accept=".sql" required>
            <br>
            <button type="submit">Importer</button>
        </form>
    <?php endif; ?>
</body>
</html>
