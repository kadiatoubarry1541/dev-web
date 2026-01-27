<?php
/**
 * Page de création de paiement
 * Permet à l'administrateur de créer un nouveau paiement pour un patient
 */
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';

requireLogin('../login.php');
requirePermission('manage_paiements', '../index.php');

$user_info = getUserInfo();
$message = '';
$message_type = '';
$success = false;

// Récupérer les patients et services
$patients = [];
$services = [];

try {
    $pdo = bdd();
    $patients = getAllPatients();
    $services = getAllServices();
} catch (Exception $e) {
    error_log("Erreur récupération données: " . $e->getMessage());
    $message = "Erreur lors du chargement des données.";
    $message_type = "danger";
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_paiement'])) {
    $montant = floatval($_POST['montant'] ?? 0);
    $date_paiement = $_POST['date_paiement'] ?? date('Y-m-d H:i:s');
    $id_patient = !empty($_POST['id_patient']) ? intval($_POST['id_patient']) : null;
    $id_service = !empty($_POST['id_service']) ? intval($_POST['id_service']) : null;
    $methode = $_POST['methode'] ?? 'espèces';
    $statut = $_POST['statut'] ?? 'payé';
    
    // Si Orange Money est sélectionné, rediriger vers le traitement Orange Money
    if (isset($_POST['methode']) && $_POST['methode'] === 'orange_money') {
        // Créer un formulaire caché et le soumettre automatiquement vers orange_process.php
        // On va utiliser une session pour passer les données
        $_SESSION['orange_payment_data'] = [
            'montant' => $montant,
            'id_patient' => $id_patient,
            'id_service' => $id_service,
            'customer_phone' => trim($_POST['customer_phone'] ?? ''),
            'date_paiement' => $date_paiement
        ];
        header('Location: orange_process.php');
        exit();
    }
    
    // Validation
    if (empty($montant) || $montant <= 0) {
        $message = "Le montant doit être supérieur à 0.00 GNF.";
        $message_type = "danger";
    } elseif (empty($id_patient)) {
        $message = "Veuillez sélectionner un patient.";
        $message_type = "danger";
    } elseif ($montant == 0 && $statut === 'payé') {
        $message = "Un paiement avec un montant de 0.00 GNF ne peut pas avoir le statut 'Payé'.";
        $message_type = "danger";
    } else {
        try {
            $pdo = bdd();
            
            // Générer un numéro de facture si le paiement est payé
            $id_facture = null;
            if ($statut === 'payé') {
                $id_facture = genererNumeroFacture();
            }
            
            // Vérifier quelles colonnes existent
            $check_id_service = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'id_service'");
            $has_id_service = $check_id_service->rowCount() > 0;
            
            $check_methode = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'Méthode_paiement'");
            $has_methode = $check_methode->rowCount() > 0;
            
            if (!$has_methode) {
                $check_methode_alt = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'methode_paiement'");
                $has_methode_alt = $check_methode_alt->rowCount() > 0;
            } else {
                $has_methode_alt = false;
            }
            
            // Vérifier si la colonne id_facture existe
            $check_id_facture = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'id_facture'");
            $has_id_facture = $check_id_facture->rowCount() > 0;
            
            // Construire la requête SQL selon les colonnes disponibles
            if ($has_id_service) {
                if ($has_methode) {
                    if ($has_id_facture) {
                        $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, id_service, Méthode_paiement, id_facture, Statut) 
                                VALUES (?, ?, ?, NULL, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $result = $stmt->execute([$montant, $date_paiement, $id_patient, $id_service, $methode, $id_facture, $statut]);
                    } else {
                        $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, id_service, Méthode_paiement, Statut) 
                                VALUES (?, ?, ?, NULL, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $result = $stmt->execute([$montant, $date_paiement, $id_patient, $id_service, $methode, $statut]);
                    }
                } elseif ($has_methode_alt) {
                    if ($has_id_facture) {
                        $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, id_service, methode_paiement, id_facture, Statut) 
                                VALUES (?, ?, ?, NULL, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $result = $stmt->execute([$montant, $date_paiement, $id_patient, $id_service, $methode, $id_facture, $statut]);
                    } else {
                        $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, id_service, methode_paiement, Statut) 
                                VALUES (?, ?, ?, NULL, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $result = $stmt->execute([$montant, $date_paiement, $id_patient, $id_service, $methode, $statut]);
                    }
                } else {
                    throw new Exception("La colonne Méthode_paiement n'existe pas dans la table PAIEMENT.");
                }
            } else {
                // Sans id_service
                if ($has_methode) {
                    if ($has_id_facture) {
                        $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, Méthode_paiement, id_facture, Statut) 
                                VALUES (?, ?, ?, NULL, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $result = $stmt->execute([$montant, $date_paiement, $id_patient, $methode, $id_facture, $statut]);
                    } else {
                        $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, Méthode_paiement, Statut) 
                                VALUES (?, ?, ?, NULL, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $result = $stmt->execute([$montant, $date_paiement, $id_patient, $methode, $statut]);
                    }
                } elseif ($has_methode_alt) {
                    if ($has_id_facture) {
                        $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, methode_paiement, id_facture, Statut) 
                                VALUES (?, ?, ?, NULL, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $result = $stmt->execute([$montant, $date_paiement, $id_patient, $methode, $id_facture, $statut]);
                    } else {
                        $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, methode_paiement, Statut) 
                                VALUES (?, ?, ?, NULL, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $result = $stmt->execute([$montant, $date_paiement, $id_patient, $methode, $statut]);
                    }
                } else {
                    throw new Exception("La colonne Méthode_paiement n'existe pas dans la table PAIEMENT.");
                }
            }
            
            if ($result) {
                $id_paiement_creé = $pdo->lastInsertId();
                
                // Si le paiement est payé, générer automatiquement le reçu
                if ($statut === 'payé') {
                    try {
                        $chemin_reçu = genererReçu($id_paiement_creé);
                        $message = "Le paiement a été créé avec succès ! Le reçu a été généré.";
                        if ($id_facture) {
                            $message .= " Numéro de facture : <strong>" . htmlspecialchars($id_facture) . "</strong>";
                        }
                    } catch (Exception $e) {
                        error_log("Erreur génération reçu: " . $e->getMessage());
                        $message = "Le paiement a été créé avec succès !";
                        if ($id_facture) {
                            $message .= " Numéro de facture : <strong>" . htmlspecialchars($id_facture) . "</strong>";
                        }
                        $message .= " <small style='color: orange;'>(Note: Le reçu n'a pas pu être généré automatiquement)</small>";
                    }
                } else {
                    $message = "Le paiement a été créé avec succès !";
                    if ($id_facture) {
                        $message .= " Numéro de facture : <strong>" . htmlspecialchars($id_facture) . "</strong>";
                    }
                }
                
                $message_type = "success";
                $success = true;
                
                // Réinitialiser les données
                $_POST = [];
            } else {
                throw new Exception("Erreur lors de la création du paiement.");
            }
        } catch (PDOException $e) {
            error_log("Erreur création paiement: " . $e->getMessage());
            $message = "Erreur lors de la création du paiement : " . $e->getMessage();
            $message_type = "danger";
        } catch (Exception $e) {
            $message = $e->getMessage();
            $message_type = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Créer un Paiement - MediCo.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="../assets/css/plugins.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
    <link class="skin" rel="stylesheet" type="text/css" href="../assets/css/skin/skin-1.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/templete.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .payment-container {
            padding: 40px 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .form-card {
            background: #fff;
            border-radius: 12px;
            padding: 40px;
            margin: 0 auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 1000px;
        }
        .page-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }
        .page-header h1 {
            color: #002939;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #002939;
            font-size: 14px;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
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
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 40px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
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
    </style>
</head>
<body>
<div class="page-wraper">
    <?php require_once '../partials/entete.php'; ?>
    
    <div class="payment-container">
        <div class="container">
            <div class="form-card">
                <a href="index.php" class="btn-retour">
                    <i class="fa fa-arrow-left"></i> Retour à la liste
                </a>
                
                <div class="page-header">
                    <h1><i class="fa fa-money"></i> Créer un Paiement</h1>
                    <p>Enregistrer un nouveau paiement pour un patient</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!$success): ?>
                <form method="post" action="" id="paiementForm">
                    <div class="form-group">
                        <label>Patient <span class="required">*</span></label>
                        <select name="id_patient" id="id_patient" required>
                            <option value="">Sélectionner un patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id_patient']; ?>" 
                                        <?php echo (isset($_POST['id_patient']) && $_POST['id_patient'] == $patient['id_patient']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($patient['Nom_patient'] . ' ' . $patient['Prénom_patient'] . ' (' . $patient['Matricule_patient'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if (!empty($services)): ?>
                    <div class="form-group">
                        <label>Service (optionnel)</label>
                        <select name="id_service" id="id_service">
                            <option value="">Aucun service</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['id_service']; ?>" 
                                        <?php echo (isset($_POST['id_service']) && $_POST['id_service'] == $service['id_service']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($service['Nom_service'] . ' - ' . number_format($service['Tarif'], 0, ',', ' ') . ' GNF'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #666;">Sélectionnez le service médical concerné par ce paiement</small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Montant (GNF) <span class="required">*</span></label>
                            <input type="number" name="montant" step="0.01" min="0.01" required 
                                   value="<?php echo htmlspecialchars($_POST['montant'] ?? ''); ?>" 
                                   placeholder="0.00" id="montant">
                            <small style="color: #666;">Le montant minimum est de 0.01 GNF</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Date de paiement <span class="required">*</span></label>
                            <input type="datetime-local" name="date_paiement" required 
                                   value="<?php echo htmlspecialchars($_POST['date_paiement'] ?? date('Y-m-d\TH:i')); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Méthode de paiement <span class="required">*</span></label>
                            <select name="methode" required id="methode_paiement" onchange="toggleOrangeFields()">
                                <option value="espèces" <?php echo (isset($_POST['methode']) && $_POST['methode'] == 'espèces') ? 'selected' : 'selected'; ?>>Espèces</option>
                                <option value="carte" <?php echo (isset($_POST['methode']) && $_POST['methode'] == 'carte') ? 'selected' : ''; ?>>Carte bancaire</option>
                                <option value="chèque" <?php echo (isset($_POST['methode']) && $_POST['methode'] == 'chèque') ? 'selected' : ''; ?>>Chèque</option>
                                <option value="virement" <?php echo (isset($_POST['methode']) && $_POST['methode'] == 'virement') ? 'selected' : ''; ?>>Virement</option>
                                <option value="orange_money" <?php echo (isset($_POST['methode']) && $_POST['methode'] == 'orange_money') ? 'selected' : ''; ?>>Orange Money</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Statut <span class="required">*</span></label>
                            <select name="statut" required id="statut">
                                <option value="payé" <?php echo (isset($_POST['statut']) && $_POST['statut'] == 'payé') ? 'selected' : 'selected'; ?>>Payé</option>
                                <option value="en_attente" <?php echo (isset($_POST['statut']) && $_POST['statut'] == 'en_attente') ? 'selected' : ''; ?>>En attente</option>
                                <option value="annulé" <?php echo (isset($_POST['statut']) && $_POST['statut'] == 'annulé') ? 'selected' : ''; ?>>Annulé</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Champs spécifiques Orange Money (affichés si Orange Money est sélectionné) -->
                    <div id="orange_money_fields" style="display: none; background: #fff3cd; padding: 20px; border-radius: 8px; margin-top: 20px; border: 2px solid #ffc107;">
                        <h3 style="color: #856404; margin-bottom: 15px;">
                            <i class="fa fa-mobile"></i> Informations Orange Money
                        </h3>
                        <div class="form-group">
                            <label>Numéro de téléphone Orange Money <span class="required">*</span></label>
                            <input type="tel" name="customer_phone" id="customer_phone" 
                                   placeholder="Ex: +224 612 34 56 78" 
                                   value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>">
                            <small style="color: #666;">Le numéro de téléphone du patient pour Orange Money</small>
                        </div>
                        <div style="background: #fff; padding: 15px; border-radius: 6px; margin-top: 10px;">
                            <p style="margin: 0; color: #856404; font-size: 14px;">
                                <i class="fa fa-info-circle"></i> 
                                <strong>Note :</strong> Avec Orange Money, le paiement sera traité en ligne. 
                                Le patient sera redirigé vers la page de paiement Orange Money.
                            </p>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px; text-align: center;">
                        <button type="submit" name="creer_paiement" class="btn-submit" id="submit_btn">
                            <i class="fa fa-save"></i> Enregistrer le Paiement
                        </button>
                        <a href="index.php" class="btn-submit" style="background: #6c757d; text-decoration: none; display: inline-block; margin-left: 10px;">
                            <i class="fa fa-list"></i> Voir les Paiements
                        </a>
                    </div>
                </form>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px 0;">
                        <i class="fa fa-check-circle" style="font-size: 64px; color: #28a745; margin-bottom: 20px;"></i>
                        <h2 style="color: #28a745; margin-bottom: 20px;">Paiement créé avec succès !</h2>
                        <a href="create.php" class="btn-submit">
                            <i class="fa fa-plus"></i> Créer un autre paiement
                        </a>
                        <a href="index.php" class="btn-submit" style="background: #6c757d; text-decoration: none; display: inline-block; margin-left: 10px;">
                            <i class="fa fa-list"></i> Voir tous les paiements
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php require_once '../partials/footer.php'; ?>
</div>

<script src="../assets/js/jquery.min.js"></script>
<script>
// Script pour mettre à jour automatiquement le montant selon le service sélectionné
document.addEventListener('DOMContentLoaded', function() {
    var serviceSelect = document.getElementById('id_service');
    var montantInput = document.getElementById('montant');
    var statutSelect = document.getElementById('statut');
    var paiementForm = document.getElementById('paiementForm');
    
    // Récupérer les tarifs des services depuis le serveur
    var servicesData = <?php 
        $services_data = [];
        foreach ($services as $service) {
            $services_data[$service['id_service']] = floatval($service['Tarif']);
        }
        echo json_encode($services_data);
    ?>;
    
    if (serviceSelect && montantInput) {
        serviceSelect.addEventListener('change', function() {
            var selectedServiceId = this.value;
            if (selectedServiceId && servicesData[selectedServiceId]) {
                var tarif = servicesData[selectedServiceId];
                montantInput.value = tarif.toFixed(2);
                // Si le montant est 0, changer automatiquement le statut
                if (tarif == 0 && statutSelect) {
                    statutSelect.value = 'en_attente';
                    alert('Attention : Le montant est de 0.00 GNF. Le statut a été automatiquement changé à "En attente".');
                }
            } else {
                montantInput.value = '';
            }
        });
    }
    
    // Fonction pour afficher/masquer les champs Orange Money
    function toggleOrangeFields() {
        var methodeSelect = document.getElementById('methode_paiement');
        var orangeFields = document.getElementById('orange_money_fields');
        var customerPhone = document.getElementById('customer_phone');
        
        if (methodeSelect.value === 'orange_money') {
            orangeFields.style.display = 'block';
            if (customerPhone) customerPhone.required = true;
        } else {
            orangeFields.style.display = 'none';
            if (customerPhone) customerPhone.required = false;
        }
    }
    
    // Appeler au chargement de la page
    toggleOrangeFields();
    
    // Validation côté client
    if (paiementForm && montantInput && statutSelect) {
        paiementForm.addEventListener('submit', function(e) {
            var montant = parseFloat(montantInput.value) || 0;
            var statut = statutSelect.value;
            var methode = document.getElementById('methode_paiement').value;
            
            // Si Orange Money, vérifier le numéro de téléphone
            if (methode === 'orange_money') {
                var customerPhone = document.getElementById('customer_phone');
                if (!customerPhone || !customerPhone.value.trim()) {
                    e.preventDefault();
                    alert('Erreur : Veuillez entrer le numéro de téléphone Orange Money du patient.');
                    if (customerPhone) customerPhone.focus();
                    return false;
                }
            }
            
            if (montant <= 0) {
                e.preventDefault();
                alert('Erreur : Le montant doit être supérieur à 0.00 GNF.');
                montantInput.focus();
                return false;
            }
            
            if (montant == 0 && statut === 'payé') {
                e.preventDefault();
                alert('Erreur : Un paiement avec un montant de 0.00 GNF ne peut pas avoir le statut "Payé".');
                statutSelect.focus();
                return false;
            }
        });
    }
});
</script>
</body>
</html>
