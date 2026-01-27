<?php
/**
 * Page pour permettre au médecin d'ajouter un patient
 */
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/traitement.php';
require_once '../config/database_functions.php';

// Vérifier que l'utilisateur est connecté
requireLogin('../login.php');

// Seuls l'accueil et l'admin peuvent ajouter des patients
// Les médecins ne peuvent pas ajouter de patients (c'est le rôle de l'accueil)
if (!hasPermission('manage_patients')) {
    $_SESSION['error_message'] = "Seuls les membres de l'accueil et les administrateurs peuvent ajouter des patients. Veuillez contacter l'accueil pour enregistrer un nouveau patient.";
    // Rediriger vers le tableau de bord approprié selon le rôle
    if (hasRole('medecin')) {
        header('Location: index.php');
    } else {
        header('Location: ../index.php');
    }
    exit();
}

$user_info = getUserInfo();
$id_med = $user_info['id_med'];
$specialisation = $user_info['specialisation'] ?? '';

$message = '';
$message_type = '';
$success = false;

// Récupérer les services
$services = [];
try {
    $services = getAllServices();
} catch (Exception $e) {
    error_log("Erreur récupération services: " . $e->getMessage());
}

// Traiter l'ajout du patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
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
            
            $pdo->commit();
            
            $message = "Le patient a été ajouté avec succès ! Matricule : <strong>" . htmlspecialchars($matricule) . "</strong>.";
            if (!empty($email)) {
                $message .= " Un compte utilisateur a été créé avec l'email fourni.";
            }
            $message_type = "success";
            $success = true;
            
            // Réinitialiser les champs
            $_POST = [];
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $message = "Cet email est déjà utilisé dans le système.";
            } else {
                $message = "Erreur lors de l'ajout du patient : " . $e->getMessage();
            }
            $message_type = "danger";
            error_log("Erreur ajout patient: " . $e->getMessage());
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
	<title>Ajouter un Patient - MediCo.</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" type="text/css" href="../assets/css/plugins.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
	<link class="skin" rel="stylesheet" type="text/css" href="../assets/css/skin/skin-1.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/templete.min.css">
	<style>
		.patient-form-container {
			padding: 40px 0;
			background: #f8f9fa;
			min-height: 100vh;
		}
		.form-card {
			background: #fff;
			border-radius: 10px;
			padding: 30px;
			margin-bottom: 20px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			max-width: 900px;
			margin: 0 auto;
		}
		.form-group {
			margin-bottom: 20px;
		}
		.form-group label {
			display: block;
			margin-bottom: 8px;
			font-weight: 600;
			color: #002939;
		}
		.form-group input,
		.form-group select,
		.form-group textarea {
			width: 100%;
			padding: 12px 15px;
			border: 1px solid #ddd;
			border-radius: 6px;
			font-size: 15px;
		}
		.form-row {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 20px;
		}
		.alert {
			padding: 15px 20px;
			border-radius: 6px;
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
		.btn-submit {
			background: #4A90E2;
			color: white;
			padding: 12px 30px;
			border: none;
			border-radius: 6px;
			font-size: 16px;
			font-weight: 600;
			cursor: pointer;
		}
		.btn-submit:hover {
			background: #357ABD;
		}
		.btn-cancel {
			background: #e2e8f0;
			color: #2d3748;
			padding: 12px 30px;
			border: none;
			border-radius: 6px;
			font-size: 16px;
			font-weight: 600;
			cursor: pointer;
			text-decoration: none;
			display: inline-block;
			margin-left: 10px;
		}
		.required {
			color: #e53e3e;
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
</head>
<body>
<div class="page-wraper">
	<?php require_once '../partials/entete.php'; ?>
	
	<div class="patient-form-container">
		<div class="container">
			<a href="index.php" class="btn-retour">
				<i class="fa fa-arrow-left"></i> Retour au tableau de bord
			</a>
			<div class="form-card">
				<h1 style="color: #002939; margin-bottom: 20px;">
					<i class="fa fa-user-plus"></i> Ajouter un Patient
				</h1>
				<p style="color: #666; margin-bottom: 30px;">
					Ajoutez un nouveau patient pour le service : <strong><?php echo htmlspecialchars($specialisation); ?></strong>
				</p>
				
				<?php if ($message): ?>
					<div class="alert alert-<?php echo $message_type; ?>">
						<?php echo htmlspecialchars($message); ?>
					</div>
				<?php endif; ?>
				
				<?php if (!$success): ?>
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
						<select name="id_service" required>
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
					
					<div style="margin-top: 30px; text-align: right;">
						<button type="submit" name="submit" class="btn-submit">
							<i class="fa fa-save"></i> Ajouter le Patient
						</button>
						<a href="index.php" class="btn-cancel">
							<i class="fa fa-times"></i> Annuler
						</a>
					</div>
				</form>
				<?php else: ?>
					<div style="text-align: center; padding: 40px 0;">
						<p style="font-size: 18px; color: #28a745; margin-bottom: 20px;">
							<i class="fa fa-check-circle" style="font-size: 48px;"></i>
						</p>
						<a href="ajouter-patient.php" class="btn-submit">
							<i class="fa fa-plus"></i> Ajouter un autre patient
						</a>
						<a href="index.php" class="btn-cancel">
							<i class="fa fa-home"></i> Retour au tableau de bord
						</a>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	
	<?php require_once '../partials/footer.php'; ?>
</div>
</body>
</html>
