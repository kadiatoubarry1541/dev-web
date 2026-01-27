<?php
/**
 * Page de vérification des credentials Orange Money
 * Teste si vos credentials fonctionnent avec l'API Orange
 */
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once 'orange_money_api.php';
require_once 'orange_config.php';

requireLogin('../login.php');
requirePermission('manage_paiements', '../index.php');

$orange_config = require 'orange_config.php';
$test_result = null;
$error = null;

// Tester les credentials
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tester_credentials'])) {
    $merchant_id = trim($_POST['merchant_id'] ?? '');
    $merchant_key = trim($_POST['merchant_key'] ?? '');
    $api_url = trim($_POST['api_url'] ?? '');
    $auth_url = trim($_POST['auth_url'] ?? '');
    
    if (empty($merchant_id) || empty($merchant_key)) {
        $error = "Veuillez remplir le Merchant ID et le Merchant Key";
    } else {
        // Créer une config temporaire pour le test
        $test_config = $orange_config;
        $test_config['merchant_id'] = $merchant_id;
        $test_config['merchant_key'] = $merchant_key;
        $test_config['simulation_mode'] = false;
        
        if (!empty($api_url)) {
            $test_config['api_url'] = $api_url;
        }
        if (!empty($auth_url)) {
            $test_config['auth_url'] = $auth_url;
        }
        
        try {
            $orange_api = new OrangeMoneyAPI($test_config);
            
            // Tester l'authentification
            $token = $orange_api->getAuthToken();
            
            if ($token) {
                $test_result = [
                    'success' => true,
                    'message' => '✅ Authentification réussie ! Vos credentials fonctionnent.',
                    'token' => substr($token, 0, 20) . '...' // Afficher seulement le début du token
                ];
            } else {
                $test_result = [
                    'success' => false,
                    'message' => '❌ Échec de l\'authentification. Vérifiez vos credentials.',
                    'error' => 'Impossible d\'obtenir un token d\'accès'
                ];
            }
        } catch (Exception $e) {
            $test_result = [
                'success' => false,
                'message' => '❌ Erreur lors du test',
                'error' => $e->getMessage()
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Vérifier Credentials Orange Money</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #002939;
            margin-bottom: 10px;
            font-size: 32px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        .info-box h3 {
            color: #1976D2;
            margin-bottom: 10px;
        }
        .info-box ol {
            margin-left: 20px;
        }
        .info-box li {
            margin: 8px 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            color: #002939;
            margin-bottom: 8px;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
            font-family: 'Courier New', monospace;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .result-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .result-box pre {
            background: #fff;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 14px;
        }
        .links {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
        }
        .links a {
            display: inline-block;
            margin: 10px 10px 10px 0;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s;
        }
        .links a:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <i class="fa fa-key"></i> Vérifier Credentials Orange Money
        </h1>
        <p class="subtitle">Testez si vos credentials du portail Orange Developer fonctionnent</p>
        
        <div class="info-box">
            <h3><i class="fa fa-info-circle"></i> Comment obtenir vos credentials ?</h3>
            <ol>
                <li>Allez sur <strong>https://developer.orange.com</strong></li>
                <li>Créez un compte (gratuit)</li>
                <li>Créez une application dans votre tableau de bord</li>
                <li>Souscrivez à <strong>Orange Money API</strong> (plan Sandbox/Test - gratuit)</li>
                <li>Copiez votre <strong>Client ID</strong> et <strong>Client Secret</strong></li>
                <li>Collez-les dans le formulaire ci-dessous et testez !</li>
            </ol>
            <p style="margin-top: 15px;">
                <strong>📚 Guide complet :</strong> Lisez <code>GUIDE_API_REELLE.md</code> pour plus de détails
            </p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($test_result): ?>
            <div class="alert alert-<?php echo $test_result['success'] ? 'success' : 'danger'; ?>">
                <strong><?php echo $test_result['message']; ?></strong>
                <?php if (isset($test_result['error'])): ?>
                    <br><small><?php echo htmlspecialchars($test_result['error']); ?></small>
                <?php endif; ?>
            </div>
            
            <?php if ($test_result['success']): ?>
                <div class="result-box">
                    <h3 style="margin-bottom: 15px; color: #002939;">
                        <i class="fa fa-check-circle"></i> Token d'Accès Obtenu
                    </h3>
                    <p><strong>Token (premiers caractères) :</strong></p>
                    <pre><?php echo htmlspecialchars($test_result['token']); ?></pre>
                    <p style="margin-top: 15px; color: #28a745;">
                        ✅ <strong>Parfait !</strong> Vos credentials fonctionnent. Vous pouvez maintenant :
                    </p>
                    <ol style="margin-left: 20px; margin-top: 10px;">
                        <li>Copier ces credentials dans <code>orange_config.php</code></li>
                        <li>Mettre <code>'simulation_mode' => false</code></li>
                        <li>Commencer à tester avec la vraie API !</li>
                    </ol>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label><i class="fa fa-id-card"></i> Merchant ID (Client ID)</label>
                <input type="text" name="merchant_id" 
                       value="<?php echo htmlspecialchars($orange_config['merchant_id']); ?>"
                       placeholder="Votre Client ID du portail Orange"
                       required>
                <div class="help-text">Trouvé dans votre application sur developer.orange.com</div>
            </div>
            
            <div class="form-group">
                <label><i class="fa fa-lock"></i> Merchant Key (Client Secret)</label>
                <input type="password" name="merchant_key" 
                       value="<?php echo htmlspecialchars($orange_config['merchant_key']); ?>"
                       placeholder="Votre Client Secret du portail Orange"
                       required>
                <div class="help-text">Trouvé dans votre application sur developer.orange.com</div>
            </div>
            
            <div class="form-group">
                <label><i class="fa fa-link"></i> API URL (optionnel - utilise la valeur par défaut si vide)</label>
                <input type="text" name="api_url" 
                       value="<?php echo htmlspecialchars($orange_config['api_url']); ?>"
                       placeholder="https://api.orange.com/orange-money-webpay/dev/v1/webpayment">
                <div class="help-text">URL de l'API Orange Money (sandbox ou production)</div>
            </div>
            
            <div class="form-group">
                <label><i class="fa fa-link"></i> Auth URL (optionnel - utilise la valeur par défaut si vide)</label>
                <input type="text" name="auth_url" 
                       value="<?php echo htmlspecialchars($orange_config['auth_url']); ?>"
                       placeholder="https://api.orange.com/oauth/v2/token">
                <div class="help-text">URL d'authentification OAuth</div>
            </div>
            
            <button type="submit" name="tester_credentials" class="btn">
                <i class="fa fa-flask"></i> Tester les Credentials
            </button>
        </form>
        
        <div class="links">
            <h3 style="margin-bottom: 15px; color: #002939;">Liens Utiles</h3>
            <a href="https://developer.orange.com" target="_blank">
                <i class="fa fa-external-link"></i> Portail Orange Developer
            </a>
            <a href="GUIDE_API_REELLE.md" target="_blank">
                <i class="fa fa-book"></i> Guide API Réelle
            </a>
            <a href="orange_config.php">
                <i class="fa fa-cog"></i> Configuration
            </a>
            <a href="test_orange.php">
                <i class="fa fa-flask"></i> Test API
            </a>
        </div>
    </div>
</body>
</html>
