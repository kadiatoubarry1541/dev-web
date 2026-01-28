<?php
require_once 'config/session.php';
require_once 'config/database_functions.php';

// Vérifier que l'utilisateur est connecté
requireLogin();

$user_info = getUserInfo();
$message = '';
$message_type = '';

// Récupérer les informations du patient
$patient = null;
if ($user_info['id_patient']) {
    $patient = getPatientById($user_info['id_patient']);
}

// Récupérer les rendez-vous du patient (uniquement les siens)
$rendez_vous = [];
$rendez_vous_confirmes = [];
$rendez_vous_en_attente = [];
if ($user_info && isset($user_info['id_patient']) && $user_info['id_patient']) {
    try {
        $rendez_vous = getRendezVousByPatient($user_info['id_patient']);
        if ($rendez_vous === false) {
            $rendez_vous = [];
        }
        
        // Séparer les rendez-vous par statut
        foreach ($rendez_vous as $rdv) {
            $statut = $rdv['Statut'] ?? 'planifié';
            if ($statut === 'confirmé' || $statut === 'terminé') {
                $rendez_vous_confirmes[] = $rdv;
            } elseif ($statut === 'planifié') {
                $rendez_vous_en_attente[] = $rdv;
            }
        }
    } catch (Exception $e) {
        error_log("Erreur récupération rendez-vous patient: " . $e->getMessage());
        $rendez_vous = [];
    }
}

// Récupérer les consultations du patient (uniquement les siennes)
$consultations = [];
if ($user_info && isset($user_info['id_patient']) && $user_info['id_patient']) {
    try {
        $consultations = getConsultationsByPatient($user_info['id_patient']);
        if ($consultations === false) {
            $consultations = [];
        }
    } catch (Exception $e) {
        error_log("Erreur récupération consultations patient: " . $e->getMessage());
        $consultations = [];
    }
}

// Récupérer les ordonnances du patient
$ordonnances = [];
if ($user_info && isset($user_info['id_patient']) && $user_info['id_patient']) {
    try {
        $ordonnances = getOrdonnancesByPatient($user_info['id_patient']);
        if ($ordonnances === false) {
            $ordonnances = [];
        }
    } catch (Exception $e) {
        error_log("Erreur récupération ordonnances patient: " . $e->getMessage());
        $ordonnances = [];
    }
}

// Grouper les ordonnances par consultation
$ordonnances_grouped = [];
foreach ($ordonnances as $ordo) {
    $id_consultation = $ordo['id_consultation'];
    if (!isset($ordonnances_grouped[$id_consultation])) {
        $ordonnances_grouped[$id_consultation] = [
            'consultation' => $ordo,
            'medicaments' => []
        ];
    }
    $ordonnances_grouped[$id_consultation]['medicaments'][] = $ordo;
}

// Récupérer les paiements avec reçus du patient
$paiements_avec_reçus = [];
if ($user_info && isset($user_info['id_patient']) && $user_info['id_patient']) {
    try {
        $paiements_avec_reçus = getPaiementsAvecReçus($user_info['id_patient']);
        if ($paiements_avec_reçus === false) {
            $paiements_avec_reçus = [];
        }
    } catch (Exception $e) {
        error_log("Erreur récupération paiements avec reçus: " . $e->getMessage());
        $paiements_avec_reçus = [];
    }
}

// Récupérer les notifications du patient
$notifications = [];
$nb_notifications_non_lues = 0;
if ($user_info && isset($user_info['id_patient']) && $user_info['id_patient']) {
    try {
        // S'assurer que la table existe
        if (!tableExists('NOTIFICATIONS')) {
            createNotificationsTable();
        }
        $notifications = getNotificationsByPatient($user_info['id_patient']);
        $nb_notifications_non_lues = countNotificationsNonLues($user_info['id_patient']);
    } catch (Exception $e) {
        error_log("Erreur récupération notifications: " . $e->getMessage());
        $notifications = [];
    }
}

// Traitement de la marque comme lue
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marquer_lu'])) {
    $id_notification = intval($_POST['id_notification'] ?? 0);
    if ($id_notification > 0) {
        marquerNotificationLue($id_notification);
        // Recharger les notifications
        $notifications = getNotificationsByPatient($user_info['id_patient']);
        $nb_notifications_non_lues = countNotificationsNonLues($user_info['id_patient']);
        $message = "Notification marquée comme lue.";
        $message_type = "success";
    }
}

