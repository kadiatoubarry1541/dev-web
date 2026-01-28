<?php
/**
 * Tableau de bord médecin - même design que l'administrateur
 */
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/permissions.php';
require_once __DIR__ . '/../config/database_functions.php';

requireLogin('login.php');
requireMedecin('index.php');

$user_info = getUserInfo();
$id_med = $user_info['id_med'];
$specialisation = $user_info['specialisation'] ?? '';

$error_message = '';
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

$mes_rdv = [];
$mes_patients = [];
$stats = [
    'rdv_total' => 0,
    'rdv_planifies' => 0,
    'rdv_confirmes' => 0,
    'patients_total' => 0
];

if ($id_med) {
    try {
        $mes_rdv = getRendezVousByMedecin($id_med, $specialisation);
        $stats['rdv_total'] = count($mes_rdv);
        foreach ($mes_rdv as $rdv) {
            if (isset($rdv['Statut'])) {
                if ($rdv['Statut'] === 'planifié') $stats['rdv_planifies']++;
                elseif ($rdv['Statut'] === 'confirmé') $stats['rdv_confirmes']++;
            }
        }
        if ($specialisation) {
            $mes_patients = getPatientsByMedecin($id_med, $specialisation);
        }
        $stats['patients_total'] = count($mes_patients);

        // Compléter les compteurs avec les DEMANDES du service (même si le RDV n'a pas encore été créé en base)
        if ($specialisation && function_exists('getIdServiceByNom')) {
            $id_service_dashboard = getIdServiceByNom($specialisation);
            if ($id_service_dashboard) {
                $pdo = bdd();
                // Demandes en attente (accueil ou service)
                $stmt_dem_att = $pdo->prepare(
                    "SELECT COUNT(*) FROM DEMANDE_RENDEZ_VOUS 
                     WHERE id_service = ? AND statut IN ('en_attente_accueil','en_attente_service')"
                );
                $stmt_dem_att->execute([(int)$id_service_dashboard]);
                $nb_dem_att = (int)$stmt_dem_att->fetchColumn();

                // Demandes déjà confirmées/traitées par le service
                $stmt_dem_ok = $pdo->prepare(
                    "SELECT COUNT(*) FROM DEMANDE_RENDEZ_VOUS 
                     WHERE id_service = ? AND statut = 'traitee'"
                );
                $stmt_dem_ok->execute([(int)$id_service_dashboard]);
                $nb_dem_ok = (int)$stmt_dem_ok->fetchColumn();

                // On ajoute ces volumes aux compteurs "En attente" et "Confirmés"
                $stats['rdv_planifies'] += $nb_dem_att;
                $stats['rdv_confirmes'] += $nb_dem_ok;

                // Le total doit au moins refléter toutes les demandes + RDV du service
                $stats['rdv_total'] = max($stats['rdv_total'], $stats['rdv_planifies'] + $stats['rdv_confirmes']);
            }
        }
    } catch (Exception $e) {
        error_log("Erreur dashboard médecin: " . $e->getMessage());
        $mes_rdv = [];
        $mes_patients = [];
    }
}

require_once __DIR__ . '/partials/header.php';
?>

