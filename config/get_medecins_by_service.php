<?php
/**
 * API endpoint pour récupérer les médecins d'un service
 * Appelé via AJAX depuis rendez-vous.php
 */

header('Content-Type: application/json');

require_once 'database_functions.php';
require_once 'bdd.php';

$response = [
    'success' => false,
    'medecins' => [],
    'message' => ''
];

try {
    if (!isset($_GET['id_service']) || empty($_GET['id_service'])) {
        $response['message'] = 'ID service manquant';
        echo json_encode($response);
        exit;
    }
    
    $id_service = intval($_GET['id_service']);
    
    if ($id_service <= 0) {
        $response['message'] = 'ID service invalide';
        echo json_encode($response);
        exit;
    }
    
    // Récupérer les médecins du service
    $medecins = getMedecinsByService($id_service);
    
    $response['success'] = true;
    $response['medecins'] = $medecins;
    $response['message'] = count($medecins) . ' médecin(s) trouvé(s)';
    
} catch (Exception $e) {
    $response['message'] = 'Erreur : ' . $e->getMessage();
    error_log("Erreur get_medecins_by_service: " . $e->getMessage());
}

echo json_encode($response);
?>
