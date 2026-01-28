<?php 
require_once 'config/session.php';

// Rediriger si déjà connecté
requireLogout('index.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>MediCo. - Choisir votre type de compte</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<style>
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}
		body {
			background: #ffffff;
			font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 20px;
		}
		.selection-container {
			max-width: 700px;
			width: 100%;
			background: #fff;
			border-radius: 16px;
			border: 1px solid #e5e7eb;
			box-shadow: 0 4px 20px rgba(0,0,0,0.08);
			overflow: hidden;
		}
		.selection-header {
			background: #f8fafc;
			color: #1e293b;
			padding: 32px;
			text-align: center;
			border-bottom: 1px solid #e5e7eb;
		}
		.selection-header h1 {
			font-size: 28px;
			margin-bottom: 8px;
			color: #0f172a;
		}
		.selection-header h1 i { color: #64748b; }
		.selection-header p {
			font-size: 15px;
			color: #64748b;
		}
		.selection-body {
			padding: 36px 32px;
		}
		.account-types {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 20px;
			margin-bottom: 28px;
		}
		@media (max-width: 600px) {
			.account-types { grid-template-columns: 1fr; }
		}
		/* Boutons avec le nom écrit dessus */
		.account-btn {
			display: flex;
			flex-direction: row;
			align-items: center;
			justify-content: center;
			gap: 12px;
			width: 100%;
			min-height: 56px;
			padding: 16px 28px;
			border: none;
			border-radius: 10px;
			font-family: inherit;
			font-size: 18px;
			font-weight: 700;
			color: #fff;
			cursor: pointer;
			transition: all 0.2s ease;
			text-align: center;
			-webkit-appearance: none;
			appearance: none;
			box-shadow: 0 2px 8px rgba(0,0,0,0.15);
		}
		.account-btn:hover {
			transform: translateY(-2px);
			box-shadow: 0 4px 14px rgba(0,0,0,0.2);
		}
		.account-btn:focus {
			outline: none;
			box-shadow: 0 0 0 3px rgba(255,255,255,0.5), 0 2px 8px rgba(0,0,0,0.2);
		}
		.account-btn:active {
			transform: translateY(0);
		}
		.account-btn.patient {
			background: #16a34a;
			color: #fff;
		}
		.account-btn.patient:hover { background: #15803d; }
		.account-btn.patient.selected {
			background: #15803d;
			box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.4);
		}
		.account-btn.medecin {
			background: #d97706;
			color: #fff;
		}
		.account-btn.medecin:hover { background: #b45309; }
		.account-btn.medecin.selected {
			background: #b45309;
			box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.4);
		}
		.account-btn .account-icon {
			font-size: 22px;
			color: #fff;
		}
		.account-btn .account-title { font-size: 18px; font-weight: 700; }
		.warning-box {
			background: #fffbeb;
			border: 1px solid #fcd34d;
			padding: 14px 16px;
			border-radius: 8px;
			margin-bottom: 24px;
			display: none;
			font-size: 14px;
			color: #92400e;
		}
		.warning-box.show { display: block; }
		.warning-box i { margin-right: 8px; color: #d97706; }
		.actions {
			display: flex;
			flex-direction: column;
			gap: 14px;
		}
		.btn-continue {
			width: 100%;
			background: #2563eb;
			color: white;
			border: none;
			padding: 16px 24px;
			border-radius: 10px;
			font-size: 17px;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.2s;
			display: none;
			font-family: inherit;
			box-shadow: 0 2px 8px rgba(37, 99, 235, 0.35);
		}
		.btn-continue.show { display: flex; align-items: center; justify-content: center; gap: 10px; }
		.btn-continue:hover {
			background: #1d4ed8;
			box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
			transform: translateY(-1px);
		}
		.btn-continue:active { transform: translateY(0); }
		.btn-continue:focus {
			outline: none;
			box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.4);
		}
		.btn-login {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			width: 100%;
			padding: 14px 24px;
			background: #fff;
			color: #2563eb;
			border: 2px solid #2563eb;
			border-radius: 10px;
			font-size: 15px;
			font-weight: 600;
			text-decoration: none;
			font-family: inherit;
			cursor: pointer;
			transition: all 0.2s;
		}
		.btn-login:hover {
			background: #eff6ff;
			border-color: #1d4ed8;
			color: #1d4ed8;
		}
		.login-link {
			text-align: center;
			margin-top: 20px;
			color: #6b7280;
			font-size: 14px;
		}
	</style>
</head>
<body>
<div class="selection-container">
	<div class="selection-header">
		<h1><i class="fa fa-user-plus"></i> Créer un compte</h1>
		<p>Choisissez le type de compte qui correspond à votre profil</p>
	</div>
	
	<div class="selection-body">
		<div class="account-types">
			<!-- Bouton Patient : le nom est écrit sur le bouton -->
			<button type="button" class="account-btn patient" data-type="patient" onclick="selectAccountType('patient')" aria-pressed="false" aria-label="Choisir Patient">
				<i class="fa fa-user account-icon"></i>
				<span class="account-title">Patient</span>
			</button>
			<!-- Bouton Médecin : le nom est écrit sur le bouton -->
			<button type="button" class="account-btn medecin" data-type="medecin" onclick="selectAccountType('medecin')" aria-pressed="false" aria-label="Choisir Médecin">
				<i class="fa fa-user-md account-icon"></i>
				<span class="account-title">Médecin</span>
			</button>
		</div>
		
		<div class="warning-box" id="warning-box">
			<i class="fa fa-exclamation-triangle"></i>
			<strong>Important :</strong> Votre demande sera soumise à validation par l'administrateur. Vous recevrez un email une fois votre compte approuvé.
		</div>
		
		<div class="actions">
			<form id="account-form" method="get" action="">
				<input type="hidden" name="type" id="account-type" value="">
				<button type="submit" class="btn-continue" id="btn-continue">
					Continuer l'inscription <i class="fa fa-arrow-right"></i>
				</button>
			</form>
			<a href="login.php" class="btn-login"><i class="fa fa-sign-in"></i> Se connecter</a>
		</div>
		
		<div class="login-link">
			Déjà inscrit ? Utilisez le bouton « Se connecter ».
		</div>
	</div>
</div>

<script>
function selectAccountType(type) {
	// Retirer la sélection précédente
	document.querySelectorAll('.account-btn').forEach(btn => {
		btn.classList.remove('selected');
		btn.setAttribute('aria-pressed', 'false');
	});
	
	// Sélectionner le bouton cliqué
	const selectedBtn = document.querySelector(`.account-btn[data-type="${type}"]`);
	selectedBtn.classList.add('selected');
	selectedBtn.setAttribute('aria-pressed', 'true');
	
	// Mettre à jour le formulaire
	document.getElementById('account-type').value = type;
	
	// Afficher le bouton continuer
	document.getElementById('btn-continue').classList.add('show');
	
	// Afficher l'avertissement pour les médecins
	const warningBox = document.getElementById('warning-box');
	if (type === 'medecin') {
		warningBox.classList.add('show');
	} else {
		warningBox.classList.remove('show');
	}
	
	// Mettre à jour l'action du formulaire
	if (type === 'patient') {
		document.getElementById('account-form').action = 'register-patient.php';
	} else if (type === 'medecin') {
		document.getElementById('account-form').action = 'register-medecin.php';
	}
}
</script>
</body>
</html>
