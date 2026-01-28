<?php
require_once 'config/database_functions.php';

// Vérifier l'état de la base de données
$db_status = checkDatabaseStatus();
$db_error = !empty($db_status['error']);
$db_error_message = $db_status['error'] ?? '';
$medecins = [];

// Si la base de données est OK et les tables existent, récupérer les médecins
if ($db_status['connected'] && $db_status['tables_exist']) {
    try {
        $medecins = getAllMedecins();
        // Si aucun médecin n'est trouvé, c'est normal, pas une erreur
        // $medecins sera un tableau vide
        
        // Debug : Vérifier si Tidian est dans la liste
        $tidian_trouve = false;
        foreach ($medecins as $med) {
            if (stripos($med['Nom_med'], 'Tidian') !== false || stripos($med['Prénom_med'], 'Tidian') !== false) {
                $tidian_trouve = true;
                break;
            }
        }
    } catch (PDOException $e) {
        // En cas d'erreur inattendue lors de la récupération
        $db_error = true;
        $db_error_message = "Erreur lors de la récupération des médecins : " . $e->getMessage();
    }
}

// Récupérer les images du dossier image/medecin
$dossier_medecin_images = 'image/medecin';
$images_medecin_list = [];

if (is_dir($dossier_medecin_images)) {
    $fichiers = scandir($dossier_medecin_images);
    foreach ($fichiers as $fichier) {
        if ($fichier != '.' && $fichier != '..') {
            $extension = strtolower(pathinfo($fichier, PATHINFO_EXTENSION));
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'jfif', 'webp'])) {
                $images_medecin_list[] = $dossier_medecin_images . '/' . $fichier;
            }
        }
    }
}

// Images par défaut pour les médecins selon la spécialisation - utiliser les images du dossier
$images_medecins = [
    'Gynécologie-Obstétrique' => isset($images_medecin_list[0]) ? $images_medecin_list[0] : 'image/medecin/i1.jfif',
    'Chirurgie générale' => isset($images_medecin_list[1]) ? $images_medecin_list[1] : 'image/medecin/i2.jfif',
    'Médecine générale' => isset($images_medecin_list[2]) ? $images_medecin_list[2] : 'image/medecin/i33.jfif',
    'Ophtalmologie' => isset($images_medecin_list[3]) ? $images_medecin_list[3] : 'image/medecin/i44.jfif',
    'Cardiologie' => isset($images_medecin_list[4]) ? $images_medecin_list[4] : 'image/medecin/I453.jfif',
    'Radiologie' => isset($images_medecin_list[5]) ? $images_medecin_list[5] : 'image/medecin/I55.jfif',
    'Pédiatrie' => isset($images_medecin_list[6]) ? $images_medecin_list[6] : 'image/medecin/I557.jfif',
    'Dermatologie' => isset($images_medecin_list[7]) ? $images_medecin_list[7] : 'image/medecin/335.jfif',
    'default' => isset($images_medecin_list[0]) ? $images_medecin_list[0] : 'image/medecin/i1.jfif'
];

