<?php
/**
 * Page de simulation de paiement Orange Money
 * Pour tester sans utiliser l'API réelle
 */
require_once '../config/session.php';
require_once '../config/database_functions.php';

requireLogin('../login.php');

$order_id = $_GET['order_id'] ?? '';
$action = $_POST['action'] ?? '';

if (empty($order_id)) {
    die("Order ID manquant");
}

// Traitement de la simulation
if ($action === 'confirm_payment') {
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
            
            if ($paiement) {
                // Mettre à jour le statut à "payé"
                $sql_update = "UPDATE PAIEMENT SET Statut = 'payé' WHERE id_paiement = ?";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([$paiement['id_paiement']]);
                
                // Générer un numéro de facture si pas déjà présent
                if (empty($paiement['id_facture'])) {
                    $id_facture = genererNumeroFacture();
                    $sql_facture = "UPDATE PAIEMENT SET id_facture = ? WHERE id_paiement = ?";
                    $stmt_facture = $pdo->prepare($sql_facture);
                    $stmt_facture->execute([$id_facture, $paiement['id_paiement']]);
                }
                
                // Générer le reçu
                try {
                    genererReçu($paiement['id_paiement']);
                } catch (Exception $e) {
                    error_log("Erreur génération reçu simulation: " . $e->getMessage());
                }
                
                // Rediriger vers la page de retour
                header('Location: orange_return.php?order_id=' . $order_id);
                exit();
            }
        }
    } catch (Exception $e) {
        error_log("Erreur simulation paiement: " . $e->getMessage());
    }
} elseif ($action === 'cancel_payment') {
    // Rediriger vers la page de retour avec annulation
    header('Location: orange_return.php?order_id=' . $order_id . '&cancel=1');
    exit();
}

// Récupérer les informations du paiement pour affichage
$paiement = null;
try {
    $pdo = bdd();
    $check_orange_id = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'orange_order_id'");
    $has_orange_id = $check_orange_id->rowCount() > 0;
    
    if ($has_orange_id) {
        $sql = "SELECT p.*, pat.Nom_patient, pat.Prénom_patient 
                FROM PAIEMENT p
                LEFT JOIN PATIENTS pat ON p.id_patient = pat.id_patient
                WHERE p.orange_order_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$order_id]);
        $paiement = $stmt->fetch();
    }
} catch (Exception $e) {
    error_log("Erreur récupération paiement simulation: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Simulation Paiement Orange Money - MediCo.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="../assets/css/plugins.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
    <link class="skin" rel="stylesheet" type="text/css" href="../assets/css/skin/skin-1.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/templete.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .simulate-container {
            padding: 40px 0;
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .simulate-card {
            background: #fff;
            border-radius: 12px;
            padding: 40px;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
        }
        .warning-banner {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 30px;
            color: #856404;
        }
        .payment-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }
        .payment-details p {
            margin: 10px 0;
        }
        .btn-simulate {
            padding: 15px 40px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin: 10px;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
<div class="page-wraper">
    <?php require_once '../partials/entete.php'; ?>
    
    <div class="simulate-container">
        <div class="simulate-card">
            <div class="warning-banner">
                <i class="fa fa-exclamation-triangle"></i>
                <strong>Mode Simulation</strong><br>
                Cette page simule le processus de paiement Orange Money pour les tests.
            </div>
            
            <h2 style="color: #002939; margin-bottom: 20px;">
                <i class="fa fa-mobile"></i> Simulation Paiement Orange Money
            </h2>
            
            <?php if ($paiement): ?>
                <div class="payment-details">
                    <h3 style="color: #002939; margin-bottom: 15px;">Détails du Paiement</h3>
                    <p><strong>Patient :</strong> <?php echo htmlspecialchars(($paiement['Nom_patient'] ?? '') . ' ' . ($paiement['Prénom_patient'] ?? '')); ?></p>
                    <p><strong>Montant :</strong> <span style="font-size: 24px; color: #667eea; font-weight: 700;">
                        <?php echo number_format(floatval($paiement['Montant']), 0, ',', ' '); ?> GNF
                    </span></p>
                    <p><strong>Order ID :</strong> <?php echo htmlspecialchars($order_id); ?></p>
                </div>
                
                <p style="margin: 30px 0; color: #666;">
                    Dans un environnement réel, le patient serait redirigé vers la page de paiement Orange Money.
                    Ici, vous pouvez simuler le succès ou l'échec du paiement.
                </p>
                
                <form method="post" action="">
                    <input type="hidden" name="action" value="confirm_payment">
                    <button type="submit" class="btn-simulate btn-success">
                        <i class="fa fa-check"></i> Simuler Paiement Réussi
                    </button>
                </form>
                
                <form method="post" action="">
                    <input type="hidden" name="action" value="cancel_payment">
                    <button type="submit" class="btn-simulate btn-danger">
                        <i class="fa fa-times"></i> Simuler Annulation
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-danger">
                    Paiement introuvable pour cet Order ID.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php require_once '../partials/footer.php'; ?>
</div>

<script src="../assets/js/jquery.min.js"></script>
</body>
</html>
