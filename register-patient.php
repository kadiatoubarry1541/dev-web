<?php 
require_once 'config/traitement.php';
require_once 'config/session.php';
require_once 'config/database_functions.php';

$errors = "";
$success = false;

// Rediriger si déjà connecté
requireLogout('index.php');

// Vérifier que c'est bien une inscription patient
if (!isset($_GET['type']) || $_GET['type'] !== 'patient') {
    header('Location: register.php');
    exit();
}

// Récupérer les services pour le dropdown
$services = [];
try {
    $services = getAllServices();
} catch (Exception $e) {
    // Ignorer l'erreur, on affichera juste un message
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $sexe = $_POST['sexe'] ?? '';
    $date_naissance = $_POST['date_naissance'] ?? null;
    $telephone = trim($_POST['telephone'] ?? '');
    // Normaliser l'email : trim + lowercase pour une comparaison fiable
    $email = trim(strtolower($_POST['email'] ?? ''));
    $adresse = trim($_POST['adresse'] ?? '');
    $id_service = !empty($_POST['id_service']) ? intval($_POST['id_service']) : null;
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    if (empty($nom) || empty($prenom) || empty($email) || empty($password) || empty($telephone)) {
        $errors = "Tous les champs obligatoires doivent être remplis";
    } elseif ($password !== $password_confirm) {
        $errors = "Les mots de passe ne correspondent pas";
    } elseif (strlen($password) < 6) {
        $errors = "Le mot de passe doit contenir au moins 6 caractères";
    } elseif (EmailExist($email, 'patient')) {
        $errors = "Cet email est déjà utilisé par un compte existant. Veuillez vous connecter ou utiliser un autre email.";
    } elseif (empty($id_service)) {
        $errors = "Veuillez sélectionner un service.";
    } else {
        try {
            // Convertir la date de naissance si nécessaire
            if ($date_naissance && strpos($date_naissance, '/') !== false) {
                $date_parts = explode('/', $date_naissance);
                if (count($date_parts) == 3) {
                    $date_naissance = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
                }
            }
            
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
                    inscription($nom, $prenom, $email, $telephone, $password, 'patient', $date_naissance, $adresse, $sexe, $id_service, null, $photo_profil);
                    
                    // Récupérer le matricule attribué
                    $pdo = bdd();
                    $stmt = $pdo->prepare("SELECT Matricule_patient FROM PATIENTS WHERE Email_patient = ? ORDER BY id_patient DESC LIMIT 1");
                    $stmt->execute([$email]);
                    $patient = $stmt->fetch();
                    $matricule = $patient['Matricule_patient'] ?? '';
                    
                    if (empty($matricule)) {
                        // Si le matricule n'est pas trouvé, essayer de le récupérer autrement
                        $stmt2 = $pdo->prepare("SELECT Matricule_patient FROM PATIENTS WHERE Email_patient = ? AND Nom_patient = ? AND Prénom_patient = ? ORDER BY id_patient DESC LIMIT 1");
                        $stmt2->execute([$email, $nom, $prenom]);
                        $patient2 = $stmt2->fetch();
                        $matricule = $patient2['Matricule_patient'] ?? 'Non attribué';
                    }
                    
                    $success = true;
                    // Message simple et clair
                    $success_message = "Inscription réussie !<br><br>Votre matricule : <strong>" . htmlspecialchars($matricule) . "</strong><br><br>Vous allez être redirigé vers la page de connexion...";
                    $errors = $success_message; // Pour compatibilité avec l'affichage existant
                    // Redirection immédiate après 3 secondes pour laisser le temps de voir le message
                    header('refresh:3;url=login.php');
                } catch (Exception $e) {
                    $errors = "Erreur lors de l'inscription : " . $e->getMessage();
                    error_log("Erreur inscription patient: " . $e->getMessage());
                }
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false || 
                strpos($e->getMessage(), 'UNIQUE constraint') !== false ||
                strpos($e->getMessage(), 'Duplicate') !== false) {
                $errors = "Cet email est déjà utilisé dans notre système. Veuillez utiliser un autre email ou vous connecter.";
            } else {
                $errors = "Une erreur est survenue lors de l'inscription : " . $e->getMessage();
            }
        } catch (Exception $e) {
            $errors = "Une erreur est survenue lors de l'inscription : " . $e->getMessage();
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
	<title>MediCo. - Inscription Patient</title>
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
			color: #28a745;
			font-size: 28px;
			font-weight: 700;
			text-align: center;
			margin-bottom: 30px;
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
			border-color: #28a745;
			box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
		}
		.date-input-wrapper {
			position: relative;
		}
		.date-input-wrapper .fa-calendar {
			position: absolute;
			right: 15px;
			top: 50%;
			transform: translateY(-50%);
			color: #666;
			pointer-events: none;
		}
		.date-input-wrapper input {
			padding-right: 45px;
		}
		.text-danger {
			color: #dc3545;
		}
		.btn-register {
			width: 100%;
			background: #28a745;
			color: #fff;
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
			background: #218838;
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
			border: 3px solid #28a745;
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
			background: rgba(40, 167, 69, 0.8);
			color: white;
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
			background: #28a745;
			color: white;
			border-radius: 6px;
			cursor: pointer;
			transition: background 0.3s;
			font-size: 14px;
		}
		.photo-label:hover {
			background: #218838;
		}
		.back-link {
			text-align: center;
			margin-top: 20px;
		}
		.back-link a {
			color: #28a745;
			text-decoration: none;
		}
	</style>
