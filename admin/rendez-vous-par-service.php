<?php
/**
 * Liste des rendez-vous d'un service - Administration
 * L'administrateur voit tous les rendez-vous rattachés à un service donné.
 */

require_once 'partials/header.php';
require_once '../config/database_functions.php';

$message        = '';
$message_type   = '';
$service        = null;
$rendez_vous    = [];

$id_service       = isset($_GET['id_service']) ? (int) $_GET['id_service'] : 0;
$only_confirmed   = isset($_GET['seulement_confirmes']) && $_GET['seulement_confirmes'] === '1';

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
            // 1) Rendez-vous "classiques" venant de la table RENDEZ_VOUS
            $rendez_vous = getRendezVousByService($id_service);

            // 2) Compléter avec les DEMANDES marquées "traitee" qui n'ont PAS encore
            //    de ligne correspondante dans RENDEZ_VOUS (même logique que côté médecin).
            try {
                $pdo = bdd();
                $sql_dem = "SELECT d.*, s.Nom_service 
                            FROM DEMANDE_RENDEZ_VOUS d
                            LEFT JOIN SERVICES s ON d.id_service = s.id_service
                            LEFT JOIN RENDEZ_VOUS r 
                                ON r.Date_rdv = d.Date_rdv_souhaitee
                               AND (r.id_service = d.id_service OR (r.id_service IS NULL AND d.id_service IS NULL))
                            WHERE d.id_service = ?
                              AND d.statut = 'traitee'
                              AND r.id_rdv IS NULL
                            ORDER BY d.Date_rdv_souhaitee DESC";
                $stmt_dem = $pdo->prepare($sql_dem);
                $stmt_dem->execute([$id_service]);
                $demandes_traitees = $stmt_dem->fetchAll(PDO::FETCH_ASSOC);

                foreach ($demandes_traitees as $d) {
                    $rendez_vous[] = [
                        'Date_rdv'        => $d['Date_rdv_souhaitee'] ?? null,
                        'Nom_patient'     => $d['nom_demandeur'] ?? '',
                        'Prénom_patient'  => '',
                        'Matricule_patient'=> $d['matricule_demandeur'] ?? '',
                        'Nom_med'         => null,
                        'Prénom_med'      => null,
                        'Spécialisation_med' => null,
                        'Nom_service'     => $d['Nom_service'] ?? ($service['Nom_service'] ?? 'Service'),
                        'Motif'           => $d['motif'] ?? '',
                        'Statut'          => 'confirmé',
                        'Date_creation'   => $d['Date_rdv_souhaitee'] ?? null,
                    ];
                }
            } catch (Exception $e_dem) {
                error_log('rendez-vous-par-service (demandes traitee): ' . $e_dem->getMessage());
            }

            // 3) Calcul des compteurs pour l'affichage (total / confirmés)
            $nb_total_rdv = count($rendez_vous);
            $nb_confirmes = 0;
            foreach ($rendez_vous as $rdv_tmp) {
                $statut_tmp = strtolower(trim($rdv_tmp['Statut'] ?? ''));
                if ($statut_tmp === 'confirmé') {
                    $nb_confirmes++;
                }
            }

            // 4) Si demandé, ne garder que les rendez-vous confirmés
            if ($only_confirmed) {
                $rendez_vous = array_values(array_filter($rendez_vous, function ($rdv) {
                    $statut = strtolower(trim($rdv['Statut'] ?? ''));
                    return $statut === 'confirmé';
                }));
            }
        }
    } catch (Exception $e) {
        $message = "Erreur lors de la récupération des données : " . $e->getMessage();
        $message_type = "danger";
    }
}
?>

