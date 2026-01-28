<?php
/**
 * Permet à l'administrateur d'entrer dans un service
 * "comme si" il était un médecin de ce service.
 *
 * Principe :
 *  - On vérifie que l'utilisateur est bien admin
 *  - On cherche un médecin dont la spécialisation correspond au service
 *  - On mémorise l'état admin dans la session
 *  - On bascule temporairement la session en mode médecin
 *  - On redirige vers le tableau de bord médecin
 *  - Un bouton "Retour à l'admin" permettra de revenir à l'état initial
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/permissions.php';
require_once __DIR__ . '/../config/bdd.php';

requireLogin('../login.php');
requireAdmin('../index.php');

$id_service = isset($_GET['id_service']) ? (int) $_GET['id_service'] : 0;

if ($id_service <= 0) {
    header('Location: liste-services.php');
    exit();
}

try {
    $pdo = bdd();

    // Récupérer le nom du service
    $stmt_service = $pdo->prepare("SELECT Nom_service FROM SERVICES WHERE id_service = ? LIMIT 1");
    $stmt_service->execute([$id_service]);
    $service = $stmt_service->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        header('Location: liste-services.php');
        exit();
    }

    $nom_service = $service['Nom_service'];

    // Chercher un médecin de ce service
    $stmt_med = $pdo->prepare("SELECT m.id_med, m.Nom_med, u.id AS user_id, u.email, u.photo_profil, m.Matricule_med 
                               FROM MEDECINS m
                               INNER JOIN users u ON u.id_med = m.id_med
                               WHERE m.Spécialisation_med = ?
                               ORDER BY m.id_med ASC
                               LIMIT 1");
    $stmt_med->execute([$nom_service]);
    $medecin = $stmt_med->fetch(PDO::FETCH_ASSOC);

    if (!$medecin) {
        // Aucun médecin pour ce service : on reste admin mais on informe
        $_SESSION['error_message'] = "Aucun médecin n'est encore rattaché au service « {$nom_service} ». Impossible d'entrer comme médecin.";
        header('Location: liste-services.php');
        exit();
    }

    // Sauvegarder le contexte admin actuel pour pouvoir revenir
    $_SESSION['admin_impersonation_backup'] = [
        'user_id'           => $_SESSION['user_id'] ?? null,
        'user_nom'          => $_SESSION['user_nom'] ?? null,
        'user_email'        => $_SESSION['user_email'] ?? null,
        'user_role'         => $_SESSION['user_role'] ?? null,
        'id_patient'        => $_SESSION['id_patient'] ?? null,
        'id_med'            => $_SESSION['id_med'] ?? null,
        'specialisation'    => $_SESSION['specialisation'] ?? null,
        'photo_profil'      => $_SESSION['photo_profil'] ?? null,
        'matricule_patient' => $_SESSION['matricule_patient'] ?? null,
        'matricule_med'     => $_SESSION['matricule_med'] ?? null,
        'medecin_statut'    => $_SESSION['medecin_statut'] ?? null,
    ];

    // Bascule de la session en mode "médecin de ce service"
    $_SESSION['user_id']        = $medecin['user_id'];
    $_SESSION['user_nom']       = $medecin['Nom_med'];
    $_SESSION['user_email']     = $medecin['email'];
    $_SESSION['user_role']      = 'medecin';
    $_SESSION['id_patient']     = null;
    $_SESSION['id_med']         = $medecin['id_med'];
    $_SESSION['specialisation'] = $nom_service;
    $_SESSION['photo_profil']   = $medecin['photo_profil'] ?? null;
    $_SESSION['matricule_med']  = $medecin['Matricule_med'] ?? null;

    // Indicateur pour le mode "impersonation admin"
    $_SESSION['admin_impersonate'] = true;

    // Redirection vers le tableau de bord médecin
    header('Location: ../medecin/index.php');
    exit();
} catch (Exception $e) {
    $_SESSION['error_message'] = "Erreur lors de l'entrée dans le service : " . $e->getMessage();
    header('Location: liste-services.php');
    exit();
}

