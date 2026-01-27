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
    
    // Si Orange Money est sélectionné, rediriger vers le traitement Orange Money
    if ($methode === 'orange_money') {
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        
        // Validation complète pour Orange Money
        if (empty($montant) || $montant <= 0 || $montant < 0.01) {
            $message = "Le montant doit être supérieur à 0.00 GNF.";
            $message_type = "danger";
        } elseif (empty($id_patient)) {
            $message = "Veuillez sélectionner un patient.";
            $message_type = "danger";
        } elseif (empty($id_service)) {
            $message = "Veuillez sélectionner un service.";
            $message_type = "danger";
        } elseif (empty($customer_phone)) {
            $message = "Veuillez entrer le numéro de téléphone Orange Money du patient.";
            $message_type = "danger";
        } else {
            // Créer un formulaire caché et le soumettre automatiquement vers orange_process.php
            // On va utiliser une session pour passer les données
            $_SESSION['orange_payment_data'] = [
                'montant' => $montant,
                'id_patient' => $id_patient,
                'id_service' => $id_service,
                'customer_phone' => $customer_phone,
                'date_paiement' => $date_paiement
            ];
            header('Location: ../payment/orange_process.php');
            exit();
        }
    } else {
        // Validation stricte pour les autres méthodes
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
        try {
            // Générer un numéro de facture si nécessaire
            $id_facture = null;
            if ($statut === 'payé') {
                $id_facture = genererNumeroFacture();
            }
            
            // Créer le paiement avec id_service
            $pdo = bdd();
            
            // Vérifier quelles colonnes existent
            $check_id_service = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'id_service'");
            $has_id_service = $check_id_service->rowCount() > 0;
            
            $check_methode = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'Méthode_paiement'");
            $has_methode = $check_methode->rowCount() > 0;
            
            // Vérifier si la colonne id_facture existe
            $check_id_facture = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'id_facture'");
            $has_id_facture = $check_id_facture->rowCount() > 0;
            
            // Si la colonne id_facture n'existe pas, essayer de l'ajouter
            if (!$has_id_facture) {
                try {
                    $pdo->exec("ALTER TABLE PAIEMENT ADD COLUMN id_facture VARCHAR(50) UNIQUE NULL AFTER Méthode_paiement");
                    $has_id_facture = true;
                } catch (Exception $e) {
                    error_log("Erreur ajout colonne id_facture: " . $e->getMessage());
                    // Continuer sans id_facture si on ne peut pas l'ajouter
                }
            }
            
            // Si la colonne Méthode_paiement n'existe pas, essayer avec un nom sans accent
            if (!$has_methode) {
                $check_methode_alt = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'methode_paiement'");
                $has_methode_alt = $check_methode_alt->rowCount() > 0;
                
                if ($has_methode_alt) {
                    // Utiliser methode_paiement sans accent
                    if ($has_id_service) {
                        if ($has_id_facture) {
                            $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, id_service, methode_paiement, id_facture, Statut) 
                                    VALUES (?, ?, ?, NULL, ?, ?, ?, ?)";
                            $stmt = $pdo->prepare($sql);
                            $result = $stmt->execute([$montant, $date_paiement, $id_patient, $id_service, $methode, $id_facture, $statut]);
                        } else {
                            $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, id_service, methode_paiement, Statut) 
                                    VALUES (?, ?, ?, NULL, ?, ?, ?)";
                            $stmt = $pdo->prepare($sql);
                            $result = $stmt->execute([$montant, $date_paiement, $id_patient, $id_service, $methode, $statut]);
                        }
                    } else {
                        if ($has_id_facture) {
                            $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, methode_paiement, id_facture, Statut) 
                                    VALUES (?, ?, ?, NULL, ?, ?, ?)";
                            $stmt = $pdo->prepare($sql);
                            $result = $stmt->execute([$montant, $date_paiement, $id_patient, $methode, $id_facture, $statut]);
                        } else {
                            $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, methode_paiement, Statut) 
                                    VALUES (?, ?, ?, NULL, ?, ?)";
                            $stmt = $pdo->prepare($sql);
                            $result = $stmt->execute([$montant, $date_paiement, $id_patient, $methode, $statut]);
                        }
                    }
                } else {
                    // La colonne n'existe pas du tout, l'ajouter
                    try {
                        $pdo->exec("ALTER TABLE PAIEMENT ADD COLUMN Méthode_paiement ENUM('espèces', 'carte', 'chèque', 'virement') DEFAULT 'espèces'");
                        $has_methode = true;
                    } catch (Exception $e) {
                        error_log("Erreur ajout colonne Méthode_paiement: " . $e->getMessage());
                        throw new Exception("La colonne Méthode_paiement n'existe pas et n'a pas pu être créée. Veuillez contacter l'administrateur.");
                    }
                }
            }
            
            // Si on a la colonne Méthode_paiement (avec accent)
            if ($has_methode && !isset($result)) {
                if ($has_id_service) {
                    if ($has_id_facture) {
                        $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, id_service, Méthode_paiement, id_facture, Statut) 
                                VALUES (?, ?, ?, NULL, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $result = $stmt->execute([$montant, $date_paiement, $id_patient, $id_service, $methode, $id_facture, $statut]);
                    } else {
                        $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, id_service, Méthode_paiement, Statut) 
                                VALUES (?, ?, ?, NULL, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $result = $stmt->execute([$montant, $date_paiement, $id_patient, $id_service, $methode, $statut]);
                    }
                } else {
                    if ($has_id_facture) {
                        $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, Méthode_paiement, id_facture, Statut) 
                                VALUES (?, ?, ?, NULL, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $result = $stmt->execute([$montant, $date_paiement, $id_patient, $methode, $id_facture, $statut]);
                    } else {
                        $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, Méthode_paiement, Statut) 
                                VALUES (?, ?, ?, NULL, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $result = $stmt->execute([$montant, $date_paiement, $id_patient, $methode, $statut]);
                    }
                }
            }
            
            if ($result) {
                $id_paiement_creé = $pdo->lastInsertId();
                
                // Si le paiement est payé, générer automatiquement le reçu
                if ($statut === 'payé') {
                    try {
                        $chemin_reçu = genererReçu($id_paiement_creé);
                        $message = "Le paiement a été créé avec succès ! Le reçu a été généré et envoyé au patient.";
                        if ($id_facture) {
                            $message .= " Numéro de facture : <strong>" . htmlspecialchars($id_facture) . "</strong>";
                        }
                    } catch (Exception $e) {
                        error_log("Erreur génération reçu: " . $e->getMessage());
                        $message = "Le paiement a été créé avec succès !";
                        if ($id_facture) {
                            $message .= " Numéro de facture : <strong>" . htmlspecialchars($id_facture) . "</strong>";
                        }
                        $message .= " <small style='color: orange;'>(Note: Le reçu n'a pas pu être généré automatiquement. Vous pouvez le créer manuellement depuis la liste des paiements)</small>";
                    }
                } else {
                    $message = "Le paiement a été créé avec succès !";
                    if ($id_facture) {
                        $message .= " Numéro de facture : <strong>" . htmlspecialchars($id_facture) . "</strong>";
                    }
                    $message .= " <small style='color: #666;'>(Le reçu sera généré automatiquement lorsque le paiement sera marqué comme 'Payé')</small>";
                }
                
                $message_type = "success";
                $success = true;
                
                // Réinitialiser les données
                $_POST = [];
                
                // Recharger les services
                $services = getAllServices();
            } else {
                throw new Exception("Erreur lors de la création du paiement.");
            }
        } catch (PDOException $e) {
            error_log("Erreur création paiement: " . $e->getMessage());
            $message = "Erreur lors de la création du paiement : " . $e->getMessage();
            $message_type = "danger";
        } catch (Exception $e) {
            $message = $e->getMessage();
            $message_type = "danger";
        }
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
								<option value="orange_money" <?php echo (isset($_POST['methode']) && $_POST['methode'] == 'orange_money') ? 'selected' : ''; ?>>Orange Money</option>
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
					
					<!-- Champ pour le numéro de téléphone Orange Money (affiché uniquement si Orange Money est sélectionné) -->
					<div class="form-group" id="orange_phone_group" style="display: none;">
						<label>Numéro de téléphone Orange Money <span class="required">*</span></label>
						<input type="tel" name="customer_phone" id="customer_phone" 
							   placeholder="Ex: +224 612 34 56 78" 
							   value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>">
						<small style="color: #666;">Entrez le numéro de téléphone Orange Money du patient (format: +224 XX XX XX XX)</small>
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
					<div style="text-align: center; padding: 40px 0;">
						<i class="fa fa-check-circle" style="font-size: 64px; color: #28a745; margin-bottom: 20px;"></i>
						<h2 style="color: #28a745; margin-bottom: 20px;">Paiement créé avec succès !</h2>
						<a href="creer-paiement.php" class="btn-submit">
							<i class="fa fa-plus"></i> Créer un autre paiement
						</a>
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
	
	// Gérer l'affichage du champ téléphone Orange Money
	var methodeSelect = document.getElementById('methode_paiement');
	var orangePhoneGroup = document.getElementById('orange_phone_group');
	var customerPhoneInput = document.getElementById('customer_phone');
	
	if (methodeSelect && orangePhoneGroup && customerPhoneInput) {
		// Fonction pour afficher/masquer le champ téléphone
		function toggleOrangePhoneField() {
			if (methodeSelect.value === 'orange_money') {
				orangePhoneGroup.style.display = 'block';
				customerPhoneInput.setAttribute('required', 'required');
			} else {
				orangePhoneGroup.style.display = 'none';
				customerPhoneInput.removeAttribute('required');
				customerPhoneInput.value = '';
			}
		}
		
		// Vérifier au chargement de la page
		toggleOrangePhoneField();
		
		// Écouter les changements
		methodeSelect.addEventListener('change', toggleOrangePhoneField);
		
		// Validation spécifique pour Orange Money
		if (paiementForm) {
			paiementForm.addEventListener('submit', function(e) {
				if (methodeSelect.value === 'orange_money') {
					var phone = customerPhoneInput.value.trim();
					if (!phone) {
						e.preventDefault();
						alert('Veuillez entrer le numéro de téléphone Orange Money du patient.');
						customerPhoneInput.focus();
						return false;
					}
					// Valider le format du numéro (optionnel mais recommandé)
					var phoneRegex = /^(\+224|224|0)?[6-7]\d{8}$/;
					if (!phoneRegex.test(phone.replace(/\s/g, ''))) {
						if (!confirm('Le format du numéro de téléphone semble incorrect. Voulez-vous continuer quand même ?')) {
							e.preventDefault();
							customerPhoneInput.focus();
							return false;
						}
					}
				}
			});
		}
	}
});
</script>
</body>
</html>
