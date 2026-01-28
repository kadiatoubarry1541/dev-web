<?php
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';

requireLogin('login.php');
requireMedecin('index.php');

$user_info = getUserInfo();
$id_med = $user_info['id_med'];
$specialisation = $user_info['specialisation'] ?? '';

$mes_rdv = [];
$rdv_planifies = [];
$rdv_confirmes = [];
$message = '';
$message_type = '';
if (!empty($_GET['msg']) && $_GET['msg'] === 'creation_rdv_accueil') {
    $message = "La création des rendez-vous est assurée par l'accueil. Vous pouvez consulter et valider vos rendez-vous depuis cette page.";
    $message_type = 'info';
}

if ($id_med) {
    try {
        // Si la spécialisation n'est pas en session, la récupérer depuis la base de données
        if (empty($specialisation)) {
            $pdo = bdd();
            $sql_med = "SELECT Spécialisation_med FROM MEDECINS WHERE id_med = ?";
            $stmt_med = $pdo->prepare($sql_med);
            $stmt_med->execute([$id_med]);
            $medecin = $stmt_med->fetch();
            if ($medecin && isset($medecin['Spécialisation_med'])) {
                $specialisation = $medecin['Spécialisation_med'];
                // Mettre à jour la session pour la prochaine fois
                $_SESSION['specialisation'] = $specialisation;
            }
        }
        
        // Récupérer les rendez-vous du médecin filtrés par son service
        // Cette fonction retourne TOUS les rendez-vous du service, pas seulement ceux assignés à ce médecin
        $mes_rdv = getRendezVousByMedecin($id_med, $specialisation);
        if ($mes_rdv === false) {
            $mes_rdv = [];
        }

        // Séparer les rendez-vous selon leur statut
        // On est tolérant sur l'écriture du statut (majuscules, accents, espaces...)
        foreach ($mes_rdv as $rdv) {
            $statut_brut = isset($rdv['Statut']) ? $rdv['Statut'] : '';
            $statut = strtolower(trim($statut_brut));

            // RDV à confirmer
            if (in_array($statut, ['planifié', 'planifie'])) {
                $rdv_planifies[] = $rdv;
                continue;
            }

            // RDV annulés (on ne les met pas dans les confirmés)
            if (in_array($statut, ['annulé', 'annule'])) {
                continue;
            }

            // Tout le reste est considéré comme "confirmé / validé"
            if ($statut !== '') {
                $rdv_confirmes[] = $rdv;
            }
        }

        // Compléter les rendez-vous confirmés avec les DEMANDES marquées "traitee" pour ce service
        // Cela permet d'aligner la liste avec le compteur "Confirmés" du tableau de bord.
        if (!empty($specialisation) && function_exists('getIdServiceByNom')) {
            $id_service_dashboard = getIdServiceByNom($specialisation);
            if ($id_service_dashboard) {
                try {
                    $pdo = bdd();
                    $sql_dem = "SELECT d.*, s.Nom_service 
                                FROM DEMANDE_RENDEZ_VOUS d
                                LEFT JOIN SERVICES s ON d.id_service = s.id_service
                                LEFT JOIN RENDEZ_VOUS r 
                                    ON r.Date_rdv = d.Date_rdv_souhaitee
                                   AND (r.id_service = d.id_service OR (r.id_service IS NULL AND d.id_service IS NULL))
                                WHERE d.id_service = ?
                                  AND d.statut = 'traitee'
                                  AND r.id_rdv IS NULL
                                ORDER BY d.Date_rdv_souhaitee DESC";
                    $stmt_dem = $pdo->prepare($sql_dem);
                    $stmt_dem->execute([$id_service_dashboard]);
                    $demandes_traitees = $stmt_dem->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($demandes_traitees as $d) {
                        $rdv_confirmes[] = [
                            'Nom_patient'   => $d['nom_demandeur'] ?? '',
                            'Prénom_patient'=> '',
                            'Date_rdv'      => $d['Date_rdv_souhaitee'] ?? null,
                            'Nom_service'   => $d['Nom_service'] ?? ($specialisation ?: 'Service'),
                            'Motif'         => $d['motif'] ?? '',
                            'Statut'        => 'confirmé'
                        ];
                    }
                } catch (Exception $e) {
                    error_log('mes-rendez-vous (demandes traitee): ' . $e->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        error_log("Erreur mes-rendez-vous: " . $e->getMessage());
        $message = "Une erreur est survenue lors de la récupération des rendez-vous.";
        $message_type = "danger";
        $mes_rdv = [];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Mes Rendez-vous - Espace Médecin</title>
	<link rel="stylesheet" type="text/css" href="../assets/css/plugins.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<style>
		.rdv-container {
			padding: 40px 0;
			background: #f8f9fa;
			min-height: 100vh;
		}
		.rdv-card {
			background: #fff;
			border-radius: 10px;
			padding: 20px;
			margin-bottom: 20px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			border-left: 4px solid #4A90E2;
		}
		.rdv-header {
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
		.statut-badge {
			padding: 6px 12px;
			border-radius: 20px;
			font-size: 12px;
			font-weight: 600;
		}
		.statut-planifie {
			background: #fff3cd;
			color: #856404;
		}
		.statut-confirme {
			background: #d4edda;
			color: #155724;
		}
		.statut-annule {
			background: #f8d7da;
			color: #721c24;
		}
		.rdv-details {
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
		.btn-approve {
			background: #28a745;
			color: white;
			border: none;
			padding: 10px 20px;
			border-radius: 6px;
			cursor: pointer;
			font-weight: 600;
		}
		.btn-approve:hover {
			background: #218838;
		}
		.info-box {
			background: #f0f7ff;
			border-left: 4px solid #4A90E2;
			padding: 15px;
			border-radius: 6px;
			margin-bottom: 20px;
		}
	</style>
</head>
<body>
<div class="page-wraper">
	<?php require_once '../partials/entete.php'; ?>
	
	<div class="rdv-container">
		<div class="container">
			<div class="row">
				<div class="col-12">
					<a href="index.php" style="color: #4A90E2; text-decoration: none; margin-bottom: 20px; display: inline-block;">
						<i class="fa fa-arrow-left"></i> Retour au tableau de bord
					</a>
					<h1 style="color: #002939; margin-bottom: 20px;">
						<i class="fa fa-calendar"></i> Mes Rendez-vous
					</h1>
					
					<div class="info-box">
						<strong>Service :</strong> <?php echo htmlspecialchars($specialisation); ?> | 
						<strong>Total :</strong> <?php echo count($mes_rdv); ?> rendez-vous 
                        (<?php echo count($rdv_planifies); ?> à confirmer, <?php echo count($rdv_confirmes); ?> confirmés)
					</div>
					
					<?php if ($message): ?>
						<div class="alert alert-<?php echo $message_type; ?>">
							<?php echo htmlspecialchars($message); ?>
						</div>
					<?php endif; ?>
					
					<?php if (empty($mes_rdv)): ?>
						<div style="text-align: center; padding: 60px 20px; color: #666;">
							<i class="fa fa-calendar-times-o" style="font-size: 64px; color: #ddd; margin-bottom: 20px;"></i>
							<h3>Aucun rendez-vous</h3>
							<p>Vous n'avez pas encore de rendez-vous.</p>
						</div>
					<?php else: ?>
                        <!-- Rendez-vous à confirmer -->
                        <h3 style="margin-top: 20px; margin-bottom: 10px;">Rendez-vous à confirmer</h3>
                        <?php if (empty($rdv_planifies)): ?>
                            <p style="color: #666;">Aucun rendez-vous en attente de confirmation.</p>
                        <?php else: ?>
						    <?php foreach ($rdv_planifies as $rdv): ?>
							<div class="rdv-card">
								<div class="rdv-header">
									<div class="patient-name">
										<i class="fa fa-user"></i> <?php echo htmlspecialchars(($rdv['Nom_patient'] ?? '') . ' ' . ($rdv['Prénom_patient'] ?? '')); ?>
									</div>
									<?php if (isset($rdv['Statut'])): ?>
										<span class="statut-badge statut-<?php echo strtolower($rdv['Statut']); ?>">
											<?php echo htmlspecialchars($rdv['Statut']); ?>
										</span>
									<?php endif; ?>
								</div>
								<div class="rdv-details">
									<?php if (isset($rdv['Date_rdv'])): ?>
										<div class="detail-item">
											<strong><i class="fa fa-calendar"></i> Date :</strong><br>
											<?php echo date('d/m/Y', strtotime($rdv['Date_rdv'])); ?>
										</div>
										<div class="detail-item">
											<strong><i class="fa fa-clock-o"></i> Heure :</strong><br>
											<?php echo date('H:i', strtotime($rdv['Date_rdv'])); ?>
										</div>
									<?php endif; ?>
									<div class="detail-item">
										<strong><i class="fa fa-stethoscope"></i> Service :</strong><br>
										<?php echo htmlspecialchars($rdv['Nom_service'] ?? 'N/A'); ?>
									</div>
									<?php if (isset($rdv['Nom_med']) && isset($rdv['Prénom_med'])): ?>
										<div class="detail-item">
											<strong><i class="fa fa-user-md"></i> Médecin :</strong><br>
											Dr. <?php echo htmlspecialchars(($rdv['Prénom_med'] ?? '') . ' ' . ($rdv['Nom_med'] ?? '')); ?>
										</div>
									<?php endif; ?>
									<?php if (isset($rdv['Motif']) && $rdv['Motif']): ?>
										<div class="detail-item">
											<strong><i class="fa fa-file-text"></i> Motif :</strong><br>
											<?php echo htmlspecialchars($rdv['Motif']); ?>
										</div>
									<?php endif; ?>
								</div>
								<?php if (isset($rdv['Statut']) && $rdv['Statut'] == 'planifié' && isset($rdv['id_rdv'])): ?>
									<button class="btn-approve" onclick="approuverRDV(<?php echo $rdv['id_rdv']; ?>)">
										<i class="fa fa-check"></i> Confirmer ce rendez-vous
									</button>
								<?php endif; ?>
							</div>
						    <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Rendez-vous déjà confirmés -->
                        <h3 style="margin-top: 30px; margin-bottom: 10px;">Rendez-vous confirmés</h3>
                        <?php if (empty($rdv_confirmes)): ?>
                            <p style="color: #666;">Vous n'avez pas encore de rendez-vous confirmés.</p>
                        <?php else: ?>
                            <?php foreach ($rdv_confirmes as $rdv): ?>
                            <div class="rdv-card">
								<div class="rdv-header">
									<div class="patient-name">
										<i class="fa fa-user"></i> <?php echo htmlspecialchars(($rdv['Nom_patient'] ?? '') . ' ' . ($rdv['Prénom_patient'] ?? '')); ?>
									</div>
									<?php if (isset($rdv['Statut'])): ?>
										<span class="statut-badge statut-<?php echo strtolower($rdv['Statut']); ?>">
											<?php echo htmlspecialchars($rdv['Statut']); ?>
										</span>
									<?php endif; ?>
								</div>
								<div class="rdv-details">
									<?php if (isset($rdv['Date_rdv'])): ?>
										<div class="detail-item">
											<strong><i class="fa fa-calendar"></i> Date :</strong><br>
											<?php echo date('d/m/Y', strtotime($rdv['Date_rdv'])); ?>
										</div>
										<div class="detail-item">
											<strong><i class="fa fa-clock-o"></i> Heure :</strong><br>
											<?php echo date('H:i', strtotime($rdv['Date_rdv'])); ?>
										</div>
									<?php endif; ?>
									<div class="detail-item">
										<strong><i class="fa fa-stethoscope"></i> Service :</strong><br>
										<?php echo htmlspecialchars($rdv['Nom_service'] ?? 'N/A'); ?>
									</div>
									<?php if (isset($rdv['Nom_med']) && isset($rdv['Prénom_med'])): ?>
										<div class="detail-item">
											<strong><i class="fa fa-user-md"></i> Médecin :</strong><br>
											Dr. <?php echo htmlspecialchars(($rdv['Prénom_med'] ?? '') . ' ' . ($rdv['Nom_med'] ?? '')); ?>
										</div>
									<?php endif; ?>
									<?php if (isset($rdv['Motif']) && $rdv['Motif']): ?>
										<div class="detail-item">
											<strong><i class="fa fa-file-text"></i> Motif :</strong><br>
											<?php echo htmlspecialchars($rdv['Motif']); ?>
										</div>
									<?php endif; ?>
								</div>
							</div>
                            <?php endforeach; ?>
                        <?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	
	<?php require_once '../partials/footer.php'; ?>
</div>

<script src="../assets/js/jquery.min.js"></script>
<script>
function approuverRDV(id) {
	if (confirm('Voulez-vous confirmer ce rendez-vous ? Le patient recevra une notification.')) {
		$.post('approuver-rdv.php', {id: id}, function(data) {
			if (data.success) {
				alert('Rendez-vous confirmé avec succès !');
				location.reload();
			} else {
				alert('Erreur : ' + data.message);
			}
		}, 'json').fail(function() {
			alert('Erreur lors de la confirmation du rendez-vous.');
		});
	}
}
</script>
</body>
</html>
