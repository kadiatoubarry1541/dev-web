<?php
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';

requireLogin('login.php');
requireMedecin('index.php');

$user_info = getUserInfo();
$id_med = $user_info['id_med'];
$specialisation = $user_info['specialisation'] ?? '';

$mes_ordonnances = [];
$message = '';
$message_type = '';

if ($id_med) {
    try {
        // Récupérer les ordonnances du médecin filtrées par son service
        $mes_ordonnances = getOrdonnancesByMedecin($id_med, $specialisation);
        if ($mes_ordonnances === false) {
            $mes_ordonnances = [];
        }
    } catch (Exception $e) {
        error_log("Erreur mes-ordonnances: " . $e->getMessage());
        $message = "Une erreur est survenue lors de la récupération des ordonnances.";
        $message_type = "danger";
        $mes_ordonnances = [];
    }
}

// Grouper les ordonnances par consultation
$ordonnances_grouped = [];
foreach ($mes_ordonnances as $ordo) {
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
	<title>Historique des Ordonnances - Espace Médecin</title>
	<link rel="stylesheet" type="text/css" href="../assets/css/plugins.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
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
			padding: 25px;
			margin-bottom: 25px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			border-left: 4px solid #4A90E2;
		}
		.ordonnance-header {
			border-bottom: 2px solid #f0f0f0;
			padding-bottom: 15px;
			margin-bottom: 20px;
		}
		.patient-name {
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
			padding: 15px;
			border-radius: 8px;
			margin-bottom: 10px;
			border-left: 3px solid #28a745;
		}
		.medicament-name {
			font-weight: 600;
			color: #333;
			font-size: 16px;
			margin-bottom: 8px;
		}
		.medicament-details {
			color: #666;
			font-size: 14px;
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
		.btn-print {
			background: #4A90E2;
			color: white;
			border: none;
			padding: 8px 15px;
			border-radius: 6px;
			cursor: pointer;
			font-size: 14px;
			margin-top: 10px;
			margin-right: 10px;
		}
		.btn-print:hover {
			background: #357ABD;
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
		.ordonnance-date {
			text-align: right;
			color: #666;
			font-size: 14px;
			margin-bottom: 15px;
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
	
	<div class="ordonnances-container">
		<div class="container">
			<div class="row">
				<div class="col-12">
					<a href="index.php" class="btn-retour">
						<i class="fa fa-arrow-left"></i> Retour au tableau de bord
					</a>
					<h1 style="color: #002939; margin-bottom: 20px;">
						<i class="fa fa-prescription"></i> Historique des Ordonnances
					</h1>
					
					<div class="info-box">
						<strong>Service :</strong> <?php echo htmlspecialchars($specialisation); ?> | 
						<strong>Total :</strong> <?php echo count($ordonnances_grouped); ?> ordonnance(s) | 
						<strong>Médicaments :</strong> <?php echo count($mes_ordonnances); ?>
					</div>
					
					<?php if ($message): ?>
						<div class="alert alert-<?php echo $message_type; ?>">
							<?php echo htmlspecialchars($message); ?>
						</div>
					<?php endif; ?>
					
					<?php if (empty($ordonnances_grouped)): ?>
						<div class="empty-state">
							<i class="fa fa-prescription" style="font-size: 64px; color: #ddd; margin-bottom: 20px;"></i>
							<h3>Aucune ordonnance</h3>
							<p>Vous n'avez pas encore créé d'ordonnance.</p>
							<a href="creer-ordonnance.php" class="site-button" style="margin-top: 20px;">
								<i class="fa fa-plus"></i> Créer une ordonnance
							</a>
						</div>
					<?php else: ?>
						<?php foreach ($ordonnances_grouped as $id_consultation => $group): 
							$consultation = $group['consultation'];
							$medicaments = $group['medicaments'];
						?>
							<div class="ordonnance-card" id="ordonnance-<?php echo $id_consultation; ?>">
								<div class="ordonnance-header">
									<div class="patient-name">
										<i class="fa fa-user"></i> 
										<?php echo htmlspecialchars(($consultation['Prénom_patient'] ?? '') . ' ' . ($consultation['Nom_patient'] ?? '')); ?>
									</div>
									<div class="consultation-info">
										<?php if (isset($consultation['Date_consultation'])): ?>
											<i class="fa fa-calendar"></i> Consultation du 
											<?php echo date('d/m/Y à H:i', strtotime($consultation['Date_consultation'])); ?>
										<?php endif; ?>
										<?php if (isset($consultation['Nom_med'])): ?>
											| <i class="fa fa-user-md"></i> Dr. 
											<?php echo htmlspecialchars($consultation['Prénom_med'] . ' ' . $consultation['Nom_med']); ?>
										<?php endif; ?>
										<?php if (isset($consultation['Matricule_patient'])): ?>
											| <i class="fa fa-id-card"></i> Matricule: 
											<?php echo htmlspecialchars($consultation['Matricule_patient']); ?>
										<?php endif; ?>
									</div>
									<?php if (isset($consultation['Motif_diagnostic']) && $consultation['Motif_diagnostic']): ?>
										<div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
											<strong>Motif/Diagnostic :</strong> 
											<?php echo nl2br(htmlspecialchars($consultation['Motif_diagnostic'])); ?>
										</div>
									<?php endif; ?>
								</div>
								
								<div class="ordonnance-date">
									<strong>Date d'émission :</strong> 
									<?php 
									if (isset($medicaments[0]['Date_émission'])) {
										echo date('d/m/Y', strtotime($medicaments[0]['Date_émission']));
									}
									?>
								</div>
								
								<h4 style="color: #4A90E2; margin-bottom: 15px;">
									<i class="fa fa-medkit"></i> Prescription
								</h4>
								
								<?php foreach ($medicaments as $medicament): ?>
									<div class="medicament-item">
										<div class="medicament-name">
											<i class="fa fa-capsules"></i> 
											<?php echo htmlspecialchars($medicament['Médicament']); ?>
										</div>
										<div class="medicament-details">
											<?php if (!empty($medicament['Dosage'])): ?>
												<div><strong>Dosage :</strong> <?php echo htmlspecialchars($medicament['Dosage']); ?></div>
											<?php endif; ?>
											<?php if (!empty($medicament['Durée_traitement'])): ?>
												<div><strong>Durée :</strong> <?php echo htmlspecialchars($medicament['Durée_traitement']); ?></div>
											<?php endif; ?>
											<?php if (!empty($medicament['Instructions'])): ?>
												<div><strong>Instructions :</strong> <?php echo htmlspecialchars($medicament['Instructions']); ?></div>
											<?php endif; ?>
										</div>
									</div>
								<?php endforeach; ?>
								
								<div style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
									<button type="button" class="btn-print" onclick="imprimerOrdonnance(<?php echo (int)$id_consultation; ?>)">
										<i class="fa fa-print"></i> Imprimer l'ordonnance (remettre au patient)
									</button>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	
	<?php require_once '../partials/footer.php'; ?>
</div>

<script src="../assets/js/jquery.min.js"></script>
<script>
function imprimerOrdonnance(idConsultation) {
    window.open('imprimer-ordonnance.php?id_consultation=' + idConsultation + '&auto=1', 'impression_ordonnance', 'width=800,height=700,scrollbars=yes,resizable=yes');
}

// Plus d'envoi d'ordonnance : uniquement l'impression et la remise au patient.
</script>
</body>
</html>
