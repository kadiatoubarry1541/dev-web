<?php
/**
 * Traitement des paiements Orange Money
 * Initie un paiement Orange Money pour un patient
 */
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';
require_once 'orange_money_api.php';
require_once 'orange_config.php';

requireLogin('../login.php');
requirePermission('manage_paiements', '../index.php');

$user_info = getUserInfo();
$message = '';
$message_type = '';
$orange_config = require 'orange_config.php';

// Traitement du formulaire (peut venir de create.php via session ou directement en POST)
$payment_data_session = $_SESSION['orange_payment_data'] ?? null;

if ($payment_data_session) {
    // Récupérer depuis la session (venant de create.php)
    $montant = floatval($payment_data_session['montant'] ?? 0);
    $id_patient = !empty($payment_data_session['id_patient']) ? intval($payment_data_session['id_patient']) : null;
    $id_service = !empty($payment_data_session['id_service']) ? intval($payment_data_session['id_service']) : null;
    $customer_phone = trim($payment_data_session['customer_phone'] ?? '');
    $date_paiement = $payment_data_session['date_paiement'] ?? date('Y-m-d H:i:s');
    
    // Nettoyer la session
    unset($_SESSION['orange_payment_data']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer directement depuis POST
    $montant = floatval($_POST['montant'] ?? 0);
    $id_patient = !empty($_POST['id_patient']) ? intval($_POST['id_patient']) : null;
    $id_service = !empty($_POST['id_service']) ? intval($_POST['id_service']) : null;
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $date_paiement = $_POST['date_paiement'] ?? date('Y-m-d H:i:s');
} else {
    // Pas de données, rediriger
    header('Location: create.php');
    exit();
}

if (isset($montant) && isset($id_patient) && isset($customer_phone)) {
    
    // Validation
    if (empty($montant) || $montant <= 0) {
        $message = "Le montant doit être supérieur à 0.00 GNF.";
        $message_type = "danger";
    } elseif (empty($id_patient)) {
        $message = "Veuillez sélectionner un patient.";
        $message_type = "danger";
    } elseif (empty($customer_phone)) {
        $message = "Veuillez entrer le numéro de téléphone Orange Money du patient.";
        $message_type = "danger";
    } else {
        try {
            // Récupérer les informations du patient
            $patient = getPatientById($id_patient);
            if (!$patient) {
                throw new Exception("Patient introuvable.");
            }
            
            // Générer un ID de commande unique
            $order_id = 'OM_' . $id_patient . '_' . time() . '_' . rand(1000, 9999);
            
            // Préparer les données du paiement
            $payment_data = [
                'order_id' => $order_id,
                'amount' => $montant,
                'currency' => $orange_config['currency'],
                'customer_phone' => $customer_phone,
                'customer_name' => ($patient['Nom_patient'] ?? '') . ' ' . ($patient['Prénom_patient'] ?? ''),
                'id_patient' => $id_patient,
                'id_service' => $id_service
            ];
            
            // Initialiser l'API Orange Money
            $orange_api = new OrangeMoneyAPI($orange_config);
            
            // Mode simulation ou API réelle
            if ($orange_config['simulation_mode']) {
                $result = $orange_api->simulatePayment($payment_data);
            } else {
                $result = $orange_api->initiatePayment($payment_data);
            }
            
            if ($result && isset($result['success']) && $result['success']) {
                // Créer un paiement en attente dans la base de données
                $pdo = bdd();
                
                // Vérifier les colonnes
                $check_methode = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'Méthode_paiement'");
                $has_methode = $check_methode->rowCount() > 0;
                
                if (!$has_methode) {
                    $check_methode_alt = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'methode_paiement'");
                    $has_methode_alt = $check_methode_alt->rowCount() > 0;
                } else {
                    $has_methode_alt = false;
                }
                
                // Vérifier si la colonne orange_order_id existe
                $check_orange_id = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'orange_order_id'");
                $has_orange_id = $check_orange_id->rowCount() > 0;
                
                // Ajouter la colonne si elle n'existe pas
                if (!$has_orange_id) {
                    try {
                        $pdo->exec("ALTER TABLE PAIEMENT ADD COLUMN orange_order_id VARCHAR(100) NULL AFTER id_facture");
                        $has_orange_id = true;
                    } catch (Exception $e) {
                        error_log("Erreur ajout colonne orange_order_id: " . $e->getMessage());
                    }
                }
                
                // Insérer le paiement (utiliser la date du formulaire ou maintenant)
                $date_paiement_insert = $date_paiement ?? date('Y-m-d H:i:s');
                $methode = 'orange_money';
                
                if ($has_methode) {
                    if ($has_orange_id) {
                        $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, id_service, Méthode_paiement, orange_order_id, Statut) 
                                VALUES (?, ?, ?, NULL, ?, ?, ?, 'en_attente')";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$montant, $date_paiement_insert, $id_patient, $id_service, $methode, $order_id]);
                    } else {
                        $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, id_service, Méthode_paiement, Statut) 
                                VALUES (?, ?, ?, NULL, ?, ?, 'en_attente')";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$montant, $date_paiement_insert, $id_patient, $id_service, $methode]);
                    }
                } elseif ($has_methode_alt) {
                    if ($has_orange_id) {
                        $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, id_service, methode_paiement, orange_order_id, Statut) 
                                VALUES (?, ?, ?, NULL, ?, ?, ?, 'en_attente')";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$montant, $date_paiement_insert, $id_patient, $id_service, $methode, $order_id]);
                    } else {
                        $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, id_service, methode_paiement, Statut) 
                                VALUES (?, ?, ?, NULL, ?, ?, 'en_attente')";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$montant, $date_paiement_insert, $id_patient, $id_service, $methode]);
                    }
                } else {
                    throw new Exception("La colonne Méthode_paiement n'existe pas dans la table PAIEMENT.");
                }
                
                $id_paiement = $pdo->lastInsertId();
                
                // Rediriger vers la page de paiement Orange Money
                if (isset($result['payment_url']) && $result['payment_url']) {
                    // Sauvegarder l'ID du paiement en session pour la récupération après retour
                    $_SESSION['orange_payment_id'] = $id_paiement;
                    $_SESSION['orange_order_id'] = $order_id;
                    
                    header('Location: ' . $result['payment_url']);
                    exit();
                } else {
                    throw new Exception("URL de paiement non disponible.");
                }
            } else {
                $error_msg = $result['error'] ?? 'Erreur lors de l\'initiation du paiement Orange Money';
                throw new Exception($error_msg);
            }
        } catch (Exception $e) {
            error_log("Erreur paiement Orange Money: " . $e->getMessage());
            $message = "Erreur : " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        // Validation échouée
        $message = "Données manquantes ou invalides.";
        $message_type = "danger";
    }
} else {
    // Pas de données disponibles
    $message = "Aucune donnée de paiement trouvée.";
    $message_type = "danger";
}

// Si on arrive ici, c'est qu'il y a eu une erreur
if (!empty($message)) {
    $_SESSION['orange_error'] = $message;
    header('Location: create.php?method=orange_money&error=1');
    exit();
}
