<?php
require_once 'partials/header.php';
require_once '../config/bdd.php';
require_once '../config/traitement.php';

$message = '';
$message_type = '';

// Traiter l'approbation/refus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id_med = intval($_POST['id_med'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($id_med > 0 && in_array($action, ['approuver', 'refuser'])) {
        try {
            $pdo = bdd();
            $pdo->beginTransaction();
            
            if ($action === 'approuver') {
                // Vérifier si le médecin a déjà un matricule
                $check_matricule = $pdo->prepare("SELECT Matricule_med FROM MEDECINS WHERE id_med = ?");
                $check_matricule->execute([$id_med]);
                $medecin = $check_matricule->fetch();
                
                // Si pas de matricule, en attribuer un
                if (empty($medecin['Matricule_med'])) {
                    // Passer $pdo pour rester dans la même transaction et éviter
                    // les problèmes de verrouillage (Lock wait timeout)
                    $matricule = attribuerMatriculeMedecin($id_med, null, $pdo);
                } else {
                    $matricule = $medecin['Matricule_med'];
                }
                
                // Mettre à jour le statut
                $statut = 'approuvé';
                $stmt = $pdo->prepare("UPDATE MEDECINS SET statut = ? WHERE id_med = ?");
                $stmt->execute([$statut, $id_med]);
                
                $pdo->commit();
                
                $message = "Le médecin a été approuvé avec succès ! Matricule attribué : <strong>" . htmlspecialchars($matricule) . "</strong>. Il peut maintenant se connecter et apparaîtra dans la page 'Docteurs'.";
                $message_type = "success";
                // Rediriger pour éviter la double soumission
                header("Location: approuver-medecins.php?success=1&matricule=" . urlencode($matricule));
                exit();
            } else {
                $statut = 'refusé';
                $stmt = $pdo->prepare("UPDATE MEDECINS SET statut = ? WHERE id_med = ?");
                $stmt->execute([$statut, $id_med]);
                
                $pdo->commit();
                
                $message = "La demande du médecin a été refusée.";
                $message_type = "warning";
                // Rediriger pour éviter la double soumission
                header("Location: approuver-medecins.php?refused=1");
                exit();
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Erreur : " . $e->getMessage();
            $message_type = "danger";
            error_log("Erreur approbation médecin: " . $e->getMessage());
        }
    }
}

// Afficher les messages de succès/refus depuis l'URL
if (isset($_GET['success'])) {
    $matricule = $_GET['matricule'] ?? '';
    if (!empty($matricule)) {
        $message = "Le médecin a été approuvé avec succès ! Matricule attribué : <strong>" . htmlspecialchars($matricule) . "</strong>. Il peut maintenant se connecter et apparaîtra dans la page 'Docteurs'.";
    } else {
        $message = "Le médecin a été approuvé avec succès ! Il peut maintenant se connecter et apparaîtra dans la page 'Docteurs'.";
    }
    $message_type = "success";
}
if (isset($_GET['refused'])) {
    $message = "La demande du médecin a été refusée.";
    $message_type = "warning";
}

