<?php
/**
 * Créer un carnet de santé pour un patient (médecin)
 * Permet au médecin de créer un carnet, puis de l'imprimer et de le remettre à la main au patient.
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
$patients = [];

if ($id_med) {
    try {
        if ($specialisation) {
            $patients = getPatientsByMedecin($id_med, $specialisation);
        } else {
            $patients = getPatientsByMedecin($id_med, null);
        }
    } catch (Exception $e) {
        error_log("Erreur creer-carnet: " . $e->getMessage());
        $message = "Une erreur est survenue lors du chargement des patients.";
        $message_type = "danger";
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_carnet'])) {
    $id_patient = !empty($_POST['id_patient']) ? intval($_POST['id_patient']) : null;
    $libelle = trim($_POST['libelle'] ?? '');

    if (empty($id_patient)) {
        $message = "Veuillez sélectionner un patient.";
        $message_type = "danger";
    } elseif (empty($libelle)) {
        $message = "Veuillez saisir un libellé pour le carnet.";
        $message_type = "danger";
    } else {
        try {
            // Vérifier que le patient appartient au service du médecin
            $patient_trouve = false;
            foreach ($patients as $p) {
                if (isset($p['id_patient']) && (int)$p['id_patient'] === $id_patient) {
                    $patient_trouve = true;
                    break;
                }
            }
            if (!$patient_trouve) {
                $message = "Ce patient n'appartient pas à votre service.";
                $message_type = "danger";
            } else {
                creerCarnet($libelle, $id_patient);
                $carnets = getCarnetsByPatient($id_patient);
                $num_carnet = !empty($carnets) ? $carnets[0]['Num_carnet'] : null;
                if ($num_carnet) {
                    header('Location: voir-carnet.php?num_carnet=' . urlencode($num_carnet) . '&nouveau=1');
                    exit;
                }
                $message = "Carnet créé. Vous pouvez le consulter dans la liste des carnets du patient.";
                $message_type = "success";
            }
        } catch (Exception $e) {
            error_log("Erreur création carnet: " . $e->getMessage());
            $message = "Erreur lors de la création du carnet : " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

$libelle_defaut = "Carnet de santé - " . date('Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Créer un Carnet - Espace Médecin</title>
	<link rel="stylesheet" type="text/css" href="../assets/css/plugins.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<style>
		.carnet-container {
			padding: 40px 0;
			background: #f8f9fa;
			min-height: 100vh;
		}
		.carnet-card {
			background: #fff;
			border-radius: 10px;
			padding: 30px;
			margin-bottom: 20px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			border-left: 4px solid #4A90E2;
		}
		.form-group {
			margin-bottom: 20px;
		}
		.form-group label {
			display: block;
			margin-bottom: 8px;
			font-weight: 600;
			color: #333;
		}
		.form-control {
			width: 100%;
			padding: 10px 15px;
			border: 1px solid #ddd;
			border-radius: 6px;
			font-size: 14px;
		}
		.form-control:focus {
			outline: none;
			border-color: #4A90E2;
			box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
		}
		.btn-submit {
			background: #4A90E2;
			color: white;
			border: none;
			padding: 12px 30px;
			border-radius: 6px;
			cursor: pointer;
			font-weight: 600;
			font-size: 16px;
		}
		.btn-submit:hover {
			background: #357ABD;
		}
		.alert {
			padding: 15px;
			border-radius: 6px;
			margin-bottom: 20px;
		}
		.alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
		.alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
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
			color: white;
			text-decoration: none;
		}
	</style>
</head>
<body>
<div class="page-wraper">
	<?php require_once '../partials/entete.php'; ?>

	<div class="carnet-container">
		<div class="container">
			<div class="row">
				<div class="col-12">
					<a href="index.php" class="btn-retour">
						<i class="fa fa-arrow-left"></i> Retour au tableau de bord
					</a>
					<h1 style="color: #002939; margin-bottom: 20px;">
						<i class="fa fa-book"></i> Créer un Carnet de Santé
					</h1>

					<div class="info-box">
						<strong><i class="fa fa-info-circle"></i> Utilisation :</strong> Créez un carnet pour un patient, imprimez-le puis remettez-le à la main au patient. Le carnet sert à consigner son suivi médical.
					</div>

					<?php if ($message): ?>
						<div class="alert alert-<?php echo $message_type; ?>">
							<?php echo htmlspecialchars($message); ?>
						</div>
					<?php endif; ?>

					<form method="POST" action="">
						<div class="carnet-card">
							<h3 style="margin-bottom: 20px; color: #002939;">
								<i class="fa fa-user"></i> Choisir le patient
							</h3>

							<div class="form-group">
								<label for="id_patient">Patient <span style="color: #dc3545;">*</span></label>
								<select name="id_patient" id="id_patient" class="form-control" required>
									<option value="">-- Choisir un patient --</option>
									<?php if (!empty($patients)): ?>
										<?php foreach ($patients as $patient): ?>
											<option value="<?php echo (int)$patient['id_patient']; ?>">
												<?php
												echo htmlspecialchars(($patient['Prénom_patient'] ?? '') . ' ' . ($patient['Nom_patient'] ?? ''));
												if (!empty($patient['Matricule_patient'])) {
													echo ' (' . htmlspecialchars($patient['Matricule_patient']) . ')';
												}
												?>
											</option>
										<?php endforeach; ?>
									<?php else: ?>
										<option value="" disabled>Aucun patient disponible</option>
									<?php endif; ?>
								</select>
							</div>

							<div class="form-group">
								<label for="libelle">Libellé du carnet <span style="color: #dc3545;">*</span></label>
								<input type="text" name="libelle" id="libelle" class="form-control"
									   value="<?php echo htmlspecialchars($_POST['libelle'] ?? $libelle_defaut); ?>"
									   placeholder="Ex : Carnet de santé 2025" required maxlength="200">
								<small style="color: #666;">Ex. : Carnet principal, Carnet de suivi, etc.</small>
							</div>

							<button type="submit" name="creer_carnet" class="btn-submit">
								<i class="fa fa-plus"></i> Créer le carnet puis afficher pour impression
							</button>
							<a href="index.php" style="margin-left: 15px; color: #666; text-decoration: none;">Annuler</a>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>

	<?php require_once '../partials/footer.php'; ?>
</div>
</body>
</html>
