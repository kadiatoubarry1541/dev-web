<?php
require_once 'config/traitement.php';
require_once 'config/session.php';

$message = '';
$message_type = '';

// Rediriger si déjà connecté
requireLogout('index.php');

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $message = "Veuillez remplir tous les champs.";
        $message_type = "danger";
    } else {
        // Vérifier d'abord si le compte existe
        if (!EmailExist($email)) {
            // Le compte n'existe pas dans la base de données
            $message = "Aucun compte trouvé avec cet email. Veuillez vérifier votre adresse email ou créer un compte.";
            $message_type = "danger";
        } else {
            try {
                if (connexion($email, $password)) {
            // Connexion réussie, rediriger selon le rôle
            require_once 'config/permissions.php';
            $user_info = getUserInfo();
            $role = $user_info['role'] ?? 'patient';
            
                    // Redirection selon le rôle
                    if ($role === 'admin') {
                        header('Location: admin/index.php');
                    } elseif ($role === 'medecin') {
                        header('Location: medecin/index.php');
                    } elseif ($role === 'accueil') {
                        header('Location: accueil/index.php');
                    } elseif ($role === 'patient') {
                        header('Location: patient/index.php');
                    } else {
                        header('Location: index.php');
                    }
                    exit();
                } else {
                    // Le compte existe mais le mot de passe est incorrect
                    $message = "Mot de passe incorrect. Veuillez réessayer.";
                    $message_type = "danger";
                }
            } catch (Exception $e) {
                // Gérer les erreurs de connexion (comme le statut en attente)
                $message = $e->getMessage();
                $message_type = "danger";
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
	<meta name="keywords" content="connexion, compte, espace patient, MediCo">
	<meta name="author" content="MediCo.">
	<meta name="robots" content="index, follow">
	<meta name="description" content="Connectez-vous à votre compte MediCo. pour accéder à vos rendez-vous et informations médicales.">
	<meta property="og:title" content="MediCo. - Connexion">
	<meta property="og:description" content="Accédez à votre espace patient en toute sécurité.">
	<meta property="og:image" content="image/1.jpeg">
	<meta name="format-detection" content="telephone=no">
	
	<!-- FAVICONS ICON -->
	<link rel="icon" href="images/favicon.ico" type="image/x-icon">
	<link rel="shortcut icon" type="image/x-icon" href="images/favicon.png">
	
	<!-- PAGE TITLE HERE -->
	<title>MediCo. - Connexion</title>
	
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
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<style>
		body {
			background: #f5f5f5;
			font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
		}
		.login-container {
			max-width: 450px;
			margin: 60px auto;
			background: #fff;
			border-radius: 12px;
			box-shadow: 0 4px 20px rgba(0,0,0,0.1);
			padding: 40px;
		}
		.login-title {
			color: #4A90E2;
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
			border-color: #4A90E2;
			box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
		}
		.login-options {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 20px;
		}
		.remember-me {
			display: flex;
			align-items: center;
		}
		.remember-me input[type="checkbox"] {
			margin-right: 8px;
		}
		.remember-me label {
			margin: 0;
			font-weight: normal;
			font-size: 14px;
			color: #666;
		}
		.forgot-password {
			color: #4A90E2;
			text-decoration: none;
			font-size: 14px;
		}
		.forgot-password:hover {
			text-decoration: underline;
		}
		.btn-login {
			width: 100%;
			background: #fff;
			color: #4A90E2;
			border: 1px solid #4A90E2;
			padding: 12px;
			border-radius: 6px;
			font-size: 16px;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.3s;
			margin-bottom: 20px;
		}
		.btn-login:hover {
			background: #4A90E2;
			color: #fff;
		}
		.separator {
			text-align: center;
			margin: 20px 0;
			position: relative;
		}
		.separator::before {
			content: '';
			position: absolute;
			left: 0;
			top: 50%;
			width: 100%;
			height: 1px;
			background: #ddd;
		}
		.separator span {
			background: #fff;
			padding: 0 15px;
			position: relative;
			color: #666;
		}
		.btn-google {
			width: 100%;
			background: #fff;
			color: #333;
			border: 1px solid #ddd;
			padding: 12px;
			border-radius: 6px;
			font-size: 16px;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.3s;
			margin-bottom: 20px;
		}
		.btn-google:hover {
			background: #f8f9fa;
			border-color: #333;
		}
		.register-link {
			text-align: center;
			margin-top: 20px;
			color: #666;
			font-size: 14px;
		}
		.register-link a {
			color: #4A90E2;
			text-decoration: none;
			font-weight: 600;
		}
		.register-link a:hover {
			text-decoration: underline;
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
	</style>
</head>
<body>
<div class="login-container">
	<h2 class="login-title">Connexion</h2>
	
	<?php if ($message): ?>
		<div class="alert alert-<?php echo $message_type; ?>">
			<?php echo htmlspecialchars($message); ?>
		</div>
	<?php endif; ?>
	
	<form method="post" action="">
		<!-- Email -->
		<div class="form-group">
			<label>Email</label>
			<input type="email" name="email" class="form-control" required 
				   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
		</div>
		
		<!-- Mot de passe -->
		<div class="form-group">
			<label>Mot de passe</label>
			<input type="password" name="password" class="form-control" required>
		</div>
		
		<!-- Options -->
		<div class="login-options">
			<div class="remember-me">
				<input type="checkbox" id="remember" name="remember">
				<label for="remember">Se souvenir de moi</label>
			</div>
			<a href="#" class="forgot-password">Mot de passe oublié ?</a>
		</div>
		
		<!-- Bouton Connexion -->
		<button type="submit" name="submit_login" class="btn-login">Se connecter</button>
	</form>
	
	<!-- Séparateur -->
	<div class="separator">
		<span>OU</span>
	</div>
	
	<!-- Bouton Google -->
	<button type="button" class="btn-google" onclick="alert('Connexion Google à implémenter')">
		Se connecter avec Google
	</button>
	
	<!-- Lien Inscription -->
	<div class="register-link">
		Vous n'avez pas de compte ? <a href="register.php">S'inscrire</a>
	</div>
</div>

<!-- JavaScript -->
<script src="assets/js/jquery.min.js"></script>
</body>
</html>
