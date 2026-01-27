<?php
/**
 * Tableau de bord patient - même design que l'administrateur
 */
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/permissions.php';
require_once __DIR__ . '/../config/database_functions.php';

requireLogin('../login.php');
if (!hasRole('patient')) {
    header('Location: ../index.php');
    exit;
}

$user_info = getUserInfo();
$id_patient = $user_info['id_patient'] ?? $_SESSION['id_patient'] ?? null;

// Toujours rafraîchir id_patient depuis la base pour afficher les rendez-vous (lien users <-> PATIENTS)
try {
    $pdo = bdd();
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $email = trim($user_info['email'] ?? '');
    if (!$id_patient && $user_id) {
        $stmt = $pdo->prepare("SELECT u.id_patient, p.Matricule_patient, p.id_patient as pid FROM users u LEFT JOIN PATIENTS p ON u.id_patient = p.id_patient WHERE u.id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['id_patient'])) {
            $id_patient = (int)$row['id_patient'];
            $_SESSION['id_patient'] = $id_patient;
            $_SESSION['matricule_patient'] = $row['Matricule_patient'] ?? $_SESSION['matricule_patient'] ?? null;
            $user_info['id_patient'] = $id_patient;
        }
    }
    if (!$id_patient && !empty($email)) {
        $stmt2 = $pdo->prepare("SELECT id_patient, Matricule_patient FROM PATIENTS WHERE LOWER(TRIM(COALESCE(Email_patient,''))) = LOWER(?) LIMIT 1");
        $stmt2->execute([$email]);
        $pat = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($pat && !empty($pat['id_patient'])) {
            $id_patient = (int)$pat['id_patient'];
            $_SESSION['id_patient'] = $id_patient;
            $_SESSION['matricule_patient'] = $pat['Matricule_patient'] ?? null;
            $user_info['id_patient'] = $id_patient;
            $pdo->prepare("UPDATE users SET id_patient = ? WHERE id = ?")->execute([$id_patient, $user_id]);
        }
    }
    if (!$id_patient && !empty($user_info['matricule_patient'])) {
        $p = trouverPatientParMatriculeTouteBase($user_info['matricule_patient'], $user_info['nom'] ?? '');
        if ($p && !empty($p['id_patient'])) {
            $id_patient = (int)$p['id_patient'];
            $_SESSION['id_patient'] = $id_patient;
            $_SESSION['matricule_patient'] = $p['Matricule_patient'] ?? null;
            $user_info['id_patient'] = $id_patient;
            $pdo->prepare("UPDATE users SET id_patient = ? WHERE id = ?")->execute([$id_patient, $user_id]);
        }
    }
} catch (Exception $e) {
    error_log("Erreur résolution id_patient dashboard: " . $e->getMessage());
}

$stats = [
    'rdv_total' => 0,
    'rdv_confirmes' => 0,
    'rdv_attente' => 0,
    'consultations' => 0,
];
$rendez_vous = [];
$rendez_vous_confirmes = [];
$rendez_vous_attente = [];

if ($id_patient) {
    try {
        $rendez_vous = getRendezVousByPatient($id_patient);
        $stats['rdv_total'] = count($rendez_vous);
        foreach ($rendez_vous as $r) {
            $s = $r['Statut'] ?? '';
            if ($s === 'confirmé' || $s === 'terminé') {
                $stats['rdv_confirmes']++;
                $rendez_vous_confirmes[] = $r;
            } elseif ($s === 'planifié') {
                $stats['rdv_attente']++;
                $rendez_vous_attente[] = $r;
            }
        }
        $consultations = getConsultationsByPatient($id_patient);
        $stats['consultations'] = is_array($consultations) ? count($consultations) : 0;
    } catch (Exception $e) {
        error_log("Erreur dashboard patient: " . $e->getMessage());
    }
}

$page_title = 'Espace Patient';
require_once __DIR__ . '/partials/header.php';
?>

