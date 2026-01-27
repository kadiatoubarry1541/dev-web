<?php
/**
 * Liste des paiements
 * Accessible selon les permissions de chaque rôle
 */
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';

requireLogin('../login.php');
requirePermission('view_paiements', '../index.php');

$user_info = getUserInfo();
$role = $user_info['role'] ?? 'patient';
$paiements = [];
$message = '';
$message_type = '';

try {
    if (hasPermission('manage_paiements')) {
        // Admin et accueil voient tous les paiements
        $paiements = getAllPaiements();
    } elseif ($role === 'medecin') {
        // Médecin voit les paiements de ses consultations
        $id_med = $user_info['id_med'] ?? null;
        $specialisation = $user_info['specialisation'] ?? '';
        
        if ($id_med) {
            $pdo = bdd();
            // Récupérer les paiements des consultations du médecin
            if ($specialisation) {
                $sql = "SELECT DISTINCT p.*, c.Date_consultation, c.Motif_diagnostic, 
                               pat.Nom_patient, pat.Prénom_patient, pat.Matricule_patient
                        FROM PAIEMENT p
                        LEFT JOIN CONSULTATION c ON p.id_consultation = c.id_consultation
                        LEFT JOIN PATIENTS pat ON p.id_patient = pat.id_patient
                        LEFT JOIN RENDEZ_VOUS r ON c.id_patient = r.id_patient
                        LEFT JOIN SERVICES s ON r.id_service = s.id_service
                        WHERE (s.Nom_service = ? OR c.id_med = ?)
                        ORDER BY p.Date_paiement DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$specialisation, $id_med]);
            } else {
                $sql = "SELECT p.*, c.Date_consultation, c.Motif_diagnostic, 
                               pat.Nom_patient, pat.Prénom_patient, pat.Matricule_patient
                        FROM PAIEMENT p
                        LEFT JOIN CONSULTATION c ON p.id_consultation = c.id_consultation
                        LEFT JOIN PATIENTS pat ON p.id_patient = pat.id_patient
                        WHERE c.id_med = ?
                        ORDER BY p.Date_paiement DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_med]);
            }
            $paiements = $stmt->fetchAll();
        }
    } elseif ($role === 'patient') {
        // Patient voit uniquement ses paiements
        $id_patient = $user_info['id_patient'] ?? null;
        if ($id_patient) {
            $paiements = getPaiementsByPatient($id_patient);
        }
    }
    
    if (!is_array($paiements)) {
        $paiements = [];
    }
} catch (Exception $e) {
    error_log("Erreur récupération paiements: " . $e->getMessage());
    $message = "Une erreur est survenue lors de la récupération des paiements.";
    $message_type = "danger";
    $paiements = [];
}

// Traitement de la mise à jour du statut
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_statut']) && hasPermission('manage_paiements')) {
    $id_paiement = intval($_POST['id_paiement'] ?? 0);
    $nouveau_statut = $_POST['nouveau_statut'] ?? '';
    
    if ($id_paiement > 0 && !empty($nouveau_statut)) {
        if (updateStatutPaiement($id_paiement, $nouveau_statut)) {
            $message = "Le statut du paiement a été mis à jour avec succès.";
            $message_type = "success";
            // Recharger les paiements
            header('Location: liste-paiements.php');
            exit();
        } else {
            $message = "Erreur lors de la mise à jour du statut.";
            $message_type = "danger";
        }
    }
}

