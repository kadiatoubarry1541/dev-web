<?php
/**
 * Page principale de gestion des paiements
 * Liste tous les paiements pour l'administrateur
 */
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';

requireLogin('../login.php');
requirePermission('manage_paiements', '../index.php');

$user_info = getUserInfo();
$message = '';
$message_type = '';

// Récupérer tous les paiements
$paiements = [];
try {
    $paiements = getAllPaiements();
} catch (Exception $e) {
    error_log("Erreur récupération paiements: " . $e->getMessage());
    $message = "Erreur lors du chargement des paiements.";
    $message_type = "danger";
}

// Filtrer les paiements si nécessaire
$filtre_statut = $_GET['statut'] ?? '';
$filtre_patient = $_GET['patient'] ?? '';

if ($filtre_statut || $filtre_patient) {
    $paiements_filtres = [];
    foreach ($paiements as $paiement) {
        $match_statut = !$filtre_statut || $paiement['Statut'] === $filtre_statut;
        $match_patient = !$filtre_patient || 
                        (isset($paiement['id_patient']) && $paiement['id_patient'] == $filtre_patient) ||
                        (isset($paiement['Nom_patient']) && stripos($paiement['Nom_patient'], $filtre_patient) !== false);
        
        if ($match_statut && $match_patient) {
            $paiements_filtres[] = $paiement;
        }
    }
    $paiements = $paiements_filtres;
}

