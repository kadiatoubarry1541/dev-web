<?php
/**
 * Demandes transmises par l'accueil : le médecin ne fait que confirmer.
 * Le patient a choisi son médecin ; à la confirmation, le RDV est créé avec le médecin connecté.
 */
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';
require_once '../config/traitement.php';

requireLogin('../login.php');
requireMedecin('../index.php');

$user_info = getUserInfo();
$id_med = $user_info['id_med'] ?? null;
$specialisation = $user_info['specialisation'] ?? '';
$id_service = null;
$demandes = [];
$message = '';
$message_type = '';
$erreur_par_demande = [];

if ($id_med && empty($specialisation)) {
    $pdo = bdd();
    $st = $pdo->prepare("SELECT Spécialisation_med FROM MEDECINS WHERE id_med = ? LIMIT 1");
    $st->execute([$id_med]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['Spécialisation_med'])) {
        $specialisation = $row['Spécialisation_med'];
        $_SESSION['specialisation'] = $specialisation;
    }
}
if (!empty($specialisation) && function_exists('getIdServiceByNom')) {
    $id_service = getIdServiceByNom($specialisation);
}
if ($id_service) {
    $demandes = function_exists('getDemandesEnAttenteService') ? getDemandesEnAttenteService($id_service) : [];
}

// Confirmer une demande : le médecin ne fait que confirmer. Le patient est trouvé ou créé automatiquement à partir de la demande (aucune erreur "patient introuvable").
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmer_demande']) && $id_service) {
    $id_demande = (int)($_POST['id_demande'] ?? 0);
    $id_med_choisi = $id_med;
    if (!$id_demande) {
        $message = "Demande invalide.";
        $message_type = "danger";
    } else {
        // On passe id_patient=0 : traiterDemandeRendezVous trouve ou crée le patient à partir de la demande, puis crée le RDV
        $result = traiterDemandeRendezVous($id_demande, 0, $id_med_choisi);
        $ok = is_array($result) ? ($result['success'] ?? false) : (bool)$result;
        $msg = (is_array($result) && isset($result['message'])) ? $result['message'] : ($ok ? "Rendez-vous créé." : "Erreur.");
        if ($ok) {
            $message = $msg;
            $message_type = "success";
            $demandes = getDemandesEnAttenteService($id_service);
        } else {
            $erreur_par_demande[$id_demande] = $msg;
            $demandes = getDemandesEnAttenteService($id_service);
        }
    }
    if (!isset($demandes) || !is_array($demandes)) {
        $demandes = getDemandesEnAttenteService($id_service);
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Demandes à confirmer - Service</title>
    <link rel="stylesheet" type="text/css" href="../assets/css/plugins.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .demandes-container { padding: 40px 0; background: #f8f9fa; min-height: 100vh; }
        .form-card { background: #fff; border-radius: 12px; padding: 40px; margin: 0 auto; box-shadow: 0 4px 20px rgba(0,0,0,0.08); max-width: 960px; }
        .page-header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #e2e8f0; }
        .page-header h1 { color: #002939; font-size: 28px; font-weight: 700; margin-bottom: 8px; }
        .page-header p { color: #666; font-size: 15px; }
        .btn-retour { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .btn-retour:hover { color: white; text-decoration: none; }
        .demande-card { background: #f8f9fa; border-radius: 10px; padding: 20px; margin-bottom: 20px; border-left: 4px solid #28a745; }
        .demande-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 16px; font-size: 14px; color: #555; }
        .demande-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 16px; }
        .btn-confirmer { background: #28a745; color: white; border: none; padding: 10px 18px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; }
        .btn-confirmer:hover { background: #218838; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .no-data { text-align: center; padding: 40px 20px; color: #666; }
    </style>
</head>
<body>
<div class="page-wraper">
    <?php require_once '../partials/entete.php'; ?>
    <div class="demandes-container">
        <div class="container">
            <a href="index.php" class="btn-retour"><i class="fa fa-arrow-left"></i> Retour</a>
            <div class="form-card">
                <div class="page-header">
                    <h1><i class="fa fa-inbox"></i> Demandes à confirmer</h1>
                    <p>Ces demandes ont été <strong>transmises par l'accueil</strong>. Le patient a choisi son médecin. Vous ne faites que <strong>confirmer</strong> la demande pour créer le rendez-vous.</p>
                </div>
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <i class="fa fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                <?php if (!$id_service): ?>
                    <div class="no-data"><p>Service non déterminé. Impossible d'afficher les demandes.</p></div>
                <?php elseif (empty($demandes)): ?>
                    <div class="no-data">
                        <i class="fa fa-check-circle" style="font-size:48px;color:#28a745;"></i>
                        <p style="margin-top:15px;">Aucune demande en attente pour votre service.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($demandes as $d): ?>
                        <div class="demande-card">
                            <?php if (!empty($erreur_par_demande[$d['id_demande']])): ?>
                                <div class="alert alert-danger" style="margin-bottom:12px;font-size:13px;"><?php echo htmlspecialchars($erreur_par_demande[$d['id_demande']]); ?></div>
                            <?php endif; ?>
                            <h3>Demande du <?php echo date('d/m/Y à H:i', strtotime($d['Date_rdv_souhaitee'])); ?> — <?php echo htmlspecialchars($d['Nom_service'] ?? 'Service'); ?></h3>
                            <div class="demande-meta">
                                <span><strong>Nom :</strong> <?php echo htmlspecialchars($d['nom_demandeur'] ?? '—'); ?></span>
                                <span><strong>Email :</strong> <?php echo htmlspecialchars($d['email_demandeur'] ?? '—'); ?></span>
                                <span><strong>Matricule :</strong> <?php echo htmlspecialchars($d['matricule_demandeur'] ?? '—'); ?></span>
                                <?php if (!empty($d['motif'])): ?>
                                    <span><strong>Motif :</strong> <?php echo htmlspecialchars(mb_substr($d['motif'], 0, 80)); ?><?php echo mb_strlen($d['motif']) > 80 ? '…' : ''; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="demande-actions">
                                <form method="post" style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;">
                                    <input type="hidden" name="id_demande" value="<?php echo (int)$d['id_demande']; ?>">
                                    <button type="submit" name="confirmer_demande" class="btn-confirmer"><i class="fa fa-check-circle"></i> Confirmer</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php require_once 'partials/footer.php'; ?>
</div>
</body>
</html>
