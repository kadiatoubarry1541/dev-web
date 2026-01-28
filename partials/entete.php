<?php
// Désactiver l'affichage des erreurs pour les utilisateurs (production)
// Les erreurs sont toujours loggées mais pas affichées à l'utilisateur
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);

// Utiliser __DIR__ pour obtenir le chemin absolu du dossier partials, puis remonter d'un niveau pour accéder à config/
try {
    require_once __DIR__ . '/../config/session.php';
} catch (Exception $e) {
    error_log("Erreur lors du chargement de session.php: " . $e->getMessage());
    // Continuer même en cas d'erreur pour permettre l'affichage de la page
}

// Déterminer le chemin de base selon le dossier actuel (une seule fois au début)
// Prendre en compte admin, medecin, accueil, paiements et payment
$base_path = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || 
              strpos($_SERVER['PHP_SELF'], '/medecin/') !== false || 
              strpos($_SERVER['PHP_SELF'], '/accueil/') !== false ||
              strpos($_SERVER['PHP_SELF'], '/paiements/') !== false ||
              strpos($_SERVER['PHP_SELF'], '/payment/') !== false) ? '../' : '';

// Chemin absolu depuis la racine du site pour les images
// Utiliser le chemin relatif depuis la racine du document
$root_path = $base_path;

