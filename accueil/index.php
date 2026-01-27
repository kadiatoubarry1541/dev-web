<?php
/**
 * Interface Accueil - Permet d'inscrire un patient (nouveau ou existant) pour un service donné
 */
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/traitement.php';
require_once '../config/database_functions.php';

// Vérifier que l'utilisateur est connecté et est accueil
requireLogin('../login.php');
requireAccueil('../index.php');

$user_info = getUserInfo();

$message = '';
$message_type = '';
$success = false;
$patient_trouve = null;
$mode = $_GET['mode'] ?? 'search'; // 'search' ou 'new'

// Récupérer les services
$services = [];
try {
    $services = getAllServices();
} catch (Exception $e) {
    error_log("Erreur récupération services: " . $e->getMessage());
}

// Traiter la recherche d'un patient existant par matricule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rechercher_patient'])) {
    $matricule = trim($_POST['matricule'] ?? '');
    
    if (empty($matricule)) {
        $message = "Veuillez entrer un matricule.";
        $message_type = "danger";
    } else {
        try {
            $patient_trouve = getPatientByMatricule($matricule);
            if ($patient_trouve) {
                // Vérifier si le patient a un compte utilisateur
                $pdo = bdd();
                $check_user = $pdo->prepare("SELECT * FROM users WHERE id_patient = ?");
                $check_user->execute([$patient_trouve['id_patient']]);
                $user_account = $check_user->fetch();
                $patient_trouve['has_account'] = ($user_account !== false);
                $patient_trouve['user_info'] = $user_account;
                
                $message = "Patient trouvé ! Vous pouvez maintenant l'inscrire à un service.";
                $message_type = "success";
                $mode = 'inscrire_existant';
            } else {
                $message = "Aucun patient trouvé avec ce matricule. Vous pouvez créer un nouveau compte.";
                $message_type = "warning";
                $mode = 'new';
            }
        } catch (Exception $e) {
            $message = "Erreur lors de la recherche : " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Traiter l'inscription d'un patient existant à un service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inscrire_existant'])) {
    $id_patient = !empty($_POST['id_patient']) ? intval($_POST['id_patient']) : null;
    $id_service = !empty($_POST['id_service']) ? intval($_POST['id_service']) : null;
    
    if (empty($id_patient) || empty($id_service)) {
        $message = "Erreur : informations manquantes.";
        $message_type = "danger";
    } else {
        try {
            $pdo = bdd();
            $pdo->beginTransaction();
            
            // Vérifier si le patient n'est pas déjà inscrit à ce service
            $check_existing = $pdo->prepare("SELECT id FROM PATIENT_SERVICES WHERE id_patient = ? AND id_service = ?");
            $check_existing->execute([$id_patient, $id_service]);
            
            if ($check_existing->rowCount() > 0) {
                throw new Exception("Ce patient est déjà inscrit à ce service.");
            }
            
            // Inscrire le patient au service
            $sql = "INSERT INTO PATIENT_SERVICES (id_patient, id_service, Statut) VALUES (?, ?, 'inscrit')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_patient, $id_service]);
            
            $pdo->commit();
            
            // Récupérer les informations pour le message
            $patient = getPatientById($id_patient);
            $service = getServiceById($id_service);
            
            $message = "Le patient <strong>" . htmlspecialchars($patient['Nom_patient'] . ' ' . $patient['Prénom_patient']) . "</strong> (Matricule: " . htmlspecialchars($patient['Matricule_patient']) . ") a été inscrit avec succès au service : <strong>" . htmlspecialchars($service['Nom_service']) . "</strong> !";
            $message_type = "success";
            $success = true;
            $patient_trouve = null;
            $mode = 'search';
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $message = "Ce patient est déjà inscrit à ce service.";
            } else {
                $message = "Erreur lors de l'inscription : " . $e->getMessage();
            }
            $message_type = "danger";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Erreur : " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Traiter l'inscription d'un nouveau patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_new'])) {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $date_naissance = $_POST['date_naissance'] ?? null;
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $sexe = $_POST['sexe'] ?? '';
    $id_service = !empty($_POST['id_service']) ? intval($_POST['id_service']) : null;
    
    // Validation
    if (empty($nom) || empty($prenom) || empty($telephone)) {
        $message = "Les champs obligatoires (Nom, Prénom, Téléphone) doivent être remplis.";
        $message_type = "danger";
    } elseif (!empty($email) && EmailExist($email, 'patient')) {
        $message = "Cet email est déjà utilisé par un compte existant. Veuillez utiliser un autre email ou laisser vide.";
        $message_type = "danger";
    } elseif (empty($id_service)) {
        $message = "Veuillez sélectionner un service.";
        $message_type = "danger";
    } else {
        try {
            $pdo = bdd();
            $pdo->beginTransaction();
            
            // Convertir la date de naissance si nécessaire
            if ($date_naissance && strpos($date_naissance, '/') !== false) {
                $date_parts = explode('/', $date_naissance);
                if (count($date_parts) == 3) {
                    $date_naissance = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
                }
            }
            
            // Si pas de date de naissance, mettre une date par défaut
            if (empty($date_naissance)) {
                $date_naissance = '1900-01-01';
            }
            
            // Vérifier que l'email n'existe pas dans PATIENTS
            if (!empty($email)) {
                $check_patient_email = $pdo->prepare("SELECT id_patient FROM PATIENTS WHERE Email_patient = ?");
                $check_patient_email->execute([$email]);
                if ($check_patient_email->rowCount() > 0) {
                    throw new Exception("Cet email est déjà utilisé par un patient.");
                }
            }
            
            // Créer le patient AVEC matricule automatique
            require_once __DIR__ . '/../config/traitement.php';
            $matricule = genererMatriculePatient();
            
            $sql_patient = "INSERT INTO PATIENTS (Matricule_patient, Nom_patient, Prénom_patient, Tel_patient, Email_patient, Date_naissance_patient, Adresse_patient) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_patient = $pdo->prepare($sql_patient);
            $stmt_patient->execute([$matricule, $nom, $prenom, $telephone, $email ?: null, $date_naissance, $adresse ?: null]);
            $id_patient = $pdo->lastInsertId();
            
            // Si un email est fourni, créer aussi un compte utilisateur
            if (!empty($email)) {
                // Vérifier que l'email n'existe pas dans users
                $check_user_email = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $check_user_email->execute([$email]);
                if ($check_user_email->rowCount() == 0) {
                    // Générer un mot de passe temporaire
                    $password_temp = bin2hex(random_bytes(4)); // Mot de passe temporaire
                    $password_hash = password_hash($password_temp, PASSWORD_DEFAULT);
                    
                    $nom_complet = trim($nom . ' ' . $prenom);
                    $sql_user = "INSERT INTO users (nom, email, telephone, password, role, id_patient) 
                                 VALUES (?, ?, ?, ?, 'patient', ?)";
                    $stmt_user = $pdo->prepare($sql_user);
                    $stmt_user->execute([$nom_complet, $email, $telephone, $password_hash, $id_patient]);
                }
            }
            
            // Inscrire le patient au service
            $sql_service = "INSERT INTO PATIENT_SERVICES (id_patient, id_service, Statut) VALUES (?, ?, 'inscrit')";
            $stmt_service = $pdo->prepare($sql_service);
            $stmt_service->execute([$id_patient, $id_service]);
            
            $pdo->commit();
            
            // Récupérer le nom du service
            $service = getServiceById($id_service);
            $service_name = $service ? $service['Nom_service'] : '';
            
            $message = "Le patient <strong>" . htmlspecialchars($nom . ' ' . $prenom) . "</strong> a été créé avec succès (Matricule: <strong>" . htmlspecialchars($matricule) . "</strong>) et inscrit au service : <strong>" . htmlspecialchars($service_name) . "</strong> !";
            if (!empty($email)) {
                $message .= " Un compte utilisateur a été créé avec l'email fourni.";
            }
            $message_type = "success";
            $success = true;
            $mode = 'search';
            
            // Réinitialiser les champs
            $_POST = [];
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $message = "Cet email est déjà utilisé dans le système.";
            } else {
                $message = "Erreur lors de l'inscription du patient : " . $e->getMessage();
            }
            $message_type = "danger";
            error_log("Erreur inscription patient accueil: " . $e->getMessage());
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Erreur : " . $e->getMessage();
            $message_type = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>Inscription Patient - MediCo.</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" type="text/css" href="../assets/css/plugins.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
	<link class="skin" rel="stylesheet" type="text/css" href="../assets/css/skin/skin-1.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/templete.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<style>
		.accueil-container {
			padding: 40px 0;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			min-height: 100vh;
		}
		.form-card {
			background: #fff;
			border-radius: 12px;
			padding: 40px;
			margin: 0 auto;
			box-shadow: 0 10px 40px rgba(0,0,0,0.2);
			max-width: 900px;
		}
		.page-header {
			text-align: center;
			margin-bottom: 40px;
			padding-bottom: 20px;
			border-bottom: 2px solid #e2e8f0;
		}
		.page-header h1 {
			color: #002939;
			font-size: 32px;
			font-weight: 700;
			margin-bottom: 10px;
		}
		.page-header p {
			color: #666;
			font-size: 16px;
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
		.tabs {
			display: flex;
			gap: 10px;
			margin-bottom: 30px;
			border-bottom: 2px solid #e2e8f0;
		}
		.tab {
			padding: 12px 24px;
			background: transparent;
			border: none;
			border-bottom: 3px solid transparent;
			cursor: pointer;
			font-size: 16px;
			font-weight: 600;
			color: #666;
			transition: all 0.3s;
		}
		.tab:hover {
			color: #667eea;
		}
		.tab.active {
			color: #667eea;
			border-bottom-color: #667eea;
		}
		.tab-content {
			display: none;
		}
		.tab-content.active {
			display: block;
		}
		.form-group {
			margin-bottom: 20px;
		}
		.form-group label {
			display: block;
			margin-bottom: 8px;
			font-weight: 600;
			color: #002939;
			font-size: 14px;
		}
		.form-group input,
		.form-group select,
		.form-group textarea {
			width: 100%;
			padding: 12px 15px;
			border: 1px solid #ddd;
			border-radius: 8px;
			font-size: 15px;
			transition: all 0.3s;
		}
		.form-group input:focus,
		.form-group select:focus,
		.form-group textarea:focus {
			outline: none;
			border-color: #667eea;
			box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
		}
		.form-row {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 20px;
		}
		.alert {
			padding: 15px 20px;
			border-radius: 8px;
			margin-bottom: 25px;
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
		.alert-warning {
			background: #fff3cd;
			border: 1px solid #ffeaa7;
			color: #856404;
		}
		.btn-submit {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 14px 40px;
			border: none;
			border-radius: 8px;
			font-size: 16px;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.3s;
			box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
		}
		.btn-submit:hover {
			transform: translateY(-2px);
			box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
		}
		.btn-search {
			background: #28a745;
			color: white;
			padding: 12px 30px;
			border: none;
			border-radius: 8px;
			font-size: 15px;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.3s;
		}
		.btn-search:hover {
			background: #218838;
		}
		.required {
			color: #e53e3e;
		}
		.user-info {
			background: #f8f9fa;
			padding: 15px 20px;
			border-radius: 8px;
			margin-bottom: 30px;
			text-align: center;
		}
		.user-info strong {
			color: #667eea;
		}
		.patient-found-card {
			background: #e8f5e9;
			border: 2px solid #4caf50;
			border-radius: 8px;
			padding: 20px;
			margin-bottom: 25px;
		}
		.patient-found-card h3 {
			color: #2e7d32;
			margin-bottom: 15px;
		}
		.patient-info {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 15px;
			margin-bottom: 15px;
		}
		.patient-info-item {
			display: flex;
			flex-direction: column;
		}
		.patient-info-item label {
			font-weight: 600;
			color: #555;
			font-size: 13px;
			margin-bottom: 5px;
		}
		.patient-info-item span {
			color: #333;
			font-size: 15px;
		}
		.account-badge {
			display: inline-block;
			padding: 5px 12px;
			border-radius: 20px;
			font-size: 13px;
			font-weight: 600;
			margin-top: 10px;
		}
		.account-badge.has-account {
			background: #4caf50;
			color: white;
		}
		.account-badge.no-account {
			background: #ff9800;
			color: white;
		}
		.success-message {
			text-align: center;
			padding: 40px 0;
		}
		.success-message i {
			font-size: 64px;
			color: #28a745;
			margin-bottom: 20px;
		}
		.search-box {
			display: flex;
			gap: 10px;
			margin-bottom: 20px;
		}
		.search-box input {
			flex: 1;
		}
	</style>
</head>
<body>
<div class="page-wraper">
	<?php require_once '../partials/entete.php'; ?>
	
	<div class="accueil-container">
		<div class="container">
			<a href="../index.php" class="btn-retour">
				<i class="fa fa-arrow-left"></i> Retour à l'accueil
			</a>
			<div class="form-card">
				<div class="page-header">
					<h1><i class="fa fa-user-plus"></i> Inscription Patient</h1>
					<p>Recherchez un patient existant ou créez un nouveau compte</p>
				</div>
				
				<div class="user-info">
					Connecté en tant que : <strong><?php echo htmlspecialchars($user_info['nom']); ?></strong> (Accueil)
				</div>
				
				<?php if ($message): ?>
					<div class="alert alert-<?php echo $message_type; ?>">
						<?php echo $message; ?>
					</div>
				<?php endif; ?>
				
				<?php if (!$success): ?>
					<!-- Onglets -->
					<div class="tabs">
						<button type="button" class="tab <?php echo ($mode == 'search' || $mode == 'inscrire_existant') ? 'active' : ''; ?>" onclick="showTab('search')">
							<i class="fa fa-search"></i> Rechercher un patient
						</button>
						<button type="button" class="tab <?php echo $mode == 'new' ? 'active' : ''; ?>" onclick="showTab('new')">
							<i class="fa fa-user-plus"></i> Nouveau patient
						</button>
					</div>
					
					<!-- Onglet Recherche -->
					<div id="tab-search" class="tab-content <?php echo ($mode == 'search' || $mode == 'inscrire_existant') ? 'active' : ''; ?>">
						<form method="post" action="">
							<div class="form-group">
								<label>Rechercher un patient par matricule <span class="required">*</span></label>
								<div class="search-box">
									<input type="text" name="matricule" required 
										   placeholder="Entrez le matricule du patient (ex: PAT202501231234)"
										   value="<?php echo htmlspecialchars($_POST['matricule'] ?? ''); ?>">
									<button type="submit" name="rechercher_patient" class="btn-search">
										<i class="fa fa-search"></i> Rechercher
									</button>
								</div>
							</div>
						</form>
						
						<?php if ($patient_trouve && $mode == 'inscrire_existant'): ?>
							<div class="patient-found-card">
								<h3><i class="fa fa-check-circle"></i> Patient trouvé</h3>
								<div class="patient-info">
									<div class="patient-info-item">
										<label>Matricule</label>
										<span><strong><?php echo htmlspecialchars($patient_trouve['Matricule_patient']); ?></strong></span>
									</div>
									<div class="patient-info-item">
										<label>Nom complet</label>
										<span><?php echo htmlspecialchars($patient_trouve['Nom_patient'] . ' ' . $patient_trouve['Prénom_patient']); ?></span>
									</div>
									<div class="patient-info-item">
										<label>Téléphone</label>
										<span><?php echo htmlspecialchars($patient_trouve['Tel_patient'] ?? 'N/A'); ?></span>
									</div>
									<div class="patient-info-item">
										<label>Email</label>
										<span><?php echo htmlspecialchars($patient_trouve['Email_patient'] ?? 'N/A'); ?></span>
									</div>
								</div>
								<span class="account-badge <?php echo $patient_trouve['has_account'] ? 'has-account' : 'no-account'; ?>">
									<i class="fa fa-<?php echo $patient_trouve['has_account'] ? 'check' : 'exclamation-triangle'; ?>"></i>
									<?php echo $patient_trouve['has_account'] ? 'Compte utilisateur existant' : 'Pas de compte utilisateur'; ?>
								</span>
								
								<form method="post" action="" style="margin-top: 20px;">
									<input type="hidden" name="id_patient" value="<?php echo $patient_trouve['id_patient']; ?>">
									<div class="form-group">
										<label>Inscrire au service <span class="required">*</span></label>
										<select name="id_service" required style="padding: 12px 15px; font-size: 16px;">
											<option value="">Sélectionner un service</option>
											<?php if (!empty($services)): ?>
												<?php foreach ($services as $service): ?>
													<option value="<?php echo $service['id_service']; ?>">
														<?php echo htmlspecialchars($service['Nom_service']); ?>
													</option>
												<?php endforeach; ?>
											<?php endif; ?>
										</select>
									</div>
									<button type="submit" name="inscrire_existant" class="btn-submit">
										<i class="fa fa-check"></i> Inscrire ce patient au service
									</button>
								</form>
							</div>
						<?php endif; ?>
					</div>
					
					<!-- Onglet Nouveau patient -->
					<div id="tab-new" class="tab-content <?php echo $mode == 'new' ? 'active' : ''; ?>">
						<form method="post" action="">
							<div class="form-row">
								<div class="form-group">
									<label>Nom <span class="required">*</span></label>
									<input type="text" name="nom" required 
										   value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
								</div>
								
								<div class="form-group">
									<label>Prénom <span class="required">*</span></label>
									<input type="text" name="prenom" required 
										   value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>">
								</div>
							</div>
							
							<div class="form-row">
								<div class="form-group">
									<label>Date de naissance</label>
									<input type="date" name="date_naissance" 
										   value="<?php echo htmlspecialchars($_POST['date_naissance'] ?? ''); ?>">
								</div>
								
								<div class="form-group">
									<label>Sexe</label>
									<select name="sexe">
										<option value="">Sélectionner</option>
										<option value="M" <?php echo (isset($_POST['sexe']) && $_POST['sexe'] == 'M') ? 'selected' : ''; ?>>Masculin</option>
										<option value="F" <?php echo (isset($_POST['sexe']) && $_POST['sexe'] == 'F') ? 'selected' : ''; ?>>Féminin</option>
									</select>
								</div>
							</div>
							
							<div class="form-row">
								<div class="form-group">
									<label>Téléphone <span class="required">*</span></label>
									<input type="tel" name="telephone" required 
										   value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
								</div>
								
								<div class="form-group">
									<label>Email (optionnel)</label>
									<input type="email" name="email" 
										   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
									<small style="color: #666; font-size: 13px;">Si fourni, un compte utilisateur sera créé automatiquement</small>
								</div>
							</div>
							
							<div class="form-group">
								<label>Adresse</label>
								<textarea name="adresse" rows="3"><?php echo htmlspecialchars($_POST['adresse'] ?? ''); ?></textarea>
							</div>
							
							<div class="form-group">
								<label>Service <span class="required">*</span></label>
								<select name="id_service" required style="padding: 12px 15px; font-size: 16px;">
									<option value="">Sélectionner un service</option>
									<?php if (!empty($services)): ?>
										<?php foreach ($services as $service): ?>
											<option value="<?php echo $service['id_service']; ?>" 
													<?php echo (isset($_POST['id_service']) && $_POST['id_service'] == $service['id_service']) ? 'selected' : ''; ?>>
												<?php echo htmlspecialchars($service['Nom_service']); ?>
											</option>
										<?php endforeach; ?>
									<?php endif; ?>
								</select>
							</div>
							
							<div style="margin-top: 30px; text-align: center;">
								<button type="submit" name="submit_new" class="btn-submit">
									<i class="fa fa-save"></i> Créer le patient et l'inscrire au service
								</button>
							</div>
						</form>
					</div>
				<?php else: ?>
					<div class="success-message">
						<i class="fa fa-check-circle"></i>
						<h2 style="color: #28a745; margin-bottom: 20px;">Inscription réussie !</h2>
						<a href="index.php" class="btn-submit">
							<i class="fa fa-plus"></i> Inscrire un autre patient
						</a>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	
	<?php require_once '../partials/footer.php'; ?>
</div>

<script>
function showTab(tabName) {
	// Masquer tous les onglets
	document.querySelectorAll('.tab-content').forEach(tab => {
		tab.classList.remove('active');
	});
	document.querySelectorAll('.tab').forEach(tab => {
		tab.classList.remove('active');
	});
	
	// Afficher l'onglet sélectionné
	document.getElementById('tab-' + tabName).classList.add('active');
	event.target.classList.add('active');
}
</script>
</body>
</html>
