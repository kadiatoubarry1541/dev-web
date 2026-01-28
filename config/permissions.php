<?php
/**
 * Système de gestion des permissions et rôles
 */

require_once 'session.php';

/**
 * Définition des rôles et leurs permissions
 * 
 * IMPORTANT : Chaque rôle a des permissions strictement définies.
 * Les médecins sont filtrés par leur service (spécialisation).
 * Les patients ne voient que leurs propres données.
 * 
 * RÈGLE FONDAMENTALE : 
 * Tout compte qui n'est PAS admin, médecin (4 services) ou accueil
 * est AUTOMATIQUEMENT un compte PATIENT.
 * 
 * Types d'utilisateurs :
 * 1. Admin - Contrôle total
 * 2-5. Médecins (4 services) - Gèrent leur service uniquement
 *    - Médecine générale
 *    - Chirurgie
 *    - Maternité
 *    - Ophtalmologie
 * 6. Accueil - Gère les patients et paiements
 * 7. Patient - Accès à ses propres données uniquement (DÉFAUT pour tous les autres comptes)
 */
$ROLES_PERMISSIONS = [
    'admin' => [
        'name' => 'Administrateur',
        'permissions' => [
            'view_all' => true,
            'manage_medecins' => true,
            'manage_patients' => true,
            'manage_services' => true,
            'manage_rendez_vous' => true,
            'create_rendez_vous' => true,  // Peut créer des rendez-vous pour n'importe quel patient
            'manage_consultations' => true,
            'view_consultations' => true,
            'manage_ordonnances' => true,
            'view_ordonnances' => true,
            'view_reports' => true,
            'manage_users' => true,
            'approve_rendez_vous' => true,
            'create_ordonnances' => true,
            'send_ordonnances' => true,  // Peut envoyer des ordonnances aux patients
            'manage_paiements' => true,
            'view_paiements' => true,
            'send_receipts' => true,  // Peut envoyer des reçus aux patients
        ]
    ],
    'medecin' => [
        'name' => 'Médecin',
        'permissions' => [
            'view_all' => false,
            'manage_medecins' => false,
            'manage_patients' => false,  // Ne peut pas ajouter de patients (réservé à l'accueil)
            'view_patients' => true,  // Peut voir les patients de son service uniquement
            'manage_services' => false,
            'manage_rendez_vous' => true,  // Gère les RDV de son service uniquement
            'create_rendez_vous' => true,  // Peut créer des rendez-vous pour les patients de son service
            'manage_consultations' => true,  // Gère les consultations de son service
            'view_consultations' => true,  // Peut voir les consultations de son service
            'manage_ordonnances' => true,  // Peut donner des ordonnances à ses patients
            'view_ordonnances' => true,  // Peut voir les ordonnances de son service
            'view_reports' => false,
            'manage_users' => false,
            'approve_rendez_vous' => true,  // Peut confirmer les rendez-vous de son service
            'create_ordonnances' => true,
            'send_ordonnances' => true,  // Peut envoyer des ordonnances aux patients
            'manage_paiements' => false,
            'view_paiements' => true,  // Peut voir les paiements de ses consultations
            'send_receipts' => true,  // Peut envoyer des reçus aux patients
        ]
    ],
    'patient' => [
        'name' => 'Patient',
        'permissions' => [
            'view_all' => false,
            'manage_medecins' => false,
            'manage_patients' => false,
            'manage_services' => false,
            'manage_rendez_vous' => false,  // Ne peut pas gérer tous les RDV
            'create_rendez_vous' => true,  // Peut créer ses propres rendez-vous / demandes
            'manage_consultations' => false,  // Ne peut pas gérer les consultations
            'view_consultations' => true,  // Peut voir ses propres consultations
            'manage_ordonnances' => false,
            'view_ordonnances' => true,  // Peut voir ses propres ordonnances
            'view_reports' => false,
            'manage_users' => false,
            'approve_rendez_vous' => false,
            'create_ordonnances' => false,
            'manage_paiements' => false,
            'view_paiements' => true,  // Peut voir ses propres paiements
            'view_receipts' => true,  // Peut voir et lire ses propres reçus
        ]
    ],
    'accueil' => [
        'name' => 'Accueil',
        'permissions' => [
            'view_all' => false,
            'manage_medecins' => false,
            'manage_patients' => true,  // Peut créer et modifier les patients
            'view_patients' => true,  // Peut voir les patients
            'manage_services' => false,
            'manage_rendez_vous' => false,
            'create_rendez_vous' => true,  // Peut créer des rendez-vous pour les patients
            'manage_consultations' => false,  // Ne peut pas gérer les consultations
            'view_consultations' => false,  // Ne peut pas voir les consultations
            'manage_ordonnances' => false,  // Ne peut pas gérer les ordonnances
            'view_ordonnances' => false,  // Ne peut pas voir les ordonnances
            'view_reports' => false,
            'manage_users' => false,
            'approve_rendez_vous' => false,  // Ne peut pas approuver les rendez-vous
            'create_ordonnances' => false,
            'manage_paiements' => true,  // Peut créer et gérer les paiements
            'view_paiements' => true,
            'send_receipts' => true,  // Peut envoyer des reçus aux patients
        ]
    ]
];