<style>
    .page-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .page-header {
        margin-bottom: 30px;
    }

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

    .badge-service {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        background: #ebf8ff;
        color: #2b6cb0;
        font-size: 13px;
        font-weight: 600;
    }

    .badge-service i {
        font-size: 13px;
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
        flex-wrap: wrap;
        gap: 10px;
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

    thead {
        background: #f7fafc;
    }

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

    tbody tr:hover {
        background: #f7fafc;
    }

    .badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        display: inline-block;
    }

    .badge-success {
        background: #c6f6d5;
        color: #22543d;
    }

    .badge-warning {
        background: #feebc8;
        color: #7c2d12;
    }

    .badge-danger  {
        background: #fed7d7;
        color: #742a2a;
    }

    .badge-default {
        background: #e2e8f0;
        color: #4a5568;
    }

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
    <a href="liste-services.php" class="btn-retour">
        <i class="fas fa-arrow-left"></i> Retour à la liste des services
    </a>

    <div class="page-header">
        <h1><i class="fas fa-calendar-check"></i> Rendez-vous du service</h1>
        <?php if ($service): ?>
            <p>
                <span class="badge-service">
                    <i class="fas fa-hospital"></i>
                    <?php echo htmlspecialchars($service['Nom_service']); ?>
                </span>
                <?php if (!empty($service['Description'])): ?>
                    <br>
                    <span style="font-size: 13px; color: #4a5568;">
                        <?php echo htmlspecialchars($service['Description']); ?>
                    </span>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p>Les rendez-vous ne peuvent pas être affichés car le service est introuvable.</p>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>" style="padding: 15px; border-radius: 8px; margin-bottom: 20px; background: <?php echo $message_type == 'danger' ? '#f8d7da' : '#d4edda'; ?>; color: <?php echo $message_type == 'danger' ? '#721c24' : '#155724'; ?>; border: 1px solid <?php echo $message_type == 'danger' ? '#f5c6cb' : '#c3e6cb'; ?>;">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($service && empty($message_type)): ?>
        <div class="table-container">
            <div class="table-header">
                <div>
                    <h2>
                        <?php if ($only_confirmed): ?>
                            Rendez-vous confirmés de ce service
                        <?php else: ?>
                            Tous les rendez-vous de ce service
                        <?php endif; ?>
                    </h2>
                    <div class="table-count">
                        <?php 
                        $total = isset($nb_total_rdv) ? $nb_total_rdv : count($rendez_vous);
                        $conf  = isset($nb_confirmes) ? $nb_confirmes : 0;
                        if ($only_confirmed) {
                            echo $conf . " rendez-vous confirmé(s)";
                        } else {
                            echo $total . " rendez-vous enregistré(s)";
                            echo " — " . $conf . " rendez-vous confirmé(s)";
                        }
                        ?>
                    </div>
                </div>
                <div>
                    <?php if ($service): ?>
                        <?php if ($only_confirmed): ?>
                            <a href="rendez-vous-par-service.php?id_service=<?php echo urlencode($service['id_service']); ?>" class="btn-action btn-action-rdv" style="background-color:#3182ce;">
                                <i class="fas fa-list"></i>
                                <span>Tous les rendez-vous</span>
                            </a>
                        <?php else: ?>
                            <a href="rendez-vous-par-service.php?id_service=<?php echo urlencode($service['id_service']); ?>&seulement_confirmes=1" class="btn-action btn-action-rdv" style="background-color:#38a169;">
                                <i class="fas fa-check-circle"></i>
                                <span>Voir seulement les confirmés</span>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (empty($rendez_vous)): ?>
                <div class="no-data">
                    <i class="fas fa-calendar-times"></i>
                    <p>Aucun rendez-vous n'est enregistré pour ce service.</p>
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
                            <th>Date de création</th>
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
                                    <span style="font-size: 12px; color: #718096;">
                                        <?php echo htmlspecialchars($rdv['Matricule_patient'] ?? '-'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($rdv['Nom_med']) || !empty($rdv['Prénom_med'])): ?>
                                        Dr. <?php echo htmlspecialchars(($rdv['Prénom_med'] ?? '') . ' ' . ($rdv['Nom_med'] ?? '')); ?><br>
                                        <span style="font-size: 12px; color: #718096;">
                                            <?php echo htmlspecialchars($rdv['Spécialisation_med'] ?? ''); ?>
                                        </span>
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
                                <td>
                                    <?php
                                    if (!empty($rdv['Date_creation'])) {
                                        $date = new DateTime($rdv['Date_creation']);
                                        echo $date->format('d/m/Y H:i');
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'partials/footer.php'; ?>

