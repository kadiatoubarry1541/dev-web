<?php
/**
 * Gestion des sessions utilisateur
 */

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifier si l'utilisateur est connecté
 */
function estConnecte() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Obtenir les informations de l'utilisateur connecté
 * Vérifie aussi que le compte existe toujours dans la base de données
 */
function getUserInfo() {
    if (!estConnecte()) {
        return null;
    }
    
    $info = [
        'id' => $_SESSION['user_id'],
        'nom' => $_SESSION['user_nom'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'patient',
        'id_patient' => $_SESSION['id_patient'] ?? null,
        'id_med' => $_SESSION['id_med'] ?? null,
        'specialisation' => $_SESSION['specialisation'] ?? null,
        'photo_profil' => $_SESSION['photo_profil'] ?? null,
        'matricule_patient' => $_SESSION['matricule_patient'] ?? null,
        'matricule_med' => $_SESSION['matricule_med'] ?? null,
        'medecin_statut' => $_SESSION['medecin_statut'] ?? null // Statut du médecin (en_attente, approuvé, refusé)
    ];
    
    // Vérifier que le compte existe toujours dans la base de données
    try {
        $bdd_path = __DIR__ . '/bdd.php';
        if (file_exists($bdd_path)) {
            require_once $bdd_path;
            if (function_exists('bdd')) {
                $pdo = bdd();
                // Récupérer aussi le statut du médecin si c'est un médecin
                $sql = "SELECT u.id, u.email, u.role, u.photo_profil, m.statut as medecin_statut 
                        FROM users u 
                        LEFT JOIN MEDECINS m ON u.id_med = m.id_med 
                        WHERE u.id = ? AND u.email = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$info['id'], $info['email']]);
                $user_check = $stmt->fetch();
                
                // Si le compte n'existe plus, détruire la session
                if (!$user_check) {
                    session_destroy();
                    return null;
                }
                
                // Mettre à jour le rôle au cas où il aurait changé
                if (isset($user_check['role']) && $user_check['role'] !== $info['role']) {
                    $_SESSION['user_role'] = $user_check['role'];
                    $info['role'] = $user_check['role'];
                }
                
                // Mettre à jour la photo de profil si elle a changé
                if (isset($user_check['photo_profil']) && $user_check['photo_profil'] !== $info['photo_profil']) {
                    $_SESSION['photo_profil'] = $user_check['photo_profil'];
                    $info['photo_profil'] = $user_check['photo_profil'];
                }
                
                // Mettre à jour le statut du médecin si disponible
                if (isset($user_check['medecin_statut'])) {
                    $_SESSION['medecin_statut'] = $user_check['medecin_statut'];
                    $info['medecin_statut'] = $user_check['medecin_statut'];
                }
            }
        }
    } catch (PDOException $e) {
        // En cas d'erreur de connexion à la base de données, logger l'erreur mais garder la session
        // pour éviter de déconnecter l'utilisateur à chaque erreur temporaire
        error_log("Erreur vérification utilisateur dans getUserInfo (PDO): " . $e->getMessage());
    } catch (Exception $e) {
        // En cas d'erreur générale, logger l'erreur mais garder la session
        error_log("Erreur vérification utilisateur dans getUserInfo: " . $e->getMessage());
    }
    
    return $info;
}

/**
 * Rediriger vers la page de connexion si l'utilisateur n'est pas connecté
 */
function requireLogin($redirect = 'login.php') {
    if (!estConnecte()) {
        header('Location: ' . $redirect);
        exit();
    }
}

/**
 * Rediriger vers une page si l'utilisateur est déjà connecté
 */
function requireLogout($redirect = 'index.php') {
    if (estConnecte()) {
        header('Location: ' . $redirect);
        exit();
    }
}

?>
