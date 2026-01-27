<?php
/**
 * Liste de toutes les consultations - Administration
 * Seuls les administrateurs peuvent accéder à cette page
 */

require_once 'partials/header.php';
require_once '../config/bdd.php';

$consultations = [];
$message = '';
$message_type = '';

try {
    $pdo = bdd();
    
    // Vérifier d'abord si la colonne id_med existe dans la table CONSULTATION
    try {
        $check_column = $pdo->query("SHOW COLUMNS FROM CONSULTATION LIKE 'id_med'");
        $column_exists = $check_column->rowCount() > 0;
        
        if (!$column_exists) {
            // Si la colonne n'existe pas, l'ajouter
            try {
                $pdo->exec("ALTER TABLE CONSULTATION ADD COLUMN id_med INT NULL AFTER id_patient");
                // Essayer d'ajouter l'index (peut échouer s'il existe déjà)
                try {
                    $pdo->exec("ALTER TABLE CONSULTATION ADD INDEX idx_med_consult (id_med)");
                } catch (Exception $e) {
                    // Index peut déjà exister, ignorer
                }
                // Essayer d'ajouter la clé étrangère (peut échouer si elle existe déjà)
                try {
                    $pdo->exec("ALTER TABLE CONSULTATION ADD FOREIGN KEY (id_med) REFERENCES MEDECINS(id_med) ON DELETE RESTRICT ON UPDATE CASCADE");
                } catch (Exception $e) {
                    // Clé étrangère peut déjà exister, ignorer
                }
            } catch (Exception $e) {
                error_log("Erreur lors de l'ajout de la colonne id_med dans CONSULTATION: " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        // Si la table n'existe pas ou autre erreur, continuer quand même
        error_log("Erreur vérification colonne id_med dans CONSULTATION: " . $e->getMessage());
        $column_exists = false;
    }
    
    // Utiliser la requête avec id_med
    $sql = "SELECT c.*, 
                   p.Nom_patient, p.Prénom_patient, p.Matricule_patient,
                   m.Nom_med, m.Prénom_med, m.Spécialisation_med
            FROM CONSULTATION c
            LEFT JOIN PATIENTS p ON c.id_patient = p.id_patient
            LEFT JOIN MEDECINS m ON c.id_med = m.id_med
            ORDER BY c.Date_consultation DESC";
    
    $stmt = $pdo->query($sql);
    $consultations = $stmt->fetchAll();
} catch (Exception $e) {
    $message = "Erreur lors de la récupération des consultations : " . $e->getMessage();
    $message_type = "danger";
    error_log("Erreur liste-consultations: " . $e->getMessage());
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
        <h1><i class="fas fa-stethoscope"></i> Liste des Consultations</h1>
        <p>Consultez toutes les consultations médicales effectuées</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>" style="padding: 15px; border-radius: 8px; margin-bottom: 20px; background: <?php echo $message_type == 'danger' ? '#f8d7da' : '#d4edda'; ?>; color: <?php echo $message_type == 'danger' ? '#721c24' : '#155724'; ?>; border: 1px solid <?php echo $message_type == 'danger' ? '#f5c6cb' : '#c3e6cb'; ?>;">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="table-container">
        <div class="table-header">
            <div>
                <h2>Toutes les Consultations</h2>
                <div class="table-count"><?php echo count($consultations); ?> consultation(s) enregistrée(s)</div>
            </div>
        </div>
        
        <?php if (empty($consultations)): ?>
            <div class="no-data">
                <i class="fas fa-stethoscope"></i>
                <p>Aucune consultation enregistrée pour le moment.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date & Heure</th>
                        <th>Patient</th>
                        <th>Médecin</th>
                        <th>Motif/Diagnostic</th>
                        <th>Note</th>
                        <th>Statut</th>
                        <th>Date de création</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($consultations as $consultation): ?>
                        <tr>
                            <td>
                                <strong>#<?php echo htmlspecialchars($consultation['id_consultation']); ?></strong>
                            </td>
                            <td>
                                <strong>
                                    <?php 
                                    if (!empty($consultation['Date_consultation'])) {
                                        $date = new DateTime($consultation['Date_consultation']);
                                        echo $date->format('d/m/Y H:i');
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </strong>
                            </td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars(($consultation['Nom_patient'] ?? '') . ' ' . ($consultation['Prénom_patient'] ?? '')); ?></strong>
                                    <div style="font-size: 12px; color: #718096;">
                                        <?php echo htmlspecialchars($consultation['Matricule_patient'] ?? '-'); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars(($consultation['Nom_med'] ?? '') . ' ' . ($consultation['Prénom_med'] ?? '')); ?></strong>
                                    <div style="font-size: 12px; color: #718096;">
                                        <?php echo htmlspecialchars($consultation['Spécialisation_med'] ?? '-'); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php 
                                $motif = $consultation['Motif_diagnostic'] ?? '';
                                echo !empty($motif) ? htmlspecialchars(substr($motif, 0, 50)) . (strlen($motif) > 50 ? '...' : '') : '-';
                                ?>
                            </td>
                            <td>
                                <?php 
                                $note = $consultation['Note'] ?? '';
                                echo !empty($note) ? htmlspecialchars(substr($note, 0, 50)) . (strlen($note) > 50 ? '...' : '') : '-';
                                ?>
                            </td>
                            <td>
                                <?php
                                $statut = $consultation['Statut'] ?? 'en_cours';
                                $badge_class = 'badge-primary';
                                $statut_text = 'En cours';
                                
                                if ($statut == 'terminée') {
                                    $badge_class = 'badge-success';
                                    $statut_text = 'Terminée';
                                } elseif ($statut == 'annulée') {
                                    $badge_class = 'badge-danger';
                                    $statut_text = 'Annulée';
                                }
                                ?>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo $statut_text; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                if (!empty($consultation['Date_creation'])) {
                                    $date = new DateTime($consultation['Date_creation']);
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