<style>
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .kpi-card {
        background: white; border-radius: 12px; padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s; position: relative; overflow: hidden;
    }
    .kpi-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--kpi-color); }
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
        display: flex; align-items: center; justify-content: center; color: white; font-size: 14px;
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
    .quick-actions-card .btn-action.green { background: #28a745; }
    .quick-actions-card .btn-action.green:hover { background: #218838; }
    .welcome-block { margin-bottom: 25px; }
    .welcome-block h2 { font-size: 18px; font-weight: 700; color: #2d3748; margin-bottom: 5px; }
    .welcome-block p { font-size: 14px; color: #718096; margin: 2px 0; }
</style>

<div class="welcome-block">
    <h2><i class="fas fa-user"></i> Bienvenue, <?php echo htmlspecialchars($user_info['nom']); ?></h2>
    <?php 
    $matricule_aff = $user_info['matricule_patient'] ?? $_SESSION['matricule_patient'] ?? null;
    if (!empty($matricule_aff)): ?>
        <p><strong>Matricule :</strong> <?php echo htmlspecialchars($matricule_aff); ?></p>
    <?php endif; ?>
    <?php if ($id_patient && $stats['rdv_total'] == 0): ?>
        <p style="margin-top: 8px; color: #4A90E2; font-size: 14px;"><i class="fas fa-calendar-day"></i> Vous n'avez pas encore de rendez-vous. <a href="../rendez-vous.php" style="color: #28a745; font-weight: 600; text-decoration: none;">Prendre rendez-vous →</a></p>
    <?php elseif ($id_patient && $stats['rdv_total'] > 0): ?>
        <p style="margin-top: 8px; color: #28a745; font-size: 14px;"><i class="fas fa-check-circle"></i> Vous avez <?php echo $stats['rdv_total']; ?> rendez-vous enregistré(s).</p>
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
        <div class="kpi-value"><?php echo $stats['rdv_attente']; ?></div>
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
            <div class="kpi-title">Consultations</div>
            <div class="kpi-icon"><i class="fas fa-stethoscope"></i></div>
        </div>
        <div class="kpi-value"><?php echo $stats['consultations']; ?></div>
        <div class="kpi-subtitle">Réalisées</div>
    </div>
</div>

<div class="dashboard-layout">
    <div class="quick-actions-card">
        <h3><i class="fas fa-bolt"></i> Actions Rapides</h3>
        <a href="../profil.php" class="btn-action"><i class="fas fa-user"></i> Mon Profil</a>
        <a href="../rendez-vous.php" class="btn-action green"><i class="fas fa-calendar-plus"></i> Prendre rendez-vous</a>
        <a href="../paiements/liste-paiements.php" class="btn-action"><i class="fas fa-money-bill-wave"></i> Mes Paiements</a>
    </div>

    <div class="recent-card" id="mes-rendez-vous">
        <h3><i class="fas fa-calendar-alt"></i> Mes rendez-vous (<?php echo $stats['rdv_total']; ?>)</h3>
        <p class="kpi-subtitle" style="margin-bottom: 20px;">Tous les rendez-vous que vous avez pris.</p>
        <?php if (empty($rendez_vous)): ?>
            <div style="background: #f0f7ff; border: 1px solid #4A90E2; border-radius: 8px; padding: 20px; margin-bottom: 16px;">
                <p style="color: #1e3a5f; margin: 0 0 8px 0;"><strong><i class="fas fa-info-circle"></i> Aucun rendez-vous pour le moment.</strong></p>
                <p style="color: #555; font-size: 14px; margin: 0 0 16px 0;">Utilisez votre matricule pour réserver un créneau. Vos rendez-vous (en attente, confirmés, passés) s'afficheront ici et dans <strong>Mon profil</strong>.</p>
                <a href="../rendez-vous.php" class="btn-action green" style="display:inline-flex; align-items:center; gap:8px; padding: 12px 20px; border-radius: 8px; background: #28a745; color: white; text-decoration: none; font-weight: 600;"><i class="fas fa-calendar-plus"></i> Prendre rendez-vous</a>
            </div>
        <?php else: ?>
            <div style="max-height: 480px; overflow-y: auto; padding-right: 8px;">
            <?php if (!empty($rendez_vous_confirmes)): ?>
                <h4 style="font-size: 15px; font-weight: 600; color: #28a745; margin: 0 0 12px 0;"><i class="fas fa-check-circle"></i> Confirmés (<?php echo count($rendez_vous_confirmes); ?>)</h4>
                <div style="display: grid; gap: 12px; margin-bottom: 24px;">
                    <?php foreach ($rendez_vous_confirmes as $r): ?>
                        <div class="recent-item" style="padding: 14px 16px; background: #f8fff9; border-left: 4px solid #28a745; border-radius: 8px;">
                            <div class="recent-item-info">
                                <div class="recent-item-name"><?php echo htmlspecialchars($r['Nom_service'] ?? 'Rendez-vous'); ?></div>
                                <div class="recent-item-detail" style="margin-top: 6px;">
                                    <strong><i class="fas fa-calendar-day"></i> <?php echo isset($r['Date_rdv']) ? date('d/m/Y à H:i', strtotime($r['Date_rdv'])) : ''; ?></strong>
                                    <?php if (!empty($r['Prénom_med']) || !empty($r['Nom_med'])): ?>
                                        <br><i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars(trim(($r['Prénom_med'] ?? '') . ' ' . ($r['Nom_med'] ?? ''))); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($r['Motif'])): ?>
                                        <br><small><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars(mb_substr($r['Motif'], 0, 80)) . (mb_strlen($r['Motif']) > 80 ? '…' : ''); ?></small>
                                    <?php endif; ?>
                                    <div style="margin-top: 8px;"><span style="padding: 4px 10px; border-radius: 6px; font-size: 12px; background: #28a745; color: white;"><i class="fas fa-check"></i> <?php echo ucfirst($r['Statut'] ?? 'confirmé'); ?></span></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($rendez_vous_attente)): ?>
                <h4 style="font-size: 15px; font-weight: 600; color: #d39e00; margin: 0 0 12px 0;"><i class="fas fa-clock"></i> En attente de confirmation (<?php echo count($rendez_vous_attente); ?>)</h4>
                <div style="display: grid; gap: 12px; margin-bottom: 24px;">
                    <?php foreach ($rendez_vous_attente as $r): ?>
                        <div class="recent-item" style="padding: 14px 16px; background: #fffdf5; border-left: 4px solid #ffc107; border-radius: 8px;">
                            <div class="recent-item-info">
                                <div class="recent-item-name"><?php echo htmlspecialchars($r['Nom_service'] ?? 'Rendez-vous'); ?></div>
                                <div class="recent-item-detail" style="margin-top: 6px;">
                                    <strong><i class="fas fa-calendar-day"></i> <?php echo isset($r['Date_rdv']) ? date('d/m/Y à H:i', strtotime($r['Date_rdv'])) : ''; ?></strong>
                                    <?php if (!empty($r['Prénom_med']) || !empty($r['Nom_med'])): ?>
                                        <br><i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars(trim(($r['Prénom_med'] ?? '') . ' ' . ($r['Nom_med'] ?? ''))); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($r['Motif'])): ?>
                                        <br><small><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars(mb_substr($r['Motif'], 0, 80)) . (mb_strlen($r['Motif']) > 80 ? '…' : ''); ?></small>
                                    <?php endif; ?>
                                    <div style="margin-top: 8px;"><span style="padding: 4px 10px; border-radius: 6px; font-size: 12px; background: #ffc107; color: #333;"><i class="fas fa-hourglass-half"></i> En attente</span></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php
            $rdv_autres = array_filter($rendez_vous, function($r) {
                $s = $r['Statut'] ?? '';
                return !in_array($s, ['confirmé', 'terminé', 'planifié']);
            });
            if (!empty($rdv_autres)): ?>
                <h4 style="font-size: 15px; font-weight: 600; color: #6c757d; margin: 0 0 12px 0;"><i class="fas fa-list"></i> Autres (<?php echo count($rdv_autres); ?>)</h4>
                <div style="display: grid; gap: 12px;">
                    <?php foreach ($rdv_autres as $r): ?>
                        <div class="recent-item" style="padding: 14px 16px; background: #f8f9fa; border-left: 4px solid #6c757d; border-radius: 8px;">
                            <div class="recent-item-info">
                                <div class="recent-item-name"><?php echo htmlspecialchars($r['Nom_service'] ?? 'Rendez-vous'); ?></div>
                                <div class="recent-item-detail" style="margin-top: 6px;">
                                    <strong><?php echo isset($r['Date_rdv']) ? date('d/m/Y à H:i', strtotime($r['Date_rdv'])) : ''; ?></strong>
                                    <div style="margin-top: 6px;"><span class="badge badge-secondary"><?php echo ucfirst($r['Statut'] ?? '—'); ?></span></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            </div>
            <div style="margin-top: 20px; display: flex; flex-wrap: wrap; gap: 12px;">
                <a href="../rendez-vous.php" class="btn-action green" style="display:inline-flex; align-items:center; gap:8px; padding: 10px 18px; border-radius: 8px; background: #28a745; color: white; text-decoration: none; font-weight: 600;"><i class="fas fa-calendar-plus"></i> Prendre un rendez-vous</a>
                <a href="../profil.php" style="display:inline-flex; align-items:center; gap:8px; padding: 10px 18px; color: #4A90E2; font-weight: 600; text-decoration: none;"><i class="fas fa-user"></i> Mon profil</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
