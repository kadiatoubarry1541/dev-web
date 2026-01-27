<?php
/**
 * Liste de tous les rendez-vous - Administration
 * Seuls les administrateurs peuvent accéder à cette page
 */

require_once 'partials/header.php';
require_once '../config/bdd.php';

$rendez_vous = [];
$message = '';
$message_type = '';

try {
    $pdo = bdd();
    
    // Vérifier d'abord si la colonne id_med existe dans la table RENDEZ_VOUS
    try {
        $check_column = $pdo->query("SHOW COLUMNS FROM RENDEZ_VOUS LIKE 'id_med'");
        $column_exists = $check_column->rowCount() > 0;
        
        if (!$column_exists) {
            // Si la colonne n'existe pas, l'ajouter
            try {
                $pdo->exec("ALTER TABLE RENDEZ_VOUS ADD COLUMN id_med INT NULL AFTER id_patient");
                // Essayer d'ajouter l'index (peut échouer s'il existe déjà)
                try {
                    $pdo->exec("ALTER TABLE RENDEZ_VOUS ADD INDEX idx_med_rdv (id_med)");
                } catch (Exception $e) {
                    // Index peut déjà exister, ignorer
                }
                // Essayer d'ajouter la clé étrangère (peut échouer si elle existe déjà)
                try {
                    $pdo->exec("ALTER TABLE RENDEZ_VOUS ADD FOREIGN KEY (id_med) REFERENCES MEDECINS(id_med) ON DELETE RESTRICT ON UPDATE CASCADE");
                } catch (Exception $e) {
                    // Clé étrangère peut déjà exister, ignorer
                }
            } catch (Exception $e) {
                error_log("Erreur lors de l'ajout de la colonne id_med: " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        // Si la table n'existe pas ou autre erreur, continuer quand même
        error_log("Erreur vérification colonne id_med: " . $e->getMessage());
        $column_exists = false;
    }
    
    // Vérifier si la colonne id_service existe dans la table RENDEZ_VOUS
    try {
        $check_service_column = $pdo->query("SHOW COLUMNS FROM RENDEZ_VOUS LIKE 'id_service'");
        $service_column_exists = $check_service_column->rowCount() > 0;
        
        if (!$service_column_exists) {
            // Si la colonne n'existe pas, l'ajouter
            try {
                $pdo->exec("ALTER TABLE RENDEZ_VOUS ADD COLUMN id_service INT NULL AFTER id_med");
                // Essayer d'ajouter l'index (peut échouer s'il existe déjà)
                try {
                    $pdo->exec("ALTER TABLE RENDEZ_VOUS ADD INDEX idx_service_rdv (id_service)");
                } catch (Exception $e) {
                    // Index peut déjà exister, ignorer
                }
                // Essayer d'ajouter la clé étrangère (peut échouer si elle existe déjà)
                try {
                    $pdo->exec("ALTER TABLE RENDEZ_VOUS ADD FOREIGN KEY (id_service) REFERENCES SERVICES(id_service) ON DELETE SET NULL ON UPDATE CASCADE");
                } catch (Exception $e) {
                    // Clé étrangère peut déjà exister, ignorer
                }
            } catch (Exception $e) {
                error_log("Erreur lors de l'ajout de la colonne id_service: " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        // Si la table n'existe pas ou autre erreur, continuer quand même
        error_log("Erreur vérification colonne id_service: " . $e->getMessage());
        $service_column_exists = false;
    }
    
    // Utiliser la requête avec id_med et id_service (seront NULL si les colonnes n'existent toujours pas)
    $sql = "SELECT r.*, 
                   p.Nom_patient, p.Prénom_patient, p.Matricule_patient, p.Tel_patient as tel_patient,
                   m.Nom_med, m.Prénom_med, m.Spécialisation_med,
                   s.Nom_service, s.Tarif
            FROM RENDEZ_VOUS r
            LEFT JOIN PATIENTS p ON r.id_patient = p.id_patient
            LEFT JOIN MEDECINS m ON r.id_med = m.id_med
            LEFT JOIN SERVICES s ON r.id_service = s.id_service
            ORDER BY r.Date_rdv DESC";
    
    $stmt = $pdo->query($sql);
    $rendez_vous = $stmt->fetchAll();
} catch (Exception $e) {
    $message = "Erreur lors de la récupération des rendez-vous : " . $e->getMessage();
    $message_type = "danger";
    error_log("Erreur liste-rendez-vous: " . $e->getMessage());
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
        font-size: 32px;
        font-weight: 700;
        color: #1e3a5f;
        margin-bottom: 10px;
    }
    
    .page-header p {
        color: #718096;
        font-size: 16px;
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
    
    thead {
        background: #f7fafc;
    }
    
    th {
        padding: 15px 20px;
        text-align: left;
        font-weight: 600;
        color: #4a5568;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
    }
    
    td {
        padding: 15px 20px;
        border-bottom: 1px solid #e2e8f0;
        color: #2d3748;
    }
    
    tbody tr:hover {
        background: #f7fafc;
    }
    
    .badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
    }
    
    .badge-primary {
        background: #bee3f8;
        color: #2c5282;
    }
    
    .badge-success {
        background: #c6f6d5;
        color: #22543d;
    }
    
    .badge-warning {
        background: #feebc8;
        color: #7c2d12;
    }
    
    .badge-danger {
        background: #fed7d7;
        color: #742a2a;
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
    
    .no-data p {
        font-size: 16px;
        margin: 0;
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
        <h1><i class="fas fa-calendar-check"></i> Liste des Rendez-vous</h1>
        <p>Consultez tous les rendez-vous planifiés dans le système</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>" style="padding: 15px; border-radius: 8px; margin-bottom: 20px; background: <?php echo $message_type == 'danger' ? '#f8d7da' : '#d4edda'; ?>; color: <?php echo $message_type == 'danger' ? '#721c24' : '#155724'; ?>; border: 1px solid <?php echo $message_type == 'danger' ? '#f5c6cb' : '#c3e6cb'; ?>;">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="table-container">
        <div class="table-header">
            <div>
                <h2>Tous les Rendez-vous</h2>
                <div class="table-count"><?php echo count($rendez_vous); ?> rendez-vous enregistré(s)</div>
            </div>
        </div>
        
        <?php if (empty($rendez_vous)): ?>
            <div class="no-data">
                <i class="fas fa-calendar-times"></i>
                <p>Aucun rendez-vous enregistré pour le moment.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date & Heure</th>
                        <th>Patient</th>
                        <th>Médecin</th>
                        <th>Service</th>
                        <th>Motif</th>
                        <th>Statut</th>
                        <th>Date de création</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rendez_vous as $rdv): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?php 
                                    if (!empty($rdv['Date_rdv'])) {
                                        $date = new DateTime($rdv['Date_rdv']);
                                        echo $date->format('d/m/Y H:i');
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </strong>
                            </td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars(($rdv['Nom_patient'] ?? '') . ' ' . ($rdv['Prénom_patient'] ?? '')); ?></strong>
                                    <div style="font-size: 12px; color: #718096;">
                                        <?php echo htmlspecialchars($rdv['Matricule_patient'] ?? '-'); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars(($rdv['Nom_med'] ?? '') . ' ' . ($rdv['Prénom_med'] ?? '')); ?></strong>
                                    <div style="font-size: 12px; color: #718096;">
                                        <?php echo htmlspecialchars($rdv['Spécialisation_med'] ?? '-'); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($rdv['Nom_service'])): ?>
                                    <span class="badge badge-primary">
                                        <?php echo htmlspecialchars($rdv['Nom_service']); ?>
                                    </span>
                                    <?php if (!empty($rdv['Tarif'])): ?>
                                        <div style="font-size: 12px; color: #718096; margin-top: 4px;">
                                            <?php echo number_format($rdv['Tarif'], 0, ',', ' '); ?> GNF
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #718096;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $motif = $rdv['Motif'] ?? '';
                                echo !empty($motif) ? htmlspecialchars(substr($motif, 0, 50)) . (strlen($motif) > 50 ? '...' : '') : '-';
                                ?>
                            </td>
                            <td>
                                <?php
                                $statut = $rdv['Statut'] ?? 'planifié';
                                $badge_class = 'badge-primary';
                                $statut_text = 'Planifié';
                                
                                if ($statut == 'confirmé') {
                                    $badge_class = 'badge-success';
                                    $statut_text = 'Confirmé';
                                } elseif ($statut == 'annulé') {
                                    $badge_class = 'badge-danger';
                                    $statut_text = 'Annulé';
                                } elseif ($statut == 'terminé') {
                                    $badge_class = 'badge-success';
                                    $statut_text = 'Terminé';
                                }
                                ?>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo $statut_text; ?>
                                </span>
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
</div>

<?php require_once 'partials/footer.php'; ?>
