<?php
/**
 * Endpoint AJAX : retourne les consultations d'un patient (pour le service du médecin connecté)
 * utilisé pour remplir le sélecteur de consultation lors de la création d'ordonnance.
 */
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database_functions.php';

require_once __DIR__ . '/../config/permissions.php';
requireLogin('../login.php');
requireMedecin('index.php');

header('Content-Type: application/json; charset=utf-8');

$id_patient = isset($_GET['id_patient']) ? (int) $_GET['id_patient'] : 0;
if ($id_patient <= 0) {
    echo json_encode(['success' => true, 'consultations' => []]);
    exit;
}

$user_info = getUserInfo();
$specialisation = $user_info['specialisation'] ?? '';

try {
    // Consultations du patient dans le service du médecin (ou par spécialisation)
    $consultations = getConsultationsByPatientAndService($id_patient, $specialisation);
    
    // Si vide et qu'on a une spécialisation, inclure aussi les consultations du patient par ce médecin (fallback)
    if (empty($consultations) && $specialisation) {
        $id_med = $user_info['id_med'] ?? null;
        if ($id_med) {
            $pdo = bdd();
            $sql = "SELECT c.*, p.Nom_patient, p.Prénom_patient, p.Matricule_patient, 
                           m.Spécialisation_med as Nom_service, m.Nom_med, m.Prénom_med
                    FROM CONSULTATION c
                    LEFT JOIN PATIENTS p ON c.id_patient = p.id_patient
                    LEFT JOIN MEDECINS m ON c.id_med = m.id_med
                    WHERE c.id_patient = ? AND c.id_med = ?
                    ORDER BY c.Date_consultation DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_patient, $id_med]);
            $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }
    
    echo json_encode([
        'success' => true,
        'consultations' => $consultations
    ]);
} catch (Exception $e) {
    error_log("get-consultations-patient: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'consultations' => [],
        'message' => 'Erreur lors du chargement des consultations.'
    ]);
}