// Mapper les spécialisations pour correspondre aux spécialités de l'image
$specialites_map = [
    'Gynécologie-Obstétrique' => 'Gynécologie',
    'Chirurgie générale' => 'Chirurgie',
    'Médecine générale' => 'Médecine Générale',
    'Ophtalmologie' => 'Ophtalmologie',
    'Cardiologie' => 'Cardiologie',
    'Radiologie' => 'Radiologie',
    'Pédiatrie' => 'Pédiatrie',
    'Dermatologie' => 'Dermatologie'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="keywords" content="médecins, docteurs, spécialistes, équipe médicale, MediCo">
	<meta name="author" content="MediCo.">
	<meta name="robots" content="index, follow">
	<meta name="description" content="Découvrez notre équipe de médecins spécialistes qualifiés chez MediCo. - Des professionnels dévoués à votre santé.">
	<meta property="og:title" content="MediCo. - Notre Équipe Médicale">
	<meta property="og:description" content="Rencontrez nos médecins spécialistes expérimentés dans différents domaines de la médecine.">
	<meta property="og:image" content="image/1.jpeg">
	<meta name="format-detection" content="telephone=no">
	
	<!-- FAVICONS ICON - utiliser le logo du site -->
	<link rel="icon" href="image/1.jpeg" type="image/jpeg">
	<link rel="shortcut icon" href="image/1.jpeg" type="image/jpeg">
	
	<!-- PAGE TITLE HERE -->
	<title>MediCo. - Nos Médecins Spécialistes</title>
	
	<!-- MOBILE SPECIFIC -->
	<meta name="viewport" content="width=device-width, initial-scale=1">
	
	<!--[if lt IE 9]>
	<script src="assets/js/html5shiv.min.js"></script>
	<script src="assets/js/respond.min.js"></script>
	<![endif]-->
	
	<!-- STYLESHEETS -->
	<link rel="stylesheet" type="text/css" href="assets/css/plugins.css">
	<link rel="stylesheet" type="text/css" href="assets/css/style.min.css">
	<link class="skin" rel="stylesheet" type="text/css" href="assets/css/skin/skin-1.css">
	<link rel="stylesheet" type="text/css" href="assets/css/templete.min.css">
	<style>
		.doctor-card {
			background: #fff;
			border-radius: 12px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			padding: 0;
			overflow: hidden;
			transition: transform 0.3s ease, box-shadow 0.3s ease;
		}
		.doctor-card:hover {
			transform: translateY(-5px);
			box-shadow: 0 5px 20px rgba(0,0,0,0.15);
		}
		.doctor-image {
			width: 100%;
			height: 400px;
			object-fit: cover;
			object-position: center top;
		}
		.doctor-info {
			padding: 20px;
			text-align: center;
		}
		.doctor-name {
			font-size: 18px;
			font-weight: 700;
			color: #002939;
			margin-bottom: 8px;
		}
		.doctor-specialty {
			font-size: 14px;
			color: #666;
			margin-bottom: 12px;
		}
		.doctor-rating {
			margin: 12px 0;
		}
		.doctor-rating .fa-star {
			color: #FFD700;
			font-size: 16px;
		}
		.doctor-contact {
			font-size: 13px;
			color: #666;
			margin: 8px 0;
		}
		.doctor-contact i {
			margin-right: 5px;
			color: #002939;
		}
		.btn-rdv {
			background: #002939;
			color: #fff;
			border: none;
			padding: 10px 20px;
			border-radius: 6px;
			font-weight: 600;
			width: 100%;
			margin-top: 15px;
			transition: background 0.3s ease;
		}
		.btn-rdv:hover {
			background: #004d66;
			color: #fff;
		}
		.cta-section {
			background: #002939;
			padding: 60px 0;
			text-align: center;
			margin-top: 50px;
		}
		.cta-section h2 {
			color: #fff;
			font-size: 32px;
			font-weight: 700;
			margin-bottom: 30px;
		}
		.btn-reserver {
			background: #4A90E2;
			color: #fff;
			border: none;
			padding: 15px 40px;
			border-radius: 8px;
			font-size: 18px;
			font-weight: 600;
			transition: background 0.3s ease;
		}
		.btn-reserver:hover {
			background: #357ABD;
			color: #fff;
		}
		.page-title-section {
			background: #f8f9fa;
			padding: 40px 0;
			text-align: center;
		}
		.page-title-section h1 {
			color: #002939;
			font-size: 36px;
			font-weight: 700;
			margin: 0;
		}
	</style>
</head>
<body id="bg">

<div id="loading-area"></div><div class="page-wraper">
    <!-- header -->
	<?php require_once 'partials/entete.php';?>
    <!-- header END -->
    
    <!-- Content -->
    <div class="page-content">
        <!-- Page Title -->
        <div class="page-title-section">
            <div class="container">
                <h1>Nos Médecins Spécialistes</h1>
            </div>
        </div>
        <!-- Page Title END -->
        
        <!-- Doctors Grid -->
        <div class="section-full content-inner" style="background: #f8f9fa; padding: 60px 0;">
            <div class="container">
                <?php if ($db_error): ?>
                    <!-- Message d'erreur de base de données (rouge) - seulement si vraie erreur -->
                    <div class="alert alert-danger" style="margin: 20px 0; padding: 25px; border-radius: 10px; background: #f8d7da; border: 2px solid #f5c6cb; color: #721c24; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <div style="display: flex; align-items: start; margin-bottom: 15px;">
                            <span style="font-size: 32px; margin-right: 15px;">⚠️</span>
                            <div style="flex: 1;">
                                <h4 style="margin: 0 0 10px 0; color: #721c24; font-size: 20px; font-weight: 700;">Erreur de Base de Données</h4>
                                <p style="margin: 0 0 15px 0; font-size: 16px; line-height: 1.6;"><strong><?php echo htmlspecialchars($db_error_message); ?></strong></p>
                                
                                <?php if (strpos($db_error_message, "initialisée") !== false || strpos($db_error_message, "install") !== false): ?>
                                    <p style="margin: 15px 0 10px 0; font-size: 15px; font-weight: 600;">Pour résoudre ce problème :</p>
                                    <ol style="margin: 10px 0 20px 20px; padding-left: 20px; line-height: 2;">
                                        <li>Accédez à <a href="install.php" style="color: #002939; font-weight: bold; text-decoration: underline;">install.php</a> pour créer automatiquement toutes les tables</li>
                                        <li>Ou importez manuellement le fichier <code style="background: #fff; padding: 2px 6px; border-radius: 3px;">config/sante1_database.sql</code> via phpMyAdmin</li>
                                    </ol>
                                    <a href="install.php" class="btn btn-rdv" style="display: inline-block; margin-top: 10px; padding: 12px 30px; text-decoration: none; font-size: 16px; font-weight: 600; border-radius: 6px; transition: all 0.3s;">
                                        <i class="fa fa-database"></i> Installer la Base de Données
                                    </a>
                                <?php else: ?>
                                    <p style="margin: 15px 0 10px 0; font-size: 15px;">Vérifiez votre configuration dans <code style="background: #fff; padding: 2px 6px; border-radius: 3px;">config/bdd.php</code></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php 
                // Debug temporaire - Afficher les informations de débogage
                if (isset($medecins) && !empty($medecins)): 
                    $noms_medecins = [];
                    foreach ($medecins as $m) {
                        $noms_medecins[] = $m['Nom_med'] . ' ' . $m['Prénom_med'];
                    }
                ?>
                <!-- Message de débogage temporaire -->
                <div class="alert alert-info" style="margin: 20px 0; padding: 15px; background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; border-radius: 8px;">
                    <strong>Debug:</strong> <?php echo count($medecins); ?> médecin(s) trouvé(s) dans la base de données.
                    <br><small>Médecins: <?php echo implode(', ', $noms_medecins); ?></small>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <?php if (!empty($medecins)): ?>
                        <?php foreach ($medecins as $medecin): 
                            // Déterminer l'image selon la spécialisation
                            $image = isset($images_medecins[$medecin['Spécialisation_med']]) 
                                ? $images_medecins[$medecin['Spécialisation_med']] 
                                : $images_medecins['default'];
                            
                            // Spécialité formatée
                            $specialite = isset($specialites_map[$medecin['Spécialisation_med']]) 
                                ? $specialites_map[$medecin['Spécialisation_med']] 
                                : $medecin['Spécialisation_med'];
                            
                            // Gérer le chemin de la photo de profil
                            $photo_profil = '';
                            if (!empty($medecin['Photo_profil'])) {
                                // Construire le chemin complet du fichier
                                $chemin_photo = $medecin['Photo_profil'];
                                
                                // Si le chemin ne commence pas par uploads/, l'ajouter
                                if (strpos($chemin_photo, 'uploads/') !== 0) {
                                    // Si c'est juste un nom de fichier, l'ajouter au dossier profiles
                                    if (strpos($chemin_photo, '/') === false) {
                                        $chemin_photo = 'uploads/profiles/' . $chemin_photo;
                                    }
                                }
                                
                                // Vérifier si le fichier existe réellement
                                if (file_exists(__DIR__ . '/' . $chemin_photo)) {
                                    $photo_profil = $chemin_photo;
                                } else {
                                    // Si le fichier n'existe pas, essayer avec uploads/profiles/ directement
                                    $chemin_alternatif = 'uploads/profiles/' . basename($medecin['Photo_profil']);
                                    if (file_exists(__DIR__ . '/' . $chemin_alternatif)) {
                                        $photo_profil = $chemin_alternatif;
                                    }
                                }
                            }
                        ?>
                        <div class="col-lg-4 col-md-6 col-sm-6 m-b30">
                            <div class="doctor-card">
                                <img src="<?php echo htmlspecialchars($photo_profil ? $photo_profil : $image); ?>" 
                                     alt="Dr. <?php echo htmlspecialchars($medecin['Prénom_med'] . ' ' . $medecin['Nom_med']); ?>" 
                                     class="doctor-image"
                                     onerror="this.src='<?php echo htmlspecialchars($image); ?>'">
                                <div class="doctor-info">
                                    <h4 class="doctor-name">Dr. <?php echo htmlspecialchars($medecin['Prénom_med'] . ' ' . $medecin['Nom_med']); ?></h4>
                                    <p class="doctor-specialty"><?php echo htmlspecialchars($specialite); ?></p>
                                    <?php if (isset($medecin['statut']) && $medecin['statut'] !== 'approuvé'): ?>
                                        <span class="badge badge-warning" style="background: #ffc107; color: #000; padding: 4px 8px; border-radius: 4px; font-size: 12px; margin-bottom: 10px; display: inline-block;">
                                            <?php 
                                            if ($medecin['statut'] === 'en_attente') {
                                                echo 'En attente d\'approbation';
                                            } elseif ($medecin['statut'] === 'refusé') {
                                                echo 'Refusé';
                                            }
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                    <div class="doctor-rating">
                                        <i class="fa fa-star"></i>
                                        <i class="fa fa-star"></i>
                                        <i class="fa fa-star"></i>
                                        <i class="fa fa-star"></i>
                                        <i class="fa fa-star"></i>
                                    </div>
                                    <?php if (!empty($medecin['Email_med'])): ?>
                                        <p class="doctor-contact">
                                            <i class="fa fa-envelope"></i> <?php echo htmlspecialchars($medecin['Email_med']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($medecin['Tel_med'])): ?>
                                        <p class="doctor-contact">
                                            <i class="fa fa-phone"></i> <?php echo htmlspecialchars($medecin['Tel_med']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (isset($medecin['statut']) && $medecin['statut'] === 'approuvé'): ?>
                                        <a href="rendez-vous.php?medecin=<?php echo $medecin['id_med']; ?>" class="btn btn-rdv">
                                            Prendre RDV
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-rdv" style="background: #6c757d; cursor: not-allowed;" disabled>
                                            Indisponible
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php elseif (!$db_error): ?>
                        <!-- Message informatif (bleu) - pas d'erreur, juste aucun médecin enregistré -->
                        <div class="col-12">
                            <div class="alert alert-info text-center" style="background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 30px; border-radius: 8px; margin: 20px 0;">
                                <i class="fa fa-info-circle" style="font-size: 48px; color: #0c5460; margin-bottom: 15px;"></i>
                                <h4 style="color: #0c5460; margin-bottom: 15px;">Aucun médecin enregistré</h4>
                                <p style="font-size: 16px; margin-bottom: 10px;">
                                    Il n'y a actuellement aucun médecin enregistré dans la base de données.
                                </p>
                                <p style="font-size: 14px; color: #666;">
                                    Dès qu'un médecin sera ajouté à la base de données, il apparaîtra automatiquement sur cette page.
                                </p>
                                <p style="font-size: 14px; color: #666; margin-top: 15px;">
                                    <i class="fa fa-database"></i> La base de données est correctement configurée et prête à recevoir des données.
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Doctors Grid END -->
        
        <!-- Call to Action Section -->
        <div class="cta-section">
            <div class="container">
                <h2>Prenez rendez-vous avec un spécialiste</h2>
                <a href="rendez-vous.php" class="btn btn-reserver">Réserver un rendez-vous</a>
            </div>
        </div>
        <!-- Call to Action END -->
    </div>
    <!-- Content END-->
    
    <!-- Footer -->
    <?php require_once 'partials/footer.php';?>
    <!-- Footer END-->
    
    <!-- scroll top button -->
    <button class="scroltop fa fa-chevron-up"></button>
</div>

<!-- JavaScript  files ========================================= -->
<script src="assets/js/jquery.min.js"></script>
<script src="assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="assets/plugins/bootstrap-select/bootstrap-select.min.js"></script>
<script src="assets/plugins/bootstrap-touchspin/jquery.bootstrap-touchspin.js"></script>
<script src="assets/plugins/magnific-popup/magnific-popup.js"></script>
<script src="assets/plugins/counter/waypoints-min.js"></script>
<script src="assets/plugins/counter/counterup.min.js"></script>
<script src="assets/plugins/imagesloaded/imagesloaded.js"></script>
<script src="assets/plugins/masonry/masonry-3.1.4.js"></script>
<script src="assets/plugins/masonry/masonry.filter.js"></script>
<script src="assets/plugins/owl-carousel/owl.carousel.js"></script>
<script src="assets/js/custom.min.js"></script>
<script src="assets/js/dz.carousel.min.js"></script>
<script src="assets/js/dz.ajax.js"></script>
<script src="assets/plugins/switcher/js/switcher.min.js"></script>
</body>
</html>
