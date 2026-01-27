<?php
/**
 * Page de test simple pour Orange Money
 * Permet de tester rapidement sans passer par le formulaire complet
 */
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';
require_once 'orange_money_api.php';
require_once 'orange_config.php';

requireLogin('../login.php');
requirePermission('manage_paiements', '../index.php');

$user_info = getUserInfo();
$message = '';
$message_type = '';
$result = null;

// Récupérer les patients pour le test
$patients = getAllPatients();
$orange_config = require 'orange_config.php';

// Traitement du test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tester_orange'])) {
    $montant = floatval($_POST['montant'] ?? 50000);
    $id_patient = !empty($_POST['id_patient']) ? intval($_POST['id_patient']) : null;
    $customer_phone = trim($_POST['customer_phone'] ?? '+224 612 34 56 78');
    
    if (empty($id_patient)) {
        $message = "Veuillez sélectionner un patient.";
        $message_type = "danger";
    } else {
        try {
            // Récupérer le patient
            $patient = getPatientById($id_patient);
            
            // Générer un order ID
            $order_id = 'TEST_OM_' . time() . '_' . rand(1000, 9999);
            
            // Préparer les données
            $payment_data = [
                'order_id' => $order_id,
                'amount' => $montant,
                'currency' => 'GNF',
                'customer_phone' => $customer_phone,
                'customer_name' => ($patient['Nom_patient'] ?? '') . ' ' . ($patient['Prénom_patient'] ?? ''),
                'id_patient' => $id_patient
            ];
            
            // Tester l'API
            $orange_api = new OrangeMoneyAPI($orange_config);
            
            if ($orange_config['simulation_mode']) {
                $result = $orange_api->simulatePayment($payment_data);
                $message = "✅ Mode Simulation activé - Test réussi !";
                $message_type = "success";
            } else {
                $result = $orange_api->initiatePayment($payment_data);
                if ($result && isset($result['success']) && $result['success']) {
                    $message = "✅ Paiement initié avec succès !";
                    $message_type = "success";
                } else {
                    $message = "❌ Erreur : " . ($result['error'] ?? 'Erreur inconnue');
                    $message_type = "danger";
                }
            }
        } catch (Exception $e) {
            $message = "Erreur : " . $e->getMessage();
            $message_type = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Test Orange Money - Apprentissage</title>
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
            max-width: 800px;
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
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        .info-box strong {
            color: #1976D2;
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
        select, input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        select:focus, input:focus {
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
        .mode-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        .mode-simulation {
            background: #fff3cd;
            color: #856404;
        }
        .mode-production {
            background: #d1ecf1;
            color: #0c5460;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <i class="fa fa-flask"></i> Test Orange Money
            <span class="mode-badge <?php echo $orange_config['simulation_mode'] ? 'mode-simulation' : 'mode-production'; ?>">
                <?php echo $orange_config['simulation_mode'] ? '🧪 MODE SIMULATION' : '🚀 MODE PRODUCTION'; ?>
            </span>
        </h1>
        <p class="subtitle">Page de test simple pour apprendre et tester Orange Money</p>
        
        <div class="info-box">
            <strong>💡 Mode Apprentissage :</strong> Cette page vous permet de tester rapidement l'API Orange Money 
            sans passer par le formulaire complet. Parfait pour comprendre comment ça fonctionne !
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label><i class="fa fa-user"></i> Patient</label>
                <select name="id_patient" required>
                    <option value="">Sélectionner un patient</option>
                    <?php foreach ($patients as $patient): ?>
                        <option value="<?php echo $patient['id_patient']; ?>">
                            <?php echo htmlspecialchars($patient['Nom_patient'] . ' ' . $patient['Prénom_patient'] . ' (' . $patient['Matricule_patient'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label><i class="fa fa-money"></i> Montant (GNF)</label>
                <input type="number" name="montant" value="50000" min="1" required>
            </div>
            
            <div class="form-group">
                <label><i class="fa fa-mobile"></i> Numéro de téléphone Orange Money</label>
                <input type="tel" name="customer_phone" value="+224 612 34 56 78" required 
                       placeholder="+224 612 34 56 78">
            </div>
            
            <button type="submit" name="tester_orange" class="btn">
                <i class="fa fa-play"></i> Tester Orange Money
            </button>
        </form>
        
        <?php if ($result): ?>
            <div class="result-box">
                <h3 style="margin-bottom: 15px; color: #002939;">
                    <i class="fa fa-code"></i> Résultat de l'API
                </h3>
                <pre><?php echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                
                <?php if (isset($result['payment_url'])): ?>
                    <div style="margin-top: 15px;">
                        <a href="<?php echo htmlspecialchars($result['payment_url']); ?>" 
                           class="btn" 
                           style="text-decoration: none; display: inline-block; width: auto;">
                            <i class="fa fa-external-link"></i> Aller à la page de paiement
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="links">
            <h3 style="margin-bottom: 15px; color: #002939;">Liens Utiles</h3>
            <a href="create.php">
                <i class="fa fa-plus"></i> Créer un Paiement Complet
            </a>
            <a href="index.php">
                <i class="fa fa-list"></i> Liste des Paiements
            </a>
            <a href="TEST_RAPIDE.md" target="_blank">
                <i class="fa fa-book"></i> Guide Test Rapide
            </a>
            <a href="GUIDE_APPRENTISSAGE.md" target="_blank">
                <i class="fa fa-graduation-cap"></i> Guide d'Apprentissage
            </a>
        </div>
    </div>
</body>
</html>