// Récupérer tous les médecins en attente
$medecins_en_attente = [];
$count_en_attente = 0;
try {
    $pdo = bdd();
    
    // Récupérer les médecins en attente, trier par id_med DESC (les plus récents en premier)
    $stmt = $pdo->query("SELECT m.*, s.Nom_service 
                         FROM MEDECINS m 
                         LEFT JOIN SERVICES s ON m.Spécialisation_med = s.Nom_service 
                         WHERE m.statut = 'en_attente' 
                         ORDER BY m.id_med DESC");
    $medecins_en_attente = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count_en_attente = count($medecins_en_attente);
} catch (Exception $e) {
    $message = "Erreur lors de la récupération des médecins : " . $e->getMessage();
    $message_type = "danger";
    error_log("Erreur approuver-medecins: " . $e->getMessage());
}
?>

<style>
    .approval-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 20px;
    }
    
    .page-header {
        margin-bottom: 30px;
    }
    
    .page-header h1 {
        font-size: 28px;
        font-weight: 700;
        color: #1e3a5f;
        margin-bottom: 10px;
    }
    
    .page-header p {
        color: #718096;
        font-size: 15px;
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
    
    .alert-warning {
        background: #fff3cd;
        border: 1px solid #ffc107;
        color: #856404;
    }
    
    .alert-danger {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
    }
    
    .alert-icon {
        font-size: 32px;
    }
    
    .medecin-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s;
        border-left: 4px solid #ffc107;
    }
    
    .medecin-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    
    .medecin-photo {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #4A90E2;
        flex-shrink: 0;
    }
    
    .medecin-info {
        flex: 1;
    }
    
    .medecin-name {
        font-size: 22px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 12px;
    }
    
    .medecin-details {
        color: #718096;
        margin-bottom: 8px;
        font-size: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .medecin-details i {
        color: #4A90E2;
        width: 20px;
    }
    
    .medecin-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
        flex-shrink: 0;
    }
    
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        font-size: 15px;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        min-width: 140px;
        justify-content: center;
    }
    
    .btn-approve {
        background: #28a745;
        color: white;
    }
    
    .btn-approve:hover {
        background: #218838;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
    }
    
    .btn-refuse {
        background: #dc3545;
        color: white;
    }
    
    .btn-refuse:hover {
        background: #c82333;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
    }
    
    .empty-state {
        text-align: center;
        padding: 80px 20px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .empty-state i {
        font-size: 80px;
        color: #cbd5e0;
        margin-bottom: 20px;
    }
    
    .empty-state h2 {
        font-size: 24px;
        color: #2d3748;
        margin-bottom: 10px;
    }
    
    .empty-state p {
        color: #718096;
        font-size: 16px;
    }
    
    @media (max-width: 768px) {
        .medecin-card {
            flex-direction: column;
            text-align: center;
        }
        
        .medecin-actions {
            flex-direction: row;
            width: 100%;
        }
        
        .btn {
            flex: 1;
        }
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

<div class="approval-container">
    <a href="index.php" class="btn-retour">
        <i class="fas fa-arrow-left"></i> Retour au tableau de bord
    </a>
    <div class="page-header">
        <h1><i class="fas fa-user-md"></i> Approuver les Médecins</h1>
        <p>Examinez et approuvez les demandes d'inscription des médecins</p>
    </div>
    
    <?php if ($count_en_attente > 0): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle alert-icon"></i>
            <div style="flex: 1;">
                <strong style="font-size: 18px; display: block; margin-bottom: 5px;">
                    <?php echo $count_en_attente; ?> médecin(s) en attente d'approbation
                </strong>
                <p style="margin: 0;">Approuvez les médecins pour qu'ils puissent se connecter et apparaître dans la page "Docteurs" pour que les patients puissent les choisir pour un rendez-vous.</p>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'danger' ? 'exclamation-circle' : 'info-circle'); ?> alert-icon"></i>
            <div><?php echo htmlspecialchars($message); ?></div>
        </div>
    <?php endif; ?>
    
    <?php if (empty($medecins_en_attente)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <h2>Aucune demande en attente</h2>
            <p>Tous les médecins ont été traités.</p>
        </div>
    <?php else: ?>
        <?php foreach ($medecins_en_attente as $medecin): ?>
            <div class="medecin-card">
                <img src="<?php echo htmlspecialchars(isset($medecin['Photo_profil']) && !empty($medecin['Photo_profil']) ? '../' . $medecin['Photo_profil'] : '../image/1.jpeg'); ?>" 
                     alt="Photo" class="medecin-photo" 
                     onerror="this.src='../image/1.jpeg'">
                <div class="medecin-info">
                    <div class="medecin-name">
                        Dr. <?php echo htmlspecialchars($medecin['Prénom_med'] . ' ' . $medecin['Nom_med']); ?>
                    </div>
                    <div class="medecin-details">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo htmlspecialchars($medecin['Email_med']); ?></span>
                    </div>
                    <div class="medecin-details">
                        <i class="fas fa-phone"></i>
                        <span><?php echo htmlspecialchars($medecin['Tel_med'] ?? 'Non renseigné'); ?></span>
                    </div>
                    <div class="medecin-details">
                        <i class="fas fa-stethoscope"></i>
                        <span><strong>Spécialisation :</strong> <?php echo htmlspecialchars($medecin['Spécialisation_med']); ?></span>
                    </div>
                    <?php if (isset($medecin['Date_creation'])): ?>
                        <div class="medecin-details">
                            <i class="fas fa-calendar"></i>
                            <span><strong>Date de demande :</strong> <?php echo date('d/m/Y à H:i', strtotime($medecin['Date_creation'])); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="medecin-actions">
                    <form method="post" action="approuver-medecins.php" style="margin: 0;">
                        <input type="hidden" name="id_med" value="<?php echo $medecin['id_med']; ?>">
                        <input type="hidden" name="action" value="approuver">
                        <button type="submit" class="btn btn-approve" onclick="return confirm('✅ Approuver ce médecin ?\n\nAprès approbation :\n- Le médecin pourra se connecter\n- Il apparaîtra dans la page \"Docteurs\"\n- Les patients pourront le choisir pour un rendez-vous');">
                            <i class="fas fa-check"></i> Approuver
                        </button>
                    </form>
                    <form method="post" action="approuver-medecins.php" style="margin: 0;">
                        <input type="hidden" name="id_med" value="<?php echo $medecin['id_med']; ?>">
                        <input type="hidden" name="action" value="refuser">
                        <button type="submit" class="btn btn-refuse" onclick="return confirm('❌ Refuser cette demande ?\n\nLe médecin ne pourra pas se connecter et n\'apparaîtra pas dans la page Docteurs.');">
                            <i class="fas fa-times"></i> Refuser
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once 'partials/footer.php'; ?>
