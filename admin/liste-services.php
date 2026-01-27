<?php
/**
 * Liste de tous les services - Administration
 * Seuls les administrateurs peuvent accéder à cette page
 */

require_once 'partials/header.php';
require_once '../config/database_functions.php';

$services = [];
$message = '';
$message_type = '';

try {
    $services = getAllServices();
} catch (Exception $e) {
    $message = "Erreur lors de la récupération des services : " . $e->getMessage();
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
    
    .service-name {
        font-weight: 600;
        color: #2d3748;
        font-size: 16px;
    }
    
    .service-description {
        font-size: 14px;
        color: #718096;
        margin-top: 4px;
    }
    
    .service-tarif {
        font-weight: 700;
        color: #28a745;
        font-size: 18px;
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
        <h1><i class="fas fa-hospital"></i> Liste des Services</h1>
        <p>Consultez tous les services médicaux disponibles dans la clinique</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>" style="padding: 15px; border-radius: 8px; margin-bottom: 20px; background: <?php echo $message_type == 'danger' ? '#f8d7da' : '#d4edda'; ?>; color: <?php echo $message_type == 'danger' ? '#721c24' : '#155724'; ?>; border: 1px solid <?php echo $message_type == 'danger' ? '#f5c6cb' : '#c3e6cb'; ?>;">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="table-container">
        <div class="table-header">
            <div>
                <h2>Tous les Services</h2>
                <div class="table-count"><?php echo count($services); ?> service(s) disponible(s)</div>
            </div>
        </div>
        
        <?php if (empty($services)): ?>
            <div class="no-data">
                <i class="fas fa-hospital"></i>
                <p>Aucun service enregistré pour le moment.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom du Service</th>
                        <th>Description</th>
                        <th>Tarif</th>
                        <th>Date de création</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td>
                                <strong>#<?php echo htmlspecialchars($service['id_service']); ?></strong>
                            </td>
                            <td>
                                <div class="service-name">
                                    <?php echo htmlspecialchars($service['Nom_service']); ?>
                                </div>
                            </td>
                            <td>
                                <div class="service-description">
                                    <?php 
                                    $description = $service['Description'] ?? '';
                                    echo !empty($description) ? htmlspecialchars(substr($description, 0, 100)) . (strlen($description) > 100 ? '...' : '') : '-';
                                    ?>
                                </div>
                            </td>
                            <td>
                                <div class="service-tarif">
                                    <?php 
                                    $tarif = $service['Tarif'] ?? 0;
                                    echo number_format($tarif, 0, ',', ' ') . ' GNF';
                                    ?>
                                </div>
                            </td>
                            <td>
                                <?php 
                                if (!empty($service['Date_creation'])) {
                                    $date = new DateTime($service['Date_creation']);
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
