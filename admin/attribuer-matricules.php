<?php
/**
 * Page admin pour attribuer les matricules aux patients et médecins
 * Seuls les administrateurs peuvent accéder à cette page
 */

require_once 'partials/header.php';
require_once '../config/bdd.php';
require_once '../config/traitement.php';

$message = '';
$message_type = '';

// Traiter l'attribution de matricule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'attribuer_patient') {
        $id_patient = intval($_POST['id_patient'] ?? 0);
        $matricule_custom = trim($_POST['matricule_custom'] ?? '');
        
        if ($id_patient > 0) {
            try {
                // Si un matricule personnalisé est fourni, l'utiliser, sinon générer automatiquement
                $matricule = !empty($matricule_custom) ? $matricule_custom : null;
                $matricule_attribue = attribuerMatriculePatient($id_patient, $matricule);
                
                $message = "Matricule attribué avec succès : <strong>" . htmlspecialchars($matricule_attribue) . "</strong>";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "Erreur : " . $e->getMessage();
                $message_type = "danger";
            }
        }
    } elseif ($action === 'attribuer_medecin') {
        $id_med = intval($_POST['id_med'] ?? 0);
        $matricule_custom = trim($_POST['matricule_custom'] ?? '');
        
        if ($id_med > 0) {
            try {
                // Si un matricule personnalisé est fourni, l'utiliser, sinon générer automatiquement
                $matricule = !empty($matricule_custom) ? $matricule_custom : null;
                $matricule_attribue = attribuerMatriculeMedecin($id_med, $matricule);
                
                $message = "Matricule attribué avec succès : <strong>" . htmlspecialchars($matricule_attribue) . "</strong>";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "Erreur : " . $e->getMessage();
                $message_type = "danger";
            }
        }
    }
}

// Récupérer les patients sans matricule
$patients_sans_matricule = [];
try {
    $pdo = bdd();
    $stmt = $pdo->query("SELECT * FROM PATIENTS WHERE Matricule_patient IS NULL ORDER BY Date_creation DESC");
    $patients_sans_matricule = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erreur récupération patients: " . $e->getMessage());
}

// Récupérer les médecins sans matricule
$medecins_sans_matricule = [];
try {
    $pdo = bdd();
    $stmt = $pdo->query("SELECT * FROM MEDECINS WHERE Matricule_med IS NULL ORDER BY Date_creation DESC");
    $medecins_sans_matricule = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erreur récupération médecins: " . $e->getMessage());
}
?>

