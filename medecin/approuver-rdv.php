<?php
/**
 * Confirmer un rendez-vous (médecin seulement)
 * Change le statut du rendez-vous de "planifié" à "confirmé" et envoie une notification au patient
 */

require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';

header('Content-Type: application/json');

// Vérifier que l'utilisateur est médecin
if (!isMedecin()) {
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id_rdv = intval($_POST['id']);
    $user_info = getUserInfo();
    $id_med = $user_info['id_med'];
    
    try {
        $pdo = bdd();
        
        // Récupérer les informations du médecin (spécialisation)
        $sql_med = "SELECT Spécialisation_med FROM MEDECINS WHERE id_med = ?";
        $stmt_med = $pdo->prepare($sql_med);
        $stmt_med->execute([$id_med]);
        $medecin = $stmt_med->fetch();
        
        if (!$medecin) {
            echo json_encode(['success' => false, 'message' => 'Médecin non trouvé']);
            exit();
        }
        
        $specialisation_med = $medecin['Spécialisation_med'];
        
        // Vérifier que le rendez-vous appartient au service du médecin
        // Un médecin peut accepter un rendez-vous de son service, même s'il n'est pas directement assigné à lui
        $sql_check = "SELECT r.id_rdv, r.id_service, s.Nom_service 
                      FROM RENDEZ_VOUS r
                      LEFT JOIN SERVICES s ON r.id_service = s.id_service
                      WHERE r.id_rdv = ? AND (r.id_med = ? OR s.Nom_service = ?)";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([$id_rdv, $id_med, $specialisation_med]);
        $rdv_check = $stmt_check->fetch();
        
        if (!$rdv_check) {
            echo json_encode(['success' => false, 'message' => 'Rendez-vous non trouvé ou vous n\'êtes pas autorisé à confirmer ce rendez-vous (service différent)']);
            exit();
        }
        
        // Confirmer le rendez-vous
        if (updateStatutRendezVous($id_rdv, 'confirmé')) {
            echo json_encode(['success' => true, 'message' => 'Rendez-vous confirmé avec succès. Le patient a été notifié.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la confirmation du rendez-vous']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Requête invalide']);
}

?>
