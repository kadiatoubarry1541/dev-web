<?php
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';

requireLogin('login.php');
requireMedecin('index.php');

$user_info = getUserInfo();
$id_med = $user_info['id_med'];
$specialisation = $user_info['specialisation'] ?? '';

$mes_consultations = [];
$message = '';
$message_type = '';

if ($id_med) {
    try {
        // Récupérer les consultations du médecin filtrées par son service
        $mes_consultations = getConsultationsByMedecin($id_med, $specialisation);
        if ($mes_consultations === false) {
            $mes_consultations = [];
        }
    } catch (Exception $e) {
        error_log("Erreur mes-consultations: " . $e->getMessage());
        $message = "Une erreur est survenue lors de la récupération des consultations.";
        $message_type = "danger";
        $mes_consultations = [];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Mes Consultations - Espace Médecin</title>
	<link rel="stylesheet" type="text/css" href="../assets/css/plugins.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<style>
		.consultations-container {
			padding: 40px 0;
			background: #f8f9fa;
			min-height: 100vh;
		}
		.consultation-card {
			background: #fff;
			border-radius: 10px;
			padding: 20px;
			margin-bottom: 20px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			border-left: 4px solid #4A90E2;
		}
		.consultation-header {
			display: flex;
			justify-content: space-between;
			align-items: start;
			margin-bottom: 15px;
		}
		.patient-name {
			font-size: 20px;
			font-weight: 700;
			color: #333;
		}
		.consultation-details {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 15px;
			margin-bottom: 15px;
		}
		.detail-item {
			color: #666;
		}
		.detail-item strong {
			color: #333;
		}
		.info-box {
			background: #f0f7ff;
			border-left: 4px solid #4A90E2;
			padding: 15px;
			border-radius: 6px;
			margin-bottom: 20px;
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
	
	<div class="consultations-container">
		<div class="container">
			<div class="row">
				<div class="col-12">
					<a href="index.php" class="btn-retour">
						<i class="fa fa-arrow-left"></i> Retour au tableau de bord
					</a>
					<h1 style="color: #002939; margin-bottom: 20px;">
						<i class="fa fa-file-text"></i> Mes Consultations
					</h1>
					
					<div class="info-box">
						<strong>Service :</strong> <?php echo htmlspecialchars($specialisation); ?> | 
						<strong>Total :</strong> <?php echo count($mes_consultations); ?> consultation(s)
					</div>
					
					<?php if ($message): ?>
						<div class="alert alert-<?php echo $message_type; ?>">
							<?php echo htmlspecialchars($message); ?>
						</div>
					<?php endif; ?>
					
					<?php if (empty($mes_consultations)): ?>
						<div style="text-align: center; padding: 60px 20px; color: #666;">
							<i class="fa fa-file-text-o" style="font-size: 64px; color: #ddd; margin-bottom: 20px;"></i>
							<h3>Aucune consultation</h3>
							<p>Vous n'avez pas encore de consultations enregistrées.</p>
						</div>
					<?php else: ?>
						<?php foreach ($mes_consultations as $consultation): ?>
							<div class="consultation-card">
								<div class="consultation-header">
									<div class="patient-name">
										<i class="fa fa-user"></i> <?php echo htmlspecialchars(($consultation['Nom_patient'] ?? '') . ' ' . ($consultation['Prénom_patient'] ?? '')); ?>
									</div>
								</div>
								<div class="consultation-details">
									<?php if (isset($consultation['Date_consultation'])): ?>
										<div class="detail-item">
											<strong><i class="fa fa-calendar"></i> Date :</strong><br>
											<?php echo date('d/m/Y', strtotime($consultation['Date_consultation'])); ?>
										</div>
										<div class="detail-item">
											<strong><i class="fa fa-clock-o"></i> Heure :</strong><br>
											<?php echo date('H:i', strtotime($consultation['Date_consultation'])); ?>
										</div>
									<?php endif; ?>
									<?php if (isset($consultation['Matricule_patient']) && $consultation['Matricule_patient']): ?>
										<div class="detail-item">
											<strong><i class="fa fa-id-card"></i> Matricule :</strong><br>
											<?php echo htmlspecialchars($consultation['Matricule_patient']); ?>
										</div>
									<?php endif; ?>
									<?php if (isset($consultation['Num_carnet']) && $consultation['Num_carnet']): ?>
										<div class="detail-item">
											<strong><i class="fa fa-book"></i> Carnet :</strong><br>
											<?php echo htmlspecialchars($consultation['Num_carnet']); ?>
										</div>
									<?php endif; ?>
									<?php if (isset($consultation['Nom_med']) && isset($consultation['Prénom_med'])): ?>
										<div class="detail-item">
											<strong><i class="fa fa-user-md"></i> Médecin :</strong><br>
											Dr. <?php echo htmlspecialchars(($consultation['Prénom_med'] ?? '') . ' ' . ($consultation['Nom_med'] ?? '')); ?>
										</div>
									<?php endif; ?>
								</div>
								<?php if (isset($consultation['Motif_diagnostic']) && $consultation['Motif_diagnostic']): ?>
									<div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 6px;">
										<strong>Motif/Diagnostic :</strong><br>
										<?php echo nl2br(htmlspecialchars($consultation['Motif_diagnostic'])); ?>
									</div>
								<?php endif; ?>
								<?php if (isset($consultation['Note']) && $consultation['Note']): ?>
									<div style="margin-top: 10px; padding: 15px; background: #fff3cd; border-radius: 6px;">
										<strong>Notes :</strong><br>
										<?php echo nl2br(htmlspecialchars($consultation['Note'])); ?>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	
	<?php require_once '../partials/footer.php'; ?>
</div>
</body>
</html>
