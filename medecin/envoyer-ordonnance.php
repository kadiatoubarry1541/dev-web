<?php
/**
 * Endpoint pour envoyer une ordonnance au patient
 */
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';

requireLogin('login.php');
requireMedecin('index.php');

// Vérifier la permission d'envoi
if (!hasPermission('send_ordonnances')) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Vous n\'avez pas la permission d\'envoyer des ordonnances.'
    ]);
    exit();
}

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée.'
    ]);
    exit();
}

// Récupérer l'ID de consultation
$id_consultation = isset($_POST['id_consultation']) ? intval($_POST['id_consultation']) : 0;

if (empty($id_consultation)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'ID de consultation manquant.'
    ]);
    exit();
}

// Vérifier que le médecin peut accéder à cette consultation
$user_info = getUserInfo();
$id_med = $user_info['id_med'] ?? null;
$specialisation = $user_info['specialisation'] ?? '';

if ($id_med) {
    try {
        $pdo = bdd();
        
        // Vérifier que la consultation appartient au médecin ou à son service
        $sql_check = "SELECT c.id_consultation, c.id_med, m.Spécialisation_med
                     FROM CONSULTATION c
                     LEFT JOIN MEDECINS m ON c.id_med = m.id_med
                     WHERE c.id_consultation = ? 
                     AND (c.id_med = ? OR m.Spécialisation_med = ?)";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([$id_consultation, $id_med, $specialisation]);
        $consultation_check = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$consultation_check) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Vous n\'avez pas accès à cette consultation.'
            ]);
            exit();
        }
        
        // Envoyer l'ordonnance au patient
        envoyerOrdonnanceAuPatient($id_consultation);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Ordonnance envoyée au patient avec succès.'
        ]);
        
    } catch (Exception $e) {
        error_log("Erreur envoyer-ordonnance: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de l\'envoi de l\'ordonnance : ' . $e->getMessage()
        ]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Médecin non identifié.'
    ]);
}
?>
