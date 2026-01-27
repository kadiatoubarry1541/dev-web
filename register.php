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
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 20px;
		}
		.selection-container {
			max-width: 800px;
			width: 100%;
			background: #fff;
			border-radius: 20px;
			box-shadow: 0 10px 40px rgba(0,0,0,0.2);
			overflow: hidden;
		}
		.selection-header {
			background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%);
			color: white;
			padding: 40px;
			text-align: center;
		}
		.selection-header h1 {
			font-size: 32px;
			margin-bottom: 10px;
		}
		.selection-header p {
			font-size: 16px;
			opacity: 0.9;
		}
		.selection-body {
			padding: 50px 40px;
		}
		.account-types {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
			gap: 30px;
			margin-bottom: 30px;
		}
		.account-card {
			border: 2px solid #e0e0e0;
			border-radius: 15px;
			padding: 30px;
			text-align: center;
			cursor: pointer;
			transition: all 0.3s;
			background: #fff;
		}
		.account-card:hover {
			border-color: #4A90E2;
			transform: translateY(-5px);
			box-shadow: 0 5px 20px rgba(74, 144, 226, 0.2);
		}
		.account-card.selected {
			border-color: #4A90E2;
			background: #f0f7ff;
		}
		.account-icon {
			width: 80px;
			height: 80px;
			margin: 0 auto 20px;
			background: #4A90E2;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			color: white;
			font-size: 36px;
		}
		.account-card.patient .account-icon {
			background: #28a745;
		}
		.account-card.medecin .account-icon {
			background: #ffc107;
		}
		.account-title {
			font-size: 24px;
			font-weight: 700;
			color: #333;
			margin-bottom: 15px;
		}
		.account-description {
			color: #666;
			font-size: 14px;
			line-height: 1.6;
			margin-bottom: 20px;
		}
		.account-features {
			list-style: none;
			text-align: left;
			margin-bottom: 20px;
		}
		.account-features li {
			padding: 8px 0;
			color: #555;
			font-size: 14px;
		}
		.account-features li i {
			color: #4A90E2;
			margin-right: 10px;
			width: 20px;
		}
		.account-card.patient .account-features li i {
			color: #28a745;
		}
		.account-card.medecin .account-features li i {
			color: #ffc107;
		}
		.warning-box {
			background: #fff3cd;
			border-left: 4px solid #ffc107;
			padding: 15px;
			border-radius: 8px;
			margin-bottom: 30px;
			display: none;
		}
		.warning-box.show {
			display: block;
		}
		.warning-box i {
			color: #ffc107;
			margin-right: 10px;
		}
		.btn-continue {
			width: 100%;
			background: #4A90E2;
			color: white;
			border: none;
			padding: 18px;
			border-radius: 10px;
			font-size: 18px;
			font-weight: 600;
			cursor: pointer;
			transition: background 0.3s;
			display: none;
		}
		.btn-continue.show {
			display: block;
		}
		.btn-continue:hover {
			background: #357ABD;
		}
		.btn-continue:disabled {
			background: #ccc;
			cursor: not-allowed;
		}
		.login-link {
			text-align: center;
			margin-top: 25px;
			color: #666;
		}
		.login-link a {
			color: #4A90E2;
			text-decoration: none;
			font-weight: 600;
		}
		.login-link a:hover {
			text-decoration: underline;
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
			<!-- Carte Patient -->
			<div class="account-card patient" data-type="patient" onclick="selectAccountType('patient')">
				<div class="account-icon">
					<i class="fa fa-user"></i>
				</div>
				<h2 class="account-title">Patient</h2>
				<p class="account-description">
					Créez votre compte patient pour accéder rapidement à vos services médicaux
				</p>
				<ul class="account-features">
					<li><i class="fa fa-check"></i> Accès immédiat à votre compte</li>
					<li><i class="fa fa-check"></i> Prendre des rendez-vous en ligne</li>
					<li><i class="fa fa-check"></i> Consulter vos dossiers médicaux</li>
					<li><i class="fa fa-check"></i> Gérer vos informations personnelles</li>
				</ul>
			</div>
			
			<!-- Carte Médecin -->
			<div class="account-card medecin" data-type="medecin" onclick="selectAccountType('medecin')">
				<div class="account-icon">
					<i class="fa fa-user-md"></i>
				</div>
				<h2 class="account-title">Médecin</h2>
				<p class="account-description">
					Inscrivez-vous en tant que professionnel de santé
				</p>
				<ul class="account-features">
					<li><i class="fa fa-check"></i> Gérer vos rendez-vous</li>
					<li><i class="fa fa-check"></i> Consulter vos patients</li>
					<li><i class="fa fa-check"></i> Créer des ordonnances</li>
					<li><i class="fa fa-info-circle"></i> <strong>Validation requise par l'administrateur</strong></li>
				</ul>
			</div>
		</div>
		
		<div class="warning-box" id="warning-box">
			<i class="fa fa-exclamation-triangle"></i>
			<strong>Important :</strong> Votre demande d'inscription en tant que médecin sera soumise à validation par l'administrateur. Vous recevrez un email de confirmation une fois votre compte approuvé.
		</div>
		
		<form id="account-form" method="get" action="">
			<input type="hidden" name="type" id="account-type" value="">
			<button type="submit" class="btn-continue" id="btn-continue">
				Continuer l'inscription <i class="fa fa-arrow-right"></i>
			</button>
		</form>
		
		<div class="login-link">
			Vous avez déjà un compte? <a href="login.php">Se connecter</a>
		</div>
	</div>
</div>

<script>
function selectAccountType(type) {
	// Retirer la sélection précédente
	document.querySelectorAll('.account-card').forEach(card => {
		card.classList.remove('selected');
	});
	
	// Ajouter la sélection à la carte cliquée
	const selectedCard = document.querySelector(`.account-card[data-type="${type}"]`);
	selectedCard.classList.add('selected');
	
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
