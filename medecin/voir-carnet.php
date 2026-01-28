<?php
/**
 * Voir et imprimer un carnet de santé (médecin)
 * Permet d'afficher le carnet créé, de l'imprimer et de le remettre à la main au patient.
 */

require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';

requireLogin('login.php');
requireMedecin('index.php');

$user_info = getUserInfo();
$num_carnet = isset($_GET['num_carnet']) ? intval($_GET['num_carnet']) : null;
$nouveau = isset($_GET['nouveau']) && $_GET['nouveau'] == '1';

$carnet = null;
$message = '';
$message_type = '';

if ($num_carnet) {
    $carnet = getCarnetById($num_carnet);
    if (!$carnet) {
        $message = "Carnet introuvable.";
        $message_type = "danger";
    }
} else {
    $message = "Numéro de carnet manquant.";
    $message_type = "danger";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo $carnet ? 'Carnet - ' . htmlspecialchars($carnet['Libellé']) : 'Carnet'; ?> - Espace Médecin</title>
	<link rel="stylesheet" type="text/css" href="../assets/css/plugins.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<style>
		.carnet-view-container {
			padding: 40px 0;
			background: #f8f9fa;
			min-height: 100vh;
		}
		.carnet-print-area {
			background: #fff;
			border-radius: 10px;
			padding: 35px;
			margin-bottom: 25px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			max-width: 800px;
			margin-left: auto;
			margin-right: auto;
		}
		.carnet-header {
			text-align: center;
			border-bottom: 2px solid #4A90E2;
			padding-bottom: 20px;
			margin-bottom: 25px;
		}
		.carnet-title {
			font-size: 24px;
			font-weight: 700;
			color: #002939;
			margin: 0 0 5px 0;
		}
		.carnet-subtitle {
			font-size: 16px;
			color: #666;
			margin: 0;
		}
		.carnet-section {
			margin-bottom: 25px;
		}
		.carnet-section h4 {
			color: #4A90E2;
			font-size: 16px;
			margin-bottom: 12px;
			padding-bottom: 6px;
			border-bottom: 1px solid #eee;
		}
		.carnet-info-row {
			display: flex;
			flex-wrap: wrap;
			gap: 20px 40px;
			margin-bottom: 8px;
			font-size: 14px;
		}
		.carnet-info-label {
			font-weight: 600;
			color: #333;
			min-width: 140px;
		}
		.carnet-info-value { color: #555; }
		.btn-print {
			background: #4A90E2;
			color: white;
			border: none;
			padding: 12px 24px;
			border-radius: 6px;
			cursor: pointer;
			font-size: 16px;
			font-weight: 600;
			margin-right: 10px;
			margin-bottom: 10px;
		}
		.btn-print:hover { background: #357ABD; }
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
			margin-bottom: 20px;
		}
		.btn-retour:hover { background: #5a6268; color: white; text-decoration: none; }
		.alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
		.alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
		.alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
		.no-print { margin-bottom: 20px; }
		.space-consultations {
			min-height: 120px;
			border: 1px dashed #ccc;
			border-radius: 8px;
			padding: 15px;
			background: #fafafa;
			color: #999;
			font-size: 14px;
		}
		@media print {
			.page-wraper .navbar, .page-wraper footer, .no-print, .btn-print, .btn-retour { display: none !important; }
			.carnet-view-container { background: #fff; padding: 0; }
			.carnet-print-area { box-shadow: none; border: 1px solid #ddd; }
		}
	</style>
</head>
<body>
<div class="page-wraper">
	<?php require_once '../partials/entete.php'; ?>

	<div class="carnet-view-container">
		<div class="container">
			<div class="row">
				<div class="col-12">
					<div class="no-print">
						<a href="creer-carnet.php" class="btn-retour">
							<i class="fa fa-arrow-left"></i> Retour à la création de carnet
						</a>
						<a href="historique-carnets.php" class="btn-retour" style="background:#4A90E2;">
							<i class="fa fa-book"></i> Historique des carnets
						</a>
						<a href="index.php" class="btn-retour" style="background:#5a6268;">
							<i class="fa fa-dashboard"></i> Tableau de bord
						</a>
					</div>

					<?php if ($message): ?>
						<div class="alert alert-<?php echo $message_type; ?>">
							<?php echo htmlspecialchars($message); ?>
						</div>
					<?php endif; ?>

					<?php if ($carnet): ?>
						<?php if ($nouveau): ?>
							<div class="alert alert-success no-print">
								<i class="fa fa-check-circle"></i> Carnet créé. Imprimez cette page puis remettez-la à la main au patient.
							</div>
						<?php endif; ?>

						<div class="no-print" style="margin-bottom: 15px;">
							<button type="button" class="btn-print" onclick="window.print()">
								<i class="fa fa-print"></i> Imprimer le carnet (remettre au patient)
							</button>
						</div>

						<div class="carnet-print-area" id="zone-impression">
							<div class="carnet-header">
								<h1 class="carnet-title"><?php echo htmlspecialchars($carnet['Libellé']); ?></h1>
								<p class="carnet-subtitle">Carnet de santé — N° <?php echo (int)$carnet['Num_carnet']; ?> — Créé le <?php echo date('d/m/Y', strtotime($carnet['Date_creation'])); ?></p>
							</div>

							<div class="carnet-section">
								<h4><i class="fa fa-user"></i> Identité du patient</h4>
								<div class="carnet-info-row">
									<span class="carnet-info-label">Nom complet :</span>
									<span class="carnet-info-value"><?php echo htmlspecialchars(($carnet['Prénom_patient'] ?? '') . ' ' . ($carnet['Nom_patient'] ?? '')); ?></span>
								</div>
								<?php if (!empty($carnet['Matricule_patient'])): ?>
								<div class="carnet-info-row">
									<span class="carnet-info-label">Matricule :</span>
									<span class="carnet-info-value"><?php echo htmlspecialchars($carnet['Matricule_patient']); ?></span>
								</div>
								<?php endif; ?>
								<?php if (!empty($carnet['Date_naissance_patient'])): ?>
								<div class="carnet-info-row">
									<span class="carnet-info-label">Date de naissance :</span>
									<span class="carnet-info-value"><?php echo date('d/m/Y', strtotime($carnet['Date_naissance_patient'])); ?></span>
								</div>
								<?php endif; ?>
								<?php if (!empty($carnet['Tel_patient'])): ?>
								<div class="carnet-info-row">
									<span class="carnet-info-label">Téléphone :</span>
									<span class="carnet-info-value"><?php echo htmlspecialchars($carnet['Tel_patient']); ?></span>
								</div>
								<?php endif; ?>
								<?php if (!empty($carnet['Email_patient'])): ?>
								<div class="carnet-info-row">
									<span class="carnet-info-label">Email :</span>
									<span class="carnet-info-value"><?php echo htmlspecialchars($carnet['Email_patient']); ?></span>
								</div>
								<?php endif; ?>
								<?php if (!empty($carnet['Adresse_patient'])): ?>
								<div class="carnet-info-row">
									<span class="carnet-info-label">Adresse :</span>
									<span class="carnet-info-value"><?php echo nl2br(htmlspecialchars($carnet['Adresse_patient'])); ?></span>
								</div>
								<?php endif; ?>
							</div>

							<div class="carnet-section">
								<h4><i class="fa fa-file-text"></i> Suivi des consultations</h4>
								<p style="color:#666; font-size:13px; margin-bottom:10px;">Les consultations et ordonnances liées à ce carnet seront consignées ci-dessous.</p>
								<div class="space-consultations">
									Espace réservé aux notes de consultation et au suivi médical.
								</div>
							</div>

							<div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #eee; font-size: 12px; color: #999;">
								Document généré le <?php echo date('d/m/Y à H:i'); ?> — Clinique — À remettre au patient.
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<?php require_once '../partials/footer.php'; ?>
</div>
</body>
</html>