// Récupérer les informations utilisateur UNIQUEMENT si l'utilisateur est connecté
$user_info = null;
$nb_notifications_non_lues = 0;
if (function_exists('estConnecte') && estConnecte()) {
    try {
        $user_info = getUserInfo();
        // Si getUserInfo retourne null (session invalide), détruire la session
        if ($user_info === null) {
            if (function_exists('session_destroy')) {
                session_destroy();
            }
            $user_info = null;
        } else {
            // Récupérer le nombre de notifications non lues pour les patients
            if (isset($user_info['id_patient']) && $user_info['id_patient']) {
                require_once __DIR__ . '/../config/database_functions.php';
                if (function_exists('countNotificationsNonLues') && function_exists('tableExists')) {
                    if (tableExists('NOTIFICATIONS')) {
                        $nb_notifications_non_lues = countNotificationsNonLues($user_info['id_patient']);
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération des informations utilisateur: " . $e->getMessage());
        $user_info = null;
    }
}
?>
<style>
    .logo-profile-wrapper {
        display: inline-block;
        float: left;
    }
    .user-profile-dropdown {
        position: relative;
        display: inline-block;
        margin-left: 20px;
        vertical-align: middle;
        margin-top: 20px;
    }
    .user-profile-btn {
        background: none !important;
        border: none !important;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 5px 10px;
        border-radius: 8px;
        transition: background 0.3s;
        text-align: center;
    }
    .user-profile-btn:hover {
        background: rgba(0,0,0,0.05) !important;
    }
    .user-dropdown-menu {
        display: none;
        position: absolute;
        top: 100%;
        right: 0;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        min-width: 200px;
        margin-top: 8px;
        z-index: 1000;
        overflow: hidden;
    }
    .user-dropdown-menu a {
        display: flex;
        align-items: center;
        padding: 10px 15px;
        color: #333;
        text-decoration: none;
        transition: background 0.2s;
    }
    .user-dropdown-menu a:hover {
        background: #f0f0f0;
    }
    .user-dropdown-menu a i {
        margin-right: 10px;
        width: 20px;
        color: #4A90E2;
    }
    .user-dropdown-menu .logout-link {
        color: #e74c3c;
    }
    .user-dropdown-menu .logout-link:hover {
        background: #ffeaea;
    }
    .user-dropdown-menu .logout-link i {
        color: #e74c3c;
    }
    .user-profile-img {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #4A90E2;
        margin-bottom: 5px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        background: #f0f0f0;
    }
    .user-profile-img-placeholder {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #4A90E2;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 20px;
        border: 2px solid #4A90E2;
        margin-bottom: 5px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .user-profile-img-large {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 12px;
        border: 2px solid #ddd;
    }
    .user-profile-name {
        font-weight: 500;
        font-size: 12px;
        color: #333;
        margin-top: 3px;
        max-width: 80px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .user-dropdown-header {
        padding: 15px;
        border-bottom: 1px solid #e0e0e0;
        background: #f8f9fa;
    }
    .user-dropdown-header-content {
        display: flex;
        align-items: center;
    }
    .user-dropdown-header-info {
        font-weight: 600;
        color: #333;
        font-size: 14px;
    }
    .user-dropdown-header-email {
        font-size: 12px;
        color: #666;
        margin-top: 2px;
    }
    .user-dropdown-divider {
        border-top: 1px solid #e0e0e0;
        margin: 5px 0;
    }
    .user-dropdown-body {
        padding: 8px 0;
    }
    .user-profile-chevron {
        display: none;
    }
    @media (max-width: 768px) {
        .logo-profile-wrapper {
            display: block;
            float: none;
        }
        .user-profile-dropdown {
            margin-left: 10px;
            margin-top: 10px;
        }
        .user-profile-img {
            width: 40px;
            height: 40px;
        }
        .user-profile-name {
            font-size: 11px;
            max-width: 60px;
        }
        .user-dropdown-menu {
            right: -10px;
        }
    }
    /* Amélioration de la visibilité des liens dans la top-bar */
    .top-bar .text-white {
        color: #002939 !important;
        font-weight: 500;
    }
    .top-bar .text-white:hover {
        color: #4A90E2 !important;
        text-decoration: none;
    }
    .top-bar a.text-white {
        transition: color 0.3s ease;
    }
    /* Style spécifique pour le bouton de déconnexion */
    .top-bar a.text-white[href*="deconnexion"] {
        color: #e74c3c !important;
        font-weight: 600;
    }
    .top-bar a.text-white[href*="deconnexion"]:hover {
        color: #c0392b !important;
        text-decoration: none;
    }
</style>
<header class="site-header header header-style-2 mo-left">
        <!-- top bar -->
        <div class="top-bar">
            <div class="container">
                <div class="row d-flex justify-content-between">
                    <div class="col-md-6">
                        <span class="text-white">Urgences 24h/24 : <strong>+224 612 34 56 78</strong></span>
                    </div>
                    <div class="col-md-6 text-right">
                        <?php if (!estConnecte() || $user_info === null): ?>
                            <a href="<?php echo $base_path; ?>register.php" class="btn-header" style="display: inline-block; background: #4A90E2; color: #fff; padding: 10px 25px; border-radius: 6px; text-decoration: none; margin-left: 10px; font-weight: 600; transition: all 0.3s; border: 2px solid #4A90E2;">Inscription</a>
                            <a href="<?php echo $base_path; ?>login.php" class="btn-header" style="display: inline-block; background: #002939; color: #fff; padding: 10px 25px; border-radius: 6px; text-decoration: none; margin-left: 10px; font-weight: 600; transition: all 0.3s; border: 2px solid #002939;">Connexion</a>
                        <?php else: ?>
                            <span class="text-white">Bienvenue, <strong><?php echo htmlspecialchars($user_info['nom']); ?></strong> | 
                            <?php 
                            require_once __DIR__ . '/../config/permissions.php';
                            $role = $user_info['role'] ?? 'patient';
                            if ($role === 'admin'): ?>
                                <a href="<?php echo $base_path; ?>admin/index.php" class="text-white"><i class="fa fa-dashboard"></i> Administration</a> | 
                            <?php elseif ($role === 'medecin'): ?>
                                <a href="<?php echo $base_path; ?>medecin/index.php" class="text-white"><i class="fa fa-user-md"></i> Espace Médecin</a> | 
                            <?php elseif ($role === 'accueil'): ?>
                                <a href="<?php echo $base_path; ?>accueil/index.php" class="text-white"><i class="fa fa-user-plus"></i> Espace Accueil</a> | 
                            <?php else: ?>
                                <a href="<?php echo $base_path; ?>profil.php" class="text-white"><i class="fa fa-user"></i> Mon Profil</a> | 
                            <?php endif; ?>
                            <a href="<?php echo $base_path; ?>deconnexion.php" class="text-white"><i class="fa fa-sign-out"></i> Déconnexion</a></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- top bar END-->
        <!-- main header -->
        <div class="sticky-header  main-bar-wraper navbar-expand-lg">
            <div class="main-bar clearfix ">
                <div class="container clearfix">
                    <!-- website logo avec profil utilisateur -->
                    <div class="logo-profile-wrapper">
                        <div class="logo-header mostion">
                            <?php 
                            // Logo principal du site (BM CONNECT) – même pour tout le projet
                            // On pointe toujours vers le même fichier de logo personnalisé
                            $logo_url = $base_path . 'image/1.jpeg';
                            ?>
                            <a href="<?php echo $base_path; ?>index.php">
                                <img src="<?php echo htmlspecialchars($logo_url); ?>" 
                                     width="193" height="89" 
                                     alt="Logo du site"
                                     onerror="this.onerror=null; this.style.display='none';">
                            </a>
                        </div>
                        <?php if (estConnecte() && $user_info !== null): 
                            require_once __DIR__ . '/../config/permissions.php';
                            $role = $user_info['role'] ?? 'patient';
                            
                            // Déterminer le chemin de la photo de profil
                            $photo_profil = $user_info['photo_profil'] ?? null;
                            $photo_url = null;
                            
                            if ($photo_profil) {
                                // Chemin absolu pour vérifier l'existence
                                $photo_abs_path = __DIR__ . '/../' . ltrim($photo_profil, '/');
                                if (file_exists($photo_abs_path)) {
                                    // Utiliser le chemin relatif depuis la racine du site
                                    $photo_url = $base_path . ltrim($photo_profil, '/');
                                }
                            }
                            
                            // Si aucune photo valide, utiliser une photo par défaut
                            if (!$photo_url) {
                                // Essayer plusieurs chemins par défaut
                                $default_photos = [
                                    'images/icons/icon1.jpg',
                                    'images/icons/icon2.jpg',
                                    'images/logo.png'
                                ];
                                
                                foreach ($default_photos as $default_photo) {
                                    $default_abs_path = __DIR__ . '/../' . $default_photo;
                                    if (file_exists($default_abs_path)) {
                                        // Utiliser le chemin relatif depuis la racine du site
                                        $photo_url = $base_path . $default_photo;
                                        break;
                                    }
                                }
                                
                                // Si aucune image par défaut n'existe, utiliser un placeholder SVG
                                if (!$photo_url) {
                                    $initial = strtoupper(substr($user_info['nom'], 0, 1));
                                    $photo_url = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50"><circle cx="25" cy="25" r="20" fill="#4A90E2"/><text x="25" y="30" text-anchor="middle" fill="white" font-size="20" font-weight="bold">' . htmlspecialchars($initial) . '</text></svg>');
                                }
                            }
                        ?>
                            <div class="user-profile-dropdown">
                                <button class="user-profile-btn">
                                    <?php 
                                    $user_initial = strtoupper(substr($user_info['nom'], 0, 1));
                                    if (strpos($photo_url, 'data:image') === 0): 
                                        // Si c'est un SVG inline, l'afficher directement
                                    ?>
                                        <div class="user-profile-img-placeholder"><?php echo htmlspecialchars($user_initial); ?></div>
                                    <?php else: ?>
                                        <img src="<?php echo htmlspecialchars($photo_url); ?>" 
                                             alt="Photo de profil" 
                                             class="user-profile-img"
                                             onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="user-profile-img-placeholder" style="display:none;"><?php echo htmlspecialchars($user_initial); ?></div>
                                    <?php endif; ?>
                                    <span class="user-profile-name"><?php echo htmlspecialchars($user_info['nom']); ?></span>
                                </button>
                                <div class="user-dropdown-menu">
                                    <div class="user-dropdown-header">
                                        <div class="user-dropdown-header-content">
                                            <?php if (strpos($photo_url, 'data:image') === 0): ?>
                                                <div class="user-profile-img-placeholder" style="margin-right:12px; border:2px solid #ddd;"><?php echo htmlspecialchars($user_initial); ?></div>
                                            <?php else: ?>
                                                <img src="<?php echo htmlspecialchars($photo_url); ?>" 
                                                     alt="Photo de profil" 
                                                     class="user-profile-img-large"
                                                     onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="user-profile-img-placeholder" style="display:none; margin-right:12px; border:2px solid #ddd;"><?php echo htmlspecialchars($user_initial); ?></div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="user-dropdown-header-info"><?php echo htmlspecialchars($user_info['nom']); ?></div>
                                                <div class="user-dropdown-header-email"><?php echo htmlspecialchars($user_info['email']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="user-dropdown-body">
                                        <?php if ($role === 'admin'): ?>
                                            <a href="<?php echo $base_path; ?>admin/index.php">
                                                <i class="fa fa-dashboard"></i>
                                                <span>Administration</span>
                                            </a>
                                        <?php elseif ($role === 'medecin'): ?>
                                            <a href="<?php echo $base_path; ?>medecin/index.php">
                                                <i class="fa fa-user-md"></i>
                                                <span>Espace Médecin</span>
                                            </a>
                                        <?php elseif ($role === 'accueil'): ?>
                                            <a href="<?php echo $base_path; ?>accueil/index.php">
                                                <i class="fa fa-user-plus"></i>
                                                <span>Espace Accueil</span>
                                            </a>
                                        <?php else: ?>
                                            <a href="<?php echo $base_path; ?>profil.php">
                                                <i class="fa fa-user"></i>
                                                <span>Mon Profil</span>
                                                <?php if ($nb_notifications_non_lues > 0): ?>
                                                    <span class="notification-badge" style="background: #dc3545; color: white; border-radius: 10px; padding: 2px 6px; font-size: 10px; font-weight: bold; margin-left: 5px;">
                                                        <?php echo $nb_notifications_non_lues; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?php echo $base_path; ?>rendez-vous.php">
                                            <i class="fa fa-calendar"></i>
                                            <span>Mes Rendez-vous</span>
                                        </a>
                                        <?php if (hasPermission('view_ordonnances')): ?>
                                            <a href="<?php echo $base_path; ?>mes-ordonnances.php">
                                                <i class="fa fa-prescription"></i>
                                                <span>Mes Ordonnances</span>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($role === 'patient' && $nb_notifications_non_lues > 0): ?>
                                            <a href="<?php echo $base_path; ?>profil.php#notifications" style="position: relative;">
                                                <i class="fa fa-bell"></i>
                                                <span>Mes Notifications</span>
                                                <span class="notification-badge" style="background: #dc3545; color: white; border-radius: 10px; padding: 2px 6px; font-size: 10px; font-weight: bold; margin-left: 5px; animation: pulse 2s infinite;">
                                                    <?php echo $nb_notifications_non_lues; ?> nouveau<?php echo $nb_notifications_non_lues > 1 ? 'x' : ''; ?>
                                                </span>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (isset($role) && $role === 'admin'): ?>
                                            <a href="<?php echo $base_path; ?>paiements/liste-paiements.php">
                                                <i class="fa fa-money"></i>
                                                <span>Mes Paiements</span>
                                            </a>
                                            <a href="<?php echo $base_path; ?>paiements/creer-paiement.php">
                                                <i class="fa fa-plus-circle"></i>
                                                <span>Créer un Paiement</span>
                                            </a>
                                        <?php endif; ?>
                                        <div class="user-dropdown-divider"></div>
                                        <a href="<?php echo $base_path; ?>deconnexion.php" class="logout-link">
                                            <i class="fa fa-sign-out"></i>
                                            <span>Déconnexion</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- nav toggle button -->
						<button class="navbar-toggler collapsed navicon justify-content-end" type="button" data-toggle="collapse" data-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
							<span></span>
							<span></span>
							<span></span>
						</button>
                    <!-- extra nav -->
                    <div class="extra-nav">
                        <div class="extra-cell">
                            <button id="quik-search-btn" type="button" class="site-button"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                    <!-- Quik search -->
                    <div class="dez-quik-search bg-primary ">
                        <form action="#">
                            <input name="search" value="" type="text" class="form-control" placeholder="Rechercher un service, un médecin...">
                            <span id="quik-search-remove"><i class="fa fa-remove"></i></span>
                        </form>
                    </div>
                    <!-- main nav -->
					<div class="header-nav navbar-collapse collapse justify-content-end" id="navbarNavDropdown">
    <ul class="nav navbar-nav">
        <li class="nav-item"> 
            <a class="nav-link" href="<?php echo $base_path; ?>index.php">Accueil</a>
        </li>
        
        <li class="nav-item dropdown"> 
            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
                Services
            </a>
            <div class="dropdown-menu">
                <a class="dropdown-item" href="<?php echo $base_path; ?>medecine-generale.php">
                    <i class="fa fa-user-md"></i> Médecine Générale
                </a>
                <a class="dropdown-item" href="<?php echo $base_path; ?>maternite.php">
                    <i class="fa fa-heart"></i> Maternité
                </a>
                <a class="dropdown-item" href="<?php echo $base_path; ?>chirurgie.php">
                    <i class="fa fa-stethoscope"></i> Chirurgie
                </a>
                <a class="dropdown-item" href="<?php echo $base_path; ?>ophtamologie.php">
                    <i class="fa fa-eye"></i> Ophtalmologie
                </a>
            </div>
        </li>
        
        <li class="nav-item"> 
            <a class="nav-link" href="<?php echo $base_path; ?>docteurs.php">Docteurs</a>
        </li>
        <li class="nav-item"> 
            <a class="nav-link" href="<?php echo $base_path; ?>rendez-vous.php">Rendez-vous</a>
        </li>
        <li class="nav-item"> 
            <a class="nav-link" href="<?php echo $base_path; ?>contact.php">Contact</a>
        </li>
    </ul>
</div>
        <!-- main header END -->
    </header>
    <?php if (estConnecte()): ?>
    <style>
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .notification-badge {
            display: inline-block;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const profileBtn = document.querySelector('.user-profile-btn');
            const dropdownMenu = document.querySelector('.user-dropdown-menu');
            
            if (profileBtn && dropdownMenu) {
                profileBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const isVisible = dropdownMenu.style.display === 'block';
                    dropdownMenu.style.display = isVisible ? 'none' : 'block';
                });
                
                // Fermer le menu en cliquant ailleurs
                document.addEventListener('click', function(e) {
                    if (!profileBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
                        dropdownMenu.style.display = 'none';
                    }
                });
            }
        });
    </script>
    <?php endif; ?>