/**
 * Vérifier si l'utilisateur a une permission spécifique
 * 
 * Cette fonction vérifie :
 * 1. Si l'utilisateur est connecté
 * 2. Si c'est un médecin en attente (permissions limitées)
 * 3. Si le rôle existe et a la permission demandée
 * 
 * @param string $permission Le nom de la permission à vérifier
 * @return bool True si l'utilisateur a la permission, false sinon
 */
function hasPermission($permission) {
    if (!estConnecte()) {
        return false;
    }
    
    global $ROLES_PERMISSIONS;
    $user_info = getUserInfo();
    $role = $user_info['role'] ?? 'patient';
    
    // Si c'est un médecin en attente, limiter les permissions
    if ($role === 'medecin' && isset($user_info['medecin_statut']) && $user_info['medecin_statut'] === 'en_attente') {
        // Permissions limitées pour les médecins en attente
        $limited_permissions = [
            'view_patients' => false,  // Ne peut pas voir les patients
            'manage_rendez_vous' => false,  // Ne peut pas gérer les rendez-vous
            'create_rendez_vous' => false,  // Ne peut pas créer de rendez-vous
            'manage_consultations' => false,  // Ne peut pas gérer les consultations
            'view_consultations' => false,  // Ne peut pas voir les consultations
            'manage_ordonnances' => false,  // Ne peut pas créer d'ordonnances
            'view_ordonnances' => false,  // Ne peut pas voir les ordonnances
            'approve_rendez_vous' => false,  // Ne peut pas approuver les rendez-vous
            'create_ordonnances' => false,  // Ne peut pas créer d'ordonnances
            'view_paiements' => false,  // Ne peut pas voir les paiements
            'send_receipts' => false  // Ne peut pas envoyer de reçus
        ];
        
        // Retourner false pour les permissions limitées, sinon vérifier les permissions normales
        if (isset($limited_permissions[$permission])) {
            return false;
        }
    }
    
    if (!isset($ROLES_PERMISSIONS[$role])) {
        return false;
    }
    
    return $ROLES_PERMISSIONS[$role]['permissions'][$permission] ?? false;
}

/**
 * Vérifier si l'utilisateur a un rôle spécifique
 */
function hasRole($role) {
    if (!estConnecte()) {
        return false;
    }
    
    $user_info = getUserInfo();
    return ($user_info['role'] ?? 'patient') === $role;
}

/**
 * Vérifier si l'utilisateur est admin
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Vérifier si l'utilisateur est médecin
 */
function isMedecin() {
    // On considère qu'un administrateur a aussi tous les droits
    // de visibilité d'un médecin.
    return hasRole('medecin') || hasRole('admin');
}

/**
 * Vérifier si l'utilisateur est patient
 */
function isPatient() {
    return hasRole('patient');
}

/**
 * Vérifier si l'utilisateur est accueil
 */
function isAccueil() {
    return hasRole('accueil');
}

/**
 * Rediriger si l'utilisateur n'a pas la permission
 * 
 * Utilisez cette fonction au début des pages pour sécuriser l'accès.
 * Exemple : requirePermission('manage_patients');
 * 
 * @param string $permission Le nom de la permission requise
 * @param string $redirect La page de redirection si pas de permission (défaut: index.php)
 */
function requirePermission($permission, $redirect = 'index.php') {
    if (!hasPermission($permission)) {
        header('Location: ' . $redirect);
        exit();
    }
}

