<?php 
require_once 'config/traitement.php';
require_once 'config/session.php';
require_once 'config/database_functions.php';

$errors = "";
$success = false;

// Rediriger si déjà connecté
requireLogout('index.php');

// Vérifier que c'est bien une inscription médecin
if (!isset($_GET['type']) || $_GET['type'] !== 'medecin') {
    header('Location: register.php');
    exit();
}

// Récupérer les services pour le dropdown
$services = [];
$services_error = null;
try {
    $services = getAllServices();
    if (empty($services)) {
        $services_error = "Aucun service disponible. Veuillez contacter l'administrateur.";
    }
} catch (Exception $e) {
    $services_error = "Erreur lors du chargement des services : " . $e->getMessage();
    error_log("Erreur getAllServices dans register-medecin.php: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    // Normaliser l'email : trim + lowercase pour une comparaison fiable
    $email = trim(strtolower($_POST['email'] ?? ''));
    $id_service = !empty($_POST['id_service']) ? intval($_POST['id_service']) : null;
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Si c'est un médecin, récupérer la spécialisation depuis le service
    $specialisation = null;
    if ($id_service) {
        $service_info = getServiceById($id_service);
        if (!$service_info) {
            $errors = "Le service sélectionné n'existe pas. Veuillez sélectionner un service valide.";
        } else {
            $specialisation = $service_info['Nom_service'];
        }
    }
    
    // Validation des champs
    if (empty($nom) || empty($prenom) || empty($email) || empty($password) || empty($telephone)) {
        $errors = "Tous les champs obligatoires doivent être remplis";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors = "L'adresse email n'est pas valide";
    } elseif ($password !== $password_confirm) {
        $errors = "Les mots de passe ne correspondent pas";
    } elseif (strlen($password) < 6) {
        $errors = "Le mot de passe doit contenir au moins 6 caractères";
    } elseif (EmailExist($email, 'medecin')) {
        $errors = "Cet email est déjà utilisé par un compte existant. Veuillez vous connecter ou utiliser un autre email.";
    } elseif (empty($id_service)) {
        $errors = "Veuillez sélectionner votre spécialisation.";
    } elseif (empty($specialisation)) {
        $errors = "Erreur : Impossible de récupérer la spécialisation. Veuillez réessayer.";
    } else {
        try {
            // Gérer l'upload de la photo de profil
            $photo_profil = null;
            if (isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] === UPLOAD_ERR_OK) {
                $upload_result = uploadPhotoProfil($_FILES['photo_profil']);
                if ($upload_result['success']) {
                    $photo_profil = $upload_result['filename'];
                } else {
                    $errors = $upload_result['error'];
                }
            }
            
            if (empty($errors)) {
                try {
                    // Appeler la fonction d'inscription
                    $result = inscription($nom, $prenom, $email, $telephone, $password, 'medecin', null, null, null, $id_service, $specialisation, $photo_profil);
                    
                    // Vérifier que l'inscription a réussi
                    if ($result === true) {
                        $success = true;
                        $errors = "Inscription réussie !<br><br>Vous pouvez maintenant vous connecter à votre compte.<br><br><strong>Important :</strong> Votre compte est en attente d'approbation par l'administrateur. Vous avez accès à votre espace mais avec des droits limités (accès uniquement à votre service).<br><br>Une fois votre compte approuvé par l'administrateur :<br>• Vous recevrez votre matricule<br>• Vous aurez accès à toutes les fonctionnalités de médecin<br>• Votre matricule sera affiché dans votre profil<br>• Vous apparaîtrez dans la liste des médecins pour les patients<br><br>Vous allez être redirigé vers la page de connexion...";
                        header('refresh:3;url=login.php');
                    } else {
                        $errors = "Une erreur est survenue lors de l'inscription. Veuillez réessayer ou contacter l'administrateur.";
                        error_log("Inscription médecin échouée - fonction inscription() a retourné false");
                    }
                } catch (Exception $e) {
                    // Cette exception sera capturée par le bloc catch plus bas
                    throw $e;
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur PDO lors de l'inscription médecin: " . $e->getMessage() . " | Code: " . $e->getCode());
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false || 
                strpos($e->getMessage(), 'UNIQUE constraint') !== false ||
                strpos($e->getMessage(), 'Duplicate') !== false) {
                $errors = "Cet email est déjà utilisé dans notre système. Veuillez utiliser un autre email ou vous connecter.";
            } elseif (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "n'existe pas") !== false) {
                $errors = "Erreur de base de données : Une table est manquante. Veuillez contacter l'administrateur.";
            } else {
                $errors = "Une erreur de base de données est survenue lors de l'inscription. Veuillez réessayer ou contacter l'administrateur.";
            }
        } catch (Exception $e) {
            error_log("Erreur lors de l'inscription médecin: " . $e->getMessage());
            $errors = "Une erreur est survenue lors de l'inscription : " . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>MediCo. - Inscription Médecin</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<style>
		body {
			background: #f5f5f5;
			font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
		}
		.register-container {
			max-width: 600px;
			margin: 40px auto;
			background: #fff;
			border-radius: 12px;
			box-shadow: 0 4px 20px rgba(0,0,0,0.1);
			padding: 40px;
		}
		.register-title {
			color: #ffc107;
			font-size: 28px;
			font-weight: 700;
			text-align: center;
			margin-bottom: 30px;
		}
		.warning-box {
			background: #fff3cd;
			border-left: 4px solid #ffc107;
			padding: 15px;
			border-radius: 8px;
			margin-bottom: 20px;
		}
		.warning-box i {
			color: #ffc107;
			margin-right: 10px;
		}
		.form-group {
			margin-bottom: 20px;
		}
		.form-group label {
			color: #333;
			font-weight: 600;
			margin-bottom: 8px;
			display: block;
			font-size: 14px;
		}
		.form-control {
			width: 100%;
			padding: 12px 15px;
			border: 1px solid #ddd;
			border-radius: 6px;
			font-size: 15px;
			transition: border-color 0.3s;
		}
		.form-control:focus {
			outline: none;
			border-color: #ffc107;
			box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.1);
		}
		.text-danger {
			color: #dc3545;
		}
		.btn-register {
			width: 100%;
			background: #ffc107;
			color: #333;
			border: none;
			padding: 15px;
			border-radius: 8px;
			font-size: 18px;
			font-weight: 600;
			cursor: pointer;
			transition: background 0.3s;
			margin-top: 10px;
		}
		.btn-register:hover {
			background: #e0a800;
		}
		.alert {
			padding: 12px 15px;
			border-radius: 6px;
			margin-bottom: 20px;
		}
		.alert-danger {
			background: #f8d7da;
			border: 1px solid #f5c6cb;
			color: #721c24;
		}
		.alert-success {
			background: #d4edda;
			border: 1px solid #c3e6cb;
			color: #155724;
		}
		.photo-upload-wrapper {
			text-align: center;
		}
		.photo-preview-container {
			position: relative;
			display: inline-block;
			margin-bottom: 15px;
			cursor: pointer;
			border-radius: 50%;
			overflow: hidden;
			width: 150px;
			height: 150px;
			border: 3px solid #ffc107;
			background: #f5f5f5;
		}
		.photo-preview {
			width: 100%;
			height: 100%;
			object-fit: cover;
			display: block;
		}
		.photo-overlay {
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: rgba(255, 193, 7, 0.8);
			color: #333;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			opacity: 0;
			transition: opacity 0.3s;
			font-size: 12px;
		}
		.photo-preview-container:hover .photo-overlay {
			opacity: 1;
		}
		.photo-input {
			display: none;
		}
		.photo-label {
			display: inline-block;
			padding: 10px 20px;
			background: #ffc107;
			color: #333;
			border-radius: 6px;
			cursor: pointer;
			transition: background 0.3s;
			font-size: 14px;
		}
		.photo-label:hover {
			background: #e0a800;
		}
		.back-link {
			text-align: center;
			margin-top: 20px;
		}
		.back-link a {
			color: #ffc107;
			text-decoration: none;
		}
	</style>
</head>
<body>
<div class="register-container">
	<h2 class="register-title"><i class="fa fa-user-md"></i> Inscription Médecin</h2>
	
	<?php if ($services_error): ?>
		<div class="alert alert-danger" style="text-align: center; font-size: 16px; padding: 20px;">
			<i class="fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars($services_error); ?>
		</div>
	<?php endif; ?>
	
	<div class="warning-box">
		<i class="fa fa-info-circle"></i>
		<strong>Information :</strong> Après votre inscription, vous pourrez vous connecter immédiatement à votre compte. Cependant, votre compte sera en attente d'approbation par l'administrateur. Pendant cette période, vous aurez accès uniquement à votre service avec des droits limités. Une fois approuvé, vous recevrez votre matricule et aurez accès à toutes les fonctionnalités de médecin.
	</div>
	
	<?php if (!empty($errors)): ?>
		<div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>" style="text-align: center; font-size: 16px; padding: 20px;">
			<?php if ($success): ?>
				<?php echo $errors; ?>
			<?php else: ?>
				<?php echo htmlspecialchars($errors); ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	
	<?php if (!$success): ?>
	<form method="post" action="" enctype="multipart/form-data">
		<!-- Photo de profil -->
		<div class="form-group">
			<label>Photo de profil</label>
			<div class="photo-upload-wrapper">
				<div class="photo-preview-container">
					<img id="photo-preview" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='150' height='150'%3E%3Crect fill='%23ddd' width='150' height='150'/%3E%3Ctext fill='%23999' x='50%25' y='50%25' text-anchor='middle' dy='.3em' font-size='14'%3EPhoto%3C/text%3E%3C/svg%3E" alt="Aperçu photo" class="photo-preview">
					<div class="photo-overlay">
						<i class="fa fa-camera"></i>
						<span>Cliquez pour changer</span>
					</div>
				</div>
				<input type="file" name="photo_profil" id="photo_profil" accept="image/*" class="photo-input" onchange="previewPhoto(this)">
				<label for="photo_profil" class="photo-label">
					<i class="fa fa-upload"></i> Télécharger une photo
				</label>
				<small style="color: #666; font-size: 12px; display: block; margin-top: 8px;">
					Tous les formats d'image sont acceptés (max 5MB)
				</small>
			</div>
		</div>
		
		<!-- Nom -->
		<div class="form-group">
			<label>Nom <span class="text-danger">*</span></label>
			<input type="text" name="nom" class="form-control" required value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
		</div>
		
		<!-- Prénom -->
		<div class="form-group">
			<label>Prénom <span class="text-danger">*</span></label>
			<input type="text" name="prenom" class="form-control" required value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>">
		</div>
		
		<!-- Téléphone -->
		<div class="form-group">
			<label>Numéro de téléphone <span class="text-danger">*</span></label>
			<input type="tel" name="telephone" class="form-control" required placeholder="Ex: +225 07 12 34 56 78" value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
		</div>
		
		<!-- Email -->
		<div class="form-group">
			<label>Email <span class="text-danger">*</span></label>
			<input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
		</div>
		
		<!-- Spécialisation -->
		<div class="form-group">
			<label>Spécialisation <span class="text-danger">*</span></label>
			<select name="id_service" class="form-control" required <?php echo empty($services) ? 'disabled' : ''; ?>>
				<option value="">Sélectionner votre spécialisation...</option>
				<?php if (!empty($services)): ?>
					<?php foreach ($services as $service): ?>
						<option value="<?php echo $service['id_service']; ?>" <?php echo (isset($_POST['id_service']) && $_POST['id_service'] == $service['id_service']) ? 'selected' : ''; ?>>
							<?php echo htmlspecialchars($service['Nom_service']); ?>
						</option>
					<?php endforeach; ?>
				<?php else: ?>
					<option value="">Aucun service disponible</option>
				<?php endif; ?>
			</select>
			<?php if (empty($services)): ?>
				<small class="text-danger">Impossible de charger les services. Veuillez contacter l'administrateur.</small>
			<?php endif; ?>
		</div>
		
		<!-- Mot de passe -->
		<div class="form-group">
			<label>Mot de passe <span class="text-danger">*</span></label>
			<input type="password" name="password" class="form-control" required minlength="6">
			<small style="color: #666; font-size: 12px;">Minimum 6 caractères</small>
		</div>
		
		<!-- Confirmer mot de passe -->
		<div class="form-group">
			<label>Confirmer le mot de passe <span class="text-danger">*</span></label>
			<input type="password" name="password_confirm" class="form-control" required minlength="6">
		</div>
		
		<!-- Bouton Submit -->
		<button type="submit" name="submit" class="btn-register">Soumettre ma demande</button>
	</form>
	
	<div class="back-link">
		<a href="register.php"><i class="fa fa-arrow-left"></i> Retour au choix du type de compte</a>
	</div>
	<?php else: ?>
		<div style="text-align: center; margin-top: 20px;">
			<a href="login.php" class="btn-register" style="text-decoration: none; display: inline-block; width: auto; padding: 12px 30px;">
				Se connecter maintenant
			</a>
		</div>
	<?php endif; ?>
</div>

<script>
function previewPhoto(input) {
	if (input.files && input.files[0]) {
		const reader = new FileReader();
		reader.onload = function(e) {
			document.getElementById('photo-preview').src = e.target.result;
		};
		reader.readAsDataURL(input.files[0]);
	}
}

document.addEventListener('DOMContentLoaded', function() {
	const photoContainer = document.querySelector('.photo-preview-container');
	const photoInput = document.getElementById('photo_profil');
	if (photoContainer && photoInput) {
		photoContainer.addEventListener('click', function() {
			photoInput.click();
		});
	}
});
</script>
</body>
</html>