// Récupérer tous les patients pour le filtre
$patients = getAllPatients();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Gestion des Paiements - MediCo.</title>
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
            max-width: 1400px;
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
        .filters-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .filters-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            font-weight: 600;
            color: #002939;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .filter-group select,
        .filter-group input {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .btn-filter {
            background: #667eea;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-filter:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        .btn-create {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
            color: white;
            text-decoration: none;
        }
        .table-container {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #002939;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
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
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 12px;
            margin: 2px;
            transition: all 0.3s;
        }
        .btn-view {
            background: #4A90E2;
            color: white;
        }
        .btn-view:hover {
            background: #357abd;
            color: white;
            text-decoration: none;
        }
        .btn-receipt {
            background: #28a745;
            color: white;
        }
        .btn-receipt:hover {
            background: #218838;
            color: white;
            text-decoration: none;
        }
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card h3 {
            margin: 0;
            font-size: 32px;
            font-weight: 700;
        }
        .stat-card p {
            margin: 5px 0 0 0;
            opacity: 0.9;
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
    </style>
</head>
<body>
<div class="page-wraper">
    <?php require_once '../partials/entete.php'; ?>
    
    <div class="payment-container">
        <div class="container">
            <div class="content-card">
                <a href="<?php 
                    $role = $user_info['role'] ?? 'patient';
                    if ($role === 'admin') {
                        echo '../admin/index.php';
                    } elseif ($role === 'accueil') {
                        echo '../accueil/index.php';
                    } else {
                        echo 'index.php';
                    }
                ?>" class="btn-retour">
                    <i class="fa fa-arrow-left"></i> Retour
                </a>
                
                <div class="page-header">
                    <h1><i class="fa fa-money"></i> Gestion des Paiements</h1>
                    <p>Gérez tous les paiements des patients</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Statistiques -->
                <?php
                $total_paye = 0;
                $total_attente = 0;
                $total_annule = 0;
                $montant_total = 0;
                
                foreach ($paiements as $p) {
                    $montant = floatval($p['Montant'] ?? 0);
                    $statut = $p['Statut'] ?? '';
                    
                    if ($statut === 'payé') {
                        $total_paye++;
                        $montant_total += $montant;
                    } elseif ($statut === 'en_attente') {
                        $total_attente++;
                    } elseif ($statut === 'annulé') {
                        $total_annule++;
                    }
                }
                ?>
                <div class="stats-section">
                    <div class="stat-card">
                        <h3><?php echo count($paiements); ?></h3>
                        <p>Total Paiements</p>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                        <h3><?php echo $total_paye; ?></h3>
                        <p>Paiements Payés</p>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);">
                        <h3><?php echo $total_attente; ?></h3>
                        <p>En Attente</p>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                        <h3><?php echo number_format($montant_total, 0, ',', ' '); ?> GNF</h3>
                        <p>Montant Total Payé</p>
                    </div>
                </div>
                
                <a href="create.php" class="btn-create">
                    <i class="fa fa-plus"></i> Créer un Nouveau Paiement
                </a>
                
                <!-- Filtres -->
                <div class="filters-section">
                    <form method="get" action="">
                        <div class="filters-row">
                            <div class="filter-group">
                                <label>Filtrer par Statut</label>
                                <select name="statut">
                                    <option value="">Tous les statuts</option>
                                    <option value="payé" <?php echo $filtre_statut === 'payé' ? 'selected' : ''; ?>>Payé</option>
                                    <option value="en_attente" <?php echo $filtre_statut === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                                    <option value="annulé" <?php echo $filtre_statut === 'annulé' ? 'selected' : ''; ?>>Annulé</option>
                                    <option value="remboursé" <?php echo $filtre_statut === 'remboursé' ? 'selected' : ''; ?>>Remboursé</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Rechercher un Patient</label>
                                <input type="text" name="patient" placeholder="Nom ou ID patient" value="<?php echo htmlspecialchars($filtre_patient); ?>">
                            </div>
                            <div>
                                <button type="submit" class="btn-filter">
                                    <i class="fa fa-filter"></i> Filtrer
                                </button>
                                <a href="index.php" class="btn-filter" style="background: #6c757d; text-decoration: none; display: inline-block; margin-left: 10px;">
                                    <i class="fa fa-refresh"></i> Réinitialiser
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Tableau des paiements -->
                <div class="table-container">
                    <?php if (empty($paiements)): ?>
                        <p style="text-align: center; padding: 40px; color: #666;">
                            <i class="fa fa-info-circle"></i> Aucun paiement trouvé.
                        </p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Patient</th>
                                    <th>Montant</th>
                                    <th>Date</th>
                                    <th>Méthode</th>
                                    <th>Statut</th>
                                    <th>Facture</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paiements as $paiement): 
                                    $statut = $paiement['Statut'] ?? 'en_attente';
                                    $badge_class = 'badge-attente';
                                    if ($statut === 'payé') $badge_class = 'badge-paye';
                                    elseif ($statut === 'annulé') $badge_class = 'badge-annule';
                                    elseif ($statut === 'remboursé') $badge_class = 'badge-rembourse';
                                    
                                    $nom_patient = ($paiement['Nom_patient'] ?? '') . ' ' . ($paiement['Prénom_patient'] ?? '');
                                    $matricule = $paiement['Matricule_patient'] ?? '';
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($paiement['id_paiement'] ?? ''); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars(trim($nom_patient)); ?>
                                            <?php if ($matricule): ?>
                                                <br><small style="color: #666;"><?php echo htmlspecialchars($matricule); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo number_format(floatval($paiement['Montant'] ?? 0), 0, ',', ' '); ?> GNF</strong></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($paiement['Date_paiement'] ?? 'now')); ?></td>
                                        <td><?php echo htmlspecialchars($paiement['Méthode_paiement'] ?? $paiement['methode_paiement'] ?? 'N/A'); ?></td>
                                        <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars(ucfirst($statut)); ?></span></td>
                                        <td>
                                            <?php if (!empty($paiement['id_facture'])): ?>
                                                <small><?php echo htmlspecialchars($paiement['id_facture']); ?></small>
                                            <?php else: ?>
                                                <small style="color: #999;">-</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="view.php?id=<?php echo $paiement['id_paiement']; ?>" class="btn-action btn-view">
                                                <i class="fa fa-eye"></i> Voir
                                            </a>
                                            <?php if ($statut === 'payé' && !empty($paiement['chemin_reçu'])): ?>
                                                <a href="../<?php echo htmlspecialchars($paiement['chemin_reçu']); ?>" target="_blank" class="btn-action btn-receipt">
                                                    <i class="fa fa-file-pdf-o"></i> Reçu
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php require_once '../partials/footer.php'; ?>
</div>

<script src="../assets/js/jquery.min.js"></script>
</body>
</html>
