<?php
/**
 * Liste de tous les rendez-vous d'un service (côté médecin)
 * Le médecin voit tous les RDV de son service, pas seulement les siens.
 */

require_once __DIR__ . '/partials/header.php';
require_once __DIR__ . '/../config/bdd.php';
require_once __DIR__ . '/../config/database_functions.php';

$user_info      = getUserInfo();
$specialisation = $user_info['specialisation'] ?? '';
$rendez_vous    = [];
$message        = '';
$message_type   = '';

try {
    // On réutilise exactement la même logique que pour "Mes rendez-vous"
    // afin d'avoir la même liste que le tableau de bord / page médecin.
    if (!empty($user_info['id_med'] ?? null) && function_exists('getRendezVousByMedecin')) {
        $rendez_vous = getRendezVousByMedecin($user_info['id_med'], $specialisation);
        if ($rendez_vous === false) {
            $rendez_vous = [];
        }
    }
} catch (Exception $e) {
    $message = "Erreur lors de la récupération des rendez-vous du service : " . $e->getMessage();
    $message_type = "danger";
    error_log("Erreur liste-rendez-vous-service (médecin): " . $e->getMessage());
}

$page_title = "Rendez-vous du service";
?>

<style>
    .page-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 20px;
    }
    .page-header { margin-bottom: 30px; }
    .page-header h1 {
        font-size: 28px;
        font-weight: 700;
        color: #1e3a5f;
        margin-bottom: 8px;
    }
    .page-header p {
        color: #718096;
        font-size: 15px;
    }
    .table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    .table-header {
        background: #f7fafc;
        padding: 20px;
        border-bottom: 2px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .table-header h2 {
        font-size: 20px;
        font-weight: 600;
        color: #2d3748;
        margin: 0;
    }
    .table-count {
        color: #718096;
        font-size: 14px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    thead { background: #f7fafc; }
    th {
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        color: #4a5568;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
    }
    td {
        padding: 12px 16px;
        border-bottom: 1px solid #e2e8f0;
        color: #2d3748;
        font-size: 14px;
    }
    tbody tr:hover { background: #f7fafc; }
    .badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        display: inline-block;
    }
    .badge-success { background: #c6f6d5; color: #22543d; }
    .badge-warning { background: #feebc8; color: #7c2d12; }
    .badge-danger  { background: #fed7d7; color: #742a2a; }
    .badge-default { background: #e2e8f0; color: #4a5568; }
    .no-data {
        padding: 60px 20px;
        text-align: center;
        color: #718096;
    }
    .no-data i {
        font-size: 48px;
        margin-bottom: 20px;
        color: #cbd5e0;
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

<div class="page-container">
    <a href="index.php" class="btn-retour">
        <i class="fas fa-arrow-left"></i> Retour au tableau de bord
    </a>
    <div class="page-header">
        <h1><i class="fas fa-calendar-check"></i> Rendez-vous du service</h1>
        <p>Vous voyez ici tous les rendez-vous du service : <strong><?php echo htmlspecialchars($specialisation ?: '—'); ?></strong>.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>" style="padding: 15px; border-radius: 8px; margin-bottom: 20px; background: <?php echo $message_type == 'danger' ? '#f8d7da' : '#d4edda'; ?>; color: <?php echo $message_type == 'danger' ? '#721c24' : '#155724'; ?>; border: 1px solid <?php echo $message_type == 'danger' ? '#f5c6cb' : '#c3e6cb'; ?>;">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="table-container">
        <div class="table-header">
            <div>
                <h2>Tous les rendez-vous du service</h2>
                <div class="table-count"><?php echo count($rendez_vous); ?> rendez-vous enregistré(s)</div>
            </div>
        </div>

        <?php if (empty($rendez_vous)): ?>
            <div class="no-data">
                <i class="fas fa-calendar-times"></i>
                <p>Aucun rendez-vous enregistré pour ce service.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date & Heure</th>
                        <th>Patient</th>
                        <th>Médecin</th>
                        <th>Motif</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rendez_vous as $rdv): ?>
                        <tr>
                            <td>
                                <?php 
                                if (!empty($rdv['Date_rdv'])) {
                                    $date = new DateTime($rdv['Date_rdv']);
                                    echo $date->format('d/m/Y H:i');
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars(($rdv['Nom_patient'] ?? '') . ' ' . ($rdv['Prénom_patient'] ?? '')); ?></strong><br>
                                <span style="font-size: 12px; color: #718096;"><?php echo htmlspecialchars($rdv['Matricule_patient'] ?? '-'); ?></span>
                            </td>
                            <td>
                                <?php if (!empty($rdv['Nom_med']) || !empty($rdv['Prénom_med'])): ?>
                                    Dr. <?php echo htmlspecialchars(($rdv['Prénom_med'] ?? '') . ' ' . ($rdv['Nom_med'] ?? '')); ?>
                                <?php else: ?>
                                    <span style="color:#718096;">Non affecté</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $motif = $rdv['Motif'] ?? '';
                                echo !empty($motif) ? htmlspecialchars(substr($motif, 0, 60)) . (strlen($motif) > 60 ? '...' : '') : '-';
                                ?>
                            </td>
                            <td>
                                <?php
                                $statut = strtolower(trim($rdv['Statut'] ?? 'planifié'));
                                $badge_class = 'badge-default';
                                $label = ucfirst($statut ?: 'planifié');
                                if ($statut === 'confirmé') { $badge_class = 'badge-success'; $label = 'Confirmé'; }
                                elseif ($statut === 'planifié') { $badge_class = 'badge-warning'; $label = 'Planifié'; }
                                elseif ($statut === 'annulé') { $badge_class = 'badge-danger'; $label = 'Annulé'; }
                                ?>
                                <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($label); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>

