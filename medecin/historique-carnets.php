<?php
/**
 * Historique des carnets créés pour les patients du médecin
 * Liste tous les carnets des patients que ce médecin peut suivre.
 */

require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';

requireLogin('login.php');
requireMedecin('index.php');

$user_info = getUserInfo();
$id_med = $user_info['id_med'];
$specialisation = $user_info['specialisation'] ?? '';

$message = '';
$message_type = '';
$carnets = [];

if ($id_med) {
    try {
        // On récupère les patients que ce médecin peut suivre
        if ($specialisation) {
            $patients = getPatientsByMedecin($id_med, $specialisation);
        } else {
            $patients = getPatientsByMedecin($id_med, null);
        }

        $patients_index = [];
        foreach ($patients as $p) {
            if (!empty($p['id_patient'])) {
                $patients_index[(int)$p['id_patient']] = $p;
            }
        }

        // Pour chaque patient, on récupère ses carnets
        foreach ($patients_index as $id_patient => $patient) {
            $carnets_patient = getCarnetsByPatient($id_patient);
            foreach ($carnets_patient as $c) {
                $c['Nom_patient'] = $patient['Nom_patient'] ?? '';
                $c['Prénom_patient'] = $patient['Prénom_patient'] ?? '';
                $c['Matricule_patient'] = $patient['Matricule_patient'] ?? '';
                $carnets[] = $c;
            }
        }

        // Trier du plus récent au plus ancien
        usort($carnets, function ($a, $b) {
            $da = isset($a['Date_creation']) ? strtotime($a['Date_creation']) : 0;
            $db = isset($b['Date_creation']) ? strtotime($b['Date_creation']) : 0;
            return $db <=> $da;
        });
    } catch (Exception $e) {
        error_log("Erreur historique-carnets: " . $e->getMessage());
        $message = "Une erreur est survenue lors du chargement de l'historique des carnets.";
        $message_type = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Historique des carnets - Espace Médecin</title>
	<link rel="stylesheet" type="text/css" href="../assets/css/plugins.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<style>
		.carnets-container {
			padding: 40px 0;
			background: #f8f9fa;
			min-height: 100vh;
		}
		.carnet-row {
			background: #fff;
			border-radius: 10px;
			padding: 15px 20px;
			margin-bottom: 12px;
			box-shadow: 0 2px 6px rgba(0,0,0,0.05);
			display: flex;
			justify-content: space-between;
			align-items: center;
			border-left: 4px solid #4A90E2;
		}
		.carnet-main {
			flex: 1;
			margin-right: 15px;
		}
		.carnet-title {
			font-weight: 600;
			color: #002939;
			margin-bottom: 4px;
		}
		.carnet-meta {
			font-size: 13px;
			color: #666;
		}
		.carnet-meta span {
			margin-right: 15px;
		}
		.btn-small {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 8px 14px;
			border-radius: 6px;
			border: none;
			text-decoration: none;
			font-size: 13px;
			font-weight: 500;
			cursor: pointer;
			background: #4A90E2;
			color: white;
		}
		.btn-small:hover {
			background: #357ABD;
			color: white;
			text-decoration: none;
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
			color: white;
			text-decoration: none;
		}
		.info-box {
			background: #f0f7ff;
			border-left: 4px solid #4A90E2;
			padding: 15px;
			border-radius: 6px;
			margin-bottom: 20px;
		}
		.alert {
			padding: 15px;
			border-radius: 6px;
			margin-bottom: 20px;
		}
		.alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
		.alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
	</style>
</head>
<body>
<div class="page-wraper">
	<?php require_once '../partials/entete.php'; ?>

	<div class="carnets-container">
		<div class="container">
			<div class="row">
				<div class="col-12">
					<a href="index.php" class="btn-retour">
						<i class="fa fa-arrow-left"></i> Retour au tableau de bord
					</a>
					<h1 style="color: #002939; margin-bottom: 20px;">
						<i class="fa fa-book"></i> Historique des carnets
					</h1>

					<div class="info-box">
						<strong><i class="fa fa-info-circle"></i> Rappel :</strong>
						Cette page affiche tous les carnets créés pour les patients que vous pouvez suivre. Vous pouvez les rouvrir et les imprimer à nouveau si nécessaire.
					</div>

					<?php if ($message): ?>
						<div class="alert alert-<?php echo $message_type; ?>">
							<?php echo htmlspecialchars($message); ?>
						</div>
					<?php endif; ?>

					<?php if (empty($carnets)): ?>
						<div style="text-align: center; padding: 60px 20px; color: #666;">
							<i class="fa fa-book" style="font-size: 64px; color: #ddd; margin-bottom: 20px;"></i>
							<h3>Aucun carnet trouvé</h3>
							<p>Vous pourrez consulter ici tous les carnets que vous créez pour vos patients.</p>
						</div>
					<?php else: ?>
						<?php foreach ($carnets as $c): ?>
							<div class="carnet-row">
								<div class="carnet-main">
									<div class="carnet-title">
										<i class="fa fa-book"></i>
										<?php echo htmlspecialchars($c['Libellé'] ?? 'Carnet'); ?>
									</div>
									<div class="carnet-meta">
										<span><i class="fa fa-user"></i>
											<?php echo htmlspecialchars((($c['Prénom_patient'] ?? '') . ' ' . ($c['Nom_patient'] ?? ''))); ?>
										</span>
										<?php if (!empty($c['Matricule_patient'])): ?>
											<span><i class="fa fa-id-card"></i> <?php echo htmlspecialchars($c['Matricule_patient']); ?></span>
										<?php endif; ?>
										<?php if (!empty($c['Date_creation'])): ?>
											<span><i class="fa fa-calendar"></i>
												Créé le <?php echo date('d/m/Y', strtotime($c['Date_creation'])); ?>
											</span>
										<?php endif; ?>
										<?php if (!empty($c['Num_carnet'])): ?>
											<span><i class="fa fa-hashtag"></i> N° <?php echo (int)$c['Num_carnet']; ?></span>
										<?php endif; ?>
									</div>
								</div>
								<div>
									<?php if (!empty($c['Num_carnet'])): ?>
										<a href="voir-carnet.php?num_carnet=<?php echo (int)$c['Num_carnet']; ?>" class="btn-small">
											<i class="fa fa-print"></i> Ouvrir / Imprimer
										</a>
									<?php endif; ?>
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
</body>
</html>

