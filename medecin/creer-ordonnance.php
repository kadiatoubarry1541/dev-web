<?php
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';
require_once '../config/types_consultations.php';

requireLogin('login.php');
requireMedecin('index.php');

$user_info = getUserInfo();
$id_med = $user_info['id_med'];
$specialisation = $user_info['specialisation'] ?? '';

$message = '';
$message_type = '';
$consultations = [];
$patients = [];
$types_consultations = [];
$patient_par_matricule = null;  // Patient trouvé par recherche matricule (élément clé)
$matricule_saisi = '';           // Matricule saisi pour affichage/formulaire
$ordonnance_created_id_consultation = null;  // Après création : id pour Imprimer / Envoyer

// Recherche par matricule (GET ou POST recherche) — le matricule est l'élément clé d'identification
$recherche_matricule = trim($_GET['matricule'] ?? $_POST['matricule_recherche'] ?? '');
$est_post_creation = ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['creer_ordonnance']));
if (!empty($recherche_matricule) && !$est_post_creation) {
    $patient_par_matricule = trouverPatientParMatriculeTouteBase($recherche_matricule);
    $matricule_saisi = $recherche_matricule;
    if ($patient_par_matricule) {
        $message = "Patient identifié par matricule : " . htmlspecialchars(($patient_par_matricule['Prénom_patient'] ?? '') . ' ' . ($patient_par_matricule['Nom_patient'] ?? '')) . " (" . htmlspecialchars($patient_par_matricule['Matricule_patient'] ?? $recherche_matricule) . ").";
        $message_type = "success";
    } else {
        $message = "Aucun patient trouvé avec le matricule « " . htmlspecialchars($recherche_matricule) . " ». Vérifiez le matricule ou choisissez un patient dans la liste.";
        $message_type = "warning";
    }
}

