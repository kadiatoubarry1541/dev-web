<?php
require_once 'config/session.php';
require_once 'config/database_functions.php';
require_once 'config/bdd.php';
require_once 'config/permissions.php';
require_once 'config/traitement.php'; // genererMatriculePatient pour création dossier à la volée

$message = '';
$message_type = '';
$patient_trouve = null;

// Vérifier si l'utilisateur est connecté et déterminer son rôle
$user_info = null;
$is_patient_connected = false;
$is_accueil = false;
$is_medecin = false;
$is_admin = false;

if (estConnecte()) {
    $user_info = getUserInfo();
    $role = $user_info['role'] ?? 'patient';
    
    // Vérifier les permissions : accès à la page de prise de rendez-vous
    $is_patient_connected = isPatient();
    $is_accueil = isAccueil();
    $is_medecin = isMedecin();
    $is_admin = isAdmin();
    if (!hasPermission('create_rendez_vous') && !hasPermission('manage_rendez_vous')) {
        header('Location: index.php?error=no_permission');
        exit();
    }
    
    // Si le patient est connecté, récupérer ses informations
    if ($is_patient_connected) {
        try {
            $pdo = bdd();
            
            // PRIORITÉ 1 : Rechercher par matricule dans toute la base (exact, trim, casse, LIKE, nom)
            if (!empty($user_info['matricule_patient'])) {
                $patient_trouve = trouverPatientParMatriculeTouteBase(
                    $user_info['matricule_patient'],
                    $user_info['nom'] ?? ''
                );
                if ($patient_trouve && is_array($patient_trouve) && !empty($patient_trouve['id_patient'])) {
                    $_SESSION['id_patient'] = $patient_trouve['id_patient'];
                    $user_info['id_patient'] = $patient_trouve['id_patient'];
                }
            }
            
            // PRIORITÉ 2 : Si pas trouvé par matricule, essayer avec l'id_patient de la session
            if ((!$patient_trouve || !is_array($patient_trouve) || empty($patient_trouve['id_patient'])) && !empty($user_info['id_patient'])) {
                $sql = "SELECT * FROM PATIENTS WHERE id_patient = ? LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([intval($user_info['id_patient'])]);
                $patient_trouve = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($patient_trouve && is_array($patient_trouve) && !empty($patient_trouve['id_patient'])) {
                    $_SESSION['id_patient'] = $patient_trouve['id_patient'];
                    $user_info['id_patient'] = $patient_trouve['id_patient'];
                }
            }
            
            // PRIORITÉ 3 : Si toujours pas trouvé, essayer par email
            if ((!$patient_trouve || !is_array($patient_trouve) || empty($patient_trouve['id_patient'])) && !empty($user_info['email'])) {
                $sql = "SELECT * FROM PATIENTS WHERE Email_patient = ? LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([trim($user_info['email'])]);
                $patient_trouve = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($patient_trouve && is_array($patient_trouve) && !empty($patient_trouve['id_patient'])) {
                    $_SESSION['id_patient'] = $patient_trouve['id_patient'];
                    $user_info['id_patient'] = $patient_trouve['id_patient'];
                }
            }
            
            // Si le patient n'est toujours pas trouvé, essayer de le récupérer depuis la session
            if ((!$patient_trouve || !is_array($patient_trouve) || empty($patient_trouve['id_patient'])) && isset($_SESSION['patient_rdv'])) {
                $patient_session = $_SESSION['patient_rdv'];
                // Vérifier que ce patient existe vraiment dans la base
                if ($patient_session && isset($patient_session['id_patient'])) {
                    $sql = "SELECT * FROM PATIENTS WHERE id_patient = ? LIMIT 1";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([intval($patient_session['id_patient'])]);
                    $patient_trouve = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($patient_trouve && is_array($patient_trouve) && !empty($patient_trouve['id_patient'])) {
                        $_SESSION['id_patient'] = $patient_trouve['id_patient'];
                        $user_info['id_patient'] = $patient_trouve['id_patient'];
                    } else {
                        $patient_trouve = null;
                    }
                }
            }
            
            // Dossier absent : d'abord lier un dossier existant (ex. même email), sinon création à la volée
            if (!$patient_trouve || empty($patient_trouve['id_patient'])) {
                $email = trim($user_info['email'] ?? '');
                if (!empty($email)) {
                    $stmt_email = $pdo->prepare("SELECT * FROM PATIENTS WHERE LOWER(TRIM(COALESCE(Email_patient,''))) = LOWER(?) LIMIT 1");
                    $stmt_email->execute([$email]);
                    $exist = $stmt_email->fetch(PDO::FETCH_ASSOC);
                    if ($exist && !empty($exist['id_patient'])) {
                        $patient_trouve = $exist;
                        $_SESSION['id_patient'] = $exist['id_patient'];
                        $_SESSION['matricule_patient'] = $exist['Matricule_patient'] ?? null;
                        $user_info['id_patient'] = $exist['id_patient'];
                        $stmt_link = $pdo->prepare("UPDATE users SET id_patient = ? WHERE id = ?");
                        $stmt_link->execute([$exist['id_patient'], (int)($_SESSION['user_id'] ?? 0)]);
                    }
                }
            }
            if ((!$patient_trouve || empty($patient_trouve['id_patient'])) && function_exists('genererMatriculePatient')) {
                try {
                    $nom_complet = trim($user_info['nom'] ?? '');
                    $email = trim($user_info['email'] ?? '');
                    if (!empty($nom_complet) || !empty($email)) {
                        $matricule = genererMatriculePatient();
                        $parts = explode(' ', $nom_complet, 2);
                        $prenom_pat = !empty($parts[0]) ? trim($parts[0]) : 'Patient';
                        $nom_pat = !empty($parts[1]) ? trim($parts[1]) : $prenom_pat;
                        $tel = $_SESSION['telephone'] ?? $user_info['telephone'] ?? null;
                        $sql_pat = "INSERT INTO PATIENTS (Matricule_patient, Nom_patient, Prénom_patient, Date_naissance_patient, Tel_patient, Email_patient, Adresse_patient) 
                                    VALUES (?, ?, ?, '1900-01-01', ?, ?, NULL)";
                        $stmt_pat = $pdo->prepare($sql_pat);
                        $stmt_pat->execute([$matricule, $nom_pat, $prenom_pat, $tel, $email ?: null]);
                        $new_id = (int) $pdo->lastInsertId();
                        if ($new_id) {
                            $_SESSION['id_patient'] = $new_id;
                            $_SESSION['matricule_patient'] = $matricule;
                            $user_info['id_patient'] = $new_id;
                            $stmt_user = $pdo->prepare("UPDATE users SET id_patient = ? WHERE id = ?");
                            $stmt_user->execute([$new_id, (int)($_SESSION['user_id'] ?? 0)]);
                            $patient_trouve = [
                                'id_patient' => $new_id,
                                'Matricule_patient' => $matricule,
                                'Nom_patient' => $nom_pat,
                                'Prénom_patient' => $prenom_pat,
                                'Email_patient' => $email,
                                'Tel_patient' => $tel
                            ];
                        }
                    }
                } catch (Exception $e) {
                    error_log("Création dossier patient à la volée (chargement): " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            error_log("Erreur récupération patient connecté: " . $e->getMessage());
        }
    }
}

// Récupérer le patient depuis la session si disponible (pour les patients non connectés)
if (!$patient_trouve && isset($_SESSION['patient_rdv'])) {
    $patient_trouve = $_SESSION['patient_rdv'];
}

// Traitement de la recherche de patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rechercher_patient'])) {
    $recherche = trim($_POST['recherche_patient'] ?? '');
    if (!empty($recherche)) {
        try {
            $pdo = bdd();
            
            // Vérifier si la table PATIENTS existe
            if (!tableExists('PATIENTS')) {
                $message = "La base de données n'est pas encore initialisée. Veuillez exécuter install.php pour créer la base de données.";
                $message_type = "danger";
            } else {
                // Recherche uniquement par matricule
                $recherche_trim = trim($recherche);
                
                // Recherche exacte par matricule d'abord (plus rapide et précise)
                $sql = "SELECT p.*, c.Num_carnet 
                        FROM PATIENTS p 
                        LEFT JOIN CARNETS c ON p.id_patient = c.id_patient 
                        WHERE p.Matricule_patient = ? 
                        LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$recherche_trim]);
                $patient_trouve = $stmt->fetch();
                
                // Si pas trouvé par matricule exacte, essayer avec LIKE (recherche partielle)
                if (!$patient_trouve) {
                    $sql = "SELECT p.*, c.Num_carnet 
                            FROM PATIENTS p 
                            LEFT JOIN CARNETS c ON p.id_patient = c.id_patient 
                            WHERE p.Matricule_patient LIKE ? 
                            LIMIT 1";
                    $stmt = $pdo->prepare($sql);
                    $search_term = '%' . $recherche_trim . '%';
                    $stmt->execute([$search_term]);
                    $patient_trouve = $stmt->fetch();
                }
                
                if ($patient_trouve) {
                    $_SESSION['patient_rdv'] = $patient_trouve;
                } else {
                    unset($_SESSION['patient_rdv']);
                    $message = "Aucun patient trouvé avec ce matricule. Veuillez vérifier votre matricule et réessayer.";
                    $message_type = "info";
                }
            }
        } catch (PDOException $e) {
            $error_msg = $e->getMessage();
            error_log("Erreur recherche patient: " . $error_msg);
            if (strpos($error_msg, "doesn't exist") !== false || 
                strpos($error_msg, "n'existe pas") !== false ||
                strpos($error_msg, "Table") !== false) {
                $message = "La base de données n'est pas encore initialisée. Veuillez exécuter install.php pour créer la base de données.";
            } else {
                $message = "Erreur lors de la recherche : " . $error_msg;
            }
            $message_type = "danger";
        } catch (Exception $e) {
            error_log("Erreur recherche patient: " . $e->getMessage());
            $message = "Erreur lors de la recherche : " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Traitement du formulaire de rendez-vous
// Objectif : pour le patient, dès que les champs obligatoires sont remplis (nom, service, date/heure),
// la demande doit toujours être enregistrée, sans autres blocages liés au matricule, au dossier patient ou au médecin.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rdv'])) {
    $id_service        = !empty($_POST['id_service']) ? intval($_POST['id_service']) : null;
    $nom_patient       = trim($_POST['nom_patient'] ?? '');
    $matricule_patient = trim($_POST['matricule_patient'] ?? '');
    $email_demandeur   = trim($_POST['email'] ?? '');
    $date_heure_rdv    = $_POST['date_heure_rdv'] ?? '';
    $motif             = trim($_POST['motif'] ?? '');

    // 1. Vérification MINIMALE : les 3 champs obligatoires
    if (empty($date_heure_rdv)) {
        $message = "Veuillez indiquer la date et l'heure du rendez-vous.";
        $message_type = "danger";
    } elseif (empty($nom_patient)) {
        $message = "Veuillez saisir le nom du patient.";
        $message_type = "danger";
    } elseif (empty($id_service)) {
        $message = "Veuillez sélectionner un service.";
        $message_type = "danger";
    } else {
        // 2. Si les champs sont remplis, on doit TOUJOURS enregistrer quelque chose
        // On enregistre systématiquement une DEMANDE_RENDEZ_VOUS
        $ts = @strtotime(trim($date_heure_rdv));
        $date_rdv_mysql = ($ts !== false && $ts > 0) ? date('Y-m-d H:i:s', $ts) : null;

        if (!$date_rdv_mysql) {
            // Si le format est vraiment illisible, on renvoie juste un message sur le format
            $message = "Indiquez la date et l'heure du rendez-vous au bon format (ex. 28/01/2026 14:30).";
            $message_type = "danger";
        } elseif (!function_exists('creerDemandeRendezVous')) {
            // Sécurité : fonction manquante côté serveur
            $message = "Le système de demande de rendez-vous n'est pas disponible pour le moment.";
            $message_type = "danger";
        } else {
            // Préparer les infos de la demande
            $email_d = $is_patient_connected && $user_info ? trim($user_info['email'] ?? '') : $email_demandeur;
            $nom_d   = $nom_patient;
            $mat_d   = $is_patient_connected && $user_info
                ? (trim($user_info['matricule_patient'] ?? '') ?: $matricule_patient)
                : $matricule_patient;
            $id_user = ($is_patient_connected && !empty($_SESSION['user_id'])) ? (int)$_SESSION['user_id'] : null;

            // 3. Enregistrement SYSTÉMATIQUE de la demande (aucun blocage sur matricule/dossier)
            if (creerDemandeRendezVous(
                $date_rdv_mysql,
                $email_d ?: null,
                $nom_d,
                $mat_d ?: null,
                (int)$id_service,
                $motif,
                $id_user
            )) {
                $message = "Votre demande de rendez-vous a été enregistrée. L'accueil la transmettra au service, puis un médecin la confirmera.";
                $message_type = "success";
                $_POST = [];
            } else {
                // Seul cas d'échec : vrai problème technique côté base/fonction
                $message = "L'enregistrement de la demande a échoué. Veuillez réessayer dans un instant.";
                $message_type = "danger";
            }
        }
    }
}

// Vérifier l'état de la base de données
$db_status = checkDatabaseStatus();
$db_error_rdv = !empty($db_status['error']);
$db_error_message = $db_status['error'] ?? '';
$services = [];
$medecins = [];
$service_to_first_med = [];

// Si la base de données est OK et les tables existent, récupérer les services et les médecins
if ($db_status['connected'] && $db_status['tables_exist']) {
    try {
        $services = getAllServices();
        // Si aucun service n'est trouvé, c'est normal, pas une erreur
        // $services sera un tableau vide
    } catch (PDOException $e) {
        // En cas d'erreur inattendue lors de la récupération
        $db_error_rdv = true;
        $db_error_message = "Erreur lors de la récupération des services : " . $e->getMessage();
    }
    
    // Récupérer tous les médecins approuvés
    try {
        $pdo = bdd();
        $sql = "SELECT * FROM MEDECINS WHERE statut = 'approuvé' ORDER BY Nom_med, Prénom_med";
        $stmt = $pdo->query($sql);
        $medecins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$medecins) {
            $medecins = [];
        }
    } catch (PDOException $e) {
        error_log("Erreur récupération médecins: " . $e->getMessage());
        $medecins = [];
    }
    
    // Pour la création de RDV : map service_id => id_med du premier médecin du service (remplit id_med avant envoi)
    $service_to_first_med = [];
    if (!empty($services)) {
        foreach ($services as $svc) {
            $sid = (int)($svc['id_service'] ?? 0);
            if ($sid) {
                $meds = getMedecinsByService($sid);
                if (!empty($meds) && !empty($meds[0]['id_med'])) {
                    $service_to_first_med[$sid] = (int)$meds[0]['id_med'];
                }
            }
        }
        if (empty($service_to_first_med) && function_exists('getPremierMedecinApprouve')) {
            $fb = getPremierMedecinApprouve();
            if ($fb && !empty($fb['id_med'])) {
                foreach (array_keys($services) as $k) {
                    $sid = (int)($services[$k]['id_service'] ?? 0);
                    if ($sid) {
                        $service_to_first_med[$sid] = (int)$fb['id_med'];
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="keywords" content="rendez-vous médical, prise de rendez-vous, consultation, MediCo">
	<meta name="author" content="MediCo.">
	<meta name="robots" content="index, follow">
	<meta name="description" content="Prenez rendez-vous en ligne avec nos médecins spécialistes chez MediCo. - Simple, rapide et efficace.">
	<meta property="og:title" content="MediCo. - Prendre un Rendez-vous">
	<meta property="og:description" content="Réservez votre consultation médicale en ligne en quelques clics.">
	<meta property="og:image" content="image/1.jpeg">
	<meta name="format-detection" content="telephone=no">
	
	<!-- FAVICONS ICON - utiliser le logo du site -->
	<link rel="icon" href="image/1.jpeg" type="image/jpeg">
	<link rel="shortcut icon" href="image/1.jpeg" type="image/jpeg">
	
	<!-- PAGE TITLE HERE -->
	<title>MediCo. - Réserver un Rendez-vous</title>
	
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
		.rdv-header {
			background: #4A90E2;
			padding: 60px 0;
			text-align: center;
		}
		.rdv-header h1 {
			color: #fff;
			font-size: 42px;
			font-weight: 700;
			margin-bottom: 15px;
		}
		.rdv-header p {
			color: #fff;
			font-size: 18px;
			margin: 0;
		}
		.rdv-form-container {
			background: #fff;
			border-radius: 12px;
			box-shadow: 0 4px 20px rgba(0,0,0,0.1);
			padding: 40px;
			margin: -50px auto 60px;
			max-width: 900px;
			position: relative;
			z-index: 1;
		}
		.rdv-form-title {
			color: #333;
			font-size: 24px;
			font-weight: 700;
			margin-bottom: 30px;
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
		.form-group label {
			color: #333;
			font-weight: 600;
			margin-bottom: 8px;
			display: block;
		}
		.form-control {
			border: 1px solid #ddd;
			border-radius: 6px;
			padding: 12px 15px;
			font-size: 15px;
		}
		.form-control:focus {
			border-color: #4A90E2;
			box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
		}
		.search-patient-section {
			background: #f8f9fa;
			padding: 20px;
			border-radius: 8px;
			margin: 20px 0;
		}
		.btn-search {
			background: #fff;
			border: 1px solid #ddd;
			color: #333;
			padding: 12px 30px;
			border-radius: 6px;
			width: 100%;
			font-weight: 600;
			transition: all 0.3s;
		}
		.btn-search:hover {
			background: #f8f9fa;
			border-color: #4A90E2;
			color: #4A90E2;
		}
		.btn-submit {
			background: #4A90E2;
			color: #fff;
			border: none;
			padding: 15px 40px;
			border-radius: 8px;
			font-size: 18px;
			font-weight: 600;
			width: 100%;
			transition: background 0.3s;
		}
		.btn-submit:hover {
			background: #357ABD;
			color: #fff;
		}
		.patient-found {
			background: #d4edda;
			border: 1px solid #c3e6cb;
			color: #155724;
			padding: 15px;
			border-radius: 8px;
			margin: 15px 0;
		}
		.datetime-input-wrapper {
			position: relative;
		}
		.datetime-input-wrapper .fa-calendar {
			position: absolute;
			right: 15px;
			top: 50%;
			transform: translateY(-50%);
			color: #666;
			pointer-events: none;
		}
		.datetime-input-wrapper input {
			padding-right: 45px;
		}
		.alert-message {
			padding: 15px;
			border-radius: 8px;
			margin-bottom: 20px;
		}
		.alert-success {
			background: #d4edda;
			border: 1px solid #c3e6cb;
			color: #155724;
		}
		.alert-danger {
			background: #f8d7da;
			border: 1px solid #f5c6cb;
			color: #721c24;
		}
		.form-control.is-invalid {
			border-color: #dc3545;
			padding-right: calc(1.5em + 0.75rem);
			background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 3.6 .4.4.4-.4m0 4.8-.4-.4-.4.4'/%3e%3c/svg%3e");
			background-repeat: no-repeat;
			background-position: right calc(0.375em + 0.1875rem) center;
			background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
		}
		.invalid-feedback {
			display: block;
			width: 100%;
			margin-top: 0.25rem;
			font-size: 0.875em;
			color: #dc3545;
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
        <!-- Header Bleu -->
        <div class="rdv-header">
            <div class="container">
                <h1>Réserver un Rendez-vous</h1>
                <p>Prenez rendez-vous avec nos spécialistes</p>
            </div>
        </div>
        <!-- Header END -->
        
        <!-- Formulaire -->
        <div class="container">
            <a href="index.php" class="btn-retour">
                <i class="fa fa-arrow-left"></i> Retour à l'accueil
            </a>
            <div class="rdv-form-container">
                <h2 class="rdv-form-title">Informations de réservation</h2>
                
                <?php if ($message): ?>
                    <div class="alert-message alert-<?php echo $message_type; ?>" style="padding: 15px; margin-bottom: 20px; border-radius: 8px; <?php if ($message_type == 'danger'): ?>background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;<?php else: ?>background: #d4edda; border: 1px solid #c3e6cb; color: #155724;<?php endif; ?>">
                        <?php if ($message_type == 'danger'): ?>
                            <strong><i class="fa fa-exclamation-triangle"></i> Erreur</strong><br>
                        <?php else: ?>
                            <strong><i class="fa fa-check-circle"></i> Succès</strong><br>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Guide rapide : prendre un rendez-vous -->
                <div class="alert" style="padding: 18px 20px; margin-bottom: 24px; border-radius: 10px; background: #e8f4fc; border-left: 4px solid #4A90E2; color: #0c5460;">
                    <strong><i class="fa fa-calendar-check-o"></i> Comment prendre un rendez-vous</strong>
                    <ul style="margin: 10px 0 0 18px; padding-left: 8px; line-height: 1.7;">
                        <li>Renseignez votre <strong>nom</strong> (et facultativement matricule, email).</li>
                        <li>Choisissez le <strong>service</strong> souhaité.</li>
                        <li>Indiquez la <strong>date et l’heure</strong> (ex. 28/01/2026 14:30).</li>
                        <li>Ajoutez éventuellement un <strong>motif</strong> de consultation.</li>
                        <li>Votre demande est enregistrée sans condition ; l'accueil la transmettra au service, puis un médecin la confirmera.</li>
                    </ul>
                    <?php if (!$is_patient_connected): ?>
                    <p style="margin: 12px 0 0 0; font-size: 14px;">
                        <i class="fa fa-info-circle"></i> Vous avez déjà un compte ? 
                        <a href="login.php?redirect=<?php echo urlencode('rendez-vous.php'); ?>" style="color: #004085; font-weight: 600; text-decoration: underline;">Connectez-vous</a> 
                        pour pré-remplir vos informations.
                    </p>
                    <?php endif; ?>
                </div>

                <form method="post" action="" id="form-rdv">
                    <?php if ($is_patient_connected): ?>
                        <!-- Affichage pour patient connecté -->
                        <?php if ($patient_trouve && is_array($patient_trouve)): ?>
                            <div class="alert alert-info" style="padding: 15px; margin-bottom: 20px; border-radius: 8px; background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460;">
                                <strong><i class="fa fa-user"></i> Patient connecté :</strong> 
                                <?php 
                                $nom_complet = trim(($patient_trouve['Prénom_patient'] ?? '') . ' ' . ($patient_trouve['Nom_patient'] ?? ''));
                                if (empty($nom_complet) && isset($user_info['nom'])) {
                                    $nom_complet = $user_info['nom'];
                                }
                                echo htmlspecialchars($nom_complet); 
                                ?>
                                <?php if (!empty($patient_trouve['Matricule_patient'])): ?>
                                    <br><small><strong>Matricule :</strong> <?php echo htmlspecialchars($patient_trouve['Matricule_patient']); ?></small>
                                <?php elseif (!empty($user_info['matricule_patient'])): ?>
                                    <br><small><strong>Matricule :</strong> <?php echo htmlspecialchars($user_info['matricule_patient']); ?></small>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info" style="padding: 15px; margin-bottom: 20px; border-radius: 8px; background: #e7f3ff; border: 1px solid #4A90E2; color: #004085;">
                                <strong><i class="fa fa-info-circle"></i> Vous pouvez faire votre demande :</strong> 
                                Renseignez le service, la date et l'heure. Votre demande sera enregistrée sans condition puis transmise au service.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($is_patient_connected): ?>
                    <div class="alert alert-info" style="padding: 12px 15px; margin-bottom: 18px; border-radius: 8px; background: #e7f3ff; border-left: 4px solid #4A90E2; color: #004085;">
                        <i class="fa fa-info-circle"></i> Vos nom et matricule sont pré-remplis. Choisissez le service, la date et l’heure, puis validez. Un médecin du service sera assigné automatiquement.
                    </div>
                    <?php endif; ?>
                    
                    <!-- Champs Patient : Nom et Matricule -->
                    <div class="form-group">
                        <label><i class="fa fa-user"></i> Nom du Patient <span class="text-danger">*</span></label>
                        <?php 
                        $nom_patient_value = '';
                        if ($patient_trouve && isset($patient_trouve['Nom_patient']) && isset($patient_trouve['Prénom_patient'])) {
                            $nom_patient_value = trim(($patient_trouve['Prénom_patient'] ?? '') . ' ' . ($patient_trouve['Nom_patient'] ?? ''));
                        } elseif (!empty($user_info['nom'])) {
                            $nom_patient_value = $user_info['nom'];
                        }
                        ?>
                        <input type="text" 
                               name="nom_patient" 
                               id="nom_patient" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($nom_patient_value); ?>"
                               placeholder="Prénom et Nom du patient"
                               required
                               style="font-weight: 600; color: #002939; font-size: 16px; padding: 12px;">
                        <small class="text-muted">
                            <i class="fa fa-info-circle"></i> Entrez le prénom et le nom du patient.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fa fa-id-card"></i> Matricule du Patient</label>
                        <?php 
                        $matricule_value = '';
                        if ($patient_trouve && isset($patient_trouve['Matricule_patient'])) {
                            $matricule_value = $patient_trouve['Matricule_patient'];
                        } elseif (!empty($user_info['matricule_patient'])) {
                            $matricule_value = $user_info['matricule_patient'];
                        }
                        ?>
                        <input type="text" 
                               name="matricule_patient" 
                               id="matricule_patient" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($matricule_value); ?>"
                               placeholder="Si vous en avez un"
                               style="font-weight: 600; color: #002939; font-size: 16px; padding: 12px;">
                        <small class="text-muted">
                            <i class="fa fa-info-circle"></i> Optionnel. Sans matricule, votre demande sera quand même enregistrée et traitée.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fa fa-envelope"></i> Email</label>
                        <?php 
                        $email_value = $is_patient_connected && !empty($user_info['email']) ? $user_info['email'] : htmlspecialchars($_POST['email'] ?? '');
                        ?>
                        <input type="email" 
                               name="email" 
                               id="email_demandeur" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($email_value); ?>"
                               placeholder="votre@email.fr"
                               style="font-size: 16px; padding: 12px;">
                        <small class="text-muted">
                            <i class="fa fa-info-circle"></i> Utile pour vous contacter ; optionnel pour enregistrer la demande.
                        </small>
                    </div>
                    
                    <?php if ($patient_trouve && isset($patient_trouve['id_patient']) && !empty($patient_trouve['id_patient'])): ?>
                        <!-- Patient trouvé - garder l'ID en cache -->
                        <input type="hidden" name="id_patient" value="<?php echo $patient_trouve['id_patient']; ?>">
                    <?php endif; ?>
                    
                    <!-- Service -->
                    <div class="form-group">
                        <label><i class="fa fa-stethoscope"></i> Service <span class="text-danger">*</span></label>
                        <?php if ($db_error_rdv): ?>
                            <!-- Message d'erreur de base de données (rouge) - seulement si vraie erreur -->
                            <div class="alert alert-danger" style="padding: 20px; margin-bottom: 15px; border-radius: 10px; background: #f8d7da; border: 2px solid #f5c6cb; color: #721c24; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                <div style="display: flex; align-items: start;">
                                    <span style="font-size: 28px; margin-right: 12px;">⚠️</span>
                                    <div style="flex: 1;">
                                        <strong style="font-size: 16px;"><i class="fa fa-exclamation-triangle"></i> Erreur de Base de Données</strong>
                                        <p style="margin: 10px 0 15px 0; font-size: 15px; line-height: 1.6;"><?php echo htmlspecialchars($db_error_message); ?></p>
                                        <?php if ($db_status['error_type'] == 'not_initialized'): ?>
                                            <p style="margin: 10px 0 0 0; font-size: 14px; font-weight: 600;">Pour résoudre ce problème :</p>
                                            <ul style="margin: 8px 0 15px 20px; padding-left: 20px; line-height: 1.8;">
                                                <li>Accédez à <a href="install.php" style="color: #002939; font-weight: bold; text-decoration: underline;">install.php</a> pour créer automatiquement toutes les tables</li>
                                                <li>Ou importez manuellement le fichier <code style="background: #fff; padding: 2px 6px; border-radius: 3px;">config/sante1_database.sql</code> via phpMyAdmin</li>
                                            </ul>
                                            <a href="install.php" style="display: inline-block; padding: 10px 25px; background: #002939; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; margin-top: 5px;">
                                                <i class="fa fa-database"></i> Installer la Base de Données
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php elseif (empty($services)): ?>
                            <!-- Message informatif (bleu) - pas d'erreur, juste aucun service enregistré -->
                            <div class="alert alert-info" style="padding: 15px; margin-bottom: 15px; border-radius: 8px; background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460;">
                                <i class="fa fa-info-circle"></i> <strong>Aucun service enregistré</strong>
                                <p style="margin: 10px 0 0 0; font-size: 14px;">
                                    Il n'y a actuellement aucun service enregistré dans la base de données.
                                </p>
                                <p style="margin: 10px 0 0 0; font-size: 14px; color: #666;">
                                    Dès qu'un service sera ajouté à la base de données, il apparaîtra automatiquement ici.
                                </p>
                            </div>
                        <?php else: ?>
                            <select name="id_service" id="id_service" required class="form-control">
                                <option value="">Sélectionner un service</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?php echo $service['id_service']; ?>"
                                            <?php echo (isset($_POST['id_service']) && $_POST['id_service'] == $service['id_service']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($service['Nom_service']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Information sur l'assignation automatique du médecin -->
                    <div class="alert alert-info" style="padding: 15px; margin-bottom: 20px; border-radius: 8px; background: #e7f3ff; border-left: 4px solid #4A90E2; color: #004085;">
                        <strong><i class="fa fa-user-md"></i> Assignation du médecin :</strong>
                        <p style="margin: 10px 0 0 0;">
                            Un médecin du service sélectionné sera automatiquement assigné à votre rendez-vous lors de la soumission.
                        </p>
                    </div>
                    
                    <!-- Champ caché pour le médecin (sera assigné automatiquement) -->
                    <input type="hidden" name="id_med" id="id_med" value="">
                    
                    
                    <!-- Date et Heure -->
                    <div class="form-group">
                        <label><i class="fa fa-calendar"></i> Date et heure du rendez-vous <span class="text-danger">*</span></label>
                        <div class="datetime-input-wrapper">
                            <input type="text" name="date_heure_rdv" required class="form-control" 
                                   placeholder="jj/mm/aaaa hh:mm" 
                                   value="<?php echo htmlspecialchars($_POST['date_heure_rdv'] ?? ''); ?>"
                                   autocomplete="off">
                            <i class="fa fa-calendar"></i>
                        </div>
                        <small class="text-muted">Format : jj/mm/aaaa hh:mm (ex: 25/01/2026 14:30)</small>
                    </div>
                    
                    <!-- Motif -->
                    <div class="form-group">
                        <label><i class="fa fa-file-text"></i> Motif de consultation (optionnel)</label>
                        <textarea name="motif" rows="4" class="form-control" 
                                  placeholder="Décrivez brièvement le motif de votre consultation..."><?php echo htmlspecialchars($_POST['motif'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Bouton Submit -->
                    <div class="form-group" style="margin-top: 30px;">
                        <button type="submit" name="submit_rdv" value="1" class="btn-submit">
                            Réserver le rendez-vous
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <!-- Formulaire END -->
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
<script>
// Map service_id => id_med du premier médecin (remplit id_med avant envoi pour que la création de RDV reçoive un médecin)
var serviceToFirstMed = <?php echo json_encode(isset($service_to_first_med) ? $service_to_first_med : []); ?>;

// Format automatique pour la date/heure
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.querySelector('input[name="date_heure_rdv"]');
    if (!dateInput) return;
    
    // Désactiver la validation HTML5 en temps réel
    dateInput.addEventListener('invalid', function(e) {
        e.preventDefault();
    });
    
    // Format automatique pendant la saisie
    dateInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        let formatted = '';
        
        // Format progressif : jj/mm/aaaa hh:mm
        if (value.length > 0) {
            formatted = value.substring(0, 2);
        }
        if (value.length > 2) {
            formatted += '/' + value.substring(2, 4);
        }
        if (value.length > 4) {
            formatted += '/' + value.substring(4, 8);
        }
        if (value.length > 8) {
            formatted += ' ' + value.substring(8, 10);
        }
        if (value.length > 10) {
            formatted += ':' + value.substring(10, 12);
        }
        
        e.target.value = formatted;
        
        // Retirer les classes d'erreur pendant la saisie
        e.target.classList.remove('is-invalid');
        const errorMsg = e.target.parentElement.querySelector('.invalid-feedback');
        if (errorMsg) {
            errorMsg.remove();
        }
    });
    
    // À la soumission : remplir id_med si besoin, laisser le serveur valider la date (formats multiples acceptés)
    const form = document.getElementById('form-rdv');
    if (form) {
        form.addEventListener('submit', function() {
            dateInput.classList.remove('is-invalid');
            var idMedEl = document.getElementById('id_med');
            var idServiceEl = document.getElementById('id_service');
            if (idMedEl && idServiceEl && typeof serviceToFirstMed === 'object') {
                var sid = idServiceEl.value ? parseInt(idServiceEl.value, 10) : 0;
                if (sid && (!idMedEl.value || idMedEl.value === '')) {
                    if (serviceToFirstMed[sid]) {
                        idMedEl.value = String(serviceToFirstMed[sid]);
                    }
                }
            }
        });
    }
});

// Variable globale pour stocker tous les médecins
let allMedecinsData = [];

// Fonction pour filtrer les médecins selon le service sélectionné
function filterMedecinsByService(id_service) {
    const medecinSelect = document.getElementById('id_med');
    if (!medecinSelect) return;
    
    // Si aucun service sélectionné, afficher tous les médecins
    if (!id_service || id_service === '') {
        showAllMedecins();
        return;
    }
    
    // Récupérer le nom du service sélectionné
    const serviceSelect = document.getElementById('id_service');
    if (!serviceSelect || !serviceSelect.selectedIndex || serviceSelect.selectedIndex === 0) {
        showAllMedecins();
        return;
    }
    
    const serviceName = serviceSelect.options[serviceSelect.selectedIndex].text.trim();
    const selectedMedecin = medecinSelect.value;
    
    // Vider la liste
    medecinSelect.innerHTML = '<option value="">Sélectionner un médecin</option>';
    
    // Filtrer les médecins par spécialisation (nom du service)
    let found = false;
    allMedecinsData.forEach(function(medecin) {
        if (medecin.specialisation === serviceName) {
            const option = document.createElement('option');
            option.value = medecin.value;
            option.textContent = medecin.text;
            option.setAttribute('data-specialisation', medecin.specialisation);
            medecinSelect.appendChild(option);
            found = true;
        }
    });
    
    if (!found) {
        medecinSelect.innerHTML = '<option value="">Un médecin sera attribué automatiquement pour ce service</option>';
    }
    
    // Restaurer la sélection si elle existe toujours
    if (selectedMedecin) {
        const optionToSelect = medecinSelect.querySelector('option[value="' + selectedMedecin + '"]');
        if (optionToSelect) {
            medecinSelect.value = selectedMedecin;
        }
    }
}

// Fonction pour afficher tous les médecins
function showAllMedecins() {
    const medecinSelect = document.getElementById('id_med');
    if (!medecinSelect) return;
    
    const selectedMedecin = medecinSelect.value;
    
    // Vider et reconstruire avec tous les médecins
    medecinSelect.innerHTML = '<option value="">Sélectionner un médecin</option>';
    
    allMedecinsData.forEach(function(medecin) {
        const option = document.createElement('option');
        option.value = medecin.value;
        option.textContent = medecin.text;
        option.setAttribute('data-specialisation', medecin.specialisation);
        medecinSelect.appendChild(option);
    });
    
    // Restaurer la sélection si elle existe
    if (selectedMedecin) {
        const optionToSelect = medecinSelect.querySelector('option[value="' + selectedMedecin + '"]');
        if (optionToSelect) {
            medecinSelect.value = selectedMedecin;
        }
    }
}

// Initialiser les données des médecins au chargement
document.addEventListener('DOMContentLoaded', function() {
    const medecinSelect = document.getElementById('id_med');
    if (medecinSelect) {
        // Stocker toutes les options originales
        const allOptions = medecinSelect.querySelectorAll('option');
        allOptions.forEach(function(option) {
            if (option.value !== '') {
                allMedecinsData.push({
                    value: option.value,
                    text: option.textContent,
                    specialisation: option.getAttribute('data-specialisation') || ''
                });
            }
        });
    }
    
    // Écouter les changements de service
    const serviceSelect = document.getElementById('id_service');
    if (serviceSelect) {
        serviceSelect.addEventListener('change', function() {
            filterMedecinsByService(this.value);
        });
        
        // Filtrer au chargement si un service est déjà sélectionné
        <?php if (isset($_POST['id_service']) && !empty($_POST['id_service'])): ?>
            filterMedecinsByService(<?php echo intval($_POST['id_service']); ?>);
            // Sélectionner le médecin si déjà choisi
            <?php if (isset($_POST['id_med']) && !empty($_POST['id_med'])): ?>
                setTimeout(function() {
                    const medecinSelect = document.getElementById('id_med');
                    if (medecinSelect) {
                        medecinSelect.value = <?php echo intval($_POST['id_med']); ?>;
                    }
                }, 100);
            <?php endif; ?>
        <?php endif; ?>
    }
});
</script>
</body>
</html>
