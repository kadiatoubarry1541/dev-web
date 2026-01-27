<?php
/**
 * Webhook/Callback Orange Money
 * Reçoit les notifications de paiement d'Orange Money
 */
require_once '../config/database_functions.php';
require_once 'orange_money_api.php';
require_once 'orange_config.php';

// Log des notifications reçues
$log_data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'data' => $_POST + $_GET,
    'headers' => getallheaders()
];

error_log("Orange Money Callback - " . json_encode($log_data));

// Récupérer les données de la notification
$notification_data = $_POST + $_GET;

// Valider la notification
$orange_config = require 'orange_config.php';
$orange_api = new OrangeMoneyAPI($orange_config);
$validated = $orange_api->validateNotification($notification_data);

if ($validated && isset($validated['order_id'])) {
    try {
        $pdo = bdd();
        
        // Récupérer le paiement par order_id
        $check_orange_id = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'orange_order_id'");
        $has_orange_id = $check_orange_id->rowCount() > 0;
        
        if ($has_orange_id) {
            $sql = "SELECT * FROM PAIEMENT WHERE orange_order_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$validated['order_id']]);
            $paiement = $stmt->fetch();
            
            if ($paiement) {
                $status = $validated['status'] ?? 'unknown';
                
                // Mapper le statut Orange Money au statut de notre système
                $new_statut = 'en_attente';
                if (in_array(strtolower($status), ['success', 'completed', 'paid', 'successful'])) {
                    $new_statut = 'payé';
                } elseif (in_array(strtolower($status), ['failed', 'cancelled', 'canceled'])) {
                    $new_statut = 'annulé';
                }
                
                // Mettre à jour le statut du paiement
                $sql_update = "UPDATE PAIEMENT SET Statut = ? WHERE id_paiement = ?";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([$new_statut, $paiement['id_paiement']]);
                
                // Si le paiement est payé, générer le reçu
                if ($new_statut === 'payé') {
                    try {
                        // Générer un numéro de facture si pas déjà présent
                        if (empty($paiement['id_facture'])) {
                            require_once '../config/database_functions.php';
                            $id_facture = genererNumeroFacture();
                            $sql_facture = "UPDATE PAIEMENT SET id_facture = ? WHERE id_paiement = ?";
                            $stmt_facture = $pdo->prepare($sql_facture);
                            $stmt_facture->execute([$id_facture, $paiement['id_paiement']]);
                        }
                        
                        // Générer le reçu
                        genererReçu($paiement['id_paiement']);
                    } catch (Exception $e) {
                        error_log("Erreur génération reçu callback: " . $e->getMessage());
                    }
                }
                
                // Répondre à Orange Money (important pour confirmer la réception)
                http_response_code(200);
                echo json_encode(['status' => 'received', 'order_id' => $validated['order_id']]);
                exit();
            }
        }
    } catch (Exception $e) {
        error_log("Erreur traitement callback Orange Money: " . $e->getMessage());
    }
}

// Répondre même en cas d'erreur (pour éviter les retries)
http_response_code(200);
echo json_encode(['status' => 'received']);