// Récupérer les consultations et patients du service
if ($id_med) {
    try {
        // Récupérer les consultations filtrées par service
        $consultations = getConsultationsByMedecin($id_med, $specialisation);
        
        // S'assurer que toutes les consultations ont le champ Nom_service
        foreach ($consultations as &$consultation) {
            if (!isset($consultation['Nom_service']) && isset($consultation['Spécialisation_med'])) {
                $consultation['Nom_service'] = $consultation['Spécialisation_med'];
            }
        }
        unset($consultation); // Libérer la référence
        
        // Si un patient est déjà identifié par matricule, ajouter ses consultations au dropdown
        if ($patient_par_matricule && !empty($patient_par_matricule['id_patient']) && $specialisation) {
            $consultations_patient = getConsultationsByPatientAndService((int)$patient_par_matricule['id_patient'], $specialisation);
            $ids_vus = array_column($consultations, 'id_consultation');
            foreach ($consultations_patient as $cp) {
                if (!in_array($cp['id_consultation'] ?? null, $ids_vus, true)) {
                    if (!isset($cp['Nom_service']) && isset($cp['Spécialisation_med'])) {
                        $cp['Nom_service'] = $cp['Spécialisation_med'];
                    }
                    $consultations[] = $cp;
                    $ids_vus[] = $cp['id_consultation'];
                }
            }
        }
        
        if ($specialisation) {
            $patients = getPatientsByMedecin($id_med, $specialisation);
            // Récupérer les types de consultations pour ce service
            $types_consultations = getTypesConsultationsParService($specialisation);
        } else {
            // Si pas de spécialisation, essayer de charger les patients quand même
            $patients = getPatientsByMedecin($id_med, null);
        }
    } catch (Exception $e) {
        error_log("Erreur creer-ordonnance: " . $e->getMessage());
        $message = "Une erreur est survenue lors du chargement des données.";
        $message_type = "danger";
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_ordonnance'])) {
    $id_consultation_input = !empty($_POST['id_consultation']) ? $_POST['id_consultation'] : null;
    $id_patient_input = !empty($_POST['id_patient']) ? $_POST['id_patient'] : null;
    $matricule_patient = trim($_POST['matricule_patient'] ?? '');
    $medicaments = $_POST['medicament'] ?? [];
    $dosages = $_POST['dosage'] ?? [];
    $durees = $_POST['duree'] ?? [];
    $instructions = $_POST['instructions'] ?? [];
    
    // Identification par matricule (élément clé) : si fourni, résoudre l'id_patient
    if (!empty($matricule_patient)) {
        $patient_par_matricule = trouverPatientParMatriculeTouteBase($matricule_patient);
        if ($patient_par_matricule && !empty($patient_par_matricule['id_patient'])) {
            $id_patient_input = $patient_par_matricule['id_patient'];
        }
    }
    
    // Vérifier si c'est un type de consultation ou un ID de consultation existante
    $id_consultation = null;
    if (!empty($id_consultation_input)) {
        if (strpos($id_consultation_input, 'type_') === 0) {
            // C'est un type de consultation, pas une consultation existante
            // Si un patient est sélectionné, on créera une consultation
            if (empty($id_patient_input)) {
                $message = "Veuillez sélectionner un patient ou saisir son matricule pour créer une ordonnance.";
                $message_type = "danger";
            }
        } else {
            // C'est un ID de consultation existante
            $id_consultation = intval($id_consultation_input);
        }
    }
    
    // Si pas de consultation mais un patient est sélectionné (ou identifié par matricule), créer une consultation
    if (empty($id_consultation) && !empty($id_patient_input)) {
        try {
            $pdo = bdd();
            $id_patient = intval($id_patient_input);
            $identifie_par_matricule = !empty($matricule_patient) && $patient_par_matricule && (int)$patient_par_matricule['id_patient'] === $id_patient;
            
            // Vérifier que le patient existe ; si identifié par matricule (élément clé), on autorise sans exiger le lien service
            $patient_check = null;
            if ($identifie_par_matricule && $patient_par_matricule) {
                $patient_check = $patient_par_matricule;
            }
            if (!$patient_check) {
                $sql_check = "SELECT DISTINCT p.* 
                         FROM PATIENTS p
                         LEFT JOIN RENDEZ_VOUS r ON p.id_patient = r.id_patient
                         LEFT JOIN SERVICES s ON r.id_service = s.id_service
                         LEFT JOIN CONSULTATION c ON p.id_patient = c.id_patient
                         LEFT JOIN MEDECINS m ON c.id_med = m.id_med
                         WHERE p.id_patient = ? 
                         AND (s.Nom_service = ? OR m.Spécialisation_med = ?)
                         LIMIT 1";
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_check->execute([$id_patient, $specialisation, $specialisation]);
                $patient_check = $stmt_check->fetch();
            }
            
            if (!$patient_check) {
                $message = $identifie_par_matricule
                    ? "Patient introuvable pour le matricule saisi."
                    : "Le patient sélectionné n'a pas de lien avec votre service. Utilisez le matricule pour l'identifier, ou choisissez un patient de la liste.";
                $message_type = "danger";
            } else {
                // Obtenir ou créer un carnet pour le patient
                $carnets = getCarnetsByPatient($id_patient);
                $num_carnet = null;
                
                if (!empty($carnets)) {
                    $num_carnet = $carnets[0]['Num_carnet'];
                } else {
                    // Créer un carnet par défaut
                    $libelle_carnet = "Carnet principal - " . date('Y');
                    creerCarnet($libelle_carnet, $id_patient);
                    $carnets = getCarnetsByPatient($id_patient);
                    if (!empty($carnets)) {
                        $num_carnet = $carnets[0]['Num_carnet'];
                    }
                }
                
                if ($num_carnet) {
                    // Créer une consultation pour ce patient
                    $date_consultation = date('Y-m-d H:i:s');
                    $motif = "Ordonnance médicale - " . $specialisation;
                    $note = "Consultation créée automatiquement lors de la création d'une ordonnance.";
                    
                    $sql_consultation = "INSERT INTO CONSULTATION (Date_consultation, Motif_diagnostic, Note, id_patient, id_med, Num_carnet, Statut) 
                                         VALUES (?, ?, ?, ?, ?, ?, 'terminée')";
                    $stmt_consultation = $pdo->prepare($sql_consultation);
                    $stmt_consultation->execute([$date_consultation, $motif, $note, $id_patient, $id_med, $num_carnet]);
                    $id_consultation = $pdo->lastInsertId();
                } else {
                    $message = "Impossible de créer un carnet pour le patient. Veuillez contacter l'administrateur.";
                    $message_type = "danger";
                }
            }
        } catch (Exception $e) {
            error_log("Erreur création consultation automatique: " . $e->getMessage());
            $message = "Une erreur est survenue lors de la création de la consultation : " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    if (empty($id_consultation)) {
        if (empty($message)) {
            $message = !empty($matricule_patient)
                ? "Aucun patient trouvé pour le matricule « " . htmlspecialchars($matricule_patient) . " ». Vérifiez le matricule ou sélectionnez un patient dans la liste."
                : "Veuillez saisir le matricule du patient (élément clé) ou sélectionner un patient pour créer une ordonnance.";
            $message_type = "danger";
        }
    } elseif (empty($medicaments) || empty(array_filter($medicaments))) {
        $message = "Veuillez ajouter au moins un médicament.";
        $message_type = "danger";
    } else {
        try {
            $pdo = bdd();
            $date_emission = date('Y-m-d');
            $success_count = 0;
            $errors = [];
            
            // Créer une ordonnance pour chaque médicament
            foreach ($medicaments as $index => $medicament) {
                if (!empty(trim($medicament))) {
                    $dosage = $dosages[$index] ?? '';
                    $duree = $durees[$index] ?? '';
                    $instruction = $instructions[$index] ?? '';
                    
                    try {
                        creerOrdonnance(
                            trim($medicament),
                            trim($dosage),
                            $date_emission,
                            $id_consultation,
                            trim($duree) ?: null,
                            trim($instruction) ?: null
                        );
                        $success_count++;
                    } catch (Exception $e) {
                        $errors[] = "Erreur pour " . htmlspecialchars($medicament) . ": " . $e->getMessage();
                    }
                }
            }
            
            if ($success_count > 0) {
                $ordonnance_created_id_consultation = $id_consultation;
                // Récupérer les informations du patient pour le message
                $patient_info = null;
                try {
                    $pdo = bdd();
                    $sql_patient = "SELECT p.Nom_patient, p.Prénom_patient, p.id_patient, p.Matricule_patient
                                   FROM CONSULTATION c 
                                   JOIN PATIENTS p ON c.id_patient = p.id_patient 
                                   WHERE c.id_consultation = ?";
                    $stmt_patient = $pdo->prepare($sql_patient);
                    $stmt_patient->execute([$id_consultation]);
                    $patient_info = $stmt_patient->fetch();
                } catch (Exception $e) {
                    error_log("Erreur récupération patient: " . $e->getMessage());
                }
                
                $message = "Ordonnance créée avec succès ! " . $success_count . " médicament(s) ajouté(s).";
                if ($patient_info) {
                    $message .= " Patient : " . htmlspecialchars($patient_info['Prénom_patient'] . ' ' . $patient_info['Nom_patient']) . " (matricule " . htmlspecialchars($patient_info['Matricule_patient'] ?? '') . "). Vous pouvez imprimer l'ordonnance ou l'envoyer au patient.";
                } else {
                    $message .= " Vous pouvez imprimer l'ordonnance ou l'envoyer au patient.";
                }
                $message_type = "success";
                // Réinitialiser le formulaire
                $_POST = [];
            } else {
                $message = "Aucune ordonnance n'a pu être créée. " . implode(" ", $errors);
                $message_type = "danger";
            }
        } catch (Exception $e) {
            error_log("Erreur création ordonnance: " . $e->getMessage());
            $message = "Une erreur est survenue lors de la création de l'ordonnance.";
            $message_type = "danger";
        }
    }
}

// Liste de médicaments courants avec suggestions
$medicaments_courants = [
    'Analgésiques' => [
        'Paracétamol 500mg',
        'Paracétamol 1000mg',
        'Ibuprofène 200mg',
        'Ibuprofène 400mg',
        'Aspirine 100mg',
        'Aspirine 500mg',
        'Diclofénac 50mg',
        'Tramadol 50mg'
    ],
    'Antibiotiques' => [
        'Amoxicilline 500mg',
        'Amoxicilline 1000mg',
        'Ciprofloxacine 500mg',
        'Azithromycine 250mg',
        'Azithromycine 500mg',
        'Céfalexine 500mg',
        'Doxycycline 100mg',
        'Métronidazole 500mg'
    ],
    'Anti-inflammatoires' => [
        'Diclofénac 50mg',
        'Ibuprofène 400mg',
        'Naproxène 250mg',
        'Piroxicam 20mg',
        'Méloxicam 15mg'
    ],
    'Antihistaminiques' => [
        'Cétirizine 10mg',
        'Loratadine 10mg',
        'Desloratadine 5mg',
        'Fexofénadine 120mg',
        'Diphenhydramine 25mg'
    ],
    'Vitamines et Suppléments' => [
        'Vitamine D3 1000 UI',
        'Vitamine C 500mg',
        'Fer 65mg',
        'Calcium 500mg',
        'Multivitamines',
        'Magnésium 400mg'
    ],
    'Gastro-intestinaux' => [
        'Oméprazole 20mg',
        'Pantoprazole 40mg',
        'Dompéridone 10mg',
        'Métoclopramide 10mg',
        'Lactulose 10g',
        'Bisacodyl 5mg'
    ],
    'Cardiovasculaires' => [
        'Amlodipine 5mg',
        'Atenolol 50mg',
        'Lisinopril 10mg',
        'Furosémide 40mg',
        'Atorvastatine 20mg'
    ],
    'Respiratoires' => [
        'Salbutamol 100mcg',
        'Budesonide 200mcg',
        'Montélukast 10mg',
        'Ambroxol 30mg',
        'Guaifénésine 200mg'
    ],
    'Dermatologiques' => [
        'Hydrocortisone 1%',
        'Bétaméthasone 0.1%',
        'Clotrimazole 1%',
        'Mupirocine 2%',
        'Acide salicylique 5%'
    ],
    'Autres' => [
        'Métformine 500mg',
        'Glibenclamide 5mg',
        'Lévothyroxine 50mcg',
        'Warfarine 5mg',
        'Allopurinol 100mg'
    ]
];

// Dosages courants
$dosages_courants = [
    '1 comprimé matin et soir',
    '1 comprimé 3 fois par jour',
    '1 comprimé 2 fois par jour',
    '1 comprimé par jour',
    '1 comprimé le soir',
    '1 comprimé le matin',
    '2 comprimés 2 fois par jour',
    '1 comprimé avant les repas',
    '1 comprimé après les repas',
    '1 comprimé au coucher',
    '1/2 comprimé 2 fois par jour',
    '1 comprimé en cas de besoin',
    '1 comprimé toutes les 8 heures',
    '1 comprimé toutes les 12 heures'
];

// Durées courantes
$durees_courantes = [
    '3 jours',
    '5 jours',
    '7 jours',
    '10 jours',
    '14 jours',
    '21 jours',
    '1 mois',
    '2 mois',
    '3 mois',
    'En continu',
    'Selon besoin'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Créer une Ordonnance - Espace Médecin</title>
	<link rel="stylesheet" type="text/css" href="../assets/css/plugins.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<style>
		.ordonnance-container {
			padding: 40px 0;
			background: #f8f9fa;
			min-height: 100vh;
		}
		.ordonnance-card {
			background: #fff;
			border-radius: 10px;
			padding: 30px;
			margin-bottom: 20px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
		.medicament-item {
			background: #f8f9fa;
			padding: 20px;
			border-radius: 8px;
			margin-bottom: 15px;
			border-left: 4px solid #4A90E2;
		}
		.btn-add-medicament {
			background: #28a745;
			color: white;
			border: none;
			padding: 10px 20px;
			border-radius: 6px;
			cursor: pointer;
			font-weight: 600;
			margin-top: 10px;
		}
		.btn-add-medicament:hover {
			background: #218838;
		}
		.btn-remove {
			background: #dc3545;
			color: white;
			border: none;
			padding: 5px 10px;
			border-radius: 4px;
			cursor: pointer;
			font-size: 12px;
		}
		.btn-remove:hover {
			background: #c82333;
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
		.btn-envoyer-ord {
			background: #28a745;
			color: white;
			border: none;
			padding: 12px 24px;
			border-radius: 6px;
			cursor: pointer;
			font-weight: 600;
			font-size: 14px;
		}
		.btn-envoyer-ord:hover {
			background: #218838;
		}
		.medicament-suggestions {
			background: #fff;
			border: 1px solid #ddd;
			border-radius: 6px;
			max-height: 200px;
			overflow-y: auto;
			display: none;
			position: absolute;
			z-index: 1000;
			width: 100%;
			box-shadow: 0 4px 6px rgba(0,0,0,0.1);
		}
		.suggestion-item {
			padding: 10px 15px;
			cursor: pointer;
			border-bottom: 1px solid #eee;
		}
		.suggestion-item:hover {
			background: #f0f7ff;
		}
		.suggestion-category {
			font-weight: 600;
			color: #4A90E2;
			padding: 8px 15px;
			background: #f0f7ff;
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
		.alert-warning {
			background: #fff3cd;
			border: 1px solid #ffc107;
			color: #856404;
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
	
	<div class="ordonnance-container">
		<div class="container">
			<div class="row">
				<div class="col-12">
					<a href="index.php" class="btn-retour">
						<i class="fa fa-arrow-left"></i> Retour au tableau de bord
					</a>
					<h1 style="color: #002939; margin-bottom: 20px;">
						<i class="fa fa-prescription"></i> Créer une Ordonnance
					</h1>
					
					<div class="info-box">
						<strong>Service :</strong> <?php echo htmlspecialchars($specialisation); ?><br>
						<small>Identifiez le patient par son <strong>matricule</strong> (élément clé) ou choisissez-le dans la liste. Vous pourrez ensuite imprimer l'ordonnance ou l'envoyer au patient.</small>
					</div>
					
					<?php if ($message): ?>
						<div class="alert alert-<?php echo $message_type; ?>">
							<?php echo htmlspecialchars($message); ?>
							<?php if ($ordonnance_created_id_consultation): ?>
								<div style="margin-top: 16px; display: flex; gap: 12px; flex-wrap: wrap;">
									<button type="button" class="btn-submit" onclick="window.open('imprimer-ordonnance.php?id_consultation=<?php echo (int)$ordonnance_created_id_consultation; ?>&auto=1', 'impression_ordonnance', 'width=800,height=700,scrollbars=yes,resizable=yes');">
										<i class="fa fa-print"></i> Imprimer l'ordonnance
									</button>
									<?php if (hasPermission('send_ordonnances')): ?>
										<button type="button" class="btn-envoyer-ord" data-id-consultation="<?php echo (int)$ordonnance_created_id_consultation; ?>">
											<i class="fa fa-paper-plane"></i> Envoyer au patient
										</button>
									<?php endif; ?>
									<a href="mes-ordonnances.php" class="btn-retour" style="margin-bottom: 0;">
										<i class="fa fa-list"></i> Voir mes ordonnances
									</a>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					
					<!-- Recherche par matricule (élément clé d'identification du patient) -->
					<div class="ordonnance-card" style="border-left: 4px solid #28a745;">
						<h3 style="margin-bottom: 16px; color: #002939;">
							<i class="fa fa-id-card"></i> Identifier le patient par matricule
						</h3>
						<form method="get" action="" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
							<div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
								<label for="matricule_recherche">Matricule du patient (élément clé)</label>
								<input type="text" name="matricule" id="matricule_recherche" class="form-control" 
									   placeholder="Ex: PAT202501234567" 
									   value="<?php echo htmlspecialchars($matricule_saisi); ?>">
							</div>
							<button type="submit" class="btn-submit" style="padding: 10px 20px;">
								<i class="fa fa-search"></i> Rechercher
							</button>
						</form>
						<small style="color: #666; display: block; margin-top: 8px;">
							Saisissez le matricule du patient pour le retrouver et pré-remplir le formulaire. Le matricule est l'identifiant unique du patient.
						</small>
					</div>
					
					<form method="POST" action="" id="ordonnanceForm">
						<div class="ordonnance-card">
							<h3 style="margin-bottom: 20px; color: #002939;">
								<i class="fa fa-user"></i> Sélection du Patient
							</h3>
							<div class="form-group">
								<label for="matricule_patient">Matricule du patient (clé d'identification — optionnel si vous choisissez dans la liste)</label>
								<input type="text" name="matricule_patient" id="matricule_patient" class="form-control" 
									   placeholder="Ex: PAT202501234567" 
									   value="<?php echo htmlspecialchars($matricule_saisi); ?>">
							</div>
							<div class="form-group">
								<label for="id_patient">Patient (matricule – nom) <span style="color: #dc3545;">*</span></label>
								<select name="id_patient" id="id_patient" class="form-control">
									<option value="">-- Choisir un patient ou saisir son matricule ci-dessus --</option>
									<?php 
									$ids_deja_vus = [];
									if ($patient_par_matricule && !empty($patient_par_matricule['id_patient'])):
										$p = $patient_par_matricule;
										$ids_deja_vus[(int)$p['id_patient']] = true;
										$mat = htmlspecialchars($p['Matricule_patient'] ?? '');
										$nom_aff = htmlspecialchars(($p['Prénom_patient'] ?? '') . ' ' . ($p['Nom_patient'] ?? ''));
									?>
										<option value="<?php echo (int)$p['id_patient']; ?>" selected><?php echo $mat ? $mat . ' – ' . $nom_aff : $nom_aff; ?></option>
									<?php endif; ?>
									<?php if (!empty($patients)): ?>
										<?php foreach ($patients as $patient): 
											if (isset($ids_deja_vus[(int)$patient['id_patient']])) continue;
											$mat = isset($patient['Matricule_patient']) ? htmlspecialchars($patient['Matricule_patient']) . ' – ' : '';
											$nom_aff = htmlspecialchars(($patient['Prénom_patient'] ?? '') . ' ' . ($patient['Nom_patient'] ?? ''));
											$sel = ($patient_par_matricule && (int)($patient_par_matricule['id_patient'] ?? 0) === (int)$patient['id_patient']) ? ' selected' : '';
										?>
											<option value="<?php echo $patient['id_patient']; ?>"<?php echo $sel; ?>><?php echo $mat . $nom_aff; ?></option>
										<?php endforeach; ?>
									<?php endif; ?>
									<?php if (empty($patients) && !$patient_par_matricule): ?>
										<option value="" disabled>Aucun patient disponible — utilisez la recherche par matricule</option>
									<?php endif; ?>
								</select>
								<small style="color: #666; display: block; margin-top: 5px;">
									<i class="fa fa-info-circle"></i> Utilisez le <strong>matricule</strong> pour identifier le patient, ou sélectionnez-le dans la liste. Après création, vous pourrez <strong>imprimer</strong> ou <strong>envoyer</strong> l'ordonnance au patient.
								</small>
								<?php if (empty($patients) && !$patient_par_matricule): ?>
									<div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 12px; margin-top: 10px; color: #856404;">
										<strong><i class="fa fa-exclamation-triangle"></i> Aucun patient dans la liste du service</strong><br>
										<small>Il n'y a actuellement aucun patient dans votre service qui a pris un rendez-vous ou eu une consultation. 
										<?php if (empty($specialisation)): ?>
											<br><strong>Note :</strong> Votre spécialisation n'est pas définie. Veuillez contacter l'administrateur.
										<?php else: ?>
											Utilisez la <strong>recherche par matricule</strong> ci-dessus pour identifier un patient, ou les patients apparaîtront ici une fois qu'ils auront eu une consultation dans votre service (<?php echo htmlspecialchars($specialisation); ?>).
										<?php endif; ?>
										</small>
									</div>
								<?php elseif ($patient_par_matricule && empty($patients)): ?>
									<small style="color: #0c5460; display: block; margin-top: 8px;"><i class="fa fa-info-circle"></i> Patient identifié par matricule. Vous pouvez créer l'ordonnance et l'envoyer directement dans son espace.</small>
								<?php endif; ?>
							</div>
							
							<div class="form-group" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
								<label for="id_consultation">Sélectionner une Consultation (Optionnel)</label>
								<select name="id_consultation" id="id_consultation" class="form-control">
									<option value="">-- Choisir une consultation (optionnel) --</option>
								</select>
								<small style="color: #666; display: block; margin-top: 5px;">
									<i class="fa fa-info-circle"></i> Si aucune consultation n'est sélectionnée, une consultation sera automatiquement créée pour le patient choisi.
								</small>
								<?php if (empty($consultations)): ?>
									<div style="background: #e7f3ff; border: 1px solid #4A90E2; border-radius: 6px; padding: 12px; margin-top: 10px; color: #004085;">
										<small><i class="fa fa-info-circle"></i> <strong>Information :</strong> Aucune consultation existante trouvée. Une nouvelle consultation sera automatiquement créée lorsque vous sélectionnerez un patient et créerez l'ordonnance.</small>
									</div>
								<?php endif; ?>
							</div>
						</div>
						
						<div class="ordonnance-card">
							<h3 style="margin-bottom: 20px; color: #002939;">
								<i class="fa fa-medkit"></i> Médicaments
							</h3>
							
							<div id="medicaments-container">
								<!-- Premier médicament -->
								<div class="medicament-item" data-index="0">
									<div class="form-group">
										<label>Médicament *</label>
										<input type="text" name="medicament[]" class="form-control medicament-input" 
											   placeholder="Tapez le nom du médicament ou sélectionnez dans la liste" 
											   autocomplete="off" required>
										<div class="medicament-suggestions" id="suggestions-0"></div>
									</div>
									<div class="row">
										<div class="col-md-4">
											<div class="form-group">
												<label>Dosage</label>
												<select name="dosage[]" class="form-control dosage-select">
													<option value="">-- Sélectionner --</option>
													<?php foreach ($dosages_courants as $dosage): ?>
														<option value="<?php echo htmlspecialchars($dosage); ?>">
															<?php echo htmlspecialchars($dosage); ?>
														</option>
													<?php endforeach; ?>
												</select>
											</div>
										</div>
										<div class="col-md-4">
											<div class="form-group">
												<label>Durée du traitement</label>
												<select name="duree[]" class="form-control duree-select">
													<option value="">-- Sélectionner --</option>
													<?php foreach ($durees_courantes as $duree): ?>
														<option value="<?php echo htmlspecialchars($duree); ?>">
															<?php echo htmlspecialchars($duree); ?>
														</option>
													<?php endforeach; ?>
												</select>
											</div>
										</div>
										<div class="col-md-4">
											<div class="form-group">
												<label>Instructions spéciales</label>
												<input type="text" name="instructions[]" class="form-control" 
													   placeholder="Ex: À jeun, avec repas, etc.">
											</div>
										</div>
									</div>
								</div>
							</div>
							
							<button type="button" class="btn-add-medicament" id="add-medicament">
								<i class="fa fa-plus"></i> Ajouter un autre médicament
							</button>
						</div>
						
						<div class="ordonnance-card">
							<button type="submit" name="creer_ordonnance" class="btn-submit">
								<i class="fa fa-check"></i> Créer l'Ordonnance
							</button>
							<a href="index.php" style="margin-left: 15px; color: #666; text-decoration: none;">
								Annuler
							</a>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
	
	<?php require_once '../partials/footer.php'; ?>
</div>

<script src="../assets/js/jquery.min.js"></script>
<script>
// Données des médicaments
const medicamentsData = <?php echo json_encode($medicaments_courants); ?>;
let medicamentIndex = 1;

// Envoyer l'ordonnance au patient (bouton affiché après création)
document.addEventListener('click', function(e) {
	var btn = e.target.closest && e.target.closest('.btn-envoyer-ord');
	if (!btn) return;
	var idConsultation = btn.getAttribute('data-id-consultation');
	if (!idConsultation) return;
	var origHtml = btn.innerHTML;
	btn.disabled = true;
	btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Envoi...';
	fetch('envoyer-ordonnance.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: 'id_consultation=' + idConsultation
	})
	.then(function(r) { return r.json(); })
	.then(function(data) {
		if (data.success) {
			btn.innerHTML = '<i class="fa fa-check"></i> Envoyé';
			btn.style.background = '#28a745';
		} else {
			btn.innerHTML = origHtml;
			btn.disabled = false;
			alert(data.message || 'Erreur lors de l\'envoi.');
		}
	})
	.catch(function() {
		btn.innerHTML = origHtml;
		btn.disabled = false;
		alert('Erreur réseau lors de l\'envoi.');
	});
});

// Données des consultations avec leurs patients
const consultationsData = <?php echo json_encode($consultations); ?>;
// Service du médecin connecté pour filtrer les consultations
const currentService = <?php echo json_encode($specialisation); ?>;

// Initialiser la liste des consultations au chargement
function initConsultationsList() {
    const consultationSelect = document.getElementById('id_consultation');
    const patientSelect = document.getElementById('id_patient');
    const selectedPatientId = patientSelect.value;
    
    // Réinitialiser les options de consultation
    consultationSelect.innerHTML = '<option value="">-- Choisir une consultation (optionnel) --</option>';
    
    // Filtrer les consultations par service ET par patient si un patient est sélectionné
    let filteredConsultations = consultationsData;
    
    // Filtrer par service (s'assurer que la consultation appartient au service du médecin)
    if (currentService) {
        filteredConsultations = filteredConsultations.filter(c => {
            // Vérifier que la consultation appartient au service actuel
            const consultationService = c.Nom_service || c.Spécialisation_med || '';
            return consultationService === currentService;
        });
    }
    
    // Filtrer par patient si un patient est sélectionné
    if (selectedPatientId) {
        filteredConsultations = filteredConsultations.filter(c => c.id_patient == selectedPatientId);
        
        if (filteredConsultations.length > 0) {
            const optgroup = document.createElement('optgroup');
            optgroup.label = 'Consultations existantes pour ce patient dans votre service';
            
            filteredConsultations.forEach(consultation => {
                const option = document.createElement('option');
                option.value = consultation.id_consultation;
                let text = '';
                if (consultation.Date_consultation) {
                    const date = new Date(consultation.Date_consultation);
                    text = date.toLocaleDateString('fr-FR') + ' ' + date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
                }
                if (consultation.Motif_diagnostic) {
                    text += (text ? ' - ' : '') + consultation.Motif_diagnostic.substring(0, 50);
                }
                option.textContent = text || 'Consultation';
                optgroup.appendChild(option);
            });
            
            consultationSelect.appendChild(optgroup);
        }
    } else {
        // Afficher toutes les consultations du service avec le nom du patient
        if (filteredConsultations.length > 0) {
            const optgroup = document.createElement('optgroup');
            optgroup.label = 'Toutes les consultations de votre service';
            
            filteredConsultations.forEach(consultation => {
                const option = document.createElement('option');
                option.value = consultation.id_consultation;
                let text = '';
                if (consultation.Prénom_patient && consultation.Nom_patient) {
                    text = consultation.Prénom_patient + ' ' + consultation.Nom_patient;
                }
                if (consultation.Date_consultation) {
                    const date = new Date(consultation.Date_consultation);
                    text += (text ? ' - ' : '') + date.toLocaleDateString('fr-FR') + ' ' + date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
                }
                option.textContent = text || 'Consultation';
                optgroup.appendChild(option);
            });
            
            consultationSelect.appendChild(optgroup);
        }
    }
}

// Remplir le sélecteur de consultations avec une liste (utilisée après chargement AJAX)
function fillConsultationSelect(consultations, groupLabel) {
    const consultationSelect = document.getElementById('id_consultation');
    consultationSelect.innerHTML = '<option value="">-- Choisir une consultation (optionnel) --</option>';
    if (!consultations || consultations.length === 0) return;
    const optgroup = document.createElement('optgroup');
    optgroup.label = groupLabel || 'Consultations existantes pour ce patient';
    consultations.forEach(function(consultation) {
        const option = document.createElement('option');
        option.value = consultation.id_consultation;
        let text = '';
        if (consultation.Date_consultation) {
            const date = new Date(consultation.Date_consultation);
            text = date.toLocaleDateString('fr-FR') + ' ' + date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
        }
        if (consultation.Motif_diagnostic) {
            text += (text ? ' - ' : '') + (consultation.Motif_diagnostic || '').substring(0, 50);
        }
        option.textContent = text || 'Consultation';
        optgroup.appendChild(option);
    });
    consultationSelect.appendChild(optgroup);
}

// Filtrer les consultations selon le patient sélectionné ; si un patient est choisi, charger ses consultations via AJAX
document.getElementById('id_patient').addEventListener('change', function() {
    var selectedPatientId = this.value;
    var consultationSelect = document.getElementById('id_consultation');
    if (selectedPatientId) {
        consultationSelect.innerHTML = '<option value="">Chargement des consultations...</option>';
        consultationSelect.disabled = true;
        fetch('get-consultations-patient.php?id_patient=' + encodeURIComponent(selectedPatientId))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                consultationSelect.disabled = false;
                if (data.success && data.consultations && data.consultations.length > 0) {
                    fillConsultationSelect(data.consultations, 'Consultations existantes pour ce patient');
                } else {
                    initConsultationsList();
                }
            })
            .catch(function() {
                consultationSelect.disabled = false;
                initConsultationsList();
            });
    } else {
        initConsultationsList();
    }
});

// Préremplir le patient si une consultation est sélectionnée (mais permettre de changer)
document.getElementById('id_consultation').addEventListener('change', function() {
    const selectedConsultationId = this.value;
    const patientSelect = document.getElementById('id_patient');
    
    if (selectedConsultationId && !selectedConsultationId.startsWith('type_')) {
        // Trouver le patient de cette consultation
        const consultation = consultationsData.find(c => c.id_consultation == selectedConsultationId);
        if (consultation && consultation.id_patient) {
            // Préremplir le patient mais ne pas bloquer si l'utilisateur veut changer
            if (!patientSelect.value || patientSelect.value == '') {
                patientSelect.value = consultation.id_patient;
            }
        }
    }
});

// Initialiser la liste au chargement
initConsultationsList();

// Validation du formulaire : patient identifié par matricule OU sélection dans la liste
document.getElementById('ordonnanceForm').addEventListener('submit', function(e) {
    const patientSelect = document.getElementById('id_patient');
    const matriculeInput = document.getElementById('matricule_patient');
    const selectedPatient = patientSelect && patientSelect.value;
    const matricule = matriculeInput && matriculeInput.value ? matriculeInput.value.trim() : '';
    
    if (!selectedPatient && !matricule) {
        e.preventDefault();
        alert('Veuillez saisir le matricule du patient (élément clé) ou sélectionner un patient dans la liste.');
        if (matriculeInput) matriculeInput.focus(); else if (patientSelect) patientSelect.focus();
        return false;
    }
    
    return true;
});

// Ajouter un nouveau médicament
document.getElementById('add-medicament').addEventListener('click', function() {
	const container = document.getElementById('medicaments-container');
	const newItem = document.createElement('div');
	newItem.className = 'medicament-item';
	newItem.setAttribute('data-index', medicamentIndex);
	
	newItem.innerHTML = `
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
			<strong style="color: #4A90E2;">Médicament #${medicamentIndex + 1}</strong>
			<button type="button" class="btn-remove remove-medicament">
				<i class="fa fa-times"></i> Supprimer
			</button>
		</div>
		<div class="form-group">
			<label>Médicament *</label>
			<input type="text" name="medicament[]" class="form-control medicament-input" 
				   placeholder="Tapez le nom du médicament ou sélectionnez dans la liste" 
				   autocomplete="off" required>
			<div class="medicament-suggestions" id="suggestions-${medicamentIndex}"></div>
		</div>
		<div class="row">
			<div class="col-md-4">
				<div class="form-group">
					<label>Dosage</label>
					<select name="dosage[]" class="form-control dosage-select">
						<option value="">-- Sélectionner --</option>
						<?php foreach ($dosages_courants as $dosage): ?>
							<option value="<?php echo htmlspecialchars($dosage); ?>">
								<?php echo htmlspecialchars($dosage); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="col-md-4">
				<div class="form-group">
					<label>Durée du traitement</label>
					<select name="duree[]" class="form-control duree-select">
						<option value="">-- Sélectionner --</option>
						<?php foreach ($durees_courantes as $duree): ?>
							<option value="<?php echo htmlspecialchars($duree); ?>">
								<?php echo htmlspecialchars($duree); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="col-md-4">
				<div class="form-group">
					<label>Instructions spéciales</label>
					<input type="text" name="instructions[]" class="form-control" 
						   placeholder="Ex: À jeun, avec repas, etc.">
				</div>
			</div>
		</div>
	`;
	
	container.appendChild(newItem);
	initMedicamentInput(newItem.querySelector('.medicament-input'), medicamentIndex);
	medicamentIndex++;
});

// Supprimer un médicament
document.addEventListener('click', function(e) {
	if (e.target.closest('.remove-medicament')) {
		const item = e.target.closest('.medicament-item');
		if (document.querySelectorAll('.medicament-item').length > 1) {
			item.remove();
		} else {
			alert('Vous devez avoir au moins un médicament.');
		}
	}
});

// Initialiser les suggestions pour tous les champs médicament
function initMedicamentInput(input, index) {
	const suggestionsDiv = document.getElementById(`suggestions-${index}`);
	
	input.addEventListener('input', function() {
		const value = this.value.toLowerCase();
		if (value.length < 2) {
			suggestionsDiv.style.display = 'none';
			return;
		}
		
		let html = '';
		let found = false;
		
		for (const [category, medicaments] of Object.entries(medicamentsData)) {
			const matches = medicaments.filter(m => m.toLowerCase().includes(value));
			if (matches.length > 0) {
				html += `<div class="suggestion-category">${category}</div>`;
				matches.forEach(med => {
					html += `<div class="suggestion-item" data-med="${med}">${med}</div>`;
					found = true;
				});
			}
		}
		
		if (found) {
			suggestionsDiv.innerHTML = html;
			suggestionsDiv.style.display = 'block';
			
			// Positionner la div de suggestions
			const rect = input.getBoundingClientRect();
			suggestionsDiv.style.top = (rect.bottom + window.scrollY) + 'px';
			suggestionsDiv.style.left = rect.left + 'px';
			suggestionsDiv.style.width = rect.width + 'px';
		} else {
			suggestionsDiv.style.display = 'none';
		}
	});
	
	// Sélectionner un médicament depuis les suggestions
	suggestionsDiv.addEventListener('click', function(e) {
		if (e.target.classList.contains('suggestion-item')) {
			input.value = e.target.getAttribute('data-med');
			suggestionsDiv.style.display = 'none';
		}
	});
	
	// Fermer les suggestions en cliquant ailleurs
	document.addEventListener('click', function(e) {
		if (!input.contains(e.target) && !suggestionsDiv.contains(e.target)) {
			suggestionsDiv.style.display = 'none';
		}
	});
}

// Initialiser le premier champ
document.querySelectorAll('.medicament-input').forEach((input, index) => {
	initMedicamentInput(input, index);
});
</script>
</body>
</html>