// Traitement pour marquer toutes les notifications comme lues
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marquer_toutes_lues'])) {
    if ($user_info && isset($user_info['id_patient']) && $user_info['id_patient']) {
        if (marquerToutesNotificationsLues($user_info['id_patient'])) {
            // Recharger les notifications
            $notifications = getNotificationsByPatient($user_info['id_patient']);
            $nb_notifications_non_lues = countNotificationsNonLues($user_info['id_patient']);
            $message = "Toutes les notifications ont été marquées comme lues.";
            $message_type = "success";
        } else {
            $message = "Erreur lors du marquage des notifications.";
            $message_type = "danger";
        }
    }
}

// Traitement de la mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $nom = trim($_POST['nom'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $date_naissance = $_POST['date_naissance'] ?? null;
    
    if ($user_info['id_patient'] && $patient) {
        try {
            $pdo = bdd();
            $sql = "UPDATE PATIENTS SET Nom_patient = ?, Prénom_patient = ?, Tel_patient = ?, Adresse_patient = ?, Date_naissance_patient = ? WHERE id_patient = ?";
            $stmt = $pdo->prepare($sql);
            
            // Séparer nom et prénom
            $nom_parts = explode(' ', $nom, 2);
            $nom_patient = $nom_parts[0];
            $prenom_patient = isset($nom_parts[1]) ? $nom_parts[1] : '';
            
            $stmt->execute([$nom_patient, $prenom_patient, $telephone, $adresse, $date_naissance, $user_info['id_patient']]);
            
            // Mettre à jour aussi la table users
            $sql_user = "UPDATE users SET nom = ?, telephone = ? WHERE id = ?";
            $stmt_user = $pdo->prepare($sql_user);
            $stmt_user->execute([$nom, $telephone, $user_info['id']]);
            
            // Mettre à jour la session
            $_SESSION['user_nom'] = $nom;
            
            $message = "Votre profil a été mis à jour avec succès !";
            $message_type = "success";
            
            // Recharger les données
            $patient = getPatientById($user_info['id_patient']);
        } catch (Exception $e) {
            $message = "Erreur lors de la mise à jour : " . $e->getMessage();
            $message_type = "danger";
        }
    }
}
$is_patient = estConnecte() && (($user_info['role'] ?? '') === 'patient');
if ($is_patient) {
    require_once __DIR__ . '/config/permissions.php';
    $page_title = 'Mon Profil';
    require_once __DIR__ . '/patient/partials/header.php';
}
?>
<?php if (!$is_patient): ?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="keywords" content="profil, compte, informations personnelles, MediCo">
	<meta name="author" content="MediCo.">
	<meta name="robots" content="index, follow">
	<meta name="description" content="Gérez votre profil et vos informations personnelles sur MediCo.">
	<meta property="og:title" content="MediCo. - Mon Profil">
	<meta name="format-detection" content="telephone=no">
	
	<!-- FAVICONS ICON -->
	<link rel="icon" href="images/favicon.ico" type="image/x-icon">
	<link rel="shortcut icon" type="image/x-icon" href="images/favicon.png">
	
	<!-- PAGE TITLE HERE -->
	<title>MediCo. - Mon Profil</title>
	
	<!-- MOBILE SPECIFIC -->
	<meta name="viewport" content="width=device-width, initial-scale=1">
	
	<!-- STYLESHEETS -->
	<link rel="stylesheet" type="text/css" href="assets/css/plugins.css">
	<link rel="stylesheet" type="text/css" href="assets/css/style.min.css">
	<link class="skin" rel="stylesheet" type="text/css" href="assets/css/skin/skin-1.css">
	<link rel="stylesheet" type="text/css" href="assets/css/templete.min.css">
</head>
<body id="bg">

<div id="loading-area"></div><div class="page-wraper">
    <!-- header -->
	<?php require_once 'partials/entete.php';?>
    <!-- header END -->
    
    <!-- Content -->
    <div class="page-content">
        <!-- Page Banner -->
        <div class="page-banner ovbl-dark" style="background-image:url(images/background/bg1.jpg);">
            <div class="container">
                <div class="page-banner-entry">
                    <h1 class="text-white">Mon Profil</h1>
                    <nav aria-label="breadcrumb" class="breadcrumb-row">
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Mon Profil</li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
        <!-- Page Banner END -->
        
        <div class="section-full content-inner bg-white">
            <div class="container">