<style>
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .kpi-card {
        background: white; border-radius: 12px; padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s; position: relative; overflow: hidden;
    }
    .kpi-card::before {
        content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%;
        background: var(--kpi-color);
    }
    .kpi-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.12); }
    .kpi-card.blue { --kpi-color: #4A90E2; }
    .kpi-card.orange { --kpi-color: #ff9800; }
    .kpi-card.green { --kpi-color: #28a745; }
    .kpi-card.red { --kpi-color: #dc3545; }
    .kpi-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
    .kpi-title { font-size: 14px; font-weight: 600; color: #718096; text-transform: uppercase; letter-spacing: 0.5px; }
    .kpi-icon {
        width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
        font-size: 20px; color: white; background: var(--kpi-color);
    }
    .kpi-value { font-size: 36px; font-weight: 700; color: #2d3748; margin-bottom: 5px; }
    .kpi-subtitle { font-size: 13px; color: #718096; }
    .dashboard-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
    @media (max-width: 1200px) { .dashboard-layout { grid-template-columns: 1fr; } }
    .recent-card {
        background: white; border-radius: 12px; padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .recent-card h3 { font-size: 18px; font-weight: 700; color: #2d3748; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0; }
    .recent-item { display: flex; align-items: center; gap: 15px; padding: 15px 0; border-bottom: 1px solid #f5f7fa; }
    .recent-item:last-child { border-bottom: none; }
    .recent-item-avatar {
        width: 40px; height: 40px; border-radius: 50%; background: #4A90E2;
        display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px;
    }
    .recent-item-info { flex: 1; }
    .recent-item-name { font-weight: 600; color: #2d3748; margin-bottom: 3px; }
    .recent-item-detail { font-size: 13px; color: #718096; }
    .quick-actions-card {
        background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-left: 4px solid #4A90E2;
    }
    .quick-actions-card h3 { font-size: 18px; font-weight: 700; color: #2d3748; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
    .quick-actions-card h3 i { color: #4A90E2; }
    .quick-actions-card .btn-action {
        display: flex; align-items: center; gap: 10px; padding: 12px 16px; margin-bottom: 8px;
        background: #4A90E2; color: white; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 14px;
        transition: all 0.3s;
    }
    .quick-actions-card .btn-action:hover { background: #357ABD; color: white; transform: translateX(4px); }
    .quick-actions-card .btn-action.payments { background: #28a745; }
    .quick-actions-card .btn-action.payments:hover { background: #218838; }
    .info-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #4A90E2; }
    .info-card h4 { color: #4A90E2; margin-bottom: 10px; font-size: 16px; }
    .info-card p { font-size: 13px; color: #718096; margin: 0; }
    .rdv-item { padding: 15px; border-left: 4px solid #4A90E2; margin-bottom: 15px; background: #f5f7fa; border-radius: 8px; }
    .btn-approve { background: #28a745; color: #fff; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-size: 13px; }
    .btn-approve:hover { background: #218838; }
    .welcome-block { margin-bottom: 25px; }
    .welcome-block h2 { font-size: 18px; font-weight: 700; color: #2d3748; margin-bottom: 5px; }
    .welcome-block p { font-size: 14px; color: #718096; margin: 2px 0; }
</style>

<?php if ($error_message): ?>
<div class="alert alert-danger" style="padding: 15px; margin-bottom: 20px; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 8px;">
    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
</div>
<?php endif; ?>

<?php if (isset($user_info['medecin_statut']) && $user_info['medecin_statut'] === 'en_attente'): ?>
<div style="padding: 20px; margin-bottom: 25px; border-radius: 12px; background: #fff3cd; border-left: 4px solid #ffc107; color: #856404;">
    <h4 style="margin: 0 0 10px 0; color: #856404;"><i class="fas fa-clock"></i> Compte en attente d'approbation</h4>
    <p style="margin: 0; line-height: 1.6; font-size: 14px;">
        Votre compte est en attente d'approbation. Vous avez accès à votre espace avec des droits limités. 
        Une fois approuvé, vous recevrez votre matricule.
    </p>
</div>
<?php endif; ?>

<div class="welcome-block">
    <h2><i class="fas fa-user-md"></i> Bienvenue, Dr. <?php echo htmlspecialchars($user_info['nom']); ?></h2>
    <?php if (!empty($user_info['matricule_med'])): ?>
        <p><strong>Matricule :</strong> <?php echo htmlspecialchars($user_info['matricule_med']); ?></p>
    <?php endif; ?>
    <?php if ($specialisation): ?>
        <p><strong>Spécialisation :</strong> <?php echo htmlspecialchars($specialisation); ?></p>
    <?php endif; ?>
</div>

<!-- Cartes KPI (style admin) -->
<div class="kpi-grid">
    <div class="kpi-card blue">
        <div class="kpi-header">
            <div class="kpi-title">Rendez-vous</div>
            <div class="kpi-icon"><i class="fas fa-calendar-check"></i></div>
        </div>
        <div class="kpi-value"><?php echo $stats['rdv_total']; ?></div>
        <div class="kpi-subtitle">Total</div>
    </div>
    <div class="kpi-card orange">
        <div class="kpi-header">
            <div class="kpi-title">En attente</div>
            <div class="kpi-icon"><i class="fas fa-hourglass-half"></i></div>
        </div>
        <div class="kpi-value"><?php echo $stats['rdv_planifies']; ?></div>
        <div class="kpi-subtitle">À confirmer</div>
    </div>
    <div class="kpi-card green">
        <div class="kpi-header">
            <div class="kpi-title">Confirmés</div>
            <div class="kpi-icon"><i class="fas fa-check-circle"></i></div>
        </div>
        <div class="kpi-value"><?php echo $stats['rdv_confirmes']; ?></div>
        <div class="kpi-subtitle">Validés</div>
    </div>
    <div class="kpi-card red">
        <div class="kpi-header">
            <div class="kpi-title">Patients</div>
            <div class="kpi-icon"><i class="fas fa-users"></i></div>
        </div>
        <div class="kpi-value"><?php echo $stats['patients_total']; ?></div>
        <div class="kpi-subtitle">Mon service</div>
    </div>
</div>

<div class="dashboard-layout">
    <!-- Actions rapides -->
    <div class="quick-actions-card">
        <h3><i class="fas fa-bolt"></i> Actions Rapides</h3>
        <a href="mes-patients.php" class="btn-action"><i class="fas fa-users"></i> Mes Patients (<?php echo $stats['patients_total']; ?>)</a>
        <a href="demandes-service.php" class="btn-action"><i class="fas fa-inbox"></i> Demandes à confirmer</a>
        <a href="creer-ordonnance.php" class="btn-action"><i class="fas fa-prescription"></i> Créer une Ordonnance</a>
        <a href="creer-carnet.php" class="btn-action"><i class="fas fa-book-medical"></i> Créer un Carnet</a>
    </div>

    <!-- Rendez-vous récents + Info -->
    <div>
        <div class="recent-card" style="margin-bottom: 20px;">
            <h3><i class="fas fa-calendar"></i> Mes Rendez-vous Récents</h3>
            <?php if (empty($mes_rdv)): ?>
                <p style="color: #718096;">Aucun rendez-vous pour le moment.</p>
            <?php else: ?>
                <?php foreach (array_slice($mes_rdv, 0, 5) as $rdv): ?>
                    <div class="recent-item">
                        <div class="recent-item-avatar">
                            <?php echo strtoupper(substr($rdv['Prénom_patient'] ?? 'P', 0, 1)); ?>
                        </div>
                        <div class="recent-item-info">
                            <div class="recent-item-name"><?php echo htmlspecialchars(($rdv['Nom_patient'] ?? '') . ' ' . ($rdv['Prénom_patient'] ?? '')); ?></div>
                            <div class="recent-item-detail">
                                <?php echo htmlspecialchars($rdv['Nom_service'] ?? 'N/A'); ?> • 
                                <?php echo isset($rdv['Date_rdv']) ? date('d/m/Y H:i', strtotime($rdv['Date_rdv'])) : ''; ?> •
                                <span style="padding: 2px 6px; border-radius: 4px; font-size: 12px; <?php 
                                    if (($rdv['Statut'] ?? '') === 'confirmé') echo 'background:#28a745;color:white;';
                                    elseif (($rdv['Statut'] ?? '') === 'planifié') echo 'background:#ffc107;color:#333;';
                                    else echo 'background:#6c757d;color:white;'; ?>"><?php echo htmlspecialchars($rdv['Statut'] ?? ''); ?></span>
                            </div>
                            <?php if (($rdv['Statut'] ?? '') === 'planifié' && !empty($rdv['id_rdv'])): ?>
                                <button class="btn-approve" onclick="approuverRDV(<?php echo (int)$rdv['id_rdv']; ?>)"><i class="fas fa-check"></i> Confirmer</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="info-card">
            <h4><i class="fas fa-info-circle"></i> Information</h4>
            <p>Vous voyez toutes les données du service : <strong><?php echo htmlspecialchars($specialisation ?: '—'); ?></strong>. Tous les médecins de ce service voient les mêmes informations.</p>
        </div>
    </div>
</div>

<script>
function approuverRDV(id) {
    if (confirm('Confirmer ce rendez-vous ? Le patient sera notifié.')) {
        $.post('approuver-rdv.php', {id: id}, function(data) {
            if (data.success) { alert('Rendez-vous confirmé.'); location.reload(); }
            else { alert('Erreur : ' + (data.message || 'Inconnue')); }
        }, 'json').fail(function() { alert('Erreur réseau.'); });
    }
}
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
