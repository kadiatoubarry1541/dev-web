<?php
/**
 * Page de statistiques - Administration
 * Seuls les administrateurs peuvent accéder à cette page
 */

require_once 'partials/header.php';
require_once '../config/bdd.php';

$stats = [
    'medecins' => 0,
    'patients' => 0,
    'rendez_vous' => 0,
    'services' => 0,
    'medecins_attente' => 0,
    'medecins_approuves' => 0,
    'medecins_refuses' => 0,
    'consultations' => 0,
    'paiements' => 0,
    'rdv_planifies' => 0,
    'rdv_confirmes' => 0,
    'rdv_annules' => 0
];

$stats_services = [];
$stats_medecins = [];
$stats_mois = [];

try {
    $pdo = bdd();
    
    // Statistiques générales
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM MEDECINS");
    $result = $stmt->fetch();
    $stats['medecins'] = $result['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM PATIENTS");
    $result = $stmt->fetch();
    $stats['patients'] = $result['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM RENDEZ_VOUS");
    $result = $stmt->fetch();
    $stats['rendez_vous'] = $result['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM SERVICES");
    $result = $stmt->fetch();
    $stats['services'] = $result['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM MEDECINS WHERE statut = 'en_attente'");
    $result = $stmt->fetch();
    $stats['medecins_attente'] = $result['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM MEDECINS WHERE statut = 'approuvé'");
    $result = $stmt->fetch();
    $stats['medecins_approuves'] = $result['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM MEDECINS WHERE statut = 'refusé'");
    $result = $stmt->fetch();
    $stats['medecins_refuses'] = $result['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM CONSULTATION");
    $result = $stmt->fetch();
    $stats['consultations'] = $result['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM PAIEMENT");
    $result = $stmt->fetch();
    $stats['paiements'] = $result['count'] ?? 0;
    
    // Statistiques des rendez-vous par statut
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM RENDEZ_VOUS WHERE Statut = 'planifié'");
    $result = $stmt->fetch();
    $stats['rdv_planifies'] = $result['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM RENDEZ_VOUS WHERE Statut = 'confirmé'");
    $result = $stmt->fetch();
    $stats['rdv_confirmes'] = $result['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM RENDEZ_VOUS WHERE Statut = 'annulé'");
    $result = $stmt->fetch();
    $stats['rdv_annules'] = $result['count'] ?? 0;
    
    // Statistiques par service
    $stmt = $pdo->query("SELECT s.Nom_service, COUNT(r.id_rdv) as nb_rdv 
                         FROM SERVICES s 
                         LEFT JOIN RENDEZ_VOUS r ON s.id_service = r.id_service 
                         GROUP BY s.id_service, s.Nom_service 
                         ORDER BY nb_rdv DESC");
    $stats_services = $stmt->fetchAll();
    
    // Top médecins par nombre de consultations
    $stmt = $pdo->query("SELECT m.Nom_med, m.Prénom_med, m.Spécialisation_med, COUNT(c.id_consultation) as nb_consultations
                         FROM MEDECINS m
                         LEFT JOIN CONSULTATION c ON m.id_med = c.id_med
                         WHERE m.statut = 'approuvé'
                         GROUP BY m.id_med, m.Nom_med, m.Prénom_med, m.Spécialisation_med
                         ORDER BY nb_consultations DESC
                         LIMIT 10");
    $stats_medecins = $stmt->fetchAll();
    
    // Statistiques par mois (derniers 6 mois)
    $stmt = $pdo->query("SELECT DATE_FORMAT(Date_rdv, '%Y-%m') as mois, COUNT(*) as nb_rdv
                         FROM RENDEZ_VOUS
                         WHERE Date_rdv >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                         GROUP BY mois
                         ORDER BY mois ASC");
    $stats_mois = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Erreur récupération statistiques: " . $e->getMessage());
}
?>

<style>
    .stats-container {
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
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }
    
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s;
        border-left: 4px solid;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    
    .stat-card.blue { border-left-color: #4A90E2; }
    .stat-card.green { border-left-color: #28a745; }
    .stat-card.orange { border-left-color: #ffc107; }
    .stat-card.red { border-left-color: #dc3545; }
    .stat-card.purple { border-left-color: #6f42c1; }
    .stat-card.teal { border-left-color: #20c997; }
    
    .stat-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .stat-card-title {
        font-size: 14px;
        font-weight: 600;
        color: #718096;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .stat-card-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: white;
    }
    
    .stat-card.blue .stat-card-icon { background: #4A90E2; }
    .stat-card.green .stat-card-icon { background: #28a745; }
    .stat-card.orange .stat-card-icon { background: #ffc107; }
    .stat-card.red .stat-card-icon { background: #dc3545; }
    .stat-card.purple .stat-card-icon { background: #6f42c1; }
    .stat-card.teal .stat-card-icon { background: #20c997; }
    
    .stat-card-value {
        font-size: 36px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 5px;
    }
    
    .stat-card-change {
        font-size: 13px;
        color: #718096;
    }
    
    .charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }
    
    .chart-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .chart-card h3 {
        font-size: 20px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .chart-card h3 i {
        color: #4A90E2;
    }
    
    .table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
        margin-bottom: 30px;
    }
    
    .table-header {
        background: #f7fafc;
        padding: 20px;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .table-header h3 {
        font-size: 20px;
        font-weight: 600;
        color: #2d3748;
        margin: 0;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
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
        background: #f7fafc;
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
        background: #4A90E2;
        color: white;
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
    
    .progress-bar {
        height: 8px;
        background: #e2e8f0;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 5px;
    }
    
    .progress-fill {
        height: 100%;
        background: #4A90E2;
        border-radius: 4px;
        transition: width 0.3s;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #718096;
    }
    
    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
        color: #cbd5e0;
    }
</style>

<div class="stats-container">
    <a href="index.php" class="btn-retour">
        <i class="fas fa-arrow-left"></i> Retour au tableau de bord
    </a>
    
    <div class="page-header">
        <h1><i class="fas fa-chart-bar"></i> Statistiques de la Clinique</h1>
        <p>Analyse détaillée des données de votre clinique</p>
    </div>
    
    <!-- Statistiques principales -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-card-header">
                <div class="stat-card-title">Médecins</div>
                <div class="stat-card-icon">
                    <i class="fas fa-user-md"></i>
                </div>
            </div>
            <div class="stat-card-value"><?php echo $stats['medecins']; ?></div>
            <div class="stat-card-change">
                <?php echo $stats['medecins_approuves']; ?> approuvés, 
                <?php echo $stats['medecins_attente']; ?> en attente
            </div>
        </div>
        
        <div class="stat-card green">
            <div class="stat-card-header">
                <div class="stat-card-title">Patients</div>
                <div class="stat-card-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="stat-card-value"><?php echo $stats['patients']; ?></div>
            <div class="stat-card-change">Total enregistrés</div>
        </div>
        
        <div class="stat-card orange">
            <div class="stat-card-header">
                <div class="stat-card-title">Rendez-vous</div>
                <div class="stat-card-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
            </div>
            <div class="stat-card-value"><?php echo $stats['rendez_vous']; ?></div>
            <div class="stat-card-change">
                <?php echo $stats['rdv_confirmes']; ?> confirmés, 
                <?php echo $stats['rdv_planifies']; ?> planifiés
            </div>
        </div>
        
        <div class="stat-card purple">
            <div class="stat-card-header">
                <div class="stat-card-title">Services</div>
                <div class="stat-card-icon">
                    <i class="fas fa-hospital"></i>
                </div>
            </div>
            <div class="stat-card-value"><?php echo $stats['services']; ?></div>
            <div class="stat-card-change">Services disponibles</div>
        </div>
        
        <div class="stat-card teal">
            <div class="stat-card-header">
                <div class="stat-card-title">Consultations</div>
                <div class="stat-card-icon">
                    <i class="fas fa-stethoscope"></i>
                </div>
            </div>
            <div class="stat-card-value"><?php echo $stats['consultations']; ?></div>
            <div class="stat-card-change">Total effectuées</div>
        </div>
        
        <div class="stat-card red">
            <div class="stat-card-header">
                <div class="stat-card-title">Paiements</div>
                <div class="stat-card-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
            <div class="stat-card-value"><?php echo $stats['paiements']; ?></div>
            <div class="stat-card-change">Total enregistrés</div>
        </div>
    </div>
    
    <!-- Graphiques et tableaux -->
    <div class="charts-grid">
        <!-- Répartition des rendez-vous par service -->
        <div class="chart-card">
            <h3><i class="fas fa-chart-pie"></i> Rendez-vous par Service</h3>
            <?php if (empty($stats_services)): ?>
                <div class="empty-state">
                    <i class="fas fa-chart-pie"></i>
                    <p>Aucune donnée disponible</p>
                </div>
            <?php else: ?>
                <?php 
                $max_rdv = max(array_column($stats_services, 'nb_rdv'));
                foreach ($stats_services as $service): 
                    $percentage = $max_rdv > 0 ? ($service['nb_rdv'] / $max_rdv) * 100 : 0;
                ?>
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="font-weight: 600; color: #2d3748;">
                                <?php echo htmlspecialchars($service['Nom_service']); ?>
                            </span>
                            <span style="color: #718096; font-weight: 600;">
                                <?php echo $service['nb_rdv']; ?> RDV
                            </span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Évolution mensuelle -->
        <div class="chart-card">
            <h3><i class="fas fa-chart-line"></i> Évolution Mensuelle (6 derniers mois)</h3>
            <?php if (empty($stats_mois)): ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <p>Aucune donnée disponible</p>
                </div>
            <?php else: ?>
                <?php 
                $max_mois = max(array_column($stats_mois, 'nb_rdv'));
                foreach ($stats_mois as $mois): 
                    $date = DateTime::createFromFormat('Y-m', $mois['mois']);
                    $mois_label = $date ? $date->format('M Y') : $mois['mois'];
                    $percentage = $max_mois > 0 ? ($mois['nb_rdv'] / $max_mois) * 100 : 0;
                ?>
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="font-weight: 600; color: #2d3748;">
                                <?php echo htmlspecialchars($mois_label); ?>
                            </span>
                            <span style="color: #718096; font-weight: 600;">
                                <?php echo $mois['nb_rdv']; ?> RDV
                            </span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $percentage; ?>%; background: #28a745;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Top médecins -->
    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-trophy"></i> Top 10 Médecins par Nombre de Consultations</h3>
        </div>
        <?php if (empty($stats_medecins)): ?>
            <div class="empty-state">
                <i class="fas fa-user-md"></i>
                <p>Aucune donnée disponible</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Rang</th>
                        <th>Médecin</th>
                        <th>Spécialisation</th>
                        <th>Consultations</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rang = 1;
                    foreach ($stats_medecins as $medecin): 
                    ?>
                        <tr>
                            <td>
                                <span class="badge badge-primary">#<?php echo $rang++; ?></span>
                            </td>
                            <td>
                                <strong>Dr. <?php echo htmlspecialchars($medecin['Prénom_med'] . ' ' . $medecin['Nom_med']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($medecin['Spécialisation_med']); ?></td>
                            <td>
                                <strong style="color: #4A90E2; font-size: 18px;">
                                    <?php echo $medecin['nb_consultations']; ?>
                                </strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Répartition des statuts de rendez-vous -->
    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-info-circle"></i> Répartition des Rendez-vous par Statut</h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Statut</th>
                    <th>Nombre</th>
                    <th>Pourcentage</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_rdv = $stats['rendez_vous'];
                $rdv_stats = [
                    ['label' => 'Confirmés', 'count' => $stats['rdv_confirmes'], 'color' => '#28a745'],
                    ['label' => 'Planifiés', 'count' => $stats['rdv_planifies'], 'color' => '#ffc107'],
                    ['label' => 'Annulés', 'count' => $stats['rdv_annules'], 'color' => '#dc3545'],
                ];
                foreach ($rdv_stats as $stat): 
                    $percentage = $total_rdv > 0 ? ($stat['count'] / $total_rdv) * 100 : 0;
                ?>
                    <tr>
                        <td>
                            <span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: <?php echo $stat['color']; ?>; margin-right: 8px;"></span>
                            <strong><?php echo $stat['label']; ?></strong>
                        </td>
                        <td><?php echo $stat['count']; ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="progress-bar" style="flex: 1;">
                                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%; background: <?php echo $stat['color']; ?>;"></div>
                                </div>
                                <span style="font-weight: 600; color: #2d3748; min-width: 50px;">
                                    <?php echo number_format($percentage, 1); ?>%
                                </span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'partials/footer.php'; ?>