// Traitement de l'envoi du reçu au patient (pour admin, médecin et accueil)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['envoyer_recu']) && hasPermission('send_receipts')) {
    $id_paiement = intval($_POST['id_paiement'] ?? 0);
    
    if ($id_paiement > 0) {
        // Vérifier que le médecin ne peut envoyer que les reçus de ses propres consultations
        if ($role === 'medecin') {
            $paiement = getPaiementById($id_paiement);
            if ($paiement && isset($paiement['id_consultation']) && $paiement['id_consultation']) {
                $pdo = bdd();
                $sql = "SELECT id_med FROM CONSULTATION WHERE id_consultation = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$paiement['id_consultation']]);
                $consultation = $stmt->fetch();
                
                if (!$consultation || $consultation['id_med'] != $user_info['id_med']) {
                    $message = "Vous n'avez pas la permission d'envoyer ce reçu.";
                    $message_type = "danger";
                } else {
                    // Pour admin et accueil, pas de restriction supplémentaire
                    try {
                        // Vérifier que le paiement existe et est payé
                        $paiement = getPaiementById($id_paiement);
                        if (!$paiement) {
                            $message = "Le paiement sélectionné n'existe pas.";
                            $message_type = "danger";
                        } elseif ($paiement['Statut'] !== 'payé') {
                            $message = "Le paiement doit être marqué comme 'Payé' avant de pouvoir générer et envoyer un reçu.";
                            $message_type = "warning";
                        } else {
                            if (envoyerReçuAuPatient($id_paiement)) {
                                $message = "Le reçu a été généré et envoyé au patient avec succès.";
                                $message_type = "success";
                                // Recharger les paiements
                                header('Location: liste-paiements.php?message=recu_envoye');
                                exit();
                            } else {
                                $message = "Le reçu n'a pas pu être généré. Veuillez vérifier les permissions du dossier uploads/reçus.";
                                $message_type = "danger";
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Erreur envoi reçu: " . $e->getMessage());
                        $message = "Erreur lors de l'envoi du reçu : " . htmlspecialchars($e->getMessage());
                        $message_type = "danger";
                    }
                }
            } else {
                $message = "Ce paiement n'est pas associé à une consultation.";
                $message_type = "danger";
            }
        } else {
            // Pour admin et accueil, pas de restriction supplémentaire
            try {
                // Vérifier que le paiement existe et est payé
                $paiement = getPaiementById($id_paiement);
                if (!$paiement) {
                    $message = "Le paiement sélectionné n'existe pas.";
                    $message_type = "danger";
                } elseif ($paiement['Statut'] !== 'payé') {
                    $message = "Le paiement doit être marqué comme 'Payé' avant de pouvoir générer et envoyer un reçu.";
                    $message_type = "warning";
                } else {
                    // Générer un numéro de facture si nécessaire
                    if (!isset($paiement['id_facture']) || empty($paiement['id_facture'])) {
                        try {
                            $pdo = bdd();
                            $id_facture = genererNumeroFacture();
                            $sql = "UPDATE PAIEMENT SET id_facture = ? WHERE id_paiement = ?";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$id_facture, $id_paiement]);
                        } catch (Exception $e) {
                            error_log("Erreur génération facture: " . $e->getMessage());
                        }
                    }
                    
                    if (envoyerReçuAuPatient($id_paiement)) {
                        $message = "Le reçu a été généré et envoyé au patient avec succès. Le patient recevra une notification.";
                        $message_type = "success";
                        // Recharger les paiements
                        header('Location: liste-paiements.php?message=recu_envoye');
                        exit();
                    } else {
                        $message = "Le reçu n'a pas pu être généré. Veuillez vérifier les permissions du dossier uploads/reçus.";
                        $message_type = "danger";
                    }
                }
            } catch (Exception $e) {
                error_log("Erreur envoi reçu: " . $e->getMessage());
                $message = "Erreur lors de l'envoi du reçu : " . htmlspecialchars($e->getMessage());
                $message_type = "danger";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>Liste des Paiements - MediCo.</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" type="text/css" href="../assets/css/plugins.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
	<link class="skin" rel="stylesheet" type="text/css" href="../assets/css/skin/skin-1.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/templete.min.css">
	<style>
		.paiements-container {
			padding: 40px 0;
			background: #f8f9fa;
			min-height: 100vh;
		}
		.page-header {
			background: white;
			padding: 30px;
			border-radius: 12px;
			margin-bottom: 30px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
		}
		.page-header h1 {
			color: #002939;
			font-size: 32px;
			font-weight: 700;
			margin-bottom: 10px;
		}
		.table-container {
			background: white;
			border-radius: 12px;
			padding: 30px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			overflow-x: auto;
		}
		table {
			width: 100%;
			border-collapse: collapse;
		}
		th, td {
			padding: 15px;
			text-align: left;
			border-bottom: 1px solid #e2e8f0;
		}
		th {
			background: #f8f9fa;
			font-weight: 600;
			color: #002939;
			text-transform: uppercase;
			font-size: 13px;
			letter-spacing: 0.5px;
		}
		tr:hover {
			background: #f8f9fa;
		}
		.badge {
			padding: 6px 12px;
			border-radius: 20px;
			font-size: 12px;
			font-weight: 600;
			display: inline-block;
		}
		.badge-paye {
			background: #d4edda;
			color: #155724;
		}
		.badge-attente {
			background: #fff3cd;
			color: #856404;
		}
		.badge-annule {
			background: #f8d7da;
			color: #721c24;
		}
		.badge-rembourse {
			background: #d1ecf1;
			color: #0c5460;
		}
		.btn-action {
			padding: 8px 15px;
			border: none;
			border-radius: 6px;
			cursor: pointer;
			font-size: 13px;
			font-weight: 600;
			transition: all 0.3s;
			text-decoration: none;
			display: inline-block;
		}
		.btn-primary {
			background: #667eea;
			color: white;
		}
		.btn-primary:hover {
			background: #5568d3;
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
		.empty-state {
			text-align: center;
			padding: 60px 20px;
			color: #718096;
		}
		.empty-state i {
			font-size: 64px;
			margin-bottom: 20px;
			opacity: 0.5;
		}
		.montant {
			font-weight: 700;
			color: #28a745;
			font-size: 16px;
		}
	</style>
</head>
<body>
<div class="page-wraper">
	<?php require_once '../partials/entete.php'; ?>
	
	<div class="paiements-container">
		<div class="container">
		<a href="<?php 
			$role = $user_info['role'] ?? 'patient';
			if ($role === 'admin') {
				echo '../admin/index.php';
			} elseif ($role === 'accueil') {
				echo '../accueil/index.php';
			} elseif ($role === 'medecin') {
				echo '../medecin/index.php';
			} else {
				echo '../profil.php';
			}
		?>" class="btn-retour">
			<i class="fa fa-arrow-left"></i> Retour
		</a>
		<div class="page-header">
			<h1><i class="fa fa-money"></i> Liste des Paiements</h1>
				<?php if (hasPermission('manage_paiements')): ?>
					<a href="creer-paiement.php" class="btn-action btn-primary">
						<i class="fa fa-plus"></i> Créer un Paiement
					</a>
				<?php endif; ?>
			</div>
			
			<?php if ($message): ?>
				<div class="alert alert-<?php echo $message_type; ?>">
					<?php echo $message; ?>
				</div>
			<?php endif; ?>
			
			<?php if (isset($_GET['message']) && $_GET['message'] === 'recu_envoye'): ?>
				<div class="alert alert-success">
					<i class="fa fa-check-circle"></i> Le reçu a été généré et envoyé au patient avec succès. Le patient recevra une notification.
				</div>
			<?php endif; ?>
			
			<div class="table-container">
				<?php if (empty($paiements)): ?>
					<div class="empty-state">
						<i class="fa fa-inbox"></i>
						<h3>Aucun paiement trouvé</h3>
						<p>Il n'y a pas encore de paiements enregistrés.</p>
						<?php if (hasPermission('manage_paiements')): ?>
							<a href="creer-paiement.php" class="btn-action btn-primary" style="margin-top: 20px;">
								<i class="fa fa-plus"></i> Créer le premier paiement
							</a>
						<?php endif; ?>
					</div>
				<?php else: ?>
					<table>
						<thead>
							<tr>
								<th>Date</th>
								<th>Patient</th>
								<th>Consultation</th>
								<th>Montant</th>
								<th>Méthode</th>
								<th>Statut</th>
								<?php if (hasPermission('manage_paiements')): ?>
									<th>Facture</th>
									<th>Actions</th>
								<?php else: ?>
									<th>Reçu</th>
								<?php endif; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($paiements as $paiement): ?>
								<tr>
									<td><?php echo date('d/m/Y H:i', strtotime($paiement['Date_paiement'])); ?></td>
									<td>
										<strong><?php echo htmlspecialchars(($paiement['Nom_patient'] ?? '') . ' ' . ($paiement['Prénom_patient'] ?? '')); ?></strong><br>
										<small style="color: #666;"><?php echo htmlspecialchars($paiement['Matricule_patient'] ?? ''); ?></small>
									</td>
									<td>
										<?php if ($paiement['Date_consultation']): ?>
											<?php echo date('d/m/Y H:i', strtotime($paiement['Date_consultation'])); ?><br>
											<small style="color: #666;"><?php echo htmlspecialchars(substr($paiement['Motif_diagnostic'] ?? '', 0, 50)); ?></small>
										<?php else: ?>
											<span style="color: #999;">-</span>
										<?php endif; ?>
									</td>
									<td class="montant"><?php echo number_format($paiement['Montant'], 0, ',', ' '); ?> GNF</td>
									<td><?php echo htmlspecialchars($paiement['Méthode_paiement'] ?? 'N/A'); ?></td>
									<td>
										<?php
										$statut = $paiement['Statut'] ?? 'en_attente';
										$badge_class = 'badge-attente';
										if ($statut === 'payé') $badge_class = 'badge-paye';
										elseif ($statut === 'annulé') $badge_class = 'badge-annule';
										elseif ($statut === 'remboursé') $badge_class = 'badge-rembourse';
										?>
										<span class="badge <?php echo $badge_class; ?>">
											<?php echo htmlspecialchars(ucfirst($statut)); ?>
										</span>
									</td>
									<?php if (hasPermission('manage_paiements')): ?>
										<td>
											<?php if (isset($paiement['id_facture']) && $paiement['id_facture']): ?>
												<small style="color: #667eea; font-weight: 600;"><?php echo htmlspecialchars($paiement['id_facture']); ?></small>
											<?php else: ?>
												<span style="color: #999;">-</span>
											<?php endif; ?>
										</td>
										<td>
											<div style="display: flex; gap: 5px; flex-wrap: wrap; align-items: center;">
												<?php if (isset($paiement['chemin_reçu']) && $paiement['chemin_reçu']): ?>
													<a href="voir-reçu.php?id=<?php echo $paiement['id_paiement']; ?>" 
													   target="_blank" 
													   class="btn-action btn-primary" 
													   style="padding: 6px 12px; font-size: 12px;">
														<i class="fa fa-receipt"></i> Voir reçu
													</a>
												<?php elseif ($statut === 'payé' && isset($paiement['id_facture']) && $paiement['id_facture']): ?>
													<a href="voir-reçu.php?id=<?php echo $paiement['id_paiement']; ?>" 
													   target="_blank" 
													   class="btn-action" 
													   style="padding: 6px 12px; font-size: 12px; background: #ffc107; color: #000;">
														<i class="fa fa-file-invoice"></i> Générer reçu
													</a>
												<?php endif; ?>
												
												<?php if (hasPermission('send_receipts') && $statut === 'payé'): ?>
													<form method="post" action="" style="display: inline-block; margin: 0;">
														<input type="hidden" name="id_paiement" value="<?php echo $paiement['id_paiement']; ?>">
														<button type="submit" name="envoyer_recu" 
																class="btn-action" 
																style="padding: 6px 12px; font-size: 12px; background: #28a745; color: white; border: none; cursor: pointer;"
																title="Générer le reçu et envoyer une notification au patient"
																onclick="return confirm('Voulez-vous générer le reçu et l\'envoyer au patient ? Le patient recevra une notification.');">
															<i class="fa fa-paper-plane"></i> Générer et envoyer le reçu
														</button>
													</form>
												<?php elseif (hasPermission('send_receipts') && $statut !== 'payé'): ?>
													<small style="color: #999; font-size: 11px; display: block; margin-top: 5px;">
														<i class="fa fa-info-circle"></i> Le paiement doit être "Payé" pour générer un reçu
													</small>
												<?php endif; ?>
												
												<form method="post" action="" style="display: inline-block; margin: 0;">
													<input type="hidden" name="id_paiement" value="<?php echo $paiement['id_paiement']; ?>">
													<select name="nouveau_statut" onchange="this.form.submit()" style="padding: 6px; border-radius: 4px; border: 1px solid #ddd;">
														<option value="en_attente" <?php echo ($statut === 'en_attente') ? 'selected' : ''; ?>>En attente</option>
														<option value="payé" <?php echo ($statut === 'payé') ? 'selected' : ''; ?>>Payé</option>
														<option value="annulé" <?php echo ($statut === 'annulé') ? 'selected' : ''; ?>>Annulé</option>
														<option value="remboursé" <?php echo ($statut === 'remboursé') ? 'selected' : ''; ?>>Remboursé</option>
													</select>
													<input type="hidden" name="update_statut" value="1">
												</form>
											</div>
										</td>
									<?php else: ?>
										<!-- Pour les patients, afficher le lien vers le reçu s'il existe -->
										<?php if (isset($paiement['chemin_reçu']) && $paiement['chemin_reçu']): ?>
											<td colspan="2">
												<a href="voir-reçu.php?id=<?php echo $paiement['id_paiement']; ?>" 
												   target="_blank" 
												   class="btn-action btn-primary">
													<i class="fa fa-receipt"></i> Voir mon reçu
												</a>
											</td>
										<?php else: ?>
											<td colspan="2">
												<span style="color: #999;">Reçu non disponible</span>
											</td>
										<?php endif; ?>
									<?php endif; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					
					<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center;">
						<strong>Total : <?php echo count($paiements); ?> paiement(s)</strong>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	
	<?php require_once '../partials/footer.php'; ?>
</div>
</body>
</html>
