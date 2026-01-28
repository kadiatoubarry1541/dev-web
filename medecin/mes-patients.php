<?php
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';

requireLogin('login.php');
requireMedecin('index.php');

$user_info = getUserInfo();
$id_med = $user_info['id_med'];
$specialisation = $user_info['specialisation'] ?? '';

$mes_patients = [];
$message = '';
$message_type = '';

if ($id_med) {
    try {
        if ($specialisation) {
            $id_service = getIdServiceByNom($specialisation);
            $mes_patients = $id_service ? getPatientsDuService($id_service) : [];

            // OPTION B : compléter la liste avec les demandes « traitées » sans RDV réel
            if ($id_service) {
                try {
                    $pdo = bdd();
                    // Indexer les patients déjà présents pour éviter les doublons (email + matricule normalisé)
                    $patients_keys = [];
                    foreach ($mes_patients as $p) {
                        $email_p = strtolower(trim($p['Email_patient'] ?? ''));
                        $mat_p = preg_replace('/\s+/', '', strtoupper($p['Matricule_patient'] ?? ''));
                        $key = $email_p . '|' . $mat_p;
                        if ($key !== '|') {
                            $patients_keys[$key] = true;
                        }
                    }

                    $sql_dem = "SELECT d.*
                                FROM DEMANDE_RENDEZ_VOUS d
                                LEFT JOIN RENDEZ_VOUS r 
                                    ON r.Date_rdv = d.Date_rdv_souhaitee
                                   AND (r.id_service = d.id_service OR (r.id_service IS NULL AND d.id_service IS NULL))
                                WHERE d.id_service = ?
                                  AND d.statut = 'traitee'
                                  AND r.id_rdv IS NULL";
                    $stmt_dem = $pdo->prepare($sql_dem);
                    $stmt_dem->execute([$id_service]);
                    $demandes_traitees = $stmt_dem->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($demandes_traitees as $d) {
                        $email_d = strtolower(trim($d['email_demandeur'] ?? ''));
                        $mat_d = preg_replace('/\s+/', '', strtoupper($d['matricule_demandeur'] ?? ''));
                        $key_d = $email_d . '|' . $mat_d;

                        // Si le patient n'est pas déjà dans la liste, on l'ajoute comme « patient virtuel »
                        if ($key_d !== '|' && !isset($patients_keys[$key_d])) {
                            $patients_keys[$key_d] = true;
                            $mes_patients[] = [
                                // pas encore de vrai dossier patient
                                'id_patient'        => null,
                                'Nom_patient'       => $d['nom_demandeur'] ?? '',
                                'Prénom_patient'    => '',
                                'Matricule_patient' => $d['matricule_demandeur'] ?? '',
                                'Email_patient'     => $d['email_demandeur'] ?? '',
                                // champ facultatif : dépend du schéma de DEMANDE_RENDEZ_VOUS
                                'Tel_patient'       => $d['telephone_demandeur'] ?? ($d['tel_demandeur'] ?? null),
                                'Photo_profil'      => null,
                            ];
                        }
                    }
                } catch (Exception $e_dem) {
                    // On ne bloque pas la page si la récupération des demandes échoue
                    error_log("mes-patients (demandes traitee sans RDV): " . $e_dem->getMessage());
                }
            }
        } else {
            $message = "Votre spécialisation (service) n'est pas définie. Veuillez contacter l'administrateur.";
            $message_type = "warning";
        }
    } catch (Exception $e) {
        error_log("Erreur mes-patients: " . $e->getMessage());
        $message = "Une erreur est survenue lors de la récupération des patients.";
        $message_type = "danger";
    }
}
$page_title = 'Patients de mon service';
require_once __DIR__ . '/partials/header.php';
?>
<style>
	.patient-card {
		background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 20px;
		box-shadow: 0 2px 8px rgba(0,0,0,0.08);
		display: flex; align-items: center; gap: 20px;
	}
	.patient-photo { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #4A90E2; }
	.patient-info { flex: 1; }
	.patient-name { font-size: 18px; font-weight: 700; color: #2d3748; margin-bottom: 5px; }
	.patient-details { color: #718096; margin-bottom: 3px; font-size: 14px; }
	.btn-action { padding: 8px 15px; border: none; border-radius: 8px; font-size: 14px; text-decoration: none; display: inline-block; margin-right: 10px; background: #4A90E2; color: white; transition: all 0.3s; }
	.btn-action:hover { background: #357ABD; }
	.alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
	.alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
	.empty-state { text-align: center; padding: 60px 20px; color: #718096; }
	.info-box { background: white; border-left: 4px solid #4A90E2; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
</style>

<a href="index.php" style="color: #4A90E2; text-decoration: none; margin-bottom: 20px; display: inline-block;">
	<i class="fas fa-arrow-left"></i> Retour au tableau de bord
</a>
<h2 style="color: #1e3a5f; margin-bottom: 20px; font-size: 22px;">
	<i class="fas fa-users"></i> Patients de mon service
</h2>
					
<div class="info-box">
	<strong>Service :</strong> <?php echo htmlspecialchars($specialisation ?: '—'); ?> | 
	<strong>Total :</strong> <?php echo count($mes_patients); ?> patient(s) (inscrits ou ayant eu un rendez-vous dans ce service)
</div>

<?php if ($message): ?>
	<div class="alert alert-<?php echo $message_type; ?>">
		<?php echo htmlspecialchars($message); ?>
	</div>
<?php endif; ?>

<?php if (empty($mes_patients)): ?>
	<div class="empty-state">
		<i class="fas fa-users" style="font-size: 64px; color: #e2e8f0; margin-bottom: 20px;"></i>
		<h3>Aucun patient dans votre service pour le moment</h3>
		<p>Les patients de votre service (inscrits ou ayant pris rendez-vous ici) apparaîtront dans cette liste.</p>
	</div>
<?php else: ?>
	<?php foreach ($mes_patients as $patient): ?>
		<div class="patient-card">
			<img src="<?php echo htmlspecialchars(isset($patient['Photo_profil']) && !empty($patient['Photo_profil']) ? '../' . $patient['Photo_profil'] : '../image/1.jpeg'); ?>" 
				 alt="Photo" class="patient-photo" 
				 onerror="this.src='../image/1.jpeg'">
			<div class="patient-info">
				<div class="patient-name">
					<?php echo htmlspecialchars(($patient['Prénom_patient'] ?? '') . ' ' . ($patient['Nom_patient'] ?? '')); ?>
				</div>
				<?php if (isset($patient['Matricule_patient'])): ?>
					<div class="patient-details">
						<i class="fas fa-id-card"></i> <strong>Matricule :</strong> <?php echo htmlspecialchars($patient['Matricule_patient']); ?>
					</div>
				<?php endif; ?>
				<div class="patient-details">
					<i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['Email_patient'] ?? 'Non renseigné'); ?>
				</div>
				<div class="patient-details">
					<i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['Tel_patient'] ?? 'Non renseigné'); ?>
				</div>
			</div>
			<?php if (isset($patient['id_patient'])): ?>
				<div>
					<a href="voir-carnet.php?id_patient=<?php echo (int)$patient['id_patient']; ?>" class="btn-action">
						<i class="fas fa-eye"></i> Voir le dossier
					</a>
				</div>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
