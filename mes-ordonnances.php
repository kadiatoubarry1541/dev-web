<?php
require_once 'config/session.php';
require_once 'config/permissions.php';
require_once 'config/database_functions.php';

requireLogin('login.php');

// Vérifier que l'utilisateur est un patient
if (!isPatient() && !isAdmin()) {
    header('Location: index.php');
    exit();
}

$user_info = getUserInfo();
$id_patient = $user_info['id_patient'] ?? null;

$ordonnances = [];
$message = '';
$message_type = '';

if (!$id_patient) {
    $message = "Vous devez être connecté en tant que patient pour voir vos ordonnances.";
    $message_type = "danger";
} else {
    try {
        $ordonnances = getOrdonnancesByPatient($id_patient);
        if ($ordonnances === false) {
            $ordonnances = [];
        }
    } catch (Exception $e) {
        error_log("Erreur récupération ordonnances patient: " . $e->getMessage());
        $message = "Une erreur est survenue lors de la récupération de vos ordonnances.";
        $message_type = "danger";
        $ordonnances = [];
    }
}

// Grouper les ordonnances par consultation
$ordonnances_grouped = [];
foreach ($ordonnances as $ordo) {
    $id_consultation = $ordo['id_consultation'];
    if (!isset($ordonnances_grouped[$id_consultation])) {
        $ordonnances_grouped[$id_consultation] = [
            'consultation' => $ordo,
            'medicaments' => []
        ];
    }
    $ordonnances_grouped[$id_consultation]['medicaments'][] = $ordo;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Mes Ordonnances - MediCo.</title>
	<link rel="stylesheet" type="text/css" href="assets/css/plugins.css">
	<link rel="stylesheet" type="text/css" href="assets/css/style.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<style>
		.ordonnances-container {
			padding: 40px 0;
			background: #f8f9fa;
			min-height: 100vh;
		}
		.ordonnance-card {
			background: #fff;
			border-radius: 10px;
			padding: 30px;
			margin-bottom: 25px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			border-left: 4px solid #4A90E2;
		}
		.ordonnance-header {
			border-bottom: 2px solid #f0f0f0;
			padding-bottom: 15px;
			margin-bottom: 20px;
		}
		.medecin-name {
			font-size: 22px;
			font-weight: 700;
			color: #002939;
			margin-bottom: 10px;
		}
		.consultation-info {
			color: #666;
			font-size: 14px;
		}
		.medicament-item {
			background: #f8f9fa;
			padding: 18px;
			border-radius: 8px;
			margin-bottom: 12px;
			border-left: 3px solid #28a745;
		}
		.medicament-name {
			font-weight: 600;
			color: #333;
			font-size: 17px;
			margin-bottom: 10px;
		}
		.medicament-details {
			color: #666;
			font-size: 14px;
			line-height: 1.6;
		}
		.medicament-details strong {
			color: #333;
		}
		.info-box {
			background: #f0f7ff;
			border-left: 4px solid #4A90E2;
			padding: 15px;
			border-radius: 6px;
			margin-bottom: 20px;
		}
		.empty-state {
			text-align: center;
			padding: 60px 20px;
			color: #666;
		}
		.empty-state i {
			font-size: 64px;
			color: #ddd;
			margin-bottom: 20px;
		}
		.btn-print {
			background: #4A90E2;
			color: white;
			border: none;
			padding: 10px 20px;
			border-radius: 6px;
			cursor: pointer;
			font-size: 14px;
			margin-top: 15px;
			transition: all 0.3s;
		}
		.btn-print:hover {
			background: #357ABD;
			transform: translateY(-2px);
			box-shadow: 0 4px 12px rgba(0,0,0,0.15);
		}
		.ordonnance-date {
			text-align: right;
			color: #666;
			font-size: 14px;
			margin-bottom: 15px;
			padding-bottom: 10px;
			border-bottom: 1px solid #eee;
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
		.alert {
			padding: 15px;
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
		@media print {
			.btn-retour, .btn-print, .info-box {
				display: none;
			}
			.ordonnance-card {
				page-break-inside: avoid;
				margin-bottom: 30px;
			}
		}
	</style>
</head>
<body>
<div class="page-wraper">
	<?php require_once 'partials/entete.php'; ?>
	
	<div class="ordonnances-container">
		<div class="container">
			<div class="row">
				<div class="col-12">
					<a href="profil.php" class="btn-retour">
						<i class="fa fa-arrow-left"></i> Retour au profil
					</a>
					<h1 style="color: #002939; margin-bottom: 20px;">
						<i class="fa fa-prescription"></i> Mes Ordonnances
					</h1>
					
					<div class="info-box">
						<strong><i class="fa fa-info-circle"></i> Informations :</strong> 
						Vous trouverez ici toutes vos ordonnances médicales. 
						<strong>Total :</strong> <?php echo count($ordonnances_grouped); ?> ordonnance(s) | 
						<strong>Médicaments :</strong> <?php echo count($ordonnances); ?>
					</div>
					
					<?php if ($message): ?>
						<div class="alert alert-<?php echo $message_type; ?>">
							<?php echo htmlspecialchars($message); ?>
						</div>
					<?php endif; ?>
					
					<?php if (empty($ordonnances_grouped)): ?>
						<div class="empty-state">
							<i class="fa fa-prescription"></i>
							<h3>Aucune ordonnance disponible</h3>
							<p>Vous n'avez pas encore d'ordonnance médicale.</p>
							<p>Vos ordonnances apparaîtront ici une fois qu'un médecin vous en aura prescrit une.</p>
							<a href="profil.php" class="site-button" style="margin-top: 20px; text-decoration: none;">
								<i class="fa fa-arrow-left"></i> Retour au profil
							</a>
						</div>
					<?php else: ?>
						<?php foreach ($ordonnances_grouped as $id_consultation => $group): 
							$consultation = $group['consultation'];
							$medicaments = $group['medicaments'];
						?>
							<div class="ordonnance-card" id="ordonnance-<?php echo $id_consultation; ?>">
								<div class="ordonnance-header">
									<div class="medecin-name">
										<i class="fa fa-user-md"></i> 
										Dr. <?php 
										if (isset($consultation['Prénom_med']) && isset($consultation['Nom_med'])) {
											echo htmlspecialchars($consultation['Prénom_med'] . ' ' . $consultation['Nom_med']);
										} else {
											echo "Médecin";
										}
										?>
									</div>
									<div class="consultation-info">
										<?php if (isset($consultation['Spécialisation_med']) && $consultation['Spécialisation_med']): ?>
											<i class="fa fa-stethoscope"></i> 
											<?php echo htmlspecialchars($consultation['Spécialisation_med']); ?>
										<?php endif; ?>
										<?php if (isset($consultation['Date_consultation'])): ?>
											| <i class="fa fa-calendar"></i> Consultation du 
											<?php echo date('d/m/Y à H:i', strtotime($consultation['Date_consultation'])); ?>
										<?php endif; ?>
									</div>
									<?php if (isset($consultation['Motif_diagnostic']) && $consultation['Motif_diagnostic']): ?>
										<div style="margin-top: 15px; padding: 12px; background: #f8f9fa; border-radius: 6px;">
											<strong><i class="fa fa-file-text"></i> Motif/Diagnostic :</strong> 
											<?php echo nl2br(htmlspecialchars($consultation['Motif_diagnostic'])); ?>
										</div>
									<?php endif; ?>
								</div>
								
								<div class="ordonnance-date">
									<strong><i class="fa fa-calendar-check-o"></i> Date d'émission :</strong> 
									<?php 
									if (isset($medicaments[0]['Date_émission'])) {
										echo date('d/m/Y', strtotime($medicaments[0]['Date_émission']));
									}
									?>
								</div>
								
								<h4 style="color: #4A90E2; margin-bottom: 20px; margin-top: 20px;">
									<i class="fa fa-medkit"></i> Prescription Médicale
								</h4>
								
								<?php foreach ($medicaments as $index => $medicament): ?>
									<div class="medicament-item">
										<div class="medicament-name">
											<i class="fa fa-capsules"></i> 
											<?php echo ($index + 1); ?>. <?php echo htmlspecialchars($medicament['Médicament']); ?>
										</div>
										<div class="medicament-details">
											<?php if (!empty($medicament['Dosage'])): ?>
												<div style="margin-bottom: 5px;">
													<strong><i class="fa fa-flask"></i> Dosage :</strong> 
													<?php echo htmlspecialchars($medicament['Dosage']); ?>
												</div>
											<?php endif; ?>
											<?php if (!empty($medicament['Durée_traitement'])): ?>
												<div style="margin-bottom: 5px;">
													<strong><i class="fa fa-clock-o"></i> Durée du traitement :</strong> 
													<?php echo htmlspecialchars($medicament['Durée_traitement']); ?>
												</div>
											<?php endif; ?>
											<?php if (!empty($medicament['Instructions'])): ?>
												<div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #ddd;">
													<strong><i class="fa fa-info-circle"></i> Instructions spéciales :</strong> 
													<?php echo nl2br(htmlspecialchars($medicament['Instructions'])); ?>
												</div>
											<?php endif; ?>
										</div>
									</div>
								<?php endforeach; ?>
								
								<button type="button" class="btn-print" onclick="window.print()">
									<i class="fa fa-print"></i> Imprimer cette ordonnance
								</button>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	
	<?php require_once 'partials/footer.php'; ?>
</div>

<script src="assets/js/jquery.min.js"></script>
</body>
</html>
