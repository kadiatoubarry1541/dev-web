<?php
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';

requireLogin('login.php');
requireMedecin('index.php');

$user_info      = getUserInfo();
$id_med         = $user_info['id_med'] ?? null;
$specialisation = $user_info['specialisation'] ?? '';

$patients_rdv = [];
$message      = '';
$message_type = '';

if ($id_med) {
    try {
        // Récupérer la spécialisation depuis la base si absente en session
        if (empty($specialisation)) {
            $pdo = bdd();
            $st  = $pdo->prepare("SELECT Spécialisation_med FROM MEDECINS WHERE id_med = ? LIMIT 1");
            $st->execute([$id_med]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['Spécialisation_med'])) {
                $specialisation            = $row['Spécialisation_med'];
                $_SESSION['specialisation'] = $specialisation;
            }
        }

        if ($specialisation) {
            $id_service = getIdServiceByNom($specialisation);
            if ($id_service) {
                $pdo = bdd();

                // 1) Patients ayant AU MOINS un rendez-vous réel (confirmé/terminé) dans ce service
                //    On récupère le rendez-vous le plus récent par patient pour afficher date/heure.
                $sql = "SELECT r.*, p.*
                        FROM RENDEZ_VOUS r
                        INNER JOIN PATIENTS p ON p.id_patient = r.id_patient
                        WHERE r.id_service = ?
                          AND LOWER(TRIM(r.Statut)) IN ('confirmé', 'confirme', 'terminé', 'termine')
                        ORDER BY r.id_patient, r.Date_rdv DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_service]);
                $rows_rdv = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                // Garder seulement le dernier rendez-vous par patient
                $patients_rdv      = [];
                $patients_rdv_byId = [];
                foreach ($rows_rdv as $row) {
                    $id_p = (int)($row['id_patient'] ?? 0);
                    if ($id_p < 1) {
                        continue;
                    }
                    if (!isset($patients_rdv_byId[$id_p])) {
                        $patients_rdv_byId[$id_p] = [
                            'id_patient'        => $row['id_patient'],
                            'Nom_patient'       => $row['Nom_patient'] ?? '',
                            'Prénom_patient'    => $row['Prénom_patient'] ?? '',
                            'Matricule_patient' => $row['Matricule_patient'] ?? '',
                            'Email_patient'     => $row['Email_patient'] ?? '',
                            'Tel_patient'       => $row['Tel_patient'] ?? null,
                            'Photo_profil'      => $row['Photo_profil'] ?? null,
                            'Date_rdv'          => $row['Date_rdv'] ?? null,
                        ];
                    }
                }
                $patients_rdv = array_values($patients_rdv_byId);

                // Préparer un index (email + matricule) pour éviter les doublons
                $patients_keys = [];
                foreach ($patients_rdv as $p) {
                    $email_p = strtolower(trim($p['Email_patient'] ?? ''));
                    $mat_p   = preg_replace('/\s+/', '', strtoupper($p['Matricule_patient'] ?? ''));
                    $key     = $email_p . '|' . $mat_p;
                    if ($key !== '|') {
                        $patients_keys[$key] = true;
                    }
                }

                // 2) Compléter avec les demandes "traitee" du service qui n'ont pas forcément de RDV
                try {
                    $sql_dem = "SELECT d.*
                                FROM DEMANDE_RENDEZ_VOUS d
                                LEFT JOIN RENDEZ_VOUS r 
                                    ON r.Date_rdv = d.Date_rdv_souhaitee
                                   AND (r.id_service = d.id_service OR (r.id_service IS NULL AND d.id_service IS NULL))
                                WHERE d.id_service = ?
                                  AND d.statut = 'traitee'";
                    $stmt_dem = $pdo->prepare($sql_dem);
                    $stmt_dem->execute([$id_service]);
                    $demandes = $stmt_dem->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    foreach ($demandes as $d) {
                        $email_d = strtolower(trim($d['email_demandeur'] ?? ''));
                        $mat_d   = preg_replace('/\s+/', '', strtoupper($d['matricule_demandeur'] ?? ''));
                        $key_d   = $email_d . '|' . $mat_d;

                        if ($key_d === '|' || isset($patients_keys[$key_d])) {
                            continue;
                        }
                        $patients_keys[$key_d] = true;

                        $patients_rdv[] = [
                            'id_patient'        => null,
                            'Nom_patient'       => $d['nom_demandeur'] ?? '',
                            'Prénom_patient'    => '',
                            'Matricule_patient' => $d['matricule_demandeur'] ?? '',
                            'Email_patient'     => $d['email_demandeur'] ?? '',
                            'Tel_patient'       => $d['telephone_demandeur'] ?? ($d['tel_demandeur'] ?? null),
                            'Photo_profil'      => null,
                            'Date_rdv'          => $d['Date_rdv_souhaitee'] ?? null,
                        ];
                    }
                } catch (Exception $e_dem) {
                    error_log("patients-rendez-vous (demandes traitee): " . $e_dem->getMessage());
                }
            } else {
                $message      = "Impossible de déterminer votre service à partir de votre spécialisation.";
                $message_type = "warning";
            }
        } else {
            $message      = "Votre spécialisation (service) n'est pas définie. Veuillez contacter l'administrateur.";
            $message_type = "warning";
        }
    } catch (Exception $e) {
        error_log("Erreur patients-rendez-vous: " . $e->getMessage());
        $message      = "Une erreur est survenue lors de la récupération des patients avec rendez-vous.";
        $message_type = "danger";
    }
}

