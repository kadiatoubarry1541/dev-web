<?php
/**
 * Vue "interface de service" pour l'administrateur.
 * Permet à l'admin de voir, pour un service donné, une vue
 * très proche du tableau de bord médecin : statistiques RDV,
 * patients du service, rendez-vous récents, etc.
 */

require_once 'partials/header.php';
require_once '../config/database_functions.php';

$message       = '';
$message_type  = '';
$service       = null;
$id_service    = isset($_GET['id_service']) ? (int) $_GET['id_service'] : 0;

$stats = [
    'rdv_total'       => 0,
    'rdv_planifies'   => 0,
    'rdv_confirmes'   => 0,
    'patients_total'  => 0,
];

$rendez_vous = [];
$patients    = [];

if ($id_service <= 0) {
    $message = "Service invalide ou non spécifié.";
    $message_type = "danger";
} else {
    try {
        $service = getServiceById($id_service);

        if (!$service) {
            $message = "Service introuvable.";
            $message_type = "danger";
        } else {
            // 1) Tous les rendez-vous du service
            if (function_exists('getRendezVousByService')) {
                $rendez_vous = getRendezVousByService($id_service);
            }

            if (!is_array($rendez_vous)) {
                $rendez_vous = [];
            }

            $stats['rdv_total'] = count($rendez_vous);

            // Répartition planifiés / confirmés (logique proche de mes-rendez-vous.php)
            foreach ($rendez_vous as $rdv) {
                $statut_brut = isset($rdv['Statut']) ? $rdv['Statut'] : '';
                $statut = strtolower(trim($statut_brut));

                if (in_array($statut, ['planifié', 'planifie'])) {
                    $stats['rdv_planifies']++;
                    continue;
                }

                if (in_array($statut, ['annulé', 'annule'])) {
                    // On ne les compte pas dans confirmés
                    continue;
                }

                if ($statut !== '') {
                    $stats['rdv_confirmes']++;
                }
            }

            // 2) Compléter les compteurs avec les DEMANDES du service
            try {
                $pdo = bdd();

                // Demandes en attente (accueil ou service)
                $stmt_dem_att = $pdo->prepare(
                    "SELECT COUNT(*) FROM DEMANDE_RENDEZ_VOUS 
                     WHERE id_service = ? AND statut IN ('en_attente_accueil','en_attente_service')"
                );
                $stmt_dem_att->execute([$id_service]);
                $nb_dem_att = (int)$stmt_dem_att->fetchColumn();

                // Demandes déjà confirmées/traitées par le service
                $stmt_dem_ok = $pdo->prepare(
                    "SELECT COUNT(*) FROM DEMANDE_RENDEZ_VOUS 
                     WHERE id_service = ? AND statut = 'traitee'"
                );
                $stmt_dem_ok->execute([$id_service]);
                $nb_dem_ok = (int)$stmt_dem_ok->fetchColumn();

                $stats['rdv_planifies'] += $nb_dem_att;
                $stats['rdv_confirmes'] += $nb_dem_ok;
                $stats['rdv_total'] = max($stats['rdv_total'], $stats['rdv_planifies'] + $stats['rdv_confirmes']);
            } catch (Exception $e_dem) {
                error_log("vue-service (demandes service): " . $e_dem->getMessage());
            }

            // 3) Patients du service (même logique que patients-rendez-vous médecin)
            if (function_exists('getPatientsWithConfirmedRdvByService')) {
                $patients = getPatientsWithConfirmedRdvByService($id_service);
            } elseif (function_exists('getPatientsByService')) {
                $patients = getPatientsByService($id_service);
            } else {
                $patients = [];
            }

            if (!is_array($patients)) {
                $patients = [];
            }

            $stats['patients_total'] = count($patients);
        }
    } catch (Exception $e) {
        $message = "Erreur lors du chargement de la vue service : " . $e->getMessage();
        $message_type = "danger";
        $rendez_vous = [];
        $patients = [];
    }
}
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

    .dashboard-layout { display: grid; grid-template-columns: 1.4fr 1fr; gap: 30px; margin-bottom: 30px; }
    @media (max-width: 1200px) { .dashboard-layout { grid-template-columns: 1fr; } }

    .card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }

    .card h3 {
        font-size: 18px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e2e8f0;
    }

    .recent-item { display: flex; align-items: center; gap: 15px; padding: 12px 0; border-bottom: 1px solid #f5f7fa; }
    .recent-item:last-child { border-bottom: none; }
    .recent-item-avatar {
        width: 40px; height: 40px; border-radius: 50%; background: #4A90E2;
        display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px;
    }
    .recent-item-info { flex: 1; }
    .recent-item-name { font-weight: 600; color: #2d3748; margin-bottom: 3px; }
    .recent-item-detail { font-size: 13px; color: #718096; }

    .badge-statut {
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
    }
    .badge-planifie { background:#fff3cd; color:#856404; }
    .badge-confirme { background:#d4edda; color:#155724; }
    .badge-autre    { background:#e2e8f0; color:#4a5568; }

    .patient-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #f5f7fa;
        font-size: 14px;
    }
    .patient-row:last-child { border-bottom: none; }
    .patient-main { display:flex; align-items:center; gap:10px; }
    .patient-avatar {
        width: 36px; height: 36px; border-radius: 50%; background:#edf2f7;
        display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:600; color:#4a5568;
    }
    .text-muted { color:#718096; font-size:13px; }
</style>

<div class="page-container">
    <a href="liste-services.php" class="btn-retour">
        <i class="fas fa-arrow-left"></i> Retour à la liste des services
    </a>

    <div class="page-header">
        <h1>
            <i class="fas fa-hospital"></i>
            Vue détaillée du service
            <?php if ($service): ?>
                « <?php echo htmlspecialchars($service['Nom_service']); ?> »
            <?php endif; ?>
        </h1>
        <?php if ($service && !empty($service['Description'])): ?>
            <p><?php echo htmlspecialchars($service['Description']); ?></p>
        <?php else: ?>
            <p>Interface d'observation : cette vue permet à l'administrateur de voir les mêmes informations globales que les médecins de ce service.</p>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>" style="padding: 15px; border-radius: 8px; margin-bottom: 20px; background: <?php echo $message_type == 'danger' ? '#f8d7da' : '#d4edda'; ?>; color: <?php echo $message_type == 'danger' ? '#721c24' : '#155724'; ?>; border: 1px solid <?php echo $message_type == 'danger' ? '#f5c6cb' : '#c3e6cb'; ?>;">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($service && empty($message_type)): ?>
        <!-- KPIs similaires au tableau de bord médecin -->
        <div class="kpi-grid">
            <div class="kpi-card blue">
                <div class="kpi-header">
                    <div class="kpi-title">Rendez-vous</div>
                    <div class="kpi-icon"><i class="fas fa-calendar-check"></i></div>
                </div>
                <div class="kpi-value"><?php echo $stats['rdv_total']; ?></div>
                <div class="kpi-subtitle">Total (RDV + demandes)</div>
            </div>
            <div class="kpi-card orange">
                <div class="kpi-header">
                    <div class="kpi-title">En attente</div>
                    <div class="kpi-icon"><i class="fas fa-hourglass-half"></i></div>
                </div>
                <div class="kpi-value"><?php echo $stats['rdv_planifies']; ?></div>
                <div class="kpi-subtitle">Rendez-vous ou demandes à confirmer</div>
            </div>
            <div class="kpi-card green">
                <div class="kpi-header">
                    <div class="kpi-title">Confirmés</div>
                    <div class="kpi-icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="kpi-value"><?php echo $stats['rdv_confirmes']; ?></div>
                <div class="kpi-subtitle">Rendez-vous/demandes validés</div>
            </div>
            <div class="kpi-card red">
                <div class="kpi-header">
                    <div class="kpi-title">Patients</div>
                    <div class="kpi-icon"><i class="fas fa-users"></i></div>
                </div>
                <div class="kpi-value"><?php echo $stats['patients_total']; ?></div>
                <div class="kpi-subtitle">Patients passés par ce service</div>
            </div>
        </div>

        <div class="dashboard-layout">
            <!-- Colonne rendez-vous récents -->
            <div>
                <div class="card">
                    <h3><i class="fas fa-calendar"></i> Rendez-vous récents du service</h3>
                    <?php if (empty($rendez_vous)): ?>
                        <p class="text-muted">Aucun rendez-vous enregistré pour ce service.</p>
                    <?php else: ?>
                        <?php foreach (array_slice($rendez_vous, 0, 8) as $rdv): ?>
                            <div class="recent-item">
                                <div class="recent-item-avatar">
                                    <?php echo strtoupper(substr($rdv['Prénom_patient'] ?? 'P', 0, 1)); ?>
                                </div>
                                <div class="recent-item-info">
                                    <div class="recent-item-name">
                                        <?php echo htmlspecialchars(($rdv['Nom_patient'] ?? '') . ' ' . ($rdv['Prénom_patient'] ?? '')); ?>
                                    </div>
                                    <div class="recent-item-detail">
                                        <?php if (!empty($rdv['Date_rdv'])): ?>
                                            <?php echo date('d/m/Y H:i', strtotime($rdv['Date_rdv'])); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                        •
                                        <?php
                                        $statut = strtolower(trim($rdv['Statut'] ?? ''));
                                        $class  = 'badge-autre';
                                        $label  = ucfirst($statut ?: 'inconnu');
                                        if (in_array($statut, ['planifié', 'planifie'])) { $class = 'badge-planifie'; $label = 'Planifié'; }
                                        elseif (in_array($statut, ['confirmé', 'confirme', 'terminé', 'termine'])) { $class = 'badge-confirme'; $label = 'Confirmé'; }
                                        ?>
                                        <span class="badge-statut <?php echo $class; ?>">
                                            <?php echo htmlspecialchars($label); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Colonne patients -->
            <div>
                <div class="card">
                    <h3><i class="fas fa-users"></i> Patients du service</h3>
                    <?php if (empty($patients)): ?>
                        <p class="text-muted">Aucun patient n'a encore de rendez-vous confirmé dans ce service.</p>
                    <?php else: ?>
                        <?php foreach (array_slice($patients, 0, 10) as $patient): ?>
                            <div class="patient-row">
                                <div class="patient-main">
                                    <div class="patient-avatar">
                                        <?php echo strtoupper(substr($patient['Prénom_patient'] ?? $patient['Nom_patient'] ?? 'P', 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div><strong><?php echo htmlspecialchars(($patient['Prénom_patient'] ?? '') . ' ' . ($patient['Nom_patient'] ?? '')); ?></strong></div>
                                        <div class="text-muted">
                                            <?php echo htmlspecialchars($patient['Matricule_patient'] ?? '-'); ?> •
                                            <?php echo htmlspecialchars($patient['Email_patient'] ?? ''); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-muted">
                                    <?php if (!empty($patient['Date_rdv'])): ?>
                                        Dernier RDV<br>
                                        <?php echo date('d/m', strtotime($patient['Date_rdv'])); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($patients) > 10): ?>
                            <p class="text-muted" style="margin-top:10px;">
                                + <?php echo count($patients) - 10; ?> autre(s) patient(s) — voir la liste complète via le bouton « Patients » du service.
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="card">
                    <h3><i class="fas fa-eye"></i> Rappel</h3>
                    <p class="text-muted">
                        Cette page est une <strong>vue de supervision</strong> :
                        elle regroupe les mêmes informations globales que voient les médecins de ce service
                        (volume de rendez-vous, patients, rendez-vous récents), sans se limiter à un seul médecin.
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'partials/footer.php'; ?>