<?php endif; ?>
<?php if ($is_patient): ?><div class="section-full content-inner bg-white" style="background:#f5f7fa;"><div class="container"><?php endif; ?>
                <a href="<?php echo $is_patient ? 'patient/index.php' : 'index.php'; ?>" class="btn-retour" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #4A90E2; color: white; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 500; transition: all 0.3s; margin-bottom: 20px;">
                    <i class="fa fa-arrow-left"></i> <?php echo $is_patient ? 'Tableau de bord' : "Retour à l'accueil"; ?>
                </a>
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Informations personnelles -->
                    <div class="col-lg-8 m-b30">
                        <div class="dez-box p-a30 border-1">
                            <h3 class="h3 text-uppercase m-b20">Mes Informations Personnelles</h3>
                            <form method="post" action="">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Nom Complet *</label>
                                            <input name="nom" type="text" class="form-control" required 
                                                   value="<?php echo htmlspecialchars($user_info['nom']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Email</label>
                                            <input type="email" class="form-control" 
                                                   value="<?php echo htmlspecialchars($user_info['email']); ?>" disabled>
                                            <small class="text-muted">L'email ne peut pas être modifié</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Téléphone *</label>
                                            <input name="telephone" type="tel" class="form-control" required 
                                                   value="<?php echo htmlspecialchars($patient['Tel_patient'] ?? $user_info['email']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Date de naissance</label>
                                            <input name="date_naissance" type="date" class="form-control" 
                                                   value="<?php echo htmlspecialchars($patient['Date_naissance_patient'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>Adresse</label>
                                            <textarea name="adresse" class="form-control" rows="3"><?php echo htmlspecialchars($patient['Adresse_patient'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <?php if ($patient): ?>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>Matricule Patient</label>
                                            <input type="text" class="form-control" 
                                                   value="<?php echo htmlspecialchars($patient['Matricule_patient']); ?>" disabled>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="col-md-12">
                                        <button name="update_profile" type="submit" class="site-button">Mettre à jour mon profil</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Mes rendez-vous et consultations -->
                    <div class="col-lg-4 m-b30">
                        <div class="dez-box p-a30 border-1 bg-gray">
                            <h4 class="h4 m-b20"><i class="fa fa-calendar"></i> Mes Rendez-vous</h4>
                            <div class="m-b20">
                                <a href="<?php echo htmlspecialchars('rendez-vous.php'); ?>" class="site-button btn-block" style="text-decoration: none; display: block; text-align: center;">
                                    <i class="fa fa-calendar-plus-o"></i> Prendre un nouveau rendez-vous
                                </a>
                            </div>
                            
                            <?php if (!empty($rendez_vous)): ?>
                                <!-- Rendez-vous Confirmés -->
                                <?php if (!empty($rendez_vous_confirmes)): ?>
                                    <div class="m-b30">
                                        <h5 class="h5 m-b15" style="color: #28a745; font-weight: 600;">
                                            <i class="fa fa-check-circle"></i> Rendez-vous Confirmés (<?php echo count($rendez_vous_confirmes); ?>)
                                        </h5>
                                        <div style="max-height: 300px; overflow-y: auto;">
                                            <?php foreach ($rendez_vous_confirmes as $rdv): ?>
                                                <div class="m-b15 p-a15 bg-white border-1" style="border-left: 4px solid #28a745;">
                                                    <?php if (isset($rdv['Date_rdv'])): ?>
                                                        <p class="m-b5"><strong><i class="fa fa-calendar-check-o"></i> <?php echo date('d/m/Y à H:i', strtotime($rdv['Date_rdv'])); ?></strong></p>
                                                    <?php endif; ?>
                                                    <?php if (isset($rdv['Prénom_med']) && isset($rdv['Nom_med'])): ?>
                                                        <p class="m-b5 text-primary"><i class="fa fa-user-md"></i> Dr. <?php echo htmlspecialchars($rdv['Prénom_med'] . ' ' . $rdv['Nom_med']); ?></p>
                                                    <?php endif; ?>
                                                    <?php if (isset($rdv['Nom_service']) && $rdv['Nom_service']): ?>
                                                        <p class="m-b5"><small><i class="fa fa-stethoscope"></i> <?php echo htmlspecialchars($rdv['Nom_service']); ?></small></p>
                                                    <?php endif; ?>
                                                    <?php if (isset($rdv['Spécialisation_med']) && $rdv['Spécialisation_med']): ?>
                                                        <p class="m-b5"><small><i class="fa fa-graduation-cap"></i> <?php echo htmlspecialchars($rdv['Spécialisation_med']); ?></small></p>
                                                    <?php endif; ?>
                                                    <?php if (isset($rdv['Motif']) && !empty($rdv['Motif'])): ?>
                                                        <p class="m-b5"><small><strong>Motif :</strong> <?php echo htmlspecialchars(substr($rdv['Motif'], 0, 60)) . (strlen($rdv['Motif']) > 60 ? '...' : ''); ?></small></p>
                                                    <?php endif; ?>
                                                    <span class="badge badge-success">
                                                        <i class="fa fa-check"></i> <?php echo ucfirst($rdv['Statut'] ?? 'confirmé'); ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Rendez-vous En Attente -->
                                <?php if (!empty($rendez_vous_en_attente)): ?>
                                    <div class="m-b30">
                                        <h5 class="h5 m-b15" style="color: #ffc107; font-weight: 600;">
                                            <i class="fa fa-clock-o"></i> Rendez-vous En Attente (<?php echo count($rendez_vous_en_attente); ?>)
                                        </h5>
                                        <div style="max-height: 300px; overflow-y: auto;">
                                            <?php foreach ($rendez_vous_en_attente as $rdv): ?>
                                                <div class="m-b15 p-a15 bg-white border-1" style="border-left: 4px solid #ffc107;">
                                                    <?php if (isset($rdv['Date_rdv'])): ?>
                                                        <p class="m-b5"><strong><i class="fa fa-calendar"></i> <?php echo date('d/m/Y à H:i', strtotime($rdv['Date_rdv'])); ?></strong></p>
                                                    <?php endif; ?>
                                                    <?php if (isset($rdv['Prénom_med']) && isset($rdv['Nom_med'])): ?>
                                                        <p class="m-b5 text-primary"><i class="fa fa-user-md"></i> Dr. <?php echo htmlspecialchars($rdv['Prénom_med'] . ' ' . $rdv['Nom_med']); ?></p>
                                                    <?php endif; ?>
                                                    <?php if (isset($rdv['Nom_service']) && $rdv['Nom_service']): ?>
                                                        <p class="m-b5"><small><i class="fa fa-stethoscope"></i> <?php echo htmlspecialchars($rdv['Nom_service']); ?></small></p>
                                                    <?php endif; ?>
                                                    <?php if (isset($rdv['Spécialisation_med']) && $rdv['Spécialisation_med']): ?>
                                                        <p class="m-b5"><small><i class="fa fa-graduation-cap"></i> <?php echo htmlspecialchars($rdv['Spécialisation_med']); ?></small></p>
                                                    <?php endif; ?>
                                                    <?php if (isset($rdv['Motif']) && !empty($rdv['Motif'])): ?>
                                                        <p class="m-b5"><small><strong>Motif :</strong> <?php echo htmlspecialchars(substr($rdv['Motif'], 0, 60)) . (strlen($rdv['Motif']) > 60 ? '...' : ''); ?></small></p>
                                                    <?php endif; ?>
                                                    <span class="badge badge-warning">
                                                        <i class="fa fa-clock-o"></i> En attente de confirmation
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Autres statuts (annulés, etc.) -->
                                <?php 
                                $rendez_vous_autres = array_filter($rendez_vous, function($rdv) {
                                    $statut = $rdv['Statut'] ?? 'planifié';
                                    return !in_array($statut, ['confirmé', 'terminé', 'planifié']);
                                });
                                if (!empty($rendez_vous_autres)): ?>
                                    <div class="m-b30">
                                        <h5 class="h5 m-b15" style="color: #6c757d; font-weight: 600;">
                                            <i class="fa fa-list"></i> Autres (<?php echo count($rendez_vous_autres); ?>)
                                        </h5>
                                        <div style="max-height: 200px; overflow-y: auto;">
                                            <?php foreach ($rendez_vous_autres as $rdv): ?>
                                                <div class="m-b15 p-a15 bg-white border-1">
                                                    <?php if (isset($rdv['Date_rdv'])): ?>
                                                        <p class="m-b5"><strong><?php echo date('d/m/Y à H:i', strtotime($rdv['Date_rdv'])); ?></strong></p>
                                                    <?php endif; ?>
                                                    <?php if (isset($rdv['Prénom_med']) && isset($rdv['Nom_med'])): ?>
                                                        <p class="m-b5 text-primary">Dr. <?php echo htmlspecialchars($rdv['Prénom_med'] . ' ' . $rdv['Nom_med']); ?></p>
                                                    <?php endif; ?>
                                                    <?php if (isset($rdv['Statut'])): ?>
                                                        <span class="badge badge-<?php 
                                                            echo $rdv['Statut'] === 'annulé' ? 'danger' : 'secondary'; 
                                                        ?>"><?php echo ucfirst($rdv['Statut']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                            <?php else: ?>
                                <p class="text-center m-b20">Aucun rendez-vous pour le moment.</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Mes Ordonnances -->
                        <div class="dez-box p-a30 border-1 bg-gray m-t30">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                <h4 class="h4 m-b0"><i class="fa fa-prescription"></i> Mes Ordonnances</h4>
                                <?php if (!empty($ordonnances_grouped)): ?>
                                    <a href="mes-ordonnances.php" class="site-button" style="padding: 8px 15px; font-size: 12px; text-decoration: none;">
                                        <i class="fa fa-eye"></i> Voir toutes
                                    </a>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($ordonnances_grouped)): ?>
                                <div style="max-height: 400px; overflow-y: auto;">
                                    <?php foreach ($ordonnances_grouped as $id_consultation => $group): 
                                        $consultation = $group['consultation'];
                                        $medicaments = $group['medicaments'];
                                    ?>
                                        <div class="m-b15 p-a15 bg-white border-1" style="border-left: 4px solid #4A90E2;">
                                            <div style="margin-bottom: 10px;">
                                                <?php if (isset($consultation['Date_consultation'])): ?>
                                                    <p class="m-b5"><strong><i class="fa fa-calendar"></i> Consultation du <?php echo date('d/m/Y à H:i', strtotime($consultation['Date_consultation'])); ?></strong></p>
                                                <?php endif; ?>
                                                <?php if (isset($consultation['Prénom_med']) && isset($consultation['Nom_med'])): ?>
                                                    <p class="m-b5 text-primary"><i class="fa fa-user-md"></i> Dr. <?php echo htmlspecialchars($consultation['Prénom_med'] . ' ' . $consultation['Nom_med']); ?></p>
                                                <?php endif; ?>
                                                <?php if (isset($consultation['Date_émission'])): ?>
                                                    <p class="m-b5"><small><i class="fa fa-file-text"></i> <strong>Date d'émission :</strong> <?php echo date('d/m/Y', strtotime($consultation['Date_émission'])); ?></small></p>
                                                <?php endif; ?>
                                            </div>
                                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                                                <strong style="color: #4A90E2;"><i class="fa fa-medkit"></i> Prescription :</strong>
                                                <ul style="margin-top: 10px; padding-left: 20px;">
                                                    <?php foreach ($medicaments as $medicament): ?>
                                                        <li style="margin-bottom: 8px;">
                                                            <strong><?php echo htmlspecialchars($medicament['Médicament']); ?></strong>
                                                            <?php if (!empty($medicament['Dosage'])): ?>
                                                                <br><small>Dosage : <?php echo htmlspecialchars($medicament['Dosage']); ?></small>
                                                            <?php endif; ?>
                                                            <?php if (!empty($medicament['Durée_traitement'])): ?>
                                                                <br><small>Durée : <?php echo htmlspecialchars($medicament['Durée_traitement']); ?></small>
                                                            <?php endif; ?>
                                                            <?php if (!empty($medicament['Instructions'])): ?>
                                                                <br><small style="color: #666;">Instructions : <?php echo htmlspecialchars($medicament['Instructions']); ?></small>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-center">Aucune ordonnance pour le moment.</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Mes Reçus -->
                        <div class="dez-box p-a30 border-1 bg-gray m-t30">
                            <h4 class="h4 m-b20"><i class="fa fa-receipt"></i> Mes Reçus de Paiement</h4>
                            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                                <i class="fa fa-info-circle"></i> Consultez et téléchargez vos reçus de paiement. Cliquez sur "Voir le reçu" pour l'ouvrir dans un nouvel onglet et l'imprimer si nécessaire.
                            </p>
                            <?php if (!empty($paiements_avec_reçus)): ?>
                                <div style="max-height: 400px; overflow-y: auto;">
                                    <?php foreach ($paiements_avec_reçus as $paiement): ?>
                                        <div class="m-b15 p-a15 bg-white border-1">
                                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                                <div>
                                                    <?php if (isset($paiement['Date_paiement'])): ?>
                                                        <p class="m-b5"><strong><?php echo date('d/m/Y à H:i', strtotime($paiement['Date_paiement'])); ?></strong></p>
                                                    <?php endif; ?>
                                                    <?php if (isset($paiement['id_facture'])): ?>
                                                        <p class="m-b5"><small><i class="fa fa-file-invoice"></i> <strong>Facture :</strong> <?php echo htmlspecialchars($paiement['id_facture']); ?></small></p>
                                                    <?php endif; ?>
                                                    <p class="m-b0"><small><strong>Montant :</strong> <?php echo number_format($paiement['Montant'], 0, ',', ' '); ?> GNF</small></p>
                                                    <p class="m-b0"><small><strong>Méthode :</strong> <?php echo htmlspecialchars(ucfirst($paiement['Méthode_paiement'] ?? 'N/A')); ?></small></p>
                                                </div>
                                                <div>
                                                    <a href="paiements/voir-reçu.php?id=<?php echo $paiement['id_paiement']; ?>" 
                                                       class="site-button" 
                                                       target="_blank"
                                                       style="padding: 8px 15px; font-size: 12px;"
                                                       title="Ouvrir le reçu dans un nouvel onglet pour le lire et l'imprimer">
                                                        <i class="fa fa-eye"></i> Voir le reçu
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div style="margin-top: 15px; text-align: center;">
                                    <a href="paiements/liste-paiements.php" class="site-button btn-block">
                                        <i class="fa fa-list"></i> Voir tous mes paiements
                                    </a>
                                </div>
                            <?php else: ?>
                                <p class="text-center">Aucun reçu disponible pour le moment.</p>
                                <div style="margin-top: 15px; text-align: center;">
                                    <a href="paiements/liste-paiements.php" class="site-button btn-block">
                                        <i class="fa fa-money"></i> Voir mes paiements
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Mes Notifications -->
                        <div class="dez-box p-a30 border-1 bg-gray m-t30">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                <h4 class="h4 m-b0">
                                    <i class="fa fa-bell"></i> Mes Notifications
                                    <?php if ($nb_notifications_non_lues > 0): ?>
                                        <span class="badge badge-danger" style="margin-left: 10px; font-size: 14px; padding: 5px 10px; animation: pulse 2s infinite;">
                                            <?php echo $nb_notifications_non_lues; ?> nouveau<?php echo $nb_notifications_non_lues > 1 ? 'x' : ''; ?>
                                        </span>
                                    <?php endif; ?>
                                </h4>
                                <?php if ($nb_notifications_non_lues > 0): ?>
                                    <form method="post" style="margin: 0;" onsubmit="return confirm('Marquer toutes les notifications comme lues ?');">
                                        <input type="hidden" name="marquer_toutes_lues" value="1">
                                        <button type="submit" class="site-button" style="padding: 6px 12px; font-size: 11px; background: #6c757d;">
                                            <i class="fa fa-check-double"></i> Tout marquer lu
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                                <i class="fa fa-info-circle"></i> Vous recevrez une notification lorsqu'un médecin confirmera votre rendez-vous.
                            </p>
                            <?php if (!empty($notifications)): ?>
                                <div style="max-height: 500px; overflow-y: auto; padding-right: 5px;">
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="m-b15 p-a15 bg-white border-1 notification-item" 
                                             style="<?php echo $notification['lu'] == 0 ? 'border-left: 5px solid #4A90E2; background: linear-gradient(to right, #f0f7ff 0%, #ffffff 10%); box-shadow: 0 2px 8px rgba(74, 144, 226, 0.15);' : 'border-left: 3px solid #e0e0e0;'; ?>">
                                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                                <div style="flex: 1; min-width: 0;">
                                                    <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                                        <h5 class="m-b0" style="font-size: 15px; font-weight: 700; color: #002939; margin-right: 8px;">
                                                            <?php echo htmlspecialchars($notification['titre']); ?>
                                                        </h5>
                                                        <?php if ($notification['lu'] == 0): ?>
                                                            <span class="badge badge-primary" style="font-size: 10px; padding: 3px 8px; background: #4A90E2; color: white; border-radius: 12px; font-weight: 600;">
                                                                <i class="fa fa-circle"></i> Nouveau
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="m-b5" style="font-size: 14px; color: #333; line-height: 1.6; word-wrap: break-word;">
                                                        <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                                                    </p>
                                                    <div style="display: flex; align-items: center; gap: 15px; margin-top: 10px;">
                                                        <p class="m-b0" style="font-size: 12px; color: #999;">
                                                            <i class="fa fa-clock-o"></i> <?php echo date('d/m/Y à H:i', strtotime($notification['Date_creation'])); ?>
                                                        </p>
                                                        <?php 
                                                        // Afficher le type de notification
                                                        $type_labels = [
                                                            'rendez_vous' => '<i class="fa fa-calendar"></i> Rendez-vous',
                                                            'paiement' => '<i class="fa fa-money"></i> Paiement',
                                                            'consultation' => '<i class="fa fa-stethoscope"></i> Consultation',
                                                            'autre' => '<i class="fa fa-bell"></i> Notification'
                                                        ];
                                                        $type_label = $type_labels[$notification['type']] ?? $type_labels['autre'];
                                                        ?>
                                                        <span style="font-size: 11px; color: #666; background: #f5f5f5; padding: 3px 8px; border-radius: 4px;">
                                                            <?php echo $type_label; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div style="margin-left: 15px; display: flex; flex-direction: column; gap: 8px; min-width: 100px;">
                                                    <?php if ($notification['lien']): ?>
                                                        <a href="<?php echo htmlspecialchars($notification['lien']); ?>" 
                                                           class="site-button" 
                                                           style="padding: 8px 15px; font-size: 12px; text-align: center; text-decoration: none; display: block; background: #4A90E2; color: white; border-radius: 6px; transition: all 0.3s;"
                                                           onmouseover="this.style.background='#357ABD';"
                                                           onmouseout="this.style.background='#4A90E2';">
                                                            <i class="fa fa-eye"></i> Voir les détails
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($notification['lu'] == 0): ?>
                                                        <form method="post" style="margin: 0;">
                                                            <input type="hidden" name="id_notification" value="<?php echo $notification['id_notification']; ?>">
                                                            <button type="submit" name="marquer_lu" 
                                                                    class="site-button" 
                                                                    style="padding: 8px 15px; font-size: 12px; background: #6c757d; color: white; border: none; border-radius: 6px; width: 100%; cursor: pointer; transition: all 0.3s;"
                                                                    onmouseover="this.style.background='#5a6268';"
                                                                    onmouseout="this.style.background='#6c757d';">
                                                                <i class="fa fa-check"></i> Marquer lu
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span style="padding: 8px 15px; font-size: 12px; background: #d4edda; color: #155724; border-radius: 6px; text-align: center; display: block;">
                                                            <i class="fa fa-check-circle"></i> Lu
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div style="text-align: center; padding: 40px 20px; color: #999;">
                                    <i class="fa fa-bell-slash" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                                    <p style="font-size: 16px; margin: 0;">Aucune notification pour le moment.</p>
                                    <p style="font-size: 13px; margin-top: 10px; color: #bbb;">Vous recevrez des notifications lorsque vos rendez-vous seront confirmés.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <style>
                            @keyframes pulse {
                                0%, 100% { opacity: 1; }
                                50% { opacity: 0.7; }
                            }
                            .notification-item {
                                transition: all 0.3s ease;
                            }
                            .notification-item:hover {
                                transform: translateX(3px);
                                box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
                            }
                        </style>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Content END-->
<?php if ($is_patient): ?>
    </div></div>
    <?php require_once __DIR__ . '/patient/partials/footer.php'; ?>
<?php else: ?>
    
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
<?php endif; ?>
