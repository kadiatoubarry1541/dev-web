<?php
/**
 * Liste de tous les médecins - Administration
 * Seuls les administrateurs peuvent accéder à cette page
 */

require_once 'partials/header.php';
require_once '../config/database_functions.php';

$medecins = [];
$message = '';
$message_type = '';

try {
    $medecins = getAllMedecins();
} catch (Exception $e) {
    $message = "Erreur lors de la récupération des médecins : " . $e->getMessage();
    $message_type = "danger";
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
    
    .medecin-photo {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        background: #e2e8f0;
    }
    
    .medecin-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .medecin-name {
        font-weight: 600;
        color: #2d3748;
    }
    
    .medecin-details {
        font-size: 13px;
        color: #718096;
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
        <h1><i class="fas fa-user-md"></i> Liste des Médecins</h1>
        <p>Gérez et consultez tous les médecins enregistrés dans le système</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>" style="padding: 15px; border-radius: 8px; margin-bottom: 20px; background: <?php echo $message_type == 'danger' ? '#f8d7da' : '#d4edda'; ?>; color: <?php echo $message_type == 'danger' ? '#721c24' : '#155724'; ?>; border: 1px solid <?php echo $message_type == 'danger' ? '#f5c6cb' : '#c3e6cb'; ?>;">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="table-container">
        <div class="table-header">
            <div>
                <h2>Tous les Médecins</h2>
                <div class="table-count"><?php echo count($medecins); ?> médecin(s) enregistré(s)</div>
            </div>
        </div>
        
        <?php if (empty($medecins)): ?>
            <div class="no-data">
                <i class="fas fa-user-md"></i>
                <p>Aucun médecin enregistré pour le moment.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Matricule</th>
                        <th>Nom complet</th>
                        <th>Spécialisation</th>
                        <th>Téléphone</th>
                        <th>Email</th>
                        <th>Statut</th>
                        <th>Date d'inscription</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medecins as $medecin): ?>
                        <tr>
                            <td>
                                <?php if (!empty($medecin['Photo_profil'])): ?>
                                    <img src="../<?php echo htmlspecialchars($medecin['Photo_profil']); ?>" alt="Photo" class="medecin-photo">
                                <?php else: ?>
                                    <div class="medecin-photo" style="display: flex; align-items: center; justify-content: center; color: #718096; font-weight: 600;">
                                        <?php echo strtoupper(substr($medecin['Nom_med'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($medecin['Matricule_med']); ?></strong>
                            </td>
                            <td>
                                <div class="medecin-info">
                                    <div>
                                        <div class="medecin-name">
                                            <?php echo htmlspecialchars($medecin['Nom_med'] . ' ' . $medecin['Prénom_med']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-success">
                                    <?php echo htmlspecialchars($medecin['Spécialisation_med']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($medecin['Tel_med'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($medecin['Email_med'] ?? '-'); ?></td>
                            <td>
                                <?php
                                $statut = $medecin['statut'] ?? 'en_attente';
                                $badge_class = 'badge-warning';
                                $statut_text = 'En attente';
                                
                                if ($statut == 'approuvé') {
                                    $badge_class = 'badge-success';
                                    $statut_text = 'Approuvé';
                                } elseif ($statut == 'refusé') {
                                    $badge_class = 'badge-danger';
                                    $statut_text = 'Refusé';
                                }
                                ?>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo $statut_text; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                if (!empty($medecin['Date_creation'])) {
                                    $date = new DateTime($medecin['Date_creation']);
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
