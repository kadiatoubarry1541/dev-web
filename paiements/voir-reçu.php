<?php
/**
 * Page pour visualiser un reçu de paiement
 */
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';

requireLogin('../login.php');

$user_info = getUserInfo();
$id_paiement = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_paiement <= 0) {
    header('Location: liste-paiements.php');
    exit();
}

// Récupérer le paiement
$paiement = getPaiementById($id_paiement);

if (!$paiement) {
    header('Location: liste-paiements.php');
    exit();
}

// Vérifier les permissions : le patient ne peut voir que ses propres reçus
if ($user_info['role'] === 'patient') {
    // Vérifier la permission de voir les reçus
    if (!hasPermission('view_receipts')) {
        header('Location: liste-paiements.php');
        exit();
    }
    
    $id_patient = $user_info['id_patient'] ?? null;
    if (!$id_patient || $paiement['id_patient'] != $id_patient) {
        header('Location: liste-paiements.php');
        exit();
    }
}

// Récupérer le chemin du reçu
$chemin_reçu = getCheminReçu($id_paiement);

// Si le reçu n'existe pas encore, le générer
if (!$chemin_reçu && $paiement['Statut'] === 'payé' && (isset($paiement['id_facture']) && $paiement['id_facture'])) {
    // Seuls les admins et accueil peuvent générer des reçus
    if (hasPermission('manage_paiements')) {
        try {
            $chemin_reçu = genererReçu($id_paiement);
        } catch (Exception $e) {
            error_log("Erreur génération reçu: " . $e->getMessage());
        }
    }
}

// Si le reçu existe, l'afficher
if ($chemin_reçu && file_exists('../' . $chemin_reçu)) {
    readfile('../' . $chemin_reçu);
    exit();
} else {
    // Si le reçu n'existe pas, afficher un message d'erreur
    $message_erreur = "Le reçu demandé n'est pas disponible pour le moment.";
    if ($user_info['role'] === 'patient') {
        if ($paiement['Statut'] !== 'payé') {
            $message_erreur = "Le reçu n'est pas encore disponible car le paiement n'est pas encore marqué comme 'Payé'. Veuillez contacter l'administration si vous avez déjà effectué le paiement.";
        } else {
            $message_erreur = "Le reçu n'est pas encore disponible. Il sera généré et envoyé par l'administration une fois le paiement confirmé. Vous recevrez une notification lorsque le reçu sera prêt.";
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reçu introuvable - MediCo.</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            body {
                font-family: Arial, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                background: #f5f5f5;
                margin: 0;
            }
            .error-container {
                text-align: center;
                background: white;
                padding: 40px;
                border-radius: 12px;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
                max-width: 500px;
            }
            .error-container h1 {
                color: #dc3545;
                margin-bottom: 20px;
            }
            .error-container p {
                color: #666;
                margin-bottom: 30px;
                line-height: 1.6;
            }
            .btn {
                display: inline-block;
                padding: 12px 24px;
                background: #667eea;
                color: white;
                text-decoration: none;
                border-radius: 8px;
                transition: all 0.3s;
            }
            .btn:hover {
                background: #5568d3;
            }
            .info-box {
                background: #e7f3ff;
                border-left: 4px solid #2196F3;
                padding: 15px;
                margin: 20px 0;
                text-align: left;
                border-radius: 4px;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1><i class="fa fa-exclamation-triangle"></i> Reçu introuvable</h1>
            <p><?php echo htmlspecialchars($message_erreur); ?></p>
            <?php if ($user_info['role'] === 'patient' && $paiement['Statut'] === 'payé'): ?>
                <div class="info-box">
                    <i class="fa fa-info-circle"></i> <strong>Information :</strong> Si vous avez effectué le paiement, veuillez contacter l'administration pour qu'elle génère et vous envoie le reçu.
                </div>
            <?php endif; ?>
            <a href="liste-paiements.php" class="btn">
                <i class="fa fa-arrow-left"></i> Retour à la liste des paiements
            </a>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>