$page_title = 'Patients avec rendez-vous de mon service';
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
	.empty-state { text-align: center; padding: 60px 20px; color: #718096; }
	.info-box { background: white; border-left: 4px solid #4A90E2; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
</style>

<a href="index.php" style="color: #4A90E2; text-decoration: none; margin-bottom: 20px; display: inline-block;">
	<i class="fas fa-arrow-left"></i> Retour au tableau de bord
</a>
<h2 style="color: #1e3a5f; margin-bottom: 20px; font-size: 22px;">
	<i class="fas fa-users"></i> Patients ayant un rendez-vous dans mon service
</h2>

<div class="info-box">
	<strong>Service :</strong> <?php echo htmlspecialchars($specialisation ?: '—'); ?> |
	<strong>Total :</strong> <?php echo count($patients_rdv); ?> patient(s) avec au moins un rendez-vous confirmé/terminé
</div>

<?php if ($message): ?>
	<div class="alert alert-<?php echo $message_type; ?>">
		<?php echo htmlspecialchars($message); ?>
	</div>
<?php endif; ?>

<?php if (empty($patients_rdv)): ?>
	<div class="empty-state">
		<i class="fas fa-users" style="font-size: 64px; color: #e2e8f0; margin-bottom: 20px;"></i>
		<h3>Aucun patient avec rendez-vous confirmé pour le moment</h3>
		<p>Les patients ayant au moins un rendez-vous confirmé ou terminé dans votre service apparaîtront dans cette liste.</p>
	</div>
<?php else: ?>
	<?php foreach ($patients_rdv as $patient): ?>
		<div class="patient-card">
			<img src="<?php echo htmlspecialchars(isset($patient['Photo_profil']) && !empty($patient['Photo_profil']) ? '../' . $patient['Photo_profil'] : '../image/1.jpeg'); ?>"
				 alt="Photo" class="patient-photo"
				 onerror="this.src='../image/1.jpeg'">
			<div class="patient-info">
				<div class="patient-name">
					<?php echo htmlspecialchars(($patient['Prénom_patient'] ?? '') . ' ' . ($patient['Nom_patient'] ?? '')); ?>
				</div>
                <?php if (!empty($patient['Date_rdv'])): ?>
                    <div class="patient-details">
                        <i class="fas fa-calendar"></i>
                        <strong>Dernier rendez-vous :</strong>
                        <?php echo date('d/m/Y', strtotime($patient['Date_rdv'])); ?>
                        à
                        <?php echo date('H:i', strtotime($patient['Date_rdv'])); ?>
                    </div>
                <?php endif; ?>
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
			<?php if (!empty($patient['id_patient'])): ?>
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

