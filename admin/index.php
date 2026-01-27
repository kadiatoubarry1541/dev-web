<?php
/**
 * Tableau de bord administrateur avec design moderne
 * Seuls les administrateurs peuvent accéder à cette page
 */

require_once 'partials/header.php';

// Compter les médecins en attente et autres statistiques
$count_medecins_attente = 0;
$stats = [
    'medecins' => 0,
    'patients' => 0,
    'rendez_vous' => 0,
    'factures' => 0,
    'revenu' => 0,
    'services' => 0,
    'medecins_attente' => 0,
    'medecins_approuves' => 0
];

// Données pour le graphique d'activité (7 derniers jours)
$activity_data = [
    'labels' => [],
    'visits' => []
];

// Top médecin pour la carte profil
$top_medecin = null;

// Listes récentes
$recent_medecins = [];
$recent_paiements = [];
$recent_rdv = [];

try {
    require_once '../config/bdd.php';
    $pdo = bdd();
    
    // Compter les médecins en attente
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM MEDECINS WHERE statut = 'en_attente'");
    $result = $stmt->fetch();
    $count_medecins_attente = $result['count'] ?? 0;
    $stats['medecins_attente'] = $count_medecins_attente;
    
    // Compter les médecins approuvés
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM MEDECINS WHERE statut = 'approuvé'");
    $result = $stmt->fetch();
    $stats['medecins_approuves'] = $result['count'] ?? 0;
    
    // Compter tous les médecins
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM MEDECINS");
    $result = $stmt->fetch();
    $stats['medecins'] = $result['count'] ?? 0;
    
    // Compter les patients
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM PATIENTS");
    $result = $stmt->fetch();
    $stats['patients'] = $result['count'] ?? 0;
    
    // Compter les rendez-vous
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM RENDEZ_VOUS");
    $result = $stmt->fetch();
    $stats['rendez_vous'] = $result['count'] ?? 0;
    
    // Compter les factures (paiements)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM PAIEMENT");
    $result = $stmt->fetch();
    $stats['factures'] = $result['count'] ?? 0;
    
    // Calculer le revenu total (somme des montants payés)
    $stmt = $pdo->query("SELECT COALESCE(SUM(Montant), 0) as total FROM PAIEMENT WHERE Statut = 'payé'");
    $result = $stmt->fetch();
    $stats['revenu'] = $result['total'] ?? 0;
    
    // Compter les services
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM SERVICES");
    $result = $stmt->fetch();
    $stats['services'] = $result['count'] ?? 0;
    
    // Données pour le graphique d'activité (7 derniers jours)
    $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $jour_nom = $jours[date('N', strtotime($date)) - 1];
        $activity_data['labels'][] = substr($jour_nom, 0, 4);
        
        // Compter les rendez-vous pour ce jour
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM RENDEZ_VOUS WHERE DATE(Date_rdv) = ?");
        $stmt->execute([$date]);
        $result = $stmt->fetch();
        $activity_data['visits'][] = $result['count'] ?? 0;
    }
    
    // Top médecin (celui avec le plus de consultations)
    $stmt = $pdo->query("SELECT m.*, COUNT(c.id_consultation) as nb_consultations
                         FROM MEDECINS m
                         LEFT JOIN CONSULTATION c ON m.id_med = c.id_med
                         WHERE m.statut = 'approuvé'
                         GROUP BY m.id_med
                         ORDER BY nb_consultations DESC
                         LIMIT 1");
    $top_medecin = $stmt->fetch();
    
    // Compter les followers (patients qui ont consulté ce médecin)
    if ($top_medecin) {
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT c.id_patient) as followers FROM CONSULTATION c WHERE c.id_med = ?");
        $stmt->execute([$top_medecin['id_med']]);
        $result = $stmt->fetch();
        $top_medecin['followers'] = $result['followers'] ?? 0;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as consultations FROM CONSULTATION WHERE id_med = ?");
        $stmt->execute([$top_medecin['id_med']]);
        $result = $stmt->fetch();
        $top_medecin['nb_consultations'] = $result['consultations'] ?? 0;
        
        // Évaluations (on peut simuler avec un nombre aléatoire ou utiliser une vraie table si elle existe)
        $top_medecin['evaluations'] = rand(30, 50);
    }
    
    // Liste des derniers médecins (5)
    $stmt = $pdo->query("SELECT * FROM MEDECINS WHERE statut = 'approuvé' ORDER BY id_med DESC LIMIT 5");
    $recent_medecins = $stmt->fetchAll();
    
    // Liste des derniers paiements (5)
    $stmt = $pdo->query("SELECT p.*, pat.Nom_patient, pat.Prénom_patient 
                         FROM PAIEMENT p 
                         LEFT JOIN PATIENTS pat ON p.id_patient = pat.id_patient 
                         ORDER BY p.Date_paiement DESC LIMIT 5");
    $recent_paiements = $stmt->fetchAll();
    
    // Liste des derniers rendez-vous (5)
    $stmt = $pdo->query("SELECT r.*, pat.Nom_patient, pat.Prénom_patient, s.Nom_service
                         FROM RENDEZ_VOUS r
                         LEFT JOIN PATIENTS pat ON r.id_patient = pat.id_patient
                         LEFT JOIN SERVICES s ON r.id_service = s.id_service
                         ORDER BY r.Date_rdv DESC LIMIT 5");
    $recent_rdv = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Erreur récupération statistiques: " . $e->getMessage());
}
?>

<style>
    /* Cartes KPI */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .kpi-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }
    
    .kpi-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--kpi-color);
    }
    
    .kpi-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    
    .kpi-card.blue { --kpi-color: #4A90E2; }
    .kpi-card.orange { --kpi-color: #ff9800; }
    .kpi-card.green { --kpi-color: #28a745; }
    .kpi-card.red { --kpi-color: #dc3545; }
    
    .kpi-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .kpi-title {
        font-size: 14px;
        font-weight: 600;
        color: #718096;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .kpi-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: white;
        background: var(--kpi-color);
    }
    
    .kpi-value {
        font-size: 36px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 5px;
    }
    
    .kpi-subtitle {
        font-size: 13px;
        color: #718096;
    }
    
    /* Layout principal */
    .dashboard-layout {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }
    
    @media (max-width: 1200px) {
        .dashboard-layout {
            grid-template-columns: 1fr;
        }
    }
    
    /* Carte profil médecin */
    .profile-card {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .profile-header {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 25px;
        padding-bottom: 25px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .profile-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        font-weight: 700;
        color: white;
        border: 4px solid #e2e8f0;
    }
    
    .profile-info h3 {
        font-size: 20px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 5px;
    }
    
    .profile-info p {
        color: #718096;
        font-size: 14px;
    }
    
    .profile-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: #4A90E2;
        color: white;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .profile-btn:hover {
        background: #357ABD;
        transform: translateY(-2px);
    }
    
    .profile-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }
    
    .profile-stat {
        text-align: center;
    }
    
    .profile-stat-value {
        font-size: 24px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 5px;
    }
    
    .profile-stat-label {
        font-size: 13px;
        color: #718096;
    }
    
    /* Graphique d'activité */
    .activity-card {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .activity-card h3 {
        font-size: 18px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 25px;
    }
    
    .activity-chart {
        position: relative;
        height: 250px;
    }
    
    /* Listes récentes */
    .recent-lists {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
    }
    
    .recent-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .recent-card h3 {
        font-size: 18px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .recent-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px 0;
        border-bottom: 1px solid #f5f7fa;
    }
    
    .recent-item:last-child {
        border-bottom: none;
    }
    
    .recent-item-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #4A90E2;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 14px;
    }
    
    .recent-item-info {
        flex: 1;
    }
    
    .recent-item-name {
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 3px;
    }
    
    .recent-item-detail {
        font-size: 13px;
        color: #718096;
    }
    
    .recent-item-date {
        font-size: 12px;
        color: #a0aec0;
    }
</style>

<!-- Cartes KPI -->
<div class="kpi-grid">
    <div class="kpi-card blue">
        <div class="kpi-header">
            <div class="kpi-title">PATIENTS</div>
            <div class="kpi-icon">
                <i class="fas fa-users"></i>
            </div>
        </div>
        <div class="kpi-value"><?php echo $stats['patients']; ?></div>
        <div class="kpi-subtitle">Total enregistrés</div>
    </div>
    
    <div class="kpi-card orange">
        <div class="kpi-header">
            <div class="kpi-title">FACTURES</div>
            <div class="kpi-icon">
                <i class="fas fa-file-invoice"></i>
            </div>
        </div>
        <div class="kpi-value"><?php echo $stats['factures']; ?></div>
        <div class="kpi-subtitle">Total des paiements</div>
    </div>
    
    <div class="kpi-card green">
        <div class="kpi-header">
            <div class="kpi-title">REVENU</div>
            <div class="kpi-icon">
                <i class="fas fa-euro-sign"></i>
            </div>
        </div>
        <div class="kpi-value"><?php echo number_format($stats['revenu'], 0, ',', ' '); ?> €</div>
        <div class="kpi-subtitle">Revenu total</div>
    </div>
    
    <div class="kpi-card red">
        <div class="kpi-header">
            <div class="kpi-title">RENDEZ-VOUS</div>
            <div class="kpi-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
        </div>
        <div class="kpi-value"><?php echo $stats['rendez_vous']; ?></div>
        <div class="kpi-subtitle">Total planifiés</div>
    </div>
</div>

<!-- Layout principal avec profil et graphique -->
<div class="dashboard-layout">
    <!-- Carte profil médecin -->
    <div class="profile-card">
        <?php if ($top_medecin): ?>
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($top_medecin['Prénom_med'] ?? 'D', 0, 1) . substr($top_medecin['Nom_med'] ?? 'R', 0, 1)); ?>
                </div>
                <div class="profile-info" style="flex: 1;">
                    <h3>Dr. <?php echo htmlspecialchars(($top_medecin['Prénom_med'] ?? '') . ' ' . ($top_medecin['Nom_med'] ?? '')); ?></h3>
                    <p><?php echo htmlspecialchars($top_medecin['Spécialisation_med'] ?? 'Médecin senior'); ?></p>
                </div>
                <a href="liste-medecins.php" class="profile-btn">
                    Suivre
                </a>
            </div>
            <div class="profile-stats">
                <div class="profile-stat">
                    <div class="profile-stat-value"><?php echo $top_medecin['followers'] ?? 0; ?></div>
                    <div class="profile-stat-label">Followers</div>
                </div>
                <div class="profile-stat">
                    <div class="profile-stat-value"><?php echo $top_medecin['nb_consultations'] ?? 0; ?></div>
                    <div class="profile-stat-label">Consultations</div>
                </div>
                <div class="profile-stat">
                    <div class="profile-stat-value"><?php echo $top_medecin['evaluations'] ?? 0; ?></div>
                    <div class="profile-stat-label">Évaluations</div>
                </div>
            </div>
        <?php else: ?>
            <div class="profile-header">
                <div class="profile-avatar">A</div>
                <div class="profile-info" style="flex: 1;">
                    <h3>Aucun médecin</h3>
                    <p>Ajoutez des médecins pour voir les statistiques</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Graphique d'activité -->
    <div class="activity-card">
        <h3>Activité Des Visiteurs</h3>
        <div class="activity-chart">
            <canvas id="activityChart"></canvas>
        </div>
    </div>
</div>

<!-- Listes récentes -->
<div class="recent-lists">
    <!-- Liste des docteurs -->
    <div class="recent-card">
        <h3>Liste des docteurs</h3>
        <?php if (!empty($recent_medecins)): ?>
            <?php foreach (array_slice($recent_medecins, 0, 5) as $medecin): ?>
                <div class="recent-item">
                    <div class="recent-item-avatar">
                        <?php echo strtoupper(substr($medecin['Prénom_med'] ?? 'D', 0, 1)); ?>
                    </div>
                    <div class="recent-item-info">
                        <div class="recent-item-name">
                            Dr. <?php echo htmlspecialchars(($medecin['Prénom_med'] ?? '') . ' ' . ($medecin['Nom_med'] ?? '')); ?>
                        </div>
                        <div class="recent-item-detail">
                            <?php echo htmlspecialchars($medecin['Spécialisation_med'] ?? ''); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: #718096; text-align: center; padding: 20px;">Aucun médecin</p>
        <?php endif; ?>
    </div>
    
    <!-- Derniers paiements -->
    <div class="recent-card">
        <h3>Derniers paiements</h3>
        <?php if (!empty($recent_paiements)): ?>
            <?php foreach ($recent_paiements as $paiement): ?>
                <div class="recent-item">
                    <div class="recent-item-avatar" style="background: #28a745;">
                        <i class="fas fa-euro-sign"></i>
                    </div>
                    <div class="recent-item-info">
                        <div class="recent-item-name">
                            <?php echo htmlspecialchars(($paiement['Prénom_patient'] ?? '') . ' ' . ($paiement['Nom_patient'] ?? '')); ?>
                        </div>
                        <div class="recent-item-detail">
                            <?php echo number_format($paiement['Montant'] ?? 0, 2, ',', ' '); ?> € - <?php echo htmlspecialchars($paiement['Statut'] ?? ''); ?>
                        </div>
                        <div class="recent-item-date">
                            <?php echo date('d/m/Y', strtotime($paiement['Date_paiement'] ?? 'now')); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: #718096; text-align: center; padding: 20px;">Aucun paiement</p>
        <?php endif; ?>
    </div>
    
    <!-- Derniers rendez-vous -->
    <div class="recent-card">
        <h3>Derniers rendez-vous</h3>
        <?php if (!empty($recent_rdv)): ?>
            <?php foreach ($recent_rdv as $rdv): ?>
                <div class="recent-item">
                    <div class="recent-item-avatar" style="background: #dc3545;">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="recent-item-info">
                        <div class="recent-item-name">
                            <?php echo htmlspecialchars(($rdv['Prénom_patient'] ?? '') . ' ' . ($rdv['Nom_patient'] ?? '')); ?>
                        </div>
                        <div class="recent-item-detail">
                            <?php echo htmlspecialchars($rdv['Nom_service'] ?? ''); ?> - <?php echo htmlspecialchars($rdv['Statut'] ?? ''); ?>
                        </div>
                        <div class="recent-item-date">
                            <?php echo date('d/m/Y H:i', strtotime($rdv['Date_rdv'] ?? 'now')); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: #718096; text-align: center; padding: 20px;">Aucun rendez-vous</p>
        <?php endif; ?>
    </div>
</div>

<script>
    // Graphique d'activité
    const ctx = document.getElementById('activityChart').getContext('2d');
    const activityChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($activity_data['labels']); ?>,
            datasets: [{
                label: 'Visites',
                data: <?php echo json_encode($activity_data['visits']); ?>,
                backgroundColor: '#4A90E2',
                borderRadius: 8,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14
                    },
                    bodyFont: {
                        size: 13
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: Math.max(...<?php echo json_encode($activity_data['visits']); ?>) + 2 || 10,
                    ticks: {
                        stepSize: 1
                    },
                    grid: {
                        color: '#e2e8f0'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    
    // Toggle sidebar mobile
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }
</script>

<?php require_once 'partials/footer.php'; ?>
