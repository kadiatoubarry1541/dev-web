<?php
/**
 * Permet de revenir au compte administrateur après avoir
 * "entré" dans un service comme médecin.
 */

require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_impersonation_backup'])) {
    // Rien à restaurer, retourner simplement vers l'admin
    header('Location: index.php');
    exit();
}

$backup = $_SESSION['admin_impersonation_backup'];

// Restaurer les variables de session de l'admin
$_SESSION['user_id']           = $backup['user_id'];
$_SESSION['user_nom']          = $backup['user_nom'];
$_SESSION['user_email']        = $backup['user_email'];
$_SESSION['user_role']         = $backup['user_role'];
$_SESSION['id_patient']        = $backup['id_patient'];
$_SESSION['id_med']            = $backup['id_med'];
$_SESSION['specialisation']    = $backup['specialisation'];
$_SESSION['photo_profil']      = $backup['photo_profil'];
$_SESSION['matricule_patient'] = $backup['matricule_patient'];
$_SESSION['matricule_med']     = $backup['matricule_med'];
$_SESSION['medecin_statut']    = $backup['medecin_statut'];

unset($_SESSION['admin_impersonation_backup'], $_SESSION['admin_impersonate']);

header('Location: index.php');
exit();

