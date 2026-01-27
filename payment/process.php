<?php
/**
 * Traitement des actions sur les paiements
 * Génération de reçus, mise à jour de statut, etc.
 */
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';

requireLogin('../login.php');
requirePermission('manage_paiements', '../index.php');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id_paiement = isset($_POST['id_paiement']) ? intval($_POST['id_paiement']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

if (!$id_paiement) {
    header('Location: index.php?error=id_manquant');
    exit();
}

$message = '';
$message_type = '';

try {
    switch ($action) {
        case 'generer_reçu':
            // Générer un reçu pour un paiement
            $paiement = getPaiementById($id_paiement);
            if (!$paiement) {
                throw new Exception("Paiement introuvable.");
            }
            
            if ($paiement['Statut'] !== 'payé') {
                throw new Exception("Seuls les paiements avec le statut 'Payé' peuvent générer un reçu.");
            }
            
            try {
                $chemin_reçu = genererReçu($id_paiement);
                $message = "Le reçu a été généré avec succès !";
                $message_type = "success";
                header('Location: view.php?id=' . $id_paiement . '&success=recu_genere');
                exit();
            } catch (Exception $e) {
                error_log("Erreur génération reçu: " . $e->getMessage());
                $message = "Erreur lors de la génération du reçu : " . $e->getMessage();
                $message_type = "danger";
                header('Location: view.php?id=' . $id_paiement . '&error=' . urlencode($message));
                exit();
            }
            break;
            
        case 'update_statut':
            // Mettre à jour le statut d'un paiement
            $nouveau_statut = $_POST['statut'] ?? '';
            $statuts_valides = ['en_attente', 'payé', 'annulé', 'remboursé'];
            
            if (!in_array($nouveau_statut, $statuts_valides)) {
                throw new Exception("Statut invalide.");
            }
            
            $result = updateStatutPaiement($id_paiement, $nouveau_statut);
            
            if ($result) {
                // Si le statut devient "payé", générer automatiquement un reçu
                if ($nouveau_statut === 'payé') {
                    try {
                        $paiement = getPaiementById($id_paiement);
                        if ($paiement && empty($paiement['chemin_reçu'])) {
                            genererReçu($id_paiement);
                        }
                    } catch (Exception $e) {
                        error_log("Erreur génération reçu automatique: " . $e->getMessage());
                        // Ne pas bloquer la mise à jour du statut si la génération du reçu échoue
                    }
                }
                
                $message = "Le statut du paiement a été mis à jour avec succès !";
                $message_type = "success";
                header('Location: view.php?id=' . $id_paiement . '&success=statut_updated');
                exit();
            } else {
                throw new Exception("Erreur lors de la mise à jour du statut.");
            }
            break;
            
        default:
            throw new Exception("Action non reconnue.");
    }
} catch (Exception $e) {
    error_log("Erreur process paiement: " . $e->getMessage());
    $message = $e->getMessage();
    $message_type = "danger";
    header('Location: view.php?id=' . $id_paiement . '&error=' . urlencode($message));
    exit();
}