/**
 * Rediriger si l'utilisateur n'a pas le rôle
 */
function requireRole($role, $redirect = 'index.php') {
    if (!hasRole($role)) {
        header('Location: ' . $redirect);
        exit();
    }
}

/**
 * Rediriger si l'utilisateur n'est pas admin
 */
function requireAdmin($redirect = 'index.php') {
    requireRole('admin', $redirect);
}

/**
 * Rediriger si l'utilisateur n'est pas médecin
 */
function requireMedecin($redirect = 'index.php') {
    // Autoriser l'accès soit aux médecins, soit à l'administrateur
    if (!hasRole('medecin') && !hasRole('admin')) {
        header('Location: ' . $redirect);
        exit();
    }
}

/**
 * Rediriger si l'utilisateur n'est pas accueil
 */
function requireAccueil($redirect = 'index.php') {
    requireRole('accueil', $redirect);
}

/**
 * Obtenir le nom du rôle de l'utilisateur
 */
function getRoleName($role = null) {
    global $ROLES_PERMISSIONS;
    
    if ($role === null) {
        if (!estConnecte()) {
            return 'Visiteur';
        }
        $user_info = getUserInfo();
        $role = $user_info['role'] ?? 'patient';
    }
    
    return $ROLES_PERMISSIONS[$role]['name'] ?? 'Inconnu';
}

/**
 * Vérifier si un médecin peut accéder aux données d'un service spécifique
 * 
 * Les médecins ne peuvent accéder qu'aux données de leur propre service.
 * Cette fonction vérifie que le service demandé correspond à la spécialisation du médecin.
 * 
 * @param int $id_service L'ID du service à vérifier
 * @param string|null $specialisation_med La spécialisation du médecin (optionnel, récupéré automatiquement si null)
 * @return bool True si le médecin peut accéder à ce service, false sinon
 */
function medecinCanAccessService($id_service, $specialisation_med = null) {
    if (!isMedecin()) {
        // Si ce n'est pas un médecin, la vérification n'est pas applicable
        return false;
    }
    
    if ($specialisation_med === null) {
        $user_info = getUserInfo();
        $specialisation_med = $user_info['specialisation'] ?? '';
    }
    
    if (empty($specialisation_med) || empty($id_service)) {
        return false;
    }
    
    try {
        require_once __DIR__ . '/bdd.php';
        $pdo = bdd();
        
        // Récupérer le nom du service
        $sql = "SELECT Nom_service FROM SERVICES WHERE id_service = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_service]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$service || !isset($service['Nom_service'])) {
            return false;
        }
        
        // Vérifier que le nom du service correspond à la spécialisation du médecin
        return $service['Nom_service'] === $specialisation_med;
    } catch (Exception $e) {
        error_log("Erreur medecinCanAccessService: " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifier si un patient peut accéder à ses propres données
 * 
 * Les patients ne peuvent accéder qu'à leurs propres données.
 * Cette fonction vérifie que l'ID patient demandé correspond à l'ID patient de l'utilisateur connecté.
 * 
 * @param int $id_patient L'ID du patient à vérifier
 * @return bool True si le patient peut accéder à ces données, false sinon
 */
function patientCanAccessData($id_patient) {
    if (!isPatient()) {
        // Si ce n'est pas un patient, la vérification n'est pas applicable
        return false;
    }
    
    $user_info = getUserInfo();
    $user_id_patient = $user_info['id_patient'] ?? null;
    
    if (empty($user_id_patient) || empty($id_patient)) {
        return false;
    }
    
    // Vérifier que l'ID patient correspond
    return intval($user_id_patient) === intval($id_patient);
}

/**
 * Obtenir la spécialisation du médecin connecté
 * 
 * @return string|null La spécialisation du médecin ou null si pas médecin
 */
function getMedecinSpecialisation() {
    if (!isMedecin()) {
        return null;
    }
    
    $user_info = getUserInfo();
    return $user_info['specialisation'] ?? null;
}

/**
 * Obtenir l'ID patient de l'utilisateur connecté
 * 
 * @return int|null L'ID patient ou null si pas de patient
 */
function getCurrentPatientId() {
    if (!isPatient()) {
        return null;
    }
    
    $user_info = getUserInfo();
    return $user_info['id_patient'] ?? null;
}

?>
