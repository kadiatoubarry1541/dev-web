<?php
/**
 * Page de retour après paiement Orange Money
 * L'utilisateur est redirigé ici après avoir effectué le paiement
 */
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';
require_once 'orange_money_api.php';
require_once 'orange_config.php';

requireLogin('../login.php');

$user_info = getUserInfo();
$message = '';
$message_type = '';
$paiement = null;

// Récupérer l'order_id depuis l'URL
$order_id = $_GET['order_id'] ?? '';
$cancel = isset($_GET['cancel']) && $_GET['cancel'] == '1';

if (empty($order_id)) {
    $message = "ID de commande manquant.";
    $message_type = "danger";
} else {
    try {
        $pdo = bdd();
        
        // Récupérer le paiement
        $check_orange_id = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'orange_order_id'");
        $has_orange_id = $check_orange_id->rowCount() > 0;
        
        if ($has_orange_id) {
            $sql = "SELECT * FROM PAIEMENT WHERE orange_order_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$order_id]);
            $paiement = $stmt->fetch();
        }
        
        if ($paiement) {
            if ($cancel) {
                $message = "Le paiement a été annulé.";
                $message_type = "warning";
            } else {
                // Vérifier le statut du paiement avec l'API Orange Money
                $orange_config = require 'orange_config.php';
                $orange_api = new OrangeMoneyAPI($orange_config);
                
                if (!$orange_config['simulation_mode']) {
                    $status = $orange_api->checkPaymentStatus($order_id);
                    if ($status && isset($status['status'])) {
                        // Mettre à jour le statut si nécessaire
                        $orange_status = strtolower($status['status']);
                        if (in_array($orange_status, ['success', 'completed', 'paid'])) {
                            if ($paiement['Statut'] !== 'payé') {
                                $sql_update = "UPDATE PAIEMENT SET Statut = 'payé' WHERE id_paiement = ?";
                                $stmt_update = $pdo->prepare($sql_update);
                                $stmt_update->execute([$paiement['id_paiement']]);
                                $paiement['Statut'] = 'payé';
                                
                                // Générer le reçu
                                try {
                                    genererReçu($paiement['id_paiement']);
                                } catch (Exception $e) {
                                    error_log("Erreur génération reçu: " . $e->getMessage());
                                }
                            }
                        }
                    }
                }
                
                if ($paiement['Statut'] === 'payé') {
                    $message = "Paiement effectué avec succès !";
                    $message_type = "success";
                } elseif ($paiement['Statut'] === 'en_attente') {
                    $message = "Le paiement est en cours de traitement. Vous serez notifié une fois confirmé.";
                    $message_type = "info";
                } else {
                    $message = "Le paiement n'a pas pu être confirmé.";
                    $message_type = "warning";
                }
            }
        } else {
            $message = "Paiement introuvable.";
            $message_type = "danger";
        }
    } catch (Exception $e) {
        error_log("Erreur retour Orange Money: " . $e->getMessage());
        $message = "Erreur lors de la vérification du paiement.";
        $message_type = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Retour Paiement Orange Money - MediCo.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="../assets/css/plugins.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
    <link class="skin" rel="stylesheet" type="text/css" href="../assets/css/skin/skin-1.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/templete.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .return-container {
            padding: 40px 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .return-card {
            background: #fff;
            border-radius: 12px;
            padding: 40px;
            max-width: 600px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
        }
        .icon-large {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .icon-success { color: #28a745; }
        .icon-warning { color: #ffc107; }
        .icon-danger { color: #dc3545; }
        .icon-info { color: #17a2b8; }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
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
        .btn-action {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            margin: 10px;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="page-wraper">
    <?php require_once '../partials/entete.php'; ?>
    
    <div class="return-container">
        <div class="return-card">
            <?php if ($message_type === 'success'): ?>
                <i class="fa fa-check-circle icon-large icon-success"></i>
            <?php elseif ($message_type === 'warning'): ?>
                <i class="fa fa-exclamation-triangle icon-large icon-warning"></i>
            <?php elseif ($message_type === 'danger'): ?>
                <i class="fa fa-times-circle icon-large icon-danger"></i>
            <?php else: ?>
                <i class="fa fa-info-circle icon-large icon-info"></i>
            <?php endif; ?>
            
            <h2 style="color: #002939; margin-bottom: 20px;">
                <?php 
                if ($message_type === 'success') echo 'Paiement Réussi';
                elseif ($message_type === 'warning') echo 'Paiement Annulé';
                elseif ($message_type === 'danger') echo 'Erreur';
                else echo 'Information';
                ?>
            </h2>
            
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            
            <?php if ($paiement): ?>
                <div style="text-align: left; background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h3 style="color: #002939; margin-bottom: 15px;">Détails du Paiement</h3>
                    <p><strong>Montant :</strong> <?php echo number_format(floatval($paiement['Montant']), 0, ',', ' '); ?> GNF</p>
                    <p><strong>Statut :</strong> 
                        <span style="padding: 5px 10px; border-radius: 20px; background: #e2e8f0;">
                            <?php echo htmlspecialchars(ucfirst($paiement['Statut'])); ?>
                        </span>
                    </p>
                    <?php if (!empty($paiement['id_facture'])): ?>
                        <p><strong>Numéro de facture :</strong> <?php echo htmlspecialchars($paiement['id_facture']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 30px;">
                <?php if ($paiement && $paiement['Statut'] === 'payé'): ?>
                    <a href="view.php?id=<?php echo $paiement['id_paiement']; ?>" class="btn-action btn-primary">
                        <i class="fa fa-eye"></i> Voir le Paiement
                    </a>
                <?php endif; ?>
                <a href="index.php" class="btn-action btn-primary">
                    <i class="fa fa-list"></i> Retour à la Liste
                </a>
            </div>
        </div>
    </div>
    
    <?php require_once '../partials/footer.php'; ?>
</div>

<script src="../assets/js/jquery.min.js"></script>
</body>
</html>
