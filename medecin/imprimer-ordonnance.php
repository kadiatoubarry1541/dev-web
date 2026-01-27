<?php
/**
 * Page d'impression d'une ordonnance pour le médecin.
 * Affiche une seule ordonnance dans un format propre à remettre au patient.
 */
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';

requireLogin('login.php');
requireMedecin('index.php');

$user_info = getUserInfo();
$id_med = $user_info['id_med'] ?? null;
$id_consultation = isset($_GET['id_consultation']) ? (int) $_GET['id_consultation'] : 0;

$ordonnance_data = null;
$erreur = '';

if (!$id_consultation) {
    $erreur = "Numéro de consultation manquant.";
} else {
    try {
        $pdo = bdd();
        $sql = "SELECT o.*, c.Date_consultation, c.Motif_diagnostic, c.id_med,
                       p.Nom_patient, p.Prénom_patient, p.Matricule_patient,
                       m.Nom_med, m.Prénom_med
                FROM ORDONNANCES o
                INNER JOIN CONSULTATION c ON o.id_consultation = c.id_consultation
                LEFT JOIN PATIENTS p ON c.id_patient = p.id_patient
                LEFT JOIN MEDECINS m ON c.id_med = m.id_med
                WHERE o.id_consultation = ? AND c.id_med = ?
                ORDER BY o.Date_émission DESC, o.id_ordonnance DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_consultation, $id_med]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            $erreur = "Aucune ordonnance trouvée pour cette consultation.";
        } else {
            $ordonnance_data = [
                'consultation' => $rows[0],
                'medicaments'  => $rows
            ];
        }
    } catch (Exception $e) {
        error_log("Erreur imprimer-ordonnance: " . $e->getMessage());
        $erreur = "Erreur lors du chargement de l'ordonnance.";
    }
}

