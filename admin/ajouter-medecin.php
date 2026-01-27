<?php
require_once 'partials/header.php';
require_once '../config/bdd.php';
require_once '../config/traitement.php';
require_once '../config/database_functions.php';

$message = '';
$message_type = '';

// Traiter l'ajout du médecin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $specialisation = trim($_POST['specialisation'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Validation
    if (empty($nom) || empty($prenom) || empty($email) || empty($telephone) || empty($specialisation) || empty($password)) {
        $message = "Tous les champs obligatoires doivent être remplis.";
        $message_type = "danger";
    } elseif ($password !== $password_confirm) {
        $message = "Les mots de passe ne correspondent pas.";
        $message_type = "danger";
    } elseif (strlen($password) < 6) {
        $message = "Le mot de passe doit contenir au moins 6 caractères.";
        $message_type = "danger";
    } elseif (EmailExist($email, 'medecin')) {
        $message = "Cet email est déjà utilisé par un compte existant. Veuillez utiliser un autre email.";
        $message_type = "danger";
    } else {
        try {
            $pdo = bdd();
            $pdo->beginTransaction();
            
            // Vérifier que l'email n'existe pas dans MEDECINS
            $check_med_email = $pdo->prepare("SELECT id_med FROM MEDECINS WHERE Email_med = ?");
            $check_med_email->execute([$email]);
            if ($check_med_email->rowCount() > 0) {
                throw new Exception("Cet email est déjà utilisé par un médecin.");
            }
            
            // Créer le médecin avec statut approuvé directement (sans matricule pour l'instant)
            $sql_medecin = "INSERT INTO MEDECINS (Matricule_med, Nom_med, Prénom_med, Spécialisation_med, Tel_med, Email_med, statut) 
                            VALUES (?, ?, ?, ?, ?, ?, 'approuvé')";
            $stmt_medecin = $pdo->prepare($sql_medecin);
            $stmt_medecin->execute([null, $nom, $prenom, $specialisation, $telephone, $email]);
            $id_med = $pdo->lastInsertId();
            
            // Attribuer automatiquement un matricule puisque c'est l'admin qui crée
            // IMPORTANT : on passe $pdo pour éviter un deuxième connexion qui provoquerait
            // un verrouillage (Lock wait timeout) sur la même ligne MEDECINS.
            $matricule_med = attribuerMatriculeMedecin($id_med, null, $pdo);
            
            // Vérifier que l'email n'existe pas dans users
            $check_user_email = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check_user_email->execute([$email]);
            if ($check_user_email->rowCount() > 0) {
                throw new Exception("Cet email est déjà utilisé dans le système.");
            }
            
            // Créer l'utilisateur
            $nom_complet = trim($nom . ' ' . $prenom);
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $sql_user = "INSERT INTO users (nom, email, telephone, password, role, id_med) 
                         VALUES (?, ?, ?, ?, 'medecin', ?)";
            $stmt_user = $pdo->prepare($sql_user);
            $stmt_user->execute([$nom_complet, $email, $telephone, $password_hash, $id_med]);
            
            $pdo->commit();
            
            $message = "Le médecin a été ajouté avec succès ! Matricule attribué : <strong>" . htmlspecialchars($matricule_med) . "</strong>. Il peut maintenant se connecter.";
            $message_type = "success";
            
            // Réinitialiser les champs
            $_POST = [];
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $message = "Cet email est déjà utilisé dans le système.";
            } else {
                $message = "Erreur lors de l'ajout du médecin : " . $e->getMessage();
            }
            $message_type = "danger";
            error_log("Erreur ajout médecin: " . $e->getMessage());
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Erreur : " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Récupérer les services pour la liste déroulante
$services = [];
try {
    $services = getAllServices();
} catch (Exception $e) {
    error_log("Erreur récupération services: " . $e->getMessage());
}
?>

<style>
    .form-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 30px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #2d3748;
        font-size: 14px;
    }
    
    .form-group input,
    .form-group select {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 15px;
        transition: all 0.3s;
    }
    
    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #4A90E2;
        box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    .btn-submit {
        background: #4A90E2;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .btn-submit:hover {
        background: #357ABD;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
    }
    
    .btn-cancel {
        background: #e2e8f0;
        color: #2d3748;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        margin-left: 10px;
        transition: all 0.3s;
    }
    
    .btn-cancel:hover {
        background: #cbd5e0;
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
    
    .btn-retour i {
        font-size: 14px;
    }
</style>

<div class="approval-container">
    <a href="index.php" class="btn-retour">
        <i class="fas fa-arrow-left"></i> Retour au tableau de bord
    </a>
    <div class="page-header">
        <h1><i class="fas fa-user-plus"></i> Ajouter un Médecin</h1>
        <p>Créez un nouveau compte médecin qui sera automatiquement approuvé.</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="form-container">
        <form method="post" action="">
            <div class="form-row">
                <div class="form-group">
                    <label>Nom <span class="required">*</span></label>
                    <input type="text" name="nom" required 
                           value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>Prénom <span class="required">*</span></label>
                    <input type="text" name="prenom" required 
                           value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>Téléphone <span class="required">*</span></label>
                    <input type="tel" name="telephone" required 
                           value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Spécialisation <span class="required">*</span></label>
                <select name="specialisation" required>
                    <option value="">Sélectionner une spécialisation</option>
                    <?php if (!empty($services)): ?>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo htmlspecialchars($service['Nom_service']); ?>" 
                                    <?php echo (isset($_POST['specialisation']) && $_POST['specialisation'] == $service['Nom_service']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($service['Nom_service']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <option value="Médecine générale" <?php echo (isset($_POST['specialisation']) && $_POST['specialisation'] == 'Médecine générale') ? 'selected' : ''; ?>>Médecine générale</option>
                    <option value="Chirurgie" <?php echo (isset($_POST['specialisation']) && $_POST['specialisation'] == 'Chirurgie') ? 'selected' : ''; ?>>Chirurgie</option>
                    <option value="Maternité" <?php echo (isset($_POST['specialisation']) && $_POST['specialisation'] == 'Maternité') ? 'selected' : ''; ?>>Maternité</option>
                    <option value="Ophtalmologie" <?php echo (isset($_POST['specialisation']) && $_POST['specialisation'] == 'Ophtalmologie') ? 'selected' : ''; ?>>Ophtalmologie</option>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Mot de passe <span class="required">*</span></label>
                    <input type="password" name="password" required minlength="6">
                    <small style="color: #718096; font-size: 13px;">Minimum 6 caractères</small>
                </div>
                
                <div class="form-group">
                    <label>Confirmer le mot de passe <span class="required">*</span></label>
                    <input type="password" name="password_confirm" required minlength="6">
                </div>
            </div>
            
            <div style="margin-top: 30px; text-align: right;">
                <button type="submit" name="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Ajouter le Médecin
                </button>
                <a href="index.php" class="btn-cancel">
                    <i class="fas fa-times"></i> Annuler
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once 'partials/footer.php'; ?>
