<?php
/**
 * Page de visualisation des détails d'un paiement
 */
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';

requireLogin('../login.php');
requirePermission('view_paiements', '../index.php');

$user_info = getUserInfo();
$message = '';
$message_type = '';

// Récupérer l'ID du paiement
$id_paiement = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id_paiement) {
    $message = "ID de paiement invalide.";
    $message_type = "danger";
    $paiement = null;
} else {
    try {
        $paiement = getPaiementById($id_paiement);
        if (!$paiement) {
            $message = "Paiement introuvable.";
            $message_type = "danger";
        }
    } catch (Exception $e) {
        error_log("Erreur récupération paiement: " . $e->getMessage());
        $message = "Erreur lors du chargement du paiement.";
        $message_type = "danger";
        $paiement = null;
    }
}

// Récupérer les informations du service si disponible
$service_info = null;
if ($paiement && isset($paiement['id_service']) && $paiement['id_service']) {
    try {
        $pdo = bdd();
        $sql_service = "SELECT * FROM SERVICES WHERE id_service = ?";
        $stmt_service = $pdo->prepare($sql_service);
        $stmt_service->execute([$paiement['id_service']]);
        $service_info = $stmt_service->fetch();
    } catch (Exception $e) {
        error_log("Erreur récupération service: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Détails du Paiement - MediCo.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="../assets/css/plugins.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
    <link class="skin" rel="stylesheet" type="text/css" href="../assets/css/skin/skin-1.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/templete.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .payment-container {
            padding: 40px 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .content-card {
            background: #fff;
            border-radius: 12px;
            padding: 40px;
            margin: 0 auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 1000px;
        }
        .page-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }
        .page-header h1 {
            color: #002939;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .details-section {
            margin-bottom: 30px;
        }
        .details-section h2 {
            color: #002939;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        .detail-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 20px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #666;
        }
        .detail-value {
            color: #002939;
        }
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-paye {
            background: #d4edda;
            color: #155724;
        }
        .badge-attente {
            background: #fff3cd;
            color: #856404;
        }
        .badge-annule {
            background: #f8d7da;
            color: #721c24;
        }
        .badge-rembourse {
            background: #d1ecf1;
            color: #0c5460;
        }
        .btn-action {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            font-weight: 600;
            margin: 5px;
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
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .btn-retour {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        .btn-retour:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            color: white;
            text-decoration: none;
        }
        .amount-highlight {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
            margin: 20px 0;
        }
    </style>
</head>
<body>
<div class="page-wraper">
    <?php require_once '../partials/entete.php'; ?>
    
    <div class="payment-container">
        <div class="container">
            <div class="content-card">
                <a href="index.php" class="btn-retour">
                    <i class="fa fa-arrow-left"></i> Retour à la liste
                </a>
                
                <div class="page-header">
                    <h1><i class="fa fa-money"></i> Détails du Paiement</h1>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($paiement): ?>
                    <!-- Informations du paiement -->
                    <div class="details-section">
                        <h2><i class="fa fa-info-circle"></i> Informations du Paiement</h2>
                        
                        <div class="detail-row">
                            <div class="detail-label">ID Paiement :</div>
                            <div class="detail-value">#<?php echo htmlspecialchars($paiement['id_paiement'] ?? 'N/A'); ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Montant :</div>
                            <div class="detail-value">
                                <span class="amount-highlight">
                                    <?php echo number_format(floatval($paiement['Montant'] ?? 0), 0, ',', ' '); ?> GNF
                                </span>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Date de paiement :</div>
                            <div class="detail-value">
                                <?php echo date('d/m/Y à H:i', strtotime($paiement['Date_paiement'] ?? 'now')); ?>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Méthode de paiement :</div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($paiement['Méthode_paiement'] ?? $paiement['methode_paiement'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Statut :</div>
                            <div class="detail-value">
                                <?php 
                                $statut = $paiement['Statut'] ?? 'en_attente';
                                $badge_class = 'badge-attente';
                                if ($statut === 'payé') $badge_class = 'badge-paye';
                                elseif ($statut === 'annulé') $badge_class = 'badge-annule';
                                elseif ($statut === 'remboursé') $badge_class = 'badge-rembourse';
                                ?>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo htmlspecialchars(ucfirst($statut)); ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if (!empty($paiement['id_facture'])): ?>
                        <div class="detail-row">
                            <div class="detail-label">Numéro de facture :</div>
                            <div class="detail-value">
                                <strong><?php echo htmlspecialchars($paiement['id_facture']); ?></strong>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($paiement['Date_creation'])): ?>
                        <div class="detail-row">
                            <div class="detail-label">Date de création :</div>
                            <div class="detail-value">
                                <?php echo date('d/m/Y à H:i', strtotime($paiement['Date_creation'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Informations du patient -->
                    <div class="details-section">
                        <h2><i class="fa fa-user"></i> Informations du Patient</h2>
                        
                        <div class="detail-row">
                            <div class="detail-label">Nom complet :</div>
                            <div class="detail-value">
                                <strong><?php echo htmlspecialchars(($paiement['Nom_patient'] ?? '') . ' ' . ($paiement['Prénom_patient'] ?? '')); ?></strong>
                            </div>
                        </div>
                        
                        <?php if (!empty($paiement['Matricule_patient'])): ?>
                        <div class="detail-row">
                            <div class="detail-label">Matricule :</div>
                            <div class="detail-value"><?php echo htmlspecialchars($paiement['Matricule_patient']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($paiement['Tel_patient'])): ?>
                        <div class="detail-row">
                            <div class="detail-label">Téléphone :</div>
                            <div class="detail-value"><?php echo htmlspecialchars($paiement['Tel_patient']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Informations du service -->
                    <?php if ($service_info): ?>
                    <div class="details-section">
                        <h2><i class="fa fa-stethoscope"></i> Service Médical</h2>
                        
                        <div class="detail-row">
                            <div class="detail-label">Nom du service :</div>
                            <div class="detail-value">
                                <strong><?php echo htmlspecialchars($service_info['Nom_service'] ?? 'N/A'); ?></strong>
                            </div>
                        </div>
                        
                        <?php if (!empty($service_info['Description'])): ?>
                        <div class="detail-row">
                            <div class="detail-label">Description :</div>
                            <div class="detail-value"><?php echo htmlspecialchars($service_info['Description']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($service_info['Tarif'])): ?>
                        <div class="detail-row">
                            <div class="detail-label">Tarif du service :</div>
                            <div class="detail-value">
                                <?php echo number_format(floatval($service_info['Tarif']), 0, ',', ' '); ?> GNF
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Informations de consultation (si disponible) -->
                    <?php if (!empty($paiement['Date_consultation'])): ?>
                    <div class="details-section">
                        <h2><i class="fa fa-calendar"></i> Consultation Associée</h2>
                        
                        <div class="detail-row">
                            <div class="detail-label">Date de consultation :</div>
                            <div class="detail-value">
                                <?php echo date('d/m/Y à H:i', strtotime($paiement['Date_consultation'])); ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($paiement['Motif_diagnostic'])): ?>
                        <div class="detail-row">
                            <div class="detail-label">Motif :</div>
                            <div class="detail-value"><?php echo htmlspecialchars($paiement['Motif_diagnostic']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Actions -->
                    <div style="margin-top: 40px; text-align: center; padding-top: 30px; border-top: 2px solid #e2e8f0;">
                        <?php if ($paiement['Statut'] === 'payé' && !empty($paiement['chemin_reçu'])): ?>
                            <a href="../<?php echo htmlspecialchars($paiement['chemin_reçu']); ?>" target="_blank" class="btn-action btn-success">
                                <i class="fa fa-file-pdf-o"></i> Voir le Reçu
                            </a>
                        <?php elseif ($paiement['Statut'] === 'payé'): ?>
                            <form method="post" action="process.php" style="display: inline;">
                                <input type="hidden" name="action" value="generer_reçu">
                                <input type="hidden" name="id_paiement" value="<?php echo $paiement['id_paiement']; ?>">
                                <button type="submit" class="btn-action btn-success">
                                    <i class="fa fa-file-pdf-o"></i> Générer le Reçu
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <a href="index.php" class="btn-action btn-secondary">
                            <i class="fa fa-list"></i> Retour à la liste
                        </a>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px;">
                        <i class="fa fa-exclamation-triangle" style="font-size: 64px; color: #ffc107; margin-bottom: 20px;"></i>
                        <h2 style="color: #666; margin-bottom: 20px;">Paiement introuvable</h2>
                        <a href="index.php" class="btn-action btn-primary">
                            <i class="fa fa-arrow-left"></i> Retour à la liste
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php require_once '../partials/footer.php'; ?>
</div>

<script src="../assets/js/jquery.min.js"></script>
</body>
</html>