$consultation = $ordonnance_data['consultation'] ?? null;
$medicaments = $ordonnance_data['medicaments'] ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Imprimer l'ordonnance - MediCo.</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<style>
		* { box-sizing: border-box; }
		body { font-family: Arial, sans-serif; margin: 0; padding: 24px; background: #fff; color: #333; font-size: 14px; }
		.impression-ordonnance { max-width: 700px; margin: 0 auto; }
		.entete-cabinet { text-align: center; border-bottom: 2px solid #4A90E2; padding-bottom: 16px; margin-bottom: 24px; }
		.entete-cabinet h1 { margin: 0 0 8px 0; color: #002939; font-size: 22px; }
		.entete-cabinet .sous-titre { color: #666; font-size: 13px; }
		.ordonnance-titre { font-size: 20px; font-weight: 700; color: #4A90E2; margin: 24px 0 16px 0; border-bottom: 1px solid #ddd; padding-bottom: 8px; }
		.bloc-patient { background: #f8f9fa; padding: 14px 18px; border-radius: 8px; border-left: 4px solid #4A90E2; margin-bottom: 20px; }
		.bloc-patient .nom { font-size: 18px; font-weight: 700; color: #002939; margin-bottom: 6px; }
		.bloc-patient .infos { color: #555; font-size: 13px; }
		.bloc-motif { margin-bottom: 18px; padding: 12px; background: #f0f7ff; border-radius: 6px; }
		.bloc-motif strong { color: #333; }
		.medicament { margin-bottom: 14px; padding: 12px 14px; border: 1px solid #e0e0e0; border-radius: 6px; border-left: 4px solid #28a745; }
		.medicament .nom-medicament { font-weight: 700; color: #222; margin-bottom: 6px; }
		.medicament .detail { color: #555; font-size: 13px; margin-top: 4px; }
		.pied-ordonnance { margin-top: 28px; padding-top: 16px; border-top: 1px solid #ddd; text-align: right; font-size: 13px; color: #666; }
		.pied-ordonnance .date-emission { font-weight: 600; color: #333; }
		.no-print { margin: 20px auto; text-align: center; }
		.btn-print { background: #4A90E2; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-size: 15px; }
		.btn-print:hover { background: #357ABD; }
		.msg-erreur { padding: 20px; text-align: center; color: #721c24; background: #f8d7da; border-radius: 8px; }
		@media print {
			body { padding: 12px; }
			.no-print { display: none !important; }
			.impression-ordonnance { max-width: 100%; }
		}
	</style>
</head>
<body>
<?php if ($erreur): ?>
	<div class="msg-erreur"><?php echo htmlspecialchars($erreur); ?></div>
	<div class="no-print" style="margin-top: 20px;">
		<button type="button" class="btn-print" onclick="window.close()">
			<i class="fa fa-times"></i> Fermer
		</button>
	</div>
<?php else: ?>
	<div class="impression-ordonnance">
		<div class="entete-cabinet">
			<h1>MediCo.</h1>
			<div class="sous-titre">Ordonnance médicale</div>
		</div>

		<div class="ordonnance-titre">Patient</div>
		<div class="bloc-patient">
			<div class="nom"><?php echo htmlspecialchars(($consultation['Prénom_patient'] ?? '') . ' ' . ($consultation['Nom_patient'] ?? '')); ?></div>
			<div class="infos">
				<?php if (!empty($consultation['Matricule_patient'])): ?>
					Matricule : <?php echo htmlspecialchars($consultation['Matricule_patient']); ?>
				<?php endif; ?>
				<?php if (!empty($consultation['Date_consultation'])): ?>
					&nbsp;|&nbsp;Consultation du <?php echo date('d/m/Y à H:i', strtotime($consultation['Date_consultation'])); ?>
				<?php endif; ?>
			</div>
		</div>

		<?php if (!empty($consultation['Motif_diagnostic'])): ?>
			<div class="bloc-motif">
				<strong>Motif / Diagnostic :</strong><br>
				<?php echo nl2br(htmlspecialchars($consultation['Motif_diagnostic'])); ?>
			</div>
		<?php endif; ?>

		<div class="ordonnance-titre">Prescription</div>
		<?php foreach ($medicaments as $med): ?>
			<div class="medicament">
				<div class="nom-medicament"><?php echo htmlspecialchars($med['Médicament']); ?></div>
				<?php if (!empty($med['Dosage'])): ?>
					<div class="detail"><strong>Dosage :</strong> <?php echo htmlspecialchars($med['Dosage']); ?></div>
				<?php endif; ?>
				<?php if (!empty($med['Durée_traitement'])): ?>
					<div class="detail"><strong>Durée :</strong> <?php echo htmlspecialchars($med['Durée_traitement']); ?></div>
				<?php endif; ?>
				<?php if (!empty($med['Instructions'])): ?>
					<div class="detail"><strong>Instructions :</strong> <?php echo nl2br(htmlspecialchars($med['Instructions'])); ?></div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<div class="pied-ordonnance">
			<span class="date-emission">Date d'émission : <?php echo isset($medicaments[0]['Date_émission']) ? date('d/m/Y', strtotime($medicaments[0]['Date_émission'])) : ''; ?></span>
			<?php if (!empty($consultation['Nom_med'])): ?>
				<br>Dr <?php echo htmlspecialchars(($consultation['Prénom_med'] ?? '') . ' ' . $consultation['Nom_med']); ?>
			<?php endif; ?>
		</div>
	</div>

	<div class="no-print">
		<button type="button" class="btn-print" onclick="window.print()">
			<i class="fa fa-print"></i> Imprimer l'ordonnance
		</button>
		&nbsp;
		<button type="button" class="btn-print" style="background: #6c757d;" onclick="window.close()">
			<i class="fa fa-times"></i> Fermer
		</button>
	</div>

	<script>
		// Ouverture automatique de la boîte d'impression pour faciliter la remise au patient
		if (window.location.search.indexOf('auto=1') !== -1) {
			window.onload = function() { window.print(); };
		}
	</script>
<?php endif; ?>
</body>
</html>
