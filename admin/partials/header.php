<?php
/**
 * Header spécifique pour l'interface d'administration avec sidebar latérale
 * Accessible uniquement aux administrateurs
 */
require_once __DIR__ . '/../../config/bdd.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/permissions.php';

// Vérifier que l'utilisateur est admin
requireLogin('../../login.php');
requireAdmin('../../index.php');

$user_info = getUserInfo();

// Compter les médecins en attente pour le badge
$count_medecins_attente = 0;
$count_matricules = 0;
try {
    $pdo = bdd();
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM MEDECINS WHERE statut = 'en_attente'");
    $result = $stmt->fetch();
    $count_medecins_attente = $result['count'] ?? 0;
    
    $stmt_patients = $pdo->query("SELECT COUNT(*) as count FROM PATIENTS WHERE Matricule_patient IS NULL");
    $count_patients = $stmt_patients->fetch()['count'] ?? 0;
    $stmt_medecins = $pdo->query("SELECT COUNT(*) as count FROM MEDECINS WHERE Matricule_med IS NULL");
    $count_medecins = $stmt_medecins->fetch()['count'] ?? 0;
    $count_matricules = $count_patients + $count_medecins;
} catch (Exception $e) {
    // Ignorer les erreurs
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administration - Tableau de bord</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f7fa;
            color: #2d3748;
            line-height: 1.6;
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .admin-sidebar {
            width: 280px;
            background: #1e3a5f;
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.1);
            flex-shrink: 0;
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 20px;
            font-weight: 700;
            color: white;
            text-decoration: none;
        }
        
        .sidebar-logo i {
            font-size: 24px;
            color: #4A90E2;
        }
        
        .sidebar-nav {
            padding: 20px 0;
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            margin-bottom: 0;
        }
        
        .nav-section {
            margin-bottom: 30px;
        }
        
        .nav-section-title {
            padding: 10px 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.5);
            margin-bottom: 10px;
        }
        
        .nav-item {
            margin: 0;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #4A90E2;
        }
        
        .nav-link.active {
            background: rgba(74, 144, 226, 0.2);
            color: white;
            border-left-color: #4A90E2;
        }
        
        .nav-link i {
            width: 20px;
            font-size: 16px;
        }
        
        .nav-badge {
            background: #ffc107;
            color: #333;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: auto;
        }
        
        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.1);
            flex-shrink: 0;
            margin-top: auto;
        }
        
        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
            padding-bottom: 0;
        }
        
        .sidebar-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #4A90E2;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
        }
        
        .sidebar-user-info {
            flex: 1;
        }
        
        .sidebar-user-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 2px;
        }
        
        .sidebar-user-role {
            font-size: 12px;
            color: rgba(255,255,255,0.7);
        }
        
        .sidebar-footer-links {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .sidebar-footer-link {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-size: 13px;
            transition: color 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 6px;
            white-space: nowrap;
        }
        
        .sidebar-footer-link:hover {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar-footer-link i {
            font-size: 14px;
        }
        
        /* Main Content */
        .admin-main {
            flex: 1;
            margin-left: 280px;
            min-height: 100vh;
        }
        
        .admin-topbar {
            background: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .topbar-title {
            font-size: 24px;
            font-weight: 700;
            color: #1e3a5f;
        }
        
        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .btn-topbar {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-topbar-primary {
            background: #4A90E2;
            color: white;
        }
        
        .btn-topbar-primary:hover {
            background: #357ABD;
        }
        
        .btn-topbar-secondary {
            background: #f5f7fa;
            color: #2d3748;
        }
        
        .btn-topbar-secondary:hover {
            background: #e2e8f0;
        }
        
        .content-wrapper {
            padding: 30px;
        }
        
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            
            .admin-sidebar.open {
                transform: translateX(0);
            }
            
            .admin-main {
                margin-left: 0;
            }
            
            .mobile-menu-toggle {
                display: block;
                background: none;
                border: none;
                font-size: 24px;
                color: #1e3a5f;
                cursor: pointer;
            }
        }
        
        @media (min-width: 769px) {
            .mobile-menu-toggle {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="admin-sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-logo">
                <img src="/ProjetClinique/image/1.jpeg" alt="" style="height:32px; width:auto; display:block;">
            </a>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Principal</div>
                <div class="nav-item">
                    <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Docteurs</div>
                <div class="nav-item">
                    <a href="liste-medecins.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'liste-medecins.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-doctor"></i>
                        <span>Tous les docteurs</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="ajouter-medecin.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'ajouter-medecin.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-plus"></i>
                        <span>Ajouter un docteur</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="approuver-medecins.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'approuver-medecins.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-check"></i>
                        <span>Approuver Médecins</span>
                        <?php if ($count_medecins_attente > 0): ?>
                            <span class="nav-badge"><?php echo $count_medecins_attente; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Patients</div>
                <div class="nav-item">
                    <a href="liste-patients.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'liste-patients.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Liste des Patients</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="../register-patient.php" class="nav-link">
                        <i class="fas fa-user-plus"></i>
                        <span>Ajouter Patient</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Rendez-vous</div>
                <div class="nav-item">
                    <a href="liste-rendez-vous.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'liste-rendez-vous.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-check"></i>
                        <span>Calendrier du rendez-vous</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="../rendez-vous.php" class="nav-link">
                        <i class="fas fa-plus-circle"></i>
                        <span>Ajouter du rendez-vous</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Autres</div>
                <div class="nav-item">
                    <a href="liste-consultations.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'liste-consultations.php' ? 'active' : ''; ?>">
                        <i class="fas fa-stethoscope"></i>
                        <span>Consultations</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="liste-services.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'liste-services.php' ? 'active' : ''; ?>">
                        <i class="fas fa-hospital"></i>
                        <span>Services</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="../paiements/liste-paiements.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'paiements') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Paiements</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="attribuer-matricules.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'attribuer-matricules.php' ? 'active' : ''; ?>">
                        <i class="fas fa-id-card"></i>
                        <span>Attribuer Matricules</span>
                        <?php if ($count_matricules > 0): ?>
                            <span class="nav-badge"><?php echo $count_matricules; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="stats.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'stats.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span>Statistiques</span>
                    </a>
                </div>
            </div>
        </nav>
        
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">
                    <?php echo strtoupper(substr($user_info['nom'], 0, 1)); ?>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?php echo htmlspecialchars($user_info['nom']); ?></div>
                    <div class="sidebar-user-role">Administrateur</div>
                </div>
            </div>
            <div class="sidebar-footer-links">
                <a href="/ProjetClinique/index.php" class="sidebar-footer-link">
                    <i class="fas fa-home"></i> Site
                </a>
                <a href="/ProjetClinique/deconnexion.php" class="sidebar-footer-link">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="admin-main">
        <div class="admin-topbar">
            <div>
                <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="topbar-title">Dashboard</h1>
            </div>
            <div class="topbar-actions">
                <a href="/ProjetClinique/index.php" class="btn-topbar btn-topbar-secondary">
                    <i class="fas fa-home"></i> Site Public
                </a>
            </div>
        </div>
        
        <div class="content-wrapper">