<style>
    .matricule-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 20px;
    }
    
    .page-header {
        margin-bottom: 30px;
    }
    
    .page-header h1 {
        font-size: 32px;
        font-weight: 700;
        color: #1e3a5f;
        margin-bottom: 10px;
    }
    
    .page-header p {
        color: #718096;
        font-size: 16px;
    }
    
    .alert {
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        gap: 15px;
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
    
    .section {
        background: white;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .section-title {
        font-size: 24px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .section-count {
        background: #4A90E2;
        color: white;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 600;
    }
    
    .user-card {
        background: #f7fafc;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 20px;
        border-left: 4px solid #4A90E2;
    }
    
    .user-photo {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #4A90E2;
        flex-shrink: 0;
    }
    
    .user-info {
        flex: 1;
    }
    
    .user-name {
        font-size: 18px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 5px;
    }
    
    .user-details {
        color: #718096;
        font-size: 14px;
        margin-bottom: 3px;
    }
    
    .user-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .matricule-input {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
        width: 200px;
    }
    
    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary {
        background: #4A90E2;
        color: white;
    }
    
    .btn-primary:hover {
        background: #357abd;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(74, 144, 226, 0.4);
    }
    
    .btn-auto {
        background: #28a745;
        color: white;
    }
    
    .btn-auto:hover {
        background: #218838;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #718096;
    }
    
    .empty-state i {
        font-size: 64px;
        margin-bottom: 20px;
        color: #cbd5e0;
    }
    
    .empty-state p {
        font-size: 16px;
        margin: 0;
    }
    
    .form-inline {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .help-text {
        font-size: 12px;
        color: #718096;
        margin-top: 5px;
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

<div class="matricule-container">
    <a href="index.php" class="btn-retour">
        <i class="fas fa-arrow-left"></i> Retour au tableau de bord
    </a>
    <div class="page-header">
        <h1><i class="fas fa-id-card"></i> Attribution des Matricules</h1>
        <p>Gérez et attribuez les matricules aux patients et médecins. Les matricules sont générés automatiquement si vous n'en spécifiez pas un personnalisé.</p>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <div><?php echo $message; ?></div>
        </div>
    <?php endif; ?>
    
    <!-- Section Patients -->
    <div class="section">
        <div class="section-title">
            <i class="fas fa-users"></i>
            <span>Patients sans matricule (anciens patients)</span>
            <?php if (count($patients_sans_matricule) > 0): ?>
                <span class="section-count"><?php echo count($patients_sans_matricule); ?></span>
            <?php endif; ?>
        </div>
        
        <div style="background: #e3f2fd; border-left: 4px solid #2196F3; padding: 15px; margin-bottom: 20px; border-radius: 6px;">
            <i class="fas fa-info-circle" style="color: #2196F3; margin-right: 8px;"></i>
            <strong>Note :</strong> Les nouveaux patients reçoivent automatiquement leur matricule lors de l'inscription. Cette section affiche uniquement les anciens patients créés avant cette modification.
        </div>
        
        <?php if (empty($patients_sans_matricule)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <p>Tous les patients ont un matricule attribué.</p>
            </div>
        <?php else: ?>
            <?php foreach ($patients_sans_matricule as $patient): ?>
                <div class="user-card">
                    <img src="<?php echo htmlspecialchars(isset($patient['Photo_profil']) && !empty($patient['Photo_profil']) ? '../' . $patient['Photo_profil'] : '../image/1.jpeg'); ?>" 
                         alt="Photo" class="user-photo" 
                         onerror="this.src='../image/1.jpeg'">
                    <div class="user-info">
                        <div class="user-name">
                            <?php echo htmlspecialchars($patient['Nom_patient'] . ' ' . $patient['Prénom_patient']); ?>
                        </div>
                        <div class="user-details">
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['Email_patient'] ?? 'Non renseigné'); ?>
                        </div>
                        <div class="user-details">
                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['Tel_patient'] ?? 'Non renseigné'); ?>
                        </div>
                        <div class="user-details">
                            <i class="fas fa-calendar"></i> Inscrit le <?php echo date('d/m/Y à H:i', strtotime($patient['Date_creation'])); ?>
                        </div>
                    </div>
                    <div class="user-actions">
                        <form method="post" action="" class="form-inline" style="margin: 0;">
                            <input type="hidden" name="action" value="attribuer_patient">
                            <input type="hidden" name="id_patient" value="<?php echo $patient['id_patient']; ?>">
                            <input type="text" name="matricule_custom" class="matricule-input" 
                                   placeholder="Auto (PAT + date + num)" 
                                   pattern="PAT\d{8}\d{4}" 
                                   title="Format: PAT + YYYYMMDD + 4 chiffres (ex: PAT202601230001)">
                            <button type="submit" class="btn btn-primary" name="attribuer">
                                <i class="fas fa-id-card"></i> Attribuer
                            </button>
                            <button type="submit" class="btn btn-auto" name="attribuer_auto" 
                                    onclick="this.form.querySelector('input[name=matricule_custom]').value='';">
                                <i class="fas fa-magic"></i> Auto
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Section Médecins -->
    <div class="section">
        <div class="section-title">
            <i class="fas fa-user-md"></i>
            <span>Médecins sans matricule</span>
            <?php if (count($medecins_sans_matricule) > 0): ?>
                <span class="section-count"><?php echo count($medecins_sans_matricule); ?></span>
            <?php endif; ?>
        </div>
        
        <?php if (empty($medecins_sans_matricule)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <p>Tous les médecins ont un matricule attribué.</p>
            </div>
        <?php else: ?>
            <?php foreach ($medecins_sans_matricule as $medecin): ?>
                <div class="user-card">
                    <img src="<?php echo htmlspecialchars(isset($medecin['Photo_profil']) && !empty($medecin['Photo_profil']) ? '../' . $medecin['Photo_profil'] : '../image/1.jpeg'); ?>" 
                         alt="Photo" class="user-photo" 
                         onerror="this.src='../image/1.jpeg'">
                    <div class="user-info">
                        <div class="user-name">
                            Dr. <?php echo htmlspecialchars($medecin['Prénom_med'] . ' ' . $medecin['Nom_med']); ?>
                        </div>
                        <div class="user-details">
                            <i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($medecin['Spécialisation_med']); ?>
                        </div>
                        <div class="user-details">
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($medecin['Email_med'] ?? 'Non renseigné'); ?>
                        </div>
                        <div class="user-details">
                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($medecin['Tel_med'] ?? 'Non renseigné'); ?>
                        </div>
                        <div class="user-details">
                            <i class="fas fa-info-circle"></i> Statut: 
                            <span style="color: <?php echo $medecin['statut'] === 'approuvé' ? '#28a745' : ($medecin['statut'] === 'en_attente' ? '#ffc107' : '#dc3545'); ?>;">
                                <?php echo ucfirst($medecin['statut']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="user-actions">
                        <form method="post" action="" class="form-inline" style="margin: 0;">
                            <input type="hidden" name="action" value="attribuer_medecin">
                            <input type="hidden" name="id_med" value="<?php echo $medecin['id_med']; ?>">
                            <input type="text" name="matricule_custom" class="matricule-input" 
                                   placeholder="Auto (MED + date + num)" 
                                   pattern="MED\d{8}\d{4}" 
                                   title="Format: MED + YYYYMMDD + 4 chiffres (ex: MED202601230001)">
                            <button type="submit" class="btn btn-primary" name="attribuer">
                                <i class="fas fa-id-card"></i> Attribuer
                            </button>
                            <button type="submit" class="btn btn-auto" name="attribuer_auto" 
                                    onclick="this.form.querySelector('input[name=matricule_custom]').value='';">
                                <i class="fas fa-magic"></i> Auto
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'partials/footer.php'; ?>
