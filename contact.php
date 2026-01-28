<?php
require_once 'config/session.php';
$user_info = getUserInfo();

$message = '';
$message_type = '';

// Traitement du formulaire de contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['envoyer'])) {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $sujet = trim($_POST['sujet'] ?? '');
    $message_text = trim($_POST['message'] ?? '');
    
    if (empty($nom) || empty($email) || empty($sujet) || empty($message_text)) {
        $message = "Veuillez remplir tous les champs.";
        $message_type = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Veuillez entrer une adresse email valide.";
        $message_type = "danger";
    } else {
        // Ici, vous pouvez ajouter l'envoi d'email ou la sauvegarde en base de données
        // Pour l'instant, on simule juste l'envoi
        $message = "Votre message a été envoyé avec succès ! Nous vous répondrons dans les plus brefs délais.";
        $message_type = "success";
        
        // Réinitialiser le formulaire
        $_POST = [];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Contactez-nous - MediCo.</title>
	<link rel="stylesheet" type="text/css" href="assets/css/plugins.css">
	<link rel="stylesheet" type="text/css" href="assets/css/style.min.css">
	<link rel="stylesheet" type="text/css" href="assets/css/skin/skin-1.css">
	<link rel="stylesheet" type="text/css" href="assets/css/templete.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<style>
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}
		body {
			font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
			background: #fff;
		}
		.contact-header {
			background: #4A90E2;
			color: white;
			padding: 60px 0;
			text-align: center;
		}
		.contact-header h1 {
			font-size: 42px;
			font-weight: 700;
			margin-bottom: 15px;
		}
		.contact-header p {
			font-size: 18px;
			opacity: 0.95;
		}
		.contact-content {
			padding: 60px 0;
			background: #fff;
		}
		.contact-container {
			max-width: 1200px;
			margin: 0 auto;
			padding: 0 20px;
		}
		.contact-grid {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 40px;
			margin-top: 40px;
		}
		.contact-info-section h2,
		.contact-form-section h2 {
			color: #4A90E2;
			font-size: 28px;
			font-weight: 700;
			margin-bottom: 30px;
		}
		.info-item {
			margin-bottom: 30px;
		}
		.info-item label {
			display: block;
			color: #333;
			font-weight: 600;
			font-size: 16px;
			margin-bottom: 10px;
		}
		.info-item p {
			color: #666;
			font-size: 15px;
			line-height: 1.6;
		}
		.info-item p:first-of-type {
			margin-bottom: 5px;
		}
		.form-group {
			margin-bottom: 25px;
		}
		.form-group label {
			display: block;
			color: #333;
			font-weight: 600;
			margin-bottom: 8px;
			font-size: 15px;
		}
		.form-control {
			width: 100%;
			padding: 12px 15px;
			border: 1px solid #ddd;
			border-radius: 6px;
			font-size: 15px;
			font-family: inherit;
			transition: border-color 0.3s;
		}
		.form-control:focus {
			outline: none;
			border-color: #4A90E2;
			box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
		}
		textarea.form-control {
			resize: vertical;
			min-height: 150px;
		}
		.btn-submit {
			background: #4A90E2;
			color: white;
			border: none;
			padding: 15px 40px;
			border-radius: 6px;
			font-size: 16px;
			font-weight: 600;
			cursor: pointer;
			transition: background 0.3s;
		}
		.btn-submit:hover {
			background: #357ABD;
		}
		.alert {
			padding: 15px 20px;
			border-radius: 6px;
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
		.contact-footer {
			background: #4A90E2;
			color: white;
			padding: 50px 0 30px;
		}
		.footer-grid {
			display: grid;
			grid-template-columns: repeat(4, 1fr);
			gap: 40px;
			margin-bottom: 30px;
		}
		.footer-column h4 {
			font-size: 18px;
			font-weight: 700;
			margin-bottom: 20px;
		}
		.footer-column ul {
			list-style: none;
		}
		.footer-column ul li {
			margin-bottom: 10px;
		}
		.footer-column ul li a {
			color: rgba(255, 255, 255, 0.9);
			text-decoration: none;
			transition: color 0.3s;
		}
		.footer-column ul li a:hover {
			color: white;
		}
		.social-buttons {
			display: flex;
			gap: 10px;
			flex-wrap: wrap;
		}
		.social-btn {
			background: rgba(255, 255, 255, 0.2);
			color: white;
			border: 1px solid rgba(255, 255, 255, 0.3);
			padding: 10px 20px;
			border-radius: 6px;
			text-decoration: none;
			font-weight: 600;
			transition: all 0.3s;
		}
		.social-btn:hover {
			background: rgba(255, 255, 255, 0.3);
			border-color: rgba(255, 255, 255, 0.5);
		}
		.copyright {
			text-align: center;
			padding-top: 30px;
			border-top: 1px solid rgba(255, 255, 255, 0.2);
			color: rgba(255, 255, 255, 0.9);
			font-size: 14px;
		}
		@media (max-width: 768px) {
			.contact-grid {
				grid-template-columns: 1fr;
			}
			.footer-grid {
				grid-template-columns: repeat(2, 1fr);
			}
		}
	</style>
</head>
<body>
	<?php require_once 'partials/entete.php'; ?>
	
	<!-- Header -->
	<div class="contact-header">
		<div class="contact-container">
			<h1>Contactez-nous</h1>
			<p>Nous sommes là pour vous aider</p>
		</div>
	</div>
	
	<!-- Main Content -->
	<div class="contact-content">
		<div class="contact-container">
			<?php if ($message): ?>
				<div class="alert alert-<?php echo $message_type; ?>">
					<?php echo htmlspecialchars($message); ?>
				</div>
			<?php endif; ?>
			
			<div class="contact-grid">
				<!-- Informations de contact -->
				<div class="contact-info-section">
					<h2>Informations de contact</h2>
					
					<div class="info-item">
						<label>Adresse</label>
						<p>Télico, Préfecture de Mamou</p>
						<p>République de Guinée</p>
					</div>
					
					<div class="info-item">
						<label>Téléphone</label>
						<p>+224 620 00 00 00</p>
					</div>
					
					<div class="info-item">
						<label>Email</label>
						<p>contact@medico-paris.fr</p>
					</div>
					
					<div class="info-item">
						<label>Horaires</label>
						<p>Lundi - Vendredi: 8h00 - 18h00</p>
						<p>Samedi: 9h00 - 13h00</p>
					</div>
				</div>
				
				<!-- Formulaire de contact -->
				<div class="contact-form-section">
					<h2>Envoyez-nous un message</h2>
					
					<form method="post" action="">
						<div class="form-group">
							<label for="nom">Nom</label>
							<input type="text" id="nom" name="nom" class="form-control" required 
								   value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
						</div>
						
						<div class="form-group">
							<label for="email">Email</label>
							<input type="email" id="email" name="email" class="form-control" required 
								   value="<?php echo htmlspecialchars($_POST['email'] ?? ($user_info ? $user_info['email'] : '')); ?>">
						</div>
						
						<div class="form-group">
							<label for="sujet">Sujet</label>
							<input type="text" id="sujet" name="sujet" class="form-control" required 
								   value="<?php echo htmlspecialchars($_POST['sujet'] ?? ''); ?>">
						</div>
						
						<div class="form-group">
							<label for="message">Message</label>
							<textarea id="message" name="message" class="form-control" required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
						</div>
						
						<button type="submit" name="envoyer" class="btn-submit">Envoyer</button>
					</form>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Footer Contact -->
	<div class="contact-footer">
		<div class="contact-container">
			<div class="footer-grid">
				<!-- Nos Partenaires -->
				<div class="footer-column">
					<h4>Nos Partenaires</h4>
					<ul>
						<li><a href="#">Partenaire 1</a></li>
						<li><a href="#">Partenaire 2</a></li>
						<li><a href="#">Partenaire 3</a></li>
					</ul>
				</div>
				
				<!-- Nos services -->
				<div class="footer-column">
					<h4>Nos services</h4>
					<ul>
						<li><a href="maternite.php">Radiologie</a></li>
						<li><a href="chirurgie.php">Chirurgie</a></li>
						<li><a href="ophtamologie.php">Examens</a></li>
					</ul>
				</div>
				
				<!-- Contact -->
				<div class="footer-column">
					<h4>Contact</h4>
					<ul>
						<li><a href="contact.php">Nous contacter</a></li>
						<li><a href="contact.php">Adresse</a></li>
						<li><a href="contact.php">Téléphone</a></li>
					</ul>
				</div>
				
				<!-- Réseaux sociaux -->
				<div class="footer-column">
					<h4>Réseaux sociaux</h4>
					<div class="social-buttons">
						<a href="#" class="social-btn">TikTok</a>
						<a href="#" class="social-btn">Facebook</a>
						<a href="#" class="social-btn">Instagram</a>
					</div>
				</div>
			</div>
			
			<div class="copyright">
				© 2024 MediCo. - Centre Médical. Tous droits réservés.
			</div>
		</div>
	</div>
	
	<script src="assets/js/jquery.min.js"></script>
</body>
</html>
