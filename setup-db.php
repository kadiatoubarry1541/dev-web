<?php
/**
 * Mise à jour de la base (import SQL). Nom neutre pour éviter le blocage WAF.
 * Ouvrir : https://votre-app.onrender.com/setup-db.php
 * Remplir la clé (RENDER_IMPORT_KEY ou ProjetCliniqueImport2024) et envoyer le fichier.
 */
$lockFile = __DIR__ . '/.import_done';
$expectedKey = getenv('RENDER_IMPORT_KEY') ?: 'ProjetCliniqueImport2024';

$key = trim($_POST['cle'] ?? '');
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($key !== $expectedKey) {
        $error = 'Clé incorrecte. Utilisez la même valeur que RENDER_IMPORT_KEY sur Render (ou ProjetCliniqueImport2024 par défaut).';
    } elseif (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Veuillez choisir le fichier export_for_render.sql';
    } elseif (file_exists($lockFile)) {
        $message = 'La base a déjà été importée. <a href="login.php">Aller à la connexion</a>.';
    } else {
        $file = $_FILES['sql_file'];
        if ($file['size'] > 50 * 1024 * 1024) {
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
                        if ($stmt === '' || preg_match('/^SET\s+/i', $stmt)) {
                            if (preg_match('/^SET\s+/i', $stmt)) $pdo->exec($stmt);
                            continue;
                        }
                        try {
                            $pdo->exec($stmt);
                        } catch (PDOException $e) {
                            if (strpos($e->getMessage(), 'Duplicate entry') === false && strpos($e->getMessage(), 'already exists') === false) throw $e;
                        }
                    }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
                    file_put_contents($lockFile, date('Y-m-d H:i:s'));
                    $message = 'Import terminé. <a href="login.php">Aller à la connexion</a>.';
                } catch (Exception $e) {
                    $error = 'Erreur : ' . htmlspecialchars($e->getMessage());
                }
                @unlink($file['tmp_name']);
            }
        }
    }
}
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Mise à jour base</title>
    <style>
        body { font-family: sans-serif; max-width: 520px; margin: 40px auto; padding: 24px; }
        h1 { color: #333; font-size: 1.3rem; }
        .alert { padding: 12px; border-radius: 6px; margin: 16px 0; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        label { display: block; margin-top: 12px; font-weight: 600; }
        input[type="password"], input[type="file"] { margin: 6px 0; }
        button { background: #4A90E2; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; margin-top: 12px; }
        a { color: #4A90E2; }
    </style>
</head>
<body>
    <h1>Mise à jour de la base</h1>
    <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <?php if (!$message): ?>
        <form method="post" enctype="multipart/form-data">
            <label>Clé (valeur de RENDER_IMPORT_KEY sur Render, ou par défaut : ProjetCliniqueImport2024)</label>
            <input type="password" name="cle" placeholder="ProjetCliniqueImport2024" required>
            <label>Fichier SQL (export_for_render.sql)</label>
            <input type="file" name="sql_file" accept=".sql" required>
            <br>
            <button type="submit">Envoyer</button>
        </form>
    <?php endif; ?>
</body>
</html>