</head>
<body>
<div class="register-container">
	<h2 class="register-title"><i class="fa fa-user"></i> Inscription Patient</h2>
	
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
		
		<!-- Sexe -->
		<div class="form-group">
			<label>Sexe <span class="text-danger">*</span></label>
			<select name="sexe" class="form-control" required>
				<option value="">Sélectionner...</option>
				<option value="M" <?php echo (isset($_POST['sexe']) && $_POST['sexe'] == 'M') ? 'selected' : ''; ?>>Masculin</option>
				<option value="F" <?php echo (isset($_POST['sexe']) && $_POST['sexe'] == 'F') ? 'selected' : ''; ?>>Féminin</option>
			</select>
		</div>
		
		<!-- Date de naissance -->
		<div class="form-group">
			<label>Date de naissance <span class="text-danger">*</span></label>
			<div class="date-input-wrapper">
				<input type="text" name="date_naissance" class="form-control" placeholder="jj/mm/aaaa" required pattern="\d{2}/\d{2}/\d{4}" value="<?php echo htmlspecialchars($_POST['date_naissance'] ?? ''); ?>">
				<i class="fa fa-calendar"></i>
			</div>
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
		
		<!-- Adresse -->
		<div class="form-group">
			<label>Adresse</label>
			<textarea name="adresse" class="form-control" rows="3" placeholder="Votre adresse complète"><?php echo htmlspecialchars($_POST['adresse'] ?? ''); ?></textarea>
		</div>
		
		<!-- Service -->
		<div class="form-group">
			<label>Choisir votre service <span class="text-danger">*</span></label>
			<select name="id_service" class="form-control" required>
				<option value="">Sélectionner un service...</option>
				<?php foreach ($services as $service): ?>
					<option value="<?php echo $service['id_service']; ?>" <?php echo (isset($_POST['id_service']) && $_POST['id_service'] == $service['id_service']) ? 'selected' : ''; ?>>
						<?php echo htmlspecialchars($service['Nom_service']); ?>
					</option>
				<?php endforeach; ?>
			</select>
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
		<button type="submit" name="submit" class="btn-register">S'Inscrire</button>
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
	
	// Format automatique pour la date
	document.querySelector('input[name="date_naissance"]').addEventListener('input', function(e) {
		let value = e.target.value.replace(/\D/g, '');
		if (value.length >= 2) {
			value = value.substring(0, 2) + '/' + value.substring(2);
		}
		if (value.length >= 5) {
			value = value.substring(0, 5) + '/' + value.substring(5, 9);
		}
		e.target.value = value;
	});
});
</script>
</body>
</html>
