<?php
/**
 * Page de création de paiement
 * Accessible par admin et accueil
 */
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';

requireLogin('../login.php');
requirePermission('manage_paiements', '../index.php');

$user_info = getUserInfo();
$message = '';
$message_type = '';
$success = false;
$recu_data = null;

// Récupérer les patients et services
$patients = [];
$services = [];

try {
    $pdo = bdd();
    
    // Vérifier si la colonne id_service existe dans la table PAIEMENT
    try {
        $check_column = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'id_service'");
        $column_exists = $check_column->rowCount() > 0;
        
        if (!$column_exists) {
            // Si la colonne n'existe pas, l'ajouter
            try {
                $pdo->exec("ALTER TABLE PAIEMENT ADD COLUMN id_service INT NULL AFTER id_consultation");
                // Essayer d'ajouter la clé étrangère
                try {
                    $pdo->exec("ALTER TABLE PAIEMENT ADD FOREIGN KEY (id_service) REFERENCES SERVICES(id_service) ON DELETE SET NULL ON UPDATE CASCADE");
                } catch (Exception $e) {
                    // Clé étrangère peut déjà exister, ignorer
                }
            } catch (Exception $e) {
                error_log("Erreur lors de l'ajout de la colonne id_service dans PAIEMENT: " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        // Si la table n'existe pas ou autre erreur, continuer quand même
        error_log("Erreur vérification colonne id_service dans PAIEMENT: " . $e->getMessage());
    }
    
    // Tenter de simplifier la contrainte sur id_patient pour éviter les erreurs de clé étrangère
    try {
        // Essayer de supprimer une ancienne contrainte étrangère qui pointe vers une ancienne table patient
        $pdo->exec("ALTER TABLE PAIEMENT DROP FOREIGN KEY paiement_ibfk_1");
    } catch (Exception $e) {
        // Si la contrainte n'existe pas ou ne peut pas être supprimée, on ignore
        error_log("Info: impossible de supprimer la contrainte paiement_ibfk_1 (peut être déjà supprimée) : " . $e->getMessage());
    }
    
    $patients = getAllPatients();
    $services = getAllServices();
} catch (Exception $e) {
    error_log("Erreur récupération données: " . $e->getMessage());
    $message = "Erreur lors du chargement des données.";
    $message_type = "danger";
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_paiement'])) {
    $montant = floatval($_POST['montant'] ?? 0);
    $date_paiement = $_POST['date_paiement'] ?? date('Y-m-d H:i:s');
    $id_patient = !empty($_POST['id_patient']) ? intval($_POST['id_patient']) : null;
    $id_service = !empty($_POST['id_service']) ? intval($_POST['id_service']) : null;
    $methode = $_POST['methode'] ?? 'espèces';
    $statut = $_POST['statut'] ?? 'payé';
    
    // Validation du paiement
    if (empty($montant) || $montant <= 0 || $montant < 0.01) {
        $message = "Le montant doit être supérieur à 0.00 GNF. Un paiement avec un montant de 0.00 GNF ne peut pas être enregistré.";
        $message_type = "danger";
    } elseif ($montant == 0 && $statut === 'payé') {
        $message = "Un paiement avec un montant de 0.00 GNF ne peut pas avoir le statut 'Payé'. Veuillez modifier le montant (minimum 0.01 GNF) ou changer le statut à 'En attente' ou 'Annulé'.";
        $message_type = "danger";
    } elseif (empty($id_patient)) {
        $message = "Veuillez sélectionner un patient.";
        $message_type = "danger";
    } elseif (empty($id_service)) {
        $message = "Veuillez sélectionner un service.";
        $message_type = "danger";
    } else {
        // Ici on ne touche plus à la base de données.
        // On utilise uniquement les informations du formulaire
        // pour générer un reçu simple à imprimer.

        // Récupérer les infos du patient sélectionné
        $patient_info = null;
        foreach ($patients as $p) {
            if ($p['id_patient'] == $id_patient) {
                $patient_info = $p;
                break;
            }
        }

        // Récupérer les infos du service sélectionné
        $service_info = null;
        foreach ($services as $s) {
            if ($s['id_service'] == $id_service) {
                $service_info = $s;
                break;
            }
        }

        $recu_data = [
            'patient_nom' => $patient_info ? ($patient_info['Nom_patient'] . ' ' . $patient_info['Prénom_patient']) : '',
            'patient_matricule' => $patient_info['Matricule_patient'] ?? '',
            'service_nom' => $service_info['Nom_service'] ?? '',
            'service_tarif' => $service_info['Tarif'] ?? $montant,
            'montant' => $montant,
            'date_paiement' => $date_paiement,
            'methode' => $methode,
            'statut' => $statut,
        ];

        $message = "Le reçu a été généré. Vous pouvez l'imprimer ci-dessous et le remettre au patient.";
        $message_type = "success";
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>Créer un Paiement - MediCo.</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" type="text/css" href="../assets/css/plugins.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
	<link class="skin" rel="stylesheet" type="text/css" href="../assets/css/skin/skin-1.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/templete.min.css">
	<style>
		.paiement-container {
			padding: 40px 0;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			min-height: 100vh;
		}
		.form-card {
			background: #fff;
			border-radius: 12px;
			padding: 40px;
			margin: 0 auto;
			box-shadow: 0 10px 40px rgba(0,0,0,0.2);
			max-width: 1000px;
		}
		.page-header {
			text-align: center;
			margin-bottom: 40px;
			padding-bottom: 20px;
			border-bottom: 2px solid #e2e8f0;
		}
		.page-header h1 {
			color: #002939;
			font-size: 32px;
			font-weight: 700;
			margin-bottom: 10px;
		}
		.form-group {
			margin-bottom: 20px;
		}
		.form-group label {
			display: block;
			margin-bottom: 8px;
			font-weight: 600;
			color: #002939;
			font-size: 14px;
		}
		.form-group input,
		.form-group select {
			width: 100%;
			padding: 12px 15px;
			border: 1px solid #ddd;
			border-radius: 8px;
			font-size: 15px;
			transition: all 0.3s;
		}
		.form-group input:focus,
		.form-group select:focus {
			outline: none;
			border-color: #667eea;
			box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
		}
		.form-row {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 20px;
		}
		.alert {
			padding: 15px 20px;
			border-radius: 8px;
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
		.alert-warning {
			background: #fff3cd;
			border: 1px solid #ffeaa7;
			color: #856404;
		}
		.btn-submit {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 14px 40px;
			border: none;
			border-radius: 8px;
			font-size: 16px;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.3s;
			box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
		}
		.btn-submit:hover {
			transform: translateY(-2px);
			box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
		}
		.consultations-list {
			background: #f8f9fa;
			border-radius: 8px;
			padding: 20px;
			margin-top: 20px;
			max-height: 300px;
			overflow-y: auto;
		}
		.consultation-item {
			padding: 15px;
			background: white;
			border-radius: 8px;
			margin-bottom: 10px;
			border-left: 4px solid #667eea;
			cursor: pointer;
			transition: all 0.3s;
		}
		.consultation-item:hover {
			background: #e8f0fe;
			transform: translateX(5px);
		}
		.consultation-item.selected {
			background: #667eea;
			color: white;
		}
		.required {
			color: #e53e3e;
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
	
	<div class="paiement-container">
		<div class="container">
			<div class="form-card">
			<a href="<?php 
				$role = $user_info['role'] ?? 'patient';
				if ($role === 'admin') {
					echo '../admin/index.php';
				} elseif ($role === 'accueil') {
					echo '../accueil/index.php';
				} else {
					echo 'liste-paiements.php';
				}
			?>" class="btn-retour">
				<i class="fa fa-arrow-left"></i> Retour
			</a>
			<div class="page-header">
				<h1><i class="fa fa-money"></i> Créer un Paiement</h1>
					<p>Enregistrer un nouveau paiement pour un patient</p>
				</div>
				
				<?php if ($message): ?>
					<div class="alert alert-<?php echo $message_type; ?>">
						<?php echo $message; ?>
					</div>
				<?php endif; ?>
				
				<?php if (!$success): ?>
				<form method="post" action="" id="paiementForm">
					<div class="form-group">
						<label>Patient <span class="required">*</span></label>
						<select name="id_patient" id="id_patient" required>
							<option value="">Sélectionner un patient</option>
							<?php foreach ($patients as $patient): ?>
								<option value="<?php echo $patient['id_patient']; ?>" 
										<?php echo (isset($_POST['id_patient']) && $_POST['id_patient'] == $patient['id_patient']) ? 'selected' : ''; ?>>
									<?php echo htmlspecialchars($patient['Nom_patient'] . ' ' . $patient['Prénom_patient'] . ' (' . $patient['Matricule_patient'] . ')'); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					
					<div class="form-group">
						<label>Service <span class="required">*</span></label>
						<select name="id_service" id="id_service" required>
							<option value="">Sélectionner un service</option>
							<?php foreach ($services as $service): ?>
								<option value="<?php echo $service['id_service']; ?>" 
										<?php echo (isset($_POST['id_service']) && $_POST['id_service'] == $service['id_service']) ? 'selected' : ''; ?>>
									<?php echo htmlspecialchars($service['Nom_service'] . ' - ' . number_format($service['Tarif'], 0, ',', ' ') . ' GNF'); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<small style="color: #666;">Sélectionnez le service médical concerné par ce paiement</small>
					</div>
					
					<div class="form-row">
						<div class="form-group">
							<label>Montant (GNF) <span class="required">*</span></label>
							<input type="number" name="montant" step="0.01" min="0.01" required 
								   value="<?php echo htmlspecialchars($_POST['montant'] ?? ''); ?>" 
								   placeholder="0.00">
							<small style="color: #666;">Le montant minimum est de 0.01 GNF</small>
						</div>
						
						<div class="form-group">
							<label>Date de paiement <span class="required">*</span></label>
							<input type="datetime-local" name="date_paiement" required 
								   value="<?php echo htmlspecialchars($_POST['date_paiement'] ?? date('Y-m-d\TH:i')); ?>">
						</div>
					</div>
					
					<div class="form-row">
						<div class="form-group">
							<label>Méthode de paiement <span class="required">*</span></label>
                            <select name="methode" id="methode_paiement" required>
                                <option value="espèces" <?php echo (isset($_POST['methode']) && $_POST['methode'] == 'espèces') ? 'selected' : 'selected'; ?>>Espèces</option>
								<option value="carte" <?php echo (isset($_POST['methode']) && $_POST['methode'] == 'carte') ? 'selected' : ''; ?>>Carte bancaire</option>
								<option value="chèque" <?php echo (isset($_POST['methode']) && $_POST['methode'] == 'chèque') ? 'selected' : ''; ?>>Chèque</option>
								<option value="virement" <?php echo (isset($_POST['methode']) && $_POST['methode'] == 'virement') ? 'selected' : ''; ?>>Virement</option>
							</select>
						</div>
						
						<div class="form-group">
							<label>Statut <span class="required">*</span></label>
							<select name="statut" required>
								<option value="payé" <?php echo (isset($_POST['statut']) && $_POST['statut'] == 'payé') ? 'selected' : 'selected'; ?>>Payé</option>
								<option value="en_attente" <?php echo (isset($_POST['statut']) && $_POST['statut'] == 'en_attente') ? 'selected' : ''; ?>>En attente</option>
								<option value="annulé" <?php echo (isset($_POST['statut']) && $_POST['statut'] == 'annulé') ? 'selected' : ''; ?>>Annulé</option>
							</select>
						</div>
					</div>
					
					<div style="margin-top: 30px; text-align: center;">
						<button type="submit" name="creer_paiement" class="btn-submit">
							<i class="fa fa-save"></i> Enregistrer le Paiement
						</button>
						<a href="liste-paiements.php" class="btn-submit" style="background: #6c757d; text-decoration: none; display: inline-block; margin-left: 10px;">
							<i class="fa fa-list"></i> Voir les Paiements
						</a>
					</div>
				</form>
				<?php else: ?>
					<div style="padding: 20px 0;">
						<div style="text-align: center; margin-bottom: 30px;">
							<i class="fa fa-check-circle" style="font-size: 64px; color: #28a745; margin-bottom: 20px;"></i>
							<h2 style="color: #28a745; margin-bottom: 10px;">Reçu prêt à être imprimé</h2>
							<p style="color: #555;">Utilisez le bouton ci-dessous pour imprimer le reçu et le remettre au patient.</p>
						</div>

						<div id="recu-print" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 30px; max-width: 800px; margin: 0 auto;">
							<h3 style="text-align: center; margin-bottom: 20px;">Reçu de Paiement</h3>
							<p><strong>Date :</strong> <?php echo isset($recu_data['date_paiement']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($recu_data['date_paiement']))) : ''; ?></p>
							<hr style="margin: 15px 0;">
							<p><strong>Patient :</strong> <?php echo htmlspecialchars($recu_data['patient_nom'] ?? ''); ?></p>
							<?php if (!empty($recu_data['patient_matricule'])): ?>
								<p><strong>Matricule :</strong> <?php echo htmlspecialchars($recu_data['patient_matricule']); ?></p>
							<?php endif; ?>
							<hr style="margin: 15px 0;">
							<p><strong>Service :</strong> <?php echo htmlspecialchars($recu_data['service_nom'] ?? ''); ?></p>
							<p><strong>Montant :</strong> <?php echo number_format($recu_data['montant'] ?? 0, 0, ',', ' '); ?> GNF</p>
							<p><strong>Méthode de paiement :</strong> <?php echo htmlspecialchars($recu_data['methode'] ?? ''); ?></p>
							<p><strong>Statut :</strong> <?php echo htmlspecialchars(ucfirst($recu_data['statut'] ?? '')); ?></p>
							<hr style="margin: 20px 0;">
							<p style="margin-top: 40px;"><strong>Signature du caissier :</strong> _____________________________</p>
						</div>

						<div style="text-align: center; margin-top: 30px;">
							<button type="button" class="btn-submit" onclick="imprimerRecu()">
								<i class="fa fa-print"></i> Imprimer le reçu
							</button>
							<a href="creer-paiement.php" class="btn-submit" style="background: #6c757d; text-decoration: none; display: inline-block; margin-left: 10px;">
								<i class="fa fa-plus"></i> Créer un autre paiement
							</a>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	
	<?php require_once '../partials/footer.php'; ?>
</div>

<script src="../assets/js/jquery.min.js"></script>
<script>
// Script pour mettre à jour automatiquement le montant selon le service sélectionné
	document.addEventListener('DOMContentLoaded', function() {
	var serviceSelect = document.getElementById('id_service');
	var montantInput = document.querySelector('input[name="montant"]');
	var statutSelect = document.querySelector('select[name="statut"]');
	var paiementForm = document.getElementById('paiementForm');
	
	// Récupérer les tarifs des services depuis le serveur
	var servicesData = <?php 
		$services_data = [];
		foreach ($services as $service) {
			$services_data[$service['id_service']] = floatval($service['Tarif']);
		}
		echo json_encode($services_data);
	?>;
	
	if (serviceSelect && montantInput) {
		serviceSelect.addEventListener('change', function() {
			var selectedServiceId = this.value;
			if (selectedServiceId && servicesData[selectedServiceId]) {
				var tarif = servicesData[selectedServiceId];
				montantInput.value = tarif.toFixed(2);
				// Si le montant est 0, changer automatiquement le statut
				if (tarif == 0 && statutSelect) {
					statutSelect.value = 'en_attente';
					alert('Attention : Le montant est de 0.00 GNF. Le statut a été automatiquement changé à "En attente".');
				}
			} else {
				montantInput.value = '';
			}
		});
	}
	
	// Validation côté client : empêcher la soumission si montant = 0 et statut = payé
	if (paiementForm && montantInput && statutSelect) {
		paiementForm.addEventListener('submit', function(e) {
			var montant = parseFloat(montantInput.value) || 0;
			var statut = statutSelect.value;
			
			if (montant <= 0) {
				e.preventDefault();
				alert('Erreur : Le montant doit être supérieur à 0.00 GNF. Un paiement ne peut pas être enregistré avec un montant de 0.');
				montantInput.focus();
				return false;
			}
			
			if (montant == 0 && statut === 'payé') {
				e.preventDefault();
				alert('Erreur : Un paiement avec un montant de 0.00 GNF ne peut pas avoir le statut "Payé". Veuillez modifier le montant ou changer le statut à "En attente" ou "Annulé".');
				statutSelect.focus();
				return false;
			}
		});
		
		// Vérifier en temps réel si le montant change
		montantInput.addEventListener('change', function() {
			var montant = parseFloat(this.value) || 0;
			if (montant == 0 && statutSelect && statutSelect.value === 'payé') {
				statutSelect.value = 'en_attente';
				alert('Attention : Le montant est de 0.00 GNF. Le statut a été automatiquement changé à "En attente".');
			}
		});
		
		// Vérifier en temps réel si le statut change
		statutSelect.addEventListener('change', function() {
			var montant = parseFloat(montantInput.value) || 0;
			if (montant == 0 && this.value === 'payé') {
				this.value = 'en_attente';
				alert('Attention : Un paiement avec un montant de 0.00 GNF ne peut pas avoir le statut "Payé". Le statut a été automatiquement changé à "En attente".');
			}
		});
	}
});

// Fonction pour imprimer uniquement le reçu
function imprimerRecu() {
	var printContents = document.getElementById('recu-print').innerHTML;
	var win = window.open('', '_blank');
	win.document.open();
	win.document.write('<html><head><title>Reçu de paiement</title>');
	win.document.write('<style>body{font-family:Arial,sans-serif;padding:40px;background:#f5f5f5;} .receipt-container{max-width:800px;margin:0 auto;background:#fff;padding:40px;border:1px solid #e2e8f0;border-radius:8px;}</style>');
	win.document.write('</head><body><div class="receipt-container">');
	win.document.write(printContents);
	win.document.write('</div><script>window.onload=function(){window.print();};<\/script></body></html>');
	win.document.close();
}
</script>
</body>
</html>
