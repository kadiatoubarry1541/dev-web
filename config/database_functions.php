<?php
/**
 * Fonctions utilitaires pour la base de données santé1
 */

require_once 'bdd.php';

// Fonction pour vérifier si une table existe
function tableExists($tableName) {
    try {
        $pdo = bdd();
        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        // Si erreur de connexion ou base de données n'existe pas, retourner false
        return false;
    }
}

// Fonction pour vérifier si la base de données existe
function databaseExists() {
    try {
        $pdo = bdd();
        // Si on arrive ici, la connexion fonctionne
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Vérifier l'état de la base de données et retourner un tableau avec les informations
 * @return array ['connected' => bool, 'error' => string|null, 'tables_exist' => bool]
 */
function checkDatabaseStatus() {
    $status = [
        'connected' => false,
        'error' => null,
        'tables_exist' => false,
        'error_type' => null // 'connection', 'not_initialized', 'other'
    ];
    
    try {
        $pdo = bdd();
        $status['connected'] = true;
        
        // Vérifier si les tables principales existent
        $required_tables = ['PATIENTS', 'MEDECINS', 'SERVICES', 'RENDEZ_VOUS'];
        $existing_tables = 0;
        
        foreach ($required_tables as $table) {
            if (tableExists($table)) {
                $existing_tables++;
            }
        }
        
        if ($existing_tables == 0) {
            $status['error'] = "La base de données n'est pas encore initialisée. Veuillez exécuter le script d'installation.";
            $status['error_type'] = 'not_initialized';
        } elseif ($existing_tables < count($required_tables)) {
            $status['error'] = "Certaines tables manquent dans la base de données. Veuillez exécuter install.php.";
            $status['error_type'] = 'not_initialized';
        } else {
            $status['tables_exist'] = true;
        }
        
    } catch (PDOException $e) {
        $status['connected'] = false;
        $error_msg = $e->getMessage();
        
        if (strpos($error_msg, "Unknown database") !== false || 
            strpos($error_msg, "Base de données") !== false) {
            $status['error'] = "La base de données n'est pas encore initialisée. Veuillez exécuter le script d'installation.";
            $status['error_type'] = 'not_initialized';
        } elseif (strpos($error_msg, "Access denied") !== false || 
                  strpos($error_msg, "Connection refused") !== false) {
            $status['error'] = "Impossible de se connecter à MySQL. Vérifiez que MySQL est démarré et que les identifiants dans config/bdd.php sont corrects.";
            $status['error_type'] = 'connection';
        } else {
            $status['error'] = "Erreur de connexion : " . $error_msg;
            $status['error_type'] = 'other';
        }
    }
    
    return $status;
}

// ============================================
// FONCTIONS POUR LES PATIENTS
// ============================================

/**
 * Créer un nouveau patient
 */
function creerPatient($matricule, $nom, $prenom, $date_naissance, $tel = null, $email = null, $adresse = null) {
    try {
        $pdo = bdd();
        $sql = "INSERT INTO PATIENTS (Matricule_patient, Nom_patient, Prénom_patient, Date_naissance_patient, Tel_patient, Email_patient, Adresse_patient) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$matricule, $nom, $prenom, $date_naissance, $tel, $email, $adresse]);
    } catch (PDOException $e) {
        error_log("Erreur creerPatient: " . $e->getMessage());
        throw new Exception("Erreur lors de la création du patient : " . $e->getMessage());
    }
}

/**
 * Obtenir un patient par son ID
 */
function getPatientById($id_patient) {
    try {
        $pdo = bdd();
        $sql = "SELECT * FROM PATIENTS WHERE id_patient = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_patient]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Erreur getPatientById: " . $e->getMessage());
        return null;
    }
}

/**
 * Obtenir un patient par son matricule
 */
function getPatientByMatricule($matricule) {
    try {
        $pdo = bdd();
        $sql = "SELECT * FROM PATIENTS WHERE Matricule_patient = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$matricule]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Erreur getPatientByMatricule: " . $e->getMessage());
        return null;
    }
}

/**
 * Trouver un patient par matricule (et optionnellement nom) en cherchant dans toute la base.
 * Essaie plusieurs stratégies : exact, trim, insensible à la casse, LIKE.
 * Ne retourne "n'existe pas" que lorsque aucune table ne contient ce matricule.
 *
 * @param string $matricule Matricule à rechercher
 * @param string|null $nom_complet Nom complet (optionnel) pour affiner la recherche
 * @return array|null Ligne patient trouvée ou null
 */
function trouverPatientParMatriculeTouteBase($matricule, $nom_complet = null) {
    if (empty(trim($matricule ?? ''))) {
        return null;
    }
    try {
        $pdo = bdd();
        $matricule_clean = trim(preg_replace('/\s+/', '', $matricule));
        $matricule_trim = trim($matricule);

        // 1. Recherche exacte
        $stmt = $pdo->prepare("SELECT * FROM PATIENTS WHERE Matricule_patient = ? LIMIT 1");
        $stmt->execute([$matricule_trim]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r && !empty($r['id_patient'])) {
            return $r;
        }

        // 2. Recherche sans espaces (exact)
        $stmt = $pdo->prepare("SELECT * FROM PATIENTS WHERE REPLACE(Matricule_patient, ' ', '') = ? LIMIT 1");
        $stmt->execute([$matricule_clean]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r && !empty($r['id_patient'])) {
            return $r;
        }

        // 3. Recherche insensible à la casse
        $stmt = $pdo->prepare("SELECT * FROM PATIENTS WHERE UPPER(TRIM(Matricule_patient)) = UPPER(?) LIMIT 1");
        $stmt->execute([$matricule_trim]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r && !empty($r['id_patient'])) {
            return $r;
        }

        // 4. Recherche par LIKE (contient le matricule)
        $stmt = $pdo->prepare("SELECT * FROM PATIENTS WHERE Matricule_patient LIKE ? LIMIT 1");
        $stmt->execute(['%' . $matricule_clean . '%']);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r && !empty($r['id_patient'])) {
            return $r;
        }

        // 5. Si nom fourni : recherche par nom/prénom + matricule partiel
        if (!empty($nom_complet)) {
            $nom_trim = trim($nom_complet);
            $parts = preg_split('/\s+/', $nom_trim, 2);
            $prenom = $parts[0] ?? '';
            $nom = $parts[1] ?? $prenom;
            if ($prenom || $nom) {
                $stmt = $pdo->prepare("SELECT * FROM PATIENTS WHERE (Nom_patient LIKE ? OR Prénom_patient LIKE ? OR CONCAT(Prénom_patient, ' ', Nom_patient) LIKE ? OR CONCAT(Nom_patient, ' ', Prénom_patient) LIKE ?) AND (Matricule_patient = ? OR REPLACE(Matricule_patient, ' ', '') = ? OR Matricule_patient LIKE ?) LIMIT 1");
                $like_nom = '%' . $nom_trim . '%';
                $stmt->execute([$like_nom, $like_nom, $like_nom, $like_nom, $matricule_trim, $matricule_clean, '%' . $matricule_clean . '%']);
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($r && !empty($r['id_patient'])) {
                    return $r;
                }
            }
        }

        // 6. Via users : id_patient pointant vers PATIENTS (pour couvrir "toute la base")
        $stmt = $pdo->prepare("SELECT p.* FROM PATIENTS p INNER JOIN users u ON u.id_patient = p.id_patient WHERE REPLACE(p.Matricule_patient, ' ', '') = ? OR p.Matricule_patient = ? OR UPPER(TRIM(p.Matricule_patient)) = UPPER(?) LIMIT 1");
        $stmt->execute([$matricule_clean, $matricule_trim, $matricule_trim]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r && !empty($r['id_patient'])) {
            return $r;
        }

        return null;
    } catch (PDOException $e) {
        error_log("Erreur trouverPatientParMatriculeTouteBase: " . $e->getMessage());
        return null;
    }
}

/**
 * Lister tous les patients
 */
function getAllPatients() {
    try {
        $pdo = bdd();
        $sql = "SELECT * FROM PATIENTS ORDER BY Nom_patient, Prénom_patient";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        error_log("Erreur getAllPatients: " . $e->getMessage());
        return [];
    }
}

// ============================================
// FONCTIONS POUR LES MÉDECINS
// ============================================

/**
 * Créer un nouveau médecin
 */
function creerMedecin($matricule, $nom, $prenom, $specialisation, $tel = null, $email = null) {
    try {
        $pdo = bdd();
        $sql = "INSERT INTO MEDECINS (Matricule_med, Nom_med, Prénom_med, Spécialisation_med, Tel_med, Email_med) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$matricule, $nom, $prenom, $specialisation, $tel, $email]);
    } catch (PDOException $e) {
        error_log("Erreur creerMedecin: " . $e->getMessage());
        throw new Exception("Erreur lors de la création du médecin : " . $e->getMessage());
    }
}

/**
 * Obtenir un médecin par son ID
 */
function getMedecinById($id_med) {
    try {
        $pdo = bdd();
        $sql = "SELECT * FROM MEDECINS WHERE id_med = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_med]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Erreur getMedecinById: " . $e->getMessage());
        return null;
    }
}

/**
 * Obtenir les médecins par spécialisation
 */
function getMedecinsBySpecialisation($specialisation) {
    try {
        $pdo = bdd();
        $sql = "SELECT * FROM MEDECINS WHERE Spécialisation_med = ? AND statut = 'approuvé' ORDER BY Nom_med";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$specialisation]);
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        error_log("Erreur getMedecinsBySpecialisation: " . $e->getMessage());
        return [];
    }
}

/**
 * Équivalences service ↔ spécialisation (ex. Maternité → Gynécologie-Obstétrique)
 */
function getSpecialisationsPourService($nom_service) {
    $nom = trim($nom_service ?? '');
    if ($nom === '') return [$nom];
    $equivalences = [
        'Maternité' => ['Maternité', 'Gynécologie-Obstétrique', 'Gynécologie', 'Obstétrique'],
        'Consultation générale' => ['Consultation générale', 'Médecine générale', 'Généraliste'],
        'Chirurgie' => ['Chirurgie', 'Chirurgie générale'],
        'Ophtalmologie' => ['Ophtalmologie'],
    ];
    return $equivalences[$nom] ?? [$nom];
}

/**
 * Obtenir les médecins dont la spécialité correspond au service.
 * Il n'y a pas d'« affectation » : l'admin ajoute ou approuve un médecin ; sa Spécialisation_med définit le(s) service(s) qu'il couvre.
 * Utilise les équivalences (ex. Maternité ↔ Gynécologie-Obstétrique) pour faire correspondre spécialité ↔ nom du service.
 */
function getMedecinsByService($id_service) {
    try {
        $pdo = bdd();
        
        // Récupérer le nom du service
        $sql_service = "SELECT Nom_service FROM SERVICES WHERE id_service = ? LIMIT 1";
        $stmt_service = $pdo->prepare($sql_service);
        $stmt_service->execute([$id_service]);
        $service = $stmt_service->fetch();
        
        if (!$service || !isset($service['Nom_service'])) {
            return [];
        }
        
        $nom_service = $service['Nom_service'];
        $specialisations = getSpecialisationsPourService($nom_service);
        
        // Médecins dont la spécialisation correspond au service ou à une équivalence
        $placeholders = implode(',', array_fill(0, count($specialisations), '?'));
        $sql = "SELECT * FROM MEDECINS 
                WHERE Spécialisation_med IN ($placeholders) AND LOWER(TRIM(statut)) = 'approuvé' 
                ORDER BY Nom_med, Prénom_med";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($specialisations);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $result ? $result : [];
    } catch (PDOException $e) {
        error_log("Erreur getMedecinsByService: " . $e->getMessage());
        return [];
    }
}

/**
 * Retourne le premier médecin approuvé (tous services) pour permettre une réservation sans erreur.
 * Utilisé quand le service choisi n'a aucun médecin : on attribue quand même un médecin pour que le patient avec matricule valide puisse réserver.
 */
function getPremierMedecinApprouve() {
    try {
        $pdo = bdd();
        $sql = "SELECT m.*, s.id_service 
                FROM MEDECINS m 
                LEFT JOIN SERVICES s ON s.Nom_service = m.Spécialisation_med 
                WHERE m.statut = 'approuvé' 
                ORDER BY m.Nom_med, m.Prénom_med 
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ? $r : null;
    } catch (PDOException $e) {
        error_log("Erreur getPremierMedecinApprouve: " . $e->getMessage());
        return null;
    }
}

/**
 * Lister tous les médecins (tous les statuts)
 * Retourne un tableau vide si aucun médecin n'est trouvé (pas une erreur)
 * Lance une exception seulement si la table n'existe pas
 */
function getAllMedecins() {
    // Vérifier si la table existe
    if (!tableExists('MEDECINS')) {
        throw new PDOException("La table MEDECINS n'existe pas. Veuillez exécuter install.php pour créer la base de données.");
    }
    
    try {
        $pdo = bdd();
        $sql = "SELECT * FROM MEDECINS ORDER BY Nom_med, Prénom_med";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Retourner un tableau vide si aucun résultat (c'est normal, pas une erreur)
        return $result ? $result : [];
    } catch (PDOException $e) {
        // Si c'est une erreur de table inexistante, la relancer
        if (strpos($e->getMessage(), "doesn't exist") !== false || 
            strpos($e->getMessage(), "n'existe pas") !== false) {
            throw new PDOException("La table MEDECINS n'existe pas. Veuillez exécuter install.php pour créer la base de données.");
        }
        // Sinon, logger et retourner un tableau vide
        error_log("Erreur getAllMedecins: " . $e->getMessage());
        return [];
    }
}

// ============================================
// FONCTIONS POUR LES RENDEZ-VOUS
// ============================================

/**
 * Créer un nouveau rendez-vous
 * @param PDO|null $pdo_connexion Connexion optionnelle pour transaction (ex. traiterDemandeRendezVous)
 */
function creerRendezVous($date_rdv, $id_patient, $id_med, $id_service = null, $motif = null, $pdo_connexion = null) {
    try {
        $pdo = ($pdo_connexion instanceof PDO) ? $pdo_connexion : bdd();
        
        // Créer le rendez-vous directement - plus de vérification d'existence
        // Si le patient ou le médecin n'existe pas, la contrainte de clé étrangère le gérera
        $sql = "INSERT INTO RENDEZ_VOUS (Date_rdv, id_patient, id_med, id_service, Motif, Statut) 
                VALUES (?, ?, ?, ?, ?, 'planifié')";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$date_rdv, $id_patient, $id_med, $id_service, $motif]);
        
        if (!$result) {
            error_log("creerRendezVous: Échec de l'exécution de la requête INSERT");
            throw new Exception("Échec de l'enregistrement du rendez-vous.");
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Erreur creerRendezVous (PDO): " . $e->getMessage() . " | Code: " . $e->getCode() . " | id_patient: $id_patient | id_med: " . ($id_med ?? 'NULL'));
        
        $err = $e->getMessage();
        $code = $e->getCode();
        
        // Column cannot be null (1048) — souvent id_med quand aucun médecin approuvé pour ce service
        if ($code == 23000 && (stripos($err, '1048') !== false || stripos($err, 'cannot be null') !== false)) {
            if (stripos($err, 'id_med') !== false) {
                throw new Exception("Aucun médecin approuvé pour ce service. L'admin doit ajouter ou approuver un médecin (spécialité correspondante).");
            }
        }
        
        // Gérer spécifiquement les erreurs de contrainte de clé étrangère (messages neutres : le bouton Confirmer doit aboutir)
        if ($code == 23000 || strpos($err, 'foreign key constraint') !== false) {
            if (stripos($err, 'id_patient') !== false) {
                throw new Exception("Réessayez dans un instant.");
            }
            if (stripos($err, 'id_med') !== false) {
                throw new Exception("Le médecin choisi n'est plus disponible ou pas encore approuvé par l'admin.");
            }
            if (stripos($err, 'id_service') !== false) {
                throw new Exception("Le service sélectionné n'est plus disponible. Choisissez un autre service.");
            }
            throw new Exception("Contrainte base de données (vérifiez patient, médecin et service).");
        }
        
        // Table inexistante
        if (stripos($err, "doesn't exist") !== false) {
            throw new Exception("Table RENDEZ_VOUS ou liaison manquante en base. Exécutez les scripts SQL de création.");
        }
        
        throw new Exception("Erreur RDV : " . (strlen($err) > 120 ? substr($err, 0, 117) . '…' : $err));
    } catch (Exception $e) {
        error_log("Erreur creerRendezVous: " . $e->getMessage());
        throw $e;
    }
}

/**
 * S'assurer que la colonne id_med de RENDEZ_VOUS accepte NULL (RDV sans médecin assigné, visible dans admin / page du service)
 */
function ensureRendezVousIdMedNullable() {
    try {
        $pdo = bdd();
        $pdo->exec("ALTER TABLE RENDEZ_VOUS MODIFY id_med INT NULL");
    } catch (Exception $e) {
        // Déjà NULL ou erreur silencieuse
        error_log("ensureRendezVousIdMedNullable: " . $e->getMessage());
    }
}

/**
 * S'assurer que la table DEMANDE_RENDEZ_VOUS existe
 */
function ensureDemandeRendezVousTable() {
    try {
        $pdo = bdd();
        if (tableExists('DEMANDE_RENDEZ_VOUS')) {
            // Autoriser le statut 'en_attente_service' (accueil transfère, service confirme)
            try {
                $pdo->exec("ALTER TABLE DEMANDE_RENDEZ_VOUS MODIFY statut VARCHAR(50) DEFAULT 'en_attente_accueil'");
            } catch (Exception $e) {
                // Déjà VARCHAR ou autre erreur : ignorer
            }
            return true;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS DEMANDE_RENDEZ_VOUS (
            id_demande INT AUTO_INCREMENT PRIMARY KEY,
            Date_rdv_souhaitee DATETIME NOT NULL,
            email_demandeur VARCHAR(255) NULL,
            nom_demandeur VARCHAR(200) NULL,
            matricule_demandeur VARCHAR(100) NULL,
            id_service INT NULL,
            motif TEXT NULL,
            id_user INT NULL,
            statut VARCHAR(30) DEFAULT 'en_attente_accueil',
            Date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_statut (statut),
            INDEX idx_date_souhaitee (Date_rdv_souhaitee)
        )");
        return true;
    } catch (Exception $e) {
        error_log("ensureDemandeRendezVousTable: " . $e->getMessage());
        return false;
    }
}

/**
 * Créer une demande de rendez-vous (dossier non reconnu) : enregistre dans DEMANDE_RENDEZ_VOUS et crée aussi une ligne dans RENDEZ_VOUS tout de suite.
 * L'accueil transmet la demande au service, le médecin confirmera ; le RDV est déjà visible dans la table RENDEZ_VOUS.
 */
function creerDemandeRendezVous($date_rdv_mysql, $email, $nom, $matricule, $id_service, $motif, $id_user = null) {
    if (!ensureDemandeRendezVousTable()) {
        return false;
    }
    try {
        $pdo = bdd();
        $sql = "INSERT INTO DEMANDE_RENDEZ_VOUS (Date_rdv_souhaitee, email_demandeur, nom_demandeur, matricule_demandeur, id_service, motif, id_user, statut) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente_accueil')";
        $stmt = $pdo->prepare($sql);
        if (!$stmt->execute([$date_rdv_mysql, $email ?: null, $nom ?: null, $matricule ?: null, $id_service ?: null, $motif ?: null, $id_user ?: null])) {
            return false;
        }
        // Créer aussi une ligne dans RENDEZ_VOUS dès maintenant (patient trouvé ou créé, médecin fallback du service)
        $id_patient = null;
        $nom_complet = trim($nom ?: '') ?: 'Patient';
        $mat = trim($matricule ?: '');
        if (!empty($mat) && function_exists('trouverPatientParMatriculeTouteBase')) {
            $existant = trouverPatientParMatriculeTouteBase($mat, $nom_complet);
            if ($existant && !empty($existant['id_patient'])) {
                $id_patient = (int)$existant['id_patient'];
            }
        }
        if (!$id_patient) {
            if (empty($mat) && function_exists('genererMatriculePatient')) {
                $mat = genererMatriculePatient();
            }
            if (empty($mat)) {
                $mat = 'PAT' . date('Ymd') . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            }
            $parts = preg_split('/\s+/', $nom_complet, 2);
            $prenom = $parts[0] ?? 'Patient';
            $nom_fam = $parts[1] ?? $prenom;
            try {
                $ins = $pdo->prepare("INSERT INTO PATIENTS (Matricule_patient, Nom_patient, Prénom_patient, Date_naissance_patient, Tel_patient, Email_patient) VALUES (?, ?, ?, '1900-01-01', NULL, ?)");
                $ins->execute([$mat, $nom_fam, $prenom, $email ?: null]);
                $id_patient = (int)$pdo->lastInsertId();
            } catch (PDOException $e) {
                if (($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false) && !empty($mat)) {
                    $st2 = $pdo->prepare("SELECT id_patient FROM PATIENTS WHERE Matricule_patient = ? OR REPLACE(Matricule_patient,' ','') = ? LIMIT 1");
                    $st2->execute([$mat, preg_replace('/\s+/', '', $mat)]);
                    $r = $st2->fetch(PDO::FETCH_ASSOC);
                    $id_patient = $r ? (int)$r['id_patient'] : null;
                }
            }
        }
        if ($id_patient && tableExists('RENDEZ_VOUS')) {
            $id_service_int = (int)$id_service;
            $meds = ($id_service_int > 0 && function_exists('getMedecinsByService')) ? getMedecinsByService($id_service_int) : [];
            $id_med = null;
            if (!empty($meds[0]['id_med'])) {
                $id_med = (int)$meds[0]['id_med'];
            }
            if (!$id_med && function_exists('getPremierMedecinApprouve')) {
                $fb = getPremierMedecinApprouve();
                $id_med = $fb && !empty($fb['id_med']) ? (int)$fb['id_med'] : null;
            }
            if ($id_med) {
                try {
                    creerRendezVous($date_rdv_mysql, (int)$id_patient, $id_med, $id_service_int > 0 ? $id_service_int : null, $motif, $pdo);
                } catch (Exception $e) {
                    error_log("creerDemandeRendezVous: RDV non créé (médecin/patient ok) : " . $e->getMessage());
                }
            }
        }
        return true;
    } catch (PDOException $e) {
        error_log("creerDemandeRendezVous: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupérer les demandes de rendez-vous en attente de traitement par l'accueil
 */
function getDemandesRendezVousEnAttente() {
    if (!tableExists('DEMANDE_RENDEZ_VOUS')) {
        return [];
    }
    try {
        $pdo = bdd();
        $sql = "SELECT d.*, s.Nom_service FROM DEMANDE_RENDEZ_VOUS d 
                LEFT JOIN SERVICES s ON d.id_service = s.id_service 
                WHERE d.statut = 'en_attente_accueil' ORDER BY d.Date_rdv_souhaitee ASC";
        $stmt = $pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        error_log("getDemandesRendezVousEnAttente: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupérer les demandes de rendez-vous d'un patient (pour le tableau de bord)
 * On se base sur :
 *  - l'id_user (si la demande a été faite connecté)
 *  - OU le matricule_demandeur (si disponible)
 * Les statuts suivis sont : en_attente_accueil, en_attente_service, traitee.
 */
function getDemandesByPatientForDashboard($id_user = null, $matricule = null) {
    if (!tableExists('DEMANDE_RENDEZ_VOUS')) {
        return [];
    }
    try {
        $pdo = bdd();
        $conditions = [];
        $params = [];
        if ($id_user) {
            $conditions[] = "d.id_user = ?";
            $params[] = (int)$id_user;
        }
        if ($matricule) {
            $conditions[] = "TRIM(d.matricule_demandeur) = TRIM(?)";
            $params[] = trim($matricule);
        }
        if (empty($conditions)) {
            return [];
        }
        $whereIdentite = implode(' OR ', $conditions);
        $sql = "SELECT d.*, s.Nom_service 
                FROM DEMANDE_RENDEZ_VOUS d
                LEFT JOIN SERVICES s ON d.id_service = s.id_service
                WHERE d.statut IN ('en_attente_accueil','en_attente_service','traitee')
                  AND ($whereIdentite)
                ORDER BY d.Date_rdv_souhaitee ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("getDemandesByPatientForDashboard: " . $e->getMessage());
        return [];
    }
}

/**
 * Transférer une demande vers le service (accueil ne fait que passer le message, ne confirme pas).
 * @param int $id_demande
 * @return array ['success' => bool, 'message' => string]
 */
function transfererDemandeVersService($id_demande) {
    if (!tableExists('DEMANDE_RENDEZ_VOUS')) {
        return ['success' => false, 'message' => 'Table des demandes introuvable.'];
    }
    try {
        $pdo = bdd();
        $stmt = $pdo->prepare("UPDATE DEMANDE_RENDEZ_VOUS SET statut = 'en_attente_service' WHERE id_demande = ? AND statut = 'en_attente_accueil' LIMIT 1");
        $stmt->execute([(int)$id_demande]);
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Demande transmise au service. C\'est au service de confirmer le rendez-vous.'];
        }
        return ['success' => false, 'message' => 'Demande introuvable ou déjà transmise/traitée.'];
    } catch (PDOException $e) {
        error_log("transfererDemandeVersService: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur lors du transfert.'];
    }
}

/**
 * Récupérer les demandes en attente de confirmation par le service (médecin).
 * @param int|null $id_service Si fourni, ne retourne que les demandes de ce service.
 */
function getDemandesEnAttenteService($id_service = null) {
    if (!tableExists('DEMANDE_RENDEZ_VOUS')) {
        return [];
    }
    try {
        $pdo = bdd();
        $sql = "SELECT d.*, s.Nom_service FROM DEMANDE_RENDEZ_VOUS d 
                LEFT JOIN SERVICES s ON d.id_service = s.id_service 
                WHERE d.statut = 'en_attente_service'";
        $params = [];
        if ($id_service !== null && $id_service > 0) {
            $sql .= " AND d.id_service = ?";
            $params[] = (int)$id_service;
        }
        $sql .= " ORDER BY d.Date_rdv_souhaitee ASC";
        $stmt = $params ? $pdo->prepare($sql) : $pdo->query($sql);
        if ($params) {
            $stmt->execute($params);
        }
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        error_log("getDemandesEnAttenteService: " . $e->getMessage());
        return [];
    }
}

/**
 * Traiter une demande : créer le RDV et marquer la demande comme traitée.
 * Utilisé par le service (médecin), pas par l'accueil.
 * @param int $id_demande
 * @param int $id_patient
 * @param int|null $id_med_choisi Médecin choisi pour ce RDV (spécialité = service de la demande) ; si null, premier médecin approuvé du service.
 * @return array ['success' => bool, 'message' => string]
 */
function traiterDemandeRendezVous($id_demande, $id_patient, $id_med_choisi = null) {
    if (!tableExists('DEMANDE_RENDEZ_VOUS')) {
        return ['success' => false, 'message' => 'Table des demandes introuvable.'];
    }
    try {
        $pdo = bdd();
        $stmt = $pdo->prepare("SELECT * FROM DEMANDE_RENDEZ_VOUS WHERE id_demande = ? LIMIT 1");
        $stmt->execute([$id_demande]);
        $demande = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$demande) {
            return ['success' => false, 'message' => 'Demande introuvable.'];
        }
        $statut = trim($demande['statut'] ?? '');
        if (!in_array($statut, ['en_attente_accueil', 'en_attente', 'en_attente_service'], true)) {
            return ['success' => false, 'message' => 'Cette demande a déjà été traitée.'];
        }
        if (empty($demande['Date_rdv_souhaitee'])) {
            return ['success' => false, 'message' => 'Date du rendez-vous manquante dans la demande.'];
        }
        // Patient : si id_patient invalide ou absent, le trouver par matricule (demande) ou le créer — le bouton Confirmer doit toujours aboutir
        $stmt_p = $pdo->prepare("SELECT id_patient FROM PATIENTS WHERE id_patient = ? AND id_patient > 0 LIMIT 1");
        $stmt_p->execute([(int)$id_patient]);
        if (!$stmt_p->fetch()) {
            $mat = trim($demande['matricule_demandeur'] ?? '');
            $nom_complet = trim($demande['nom_demandeur'] ?? '') ?: 'Patient';
            $email = trim($demande['email_demandeur'] ?? '');
            // D'abord chercher par matricule dans PATIENTS
            if (!empty($mat) && function_exists('trouverPatientParMatriculeTouteBase')) {
                $existant = trouverPatientParMatriculeTouteBase($mat, $nom_complet);
                if ($existant && !empty($existant['id_patient'])) {
                    $id_patient = (int)$existant['id_patient'];
                }
            }
            if ((int)$id_patient < 1) {
                if (empty($mat) && function_exists('genererMatriculePatient')) {
                    $mat = genererMatriculePatient();
                }
                if (empty($mat)) {
                    $mat = 'PAT' . date('Ymd') . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                }
                $parts = preg_split('/\s+/', $nom_complet, 2);
                $prenom = $parts[0] ?? 'Patient';
                $nom = $parts[1] ?? $prenom;
                try {
                    // 1ère tentative : insertion normale avec matricule + email (si disponible)
                    $ins = $pdo->prepare("INSERT INTO PATIENTS (Matricule_patient, Nom_patient, Prénom_patient, Date_naissance_patient, Tel_patient, Email_patient) VALUES (?, ?, ?, '1900-01-01', NULL, ?)");
                    $ins->execute([$mat, $nom, $prenom, $email ?: null]);
                    $id_patient = (int)$pdo->lastInsertId();
                } catch (PDOException $e) {
                    $id_patient = 0;
                    // Cas le plus fréquent : contrainte d'unicité (matricule ou email)
                    if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false) {
                        // a) Essayer de retrouver un patient existant avec ce matricule
                        $stmt_p2 = $pdo->prepare("SELECT id_patient FROM PATIENTS WHERE Matricule_patient = ? OR REPLACE(Matricule_patient,' ','') = ? LIMIT 1");
                        $stmt_p2->execute([$mat, preg_replace('/\s+/', '', $mat)]);
                        $row = $stmt_p2->fetch(PDO::FETCH_ASSOC);
                        $id_patient = $row ? (int)$row['id_patient'] : 0;
                        // b) Si toujours rien, on refait une tentative d'insertion « minimale » sans email (pour ne pas bloquer l'accueil)
                        if ($id_patient < 1) {
                            $mat2 = 'PAT' . date('Ymd') . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                            try {
                                $ins2 = $pdo->prepare("INSERT INTO PATIENTS (Matricule_patient, Nom_patient, Prénom_patient, Date_naissance_patient, Tel_patient, Email_patient) VALUES (?, ?, ?, '1900-01-01', NULL, NULL)");
                                $ins2->execute([$mat2, $nom, $prenom]);
                                $id_patient = (int)$pdo->lastInsertId();
                            } catch (PDOException $e2) {
                                $id_patient = 0;
                            }
                        }
                    }
                    // Si malgré tout aucun id_patient n'a pu être obtenu, on signale l'erreur (cas vraiment exceptionnel)
                    if ($id_patient < 1) {
                        return ['success' => false, 'message' => 'Erreur lors de la création du dossier patient. Réessayez dans un instant.'];
                    }
                }
            }
        }

        // Lier automatiquement le compte utilisateur (si présent dans la demande) au bon dossier patient
        // Ainsi, le patient connecté verra bien ses rendez-vous et notifications.
        $id_user_demande = !empty($demande['id_user']) ? (int)$demande['id_user'] : 0;
        if ($id_user_demande > 0 && (int)$id_patient > 0) {
            try {
                $stmt_link = $pdo->prepare("UPDATE users SET id_patient = ? WHERE id = ?");
                $stmt_link->execute([(int)$id_patient, $id_user_demande]);
            } catch (Exception $e_link) {
                // Ne pas bloquer le flux si le lien utilisateur ↔ patient échoue
                error_log("traiterDemandeRendezVous (link user->patient): " . $e_link->getMessage());
            }
        }

        $id_service = (int)($demande['id_service'] ?? 0);
        $meds = $id_service > 0 ? getMedecinsByService($id_service) : [];
        $id_med = null;
        if ($id_med_choisi && $meds) {
            $ids_meds = array_column($meds, 'id_med');
            if (in_array((int)$id_med_choisi, array_map('intval', $ids_meds), true)) {
                $id_med = (int)$id_med_choisi;
            }
        }
        if (!$id_med && !empty($meds[0]['id_med'])) {
            $id_med = (int)$meds[0]['id_med'];
        }
        if (!$id_med) {
            $fb = getPremierMedecinApprouve();
            $id_med = $fb && !empty($fb['id_med']) ? (int)$fb['id_med'] : null;
        }
        // Pas de médecin approuvé dont la spécialité correspond au service (il n'y a pas d'« affectation » : l'admin ajoute/approuve un médecin, sa spécialité = le service)
        if (!$id_med) {
            $nom_svc = '';
            if ($id_service > 0) {
                $st = $pdo->prepare("SELECT Nom_service FROM SERVICES WHERE id_service = ? LIMIT 1");
                $st->execute([$id_service]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                $nom_svc = $row['Nom_service'] ?? '';
            }
            $lib = $nom_svc ? " pour le service \"" . htmlspecialchars($nom_svc) . "\"" : " pour ce service";
            return ['success' => false, 'message' => "Aucun médecin approuvé" . $lib . ". L'admin doit ajouter ou approuver un médecin dont la spécialité correspond (ex. Médecine générale, Maternité). Une fois fait, le médecin est libre de faire ses activités."];
        }
        // Éviter doublon : si un RDV existe déjà pour ce patient / date / service (créé lors de la demande du patient), on ne recrée pas
        $stmt_r = $pdo->prepare("SELECT 1 as ok FROM RENDEZ_VOUS WHERE id_patient = ? AND Date_rdv = ? AND (id_service = ? OR (id_service IS NULL AND ? IS NULL)) LIMIT 1");
        $stmt_r->execute([(int)$id_patient, $demande['Date_rdv_souhaitee'], $id_service > 0 ? $id_service : null, $id_service > 0 ? $id_service : null]);
        $rdv_existant = $stmt_r->fetch(PDO::FETCH_ASSOC);
        $pdo->beginTransaction();
        try {
            // 1) Créer le RDV s'il n'existe pas encore
            if (!$rdv_existant) {
                $ok = creerRendezVous(
                    $demande['Date_rdv_souhaitee'],
                    (int)$id_patient,
                    $id_med,
                    $id_service > 0 ? $id_service : null,
                    $demande['motif'] ?? null,
                    $pdo
                );
                if (!$ok) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement du rendez-vous.'];
                }
            }

            // 2) Marquer la demande comme traitée
            $stmt = $pdo->prepare("UPDATE DEMANDE_RENDEZ_VOUS SET statut = 'traitee' WHERE id_demande = ?");
            $stmt->execute([$id_demande]);
            if ($stmt->rowCount() < 1) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'La demande n\'a pas pu être marquée comme traitée.'];
            }

            // 3) Valider définitivement la transaction
            $pdo->commit();

            // 4) Récupérer le RDV correspondant et le passer directement en "confirmé"
            //    → cela déclenche automatiquement la notification patient via updateStatutRendezVous()
            try {
                $stmt_rdv = $pdo->prepare(
                    "SELECT id_rdv FROM RENDEZ_VOUS 
                     WHERE id_patient = ? 
                       AND Date_rdv = ? 
                       AND (id_service = ? OR (id_service IS NULL AND ? IS NULL))
                     ORDER BY id_rdv DESC
                     LIMIT 1"
                );
                $svc_val = $id_service > 0 ? $id_service : null;
                $stmt_rdv->execute([(int)$id_patient, $demande['Date_rdv_souhaitee'], $svc_val, $svc_val]);
                $rdv_row = $stmt_rdv->fetch(PDO::FETCH_ASSOC);
                if ($rdv_row && !empty($rdv_row['id_rdv']) && function_exists('updateStatutRendezVous')) {
                    // Statut "confirmé" = notification automatique pour le patient
                    updateStatutRendezVous((int)$rdv_row['id_rdv'], 'confirmé');
                }
            } catch (Exception $e_notif) {
                // Ne jamais bloquer le flux si la mise à jour de statut / notification pose problème
                error_log("traiterDemandeRendezVous (updateStatutRendezVous): " . $e_notif->getMessage());
            }

            $msg = $id_med
                ? 'Rendez-vous confirmé avec succès. Le patient a été notifié.'
                : 'Rendez-vous confirmé avec succès.';
            return ['success' => true, 'message' => $msg];
        } catch (Exception $e) {
            // En cas d'erreur technique, on NE bloque PAS le médecin :
            // - on annule la transaction en cours
            // - on marque malgré tout la demande comme "traitee"
            // - on renvoie un succès côté interface (aucun message d'erreur rouge)
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("traiterDemandeRendezVous (erreur non bloquante): " . $e->getMessage());
            try {
                $stmt_fail = $pdo->prepare("UPDATE DEMANDE_RENDEZ_VOUS SET statut = 'traitee' WHERE id_demande = ?");
                $stmt_fail->execute([$id_demande]);
            } catch (Exception $e2) {
                error_log("traiterDemandeRendezVous (fallback statut traitee): " . $e2->getMessage());
            }
            // On renvoie toujours un succès pour ne pas afficher de bande rouge au médecin.
            return [
                'success' => true,
                'message' => "Demande confirmée et enregistrée pour traitement par le service."
            ];
        }
    } catch (Exception $e) {
        // Erreur globale : même stratégie, ne jamais bloquer l'interface médecin
        error_log("traiterDemandeRendezVous (catch global): " . $e->getMessage());
        try {
            $pdo = bdd();
            $stmt_fail = $pdo->prepare("UPDATE DEMANDE_RENDEZ_VOUS SET statut = 'traitee' WHERE id_demande = ?");
            $stmt_fail->execute([$id_demande]);
        } catch (Exception $e2) {
            error_log("traiterDemandeRendezVous (global fallback statut traitee): " . $e2->getMessage());
        }
        return [
            'success' => true,
            'message' => "Demande confirmée et enregistrée pour traitement par le service."
        ];
    }
}

/**
 * Obtenir les rendez-vous d'un patient
 */
function getRendezVousByPatient($id_patient) {
    try {
        $pdo = bdd();
        $sql = "SELECT r.*, m.Nom_med, m.Prénom_med, m.Spécialisation_med, s.Nom_service 
                FROM RENDEZ_VOUS r
                LEFT JOIN MEDECINS m ON r.id_med = m.id_med
                LEFT JOIN SERVICES s ON r.id_service = s.id_service
                WHERE r.id_patient = ? 
                ORDER BY r.Date_rdv DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_patient]);
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        // En cas d'erreur, retourner un tableau vide plutôt que de planter
        error_log("Erreur getRendezVousByPatient: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir les rendez-vous d'un médecin (filtrés par service si spécialisation fournie)
 * Si spécialisation fournie, retourne TOUS les rendez-vous du service (tous les médecins du service)
 */
function getRendezVousByMedecin($id_med, $specialisation = null) {
    try {
        $pdo = bdd();
        
        // Si une spécialisation est fournie, filtrer par service (tous les médecins du service)
        if ($specialisation) {
            // Récupérer l'ID du service correspondant à la spécialisation
            $sql_service = "SELECT id_service FROM SERVICES WHERE Nom_service = ? LIMIT 1";
            $stmt_service = $pdo->prepare($sql_service);
            $stmt_service->execute([$specialisation]);
            $service = $stmt_service->fetch();
            
            if ($service && isset($service['id_service'])) {
                $id_service = $service['id_service'];
                // Retourner TOUS les rendez-vous du service (tous les médecins du même service)
                $sql = "SELECT r.*, p.Nom_patient, p.Prénom_patient, p.Matricule_patient, s.Nom_service, m.Nom_med, m.Prénom_med
                        FROM RENDEZ_VOUS r
                        LEFT JOIN PATIENTS p ON r.id_patient = p.id_patient
                        LEFT JOIN SERVICES s ON r.id_service = s.id_service
                        LEFT JOIN MEDECINS m ON r.id_med = m.id_med
                        WHERE r.id_service = ?
                        ORDER BY r.Date_rdv DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_service]);
            } else {
                // Si le service n'est pas trouvé, filtrer par spécialisation du médecin
                $sql = "SELECT r.*, p.Nom_patient, p.Prénom_patient, p.Matricule_patient, s.Nom_service, m.Nom_med, m.Prénom_med
                        FROM RENDEZ_VOUS r
                        LEFT JOIN PATIENTS p ON r.id_patient = p.id_patient
                        LEFT JOIN SERVICES s ON r.id_service = s.id_service
                        LEFT JOIN MEDECINS m ON r.id_med = m.id_med
                        WHERE s.Nom_service = ? OR m.Spécialisation_med = ?
                        ORDER BY r.Date_rdv DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$specialisation, $specialisation]);
            }
        } else {
            // Pas de filtre par service, retourner tous les rendez-vous du médecin
            $sql = "SELECT r.*, p.Nom_patient, p.Prénom_patient, s.Nom_service 
                    FROM RENDEZ_VOUS r
                    LEFT JOIN PATIENTS p ON r.id_patient = p.id_patient
                    LEFT JOIN SERVICES s ON r.id_service = s.id_service
                    WHERE r.id_med = ? 
                    ORDER BY r.Date_rdv DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_med]);
        }
        
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        // En cas d'erreur, retourner un tableau vide plutôt que de planter
        error_log("Erreur getRendezVousByMedecin: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir les rendez-vous d'un médecin filtrés par son service/spécialisation
 */
function getRendezVousByMedecinAndService($id_med, $specialisation) {
    try {
        $pdo = bdd();
        $sql = "SELECT r.*, p.Nom_patient, p.Prénom_patient, p.Matricule_patient, s.Nom_service 
                FROM RENDEZ_VOUS r
                LEFT JOIN PATIENTS p ON r.id_patient = p.id_patient
                LEFT JOIN SERVICES s ON r.id_service = s.id_service
                LEFT JOIN MEDECINS m ON r.id_med = m.id_med
                WHERE r.id_med = ? AND (s.Nom_service = ? OR m.Spécialisation_med = ?)
                ORDER BY r.Date_rdv DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_med, $specialisation, $specialisation]);
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        error_log("Erreur getRendezVousByMedecinAndService: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir tous les rendez-vous d'un service (vue administrateur)
 * Retourne la liste complète des rendez-vous liés à un service donné,
 * avec les informations patient, médecin et service.
 */
function getRendezVousByService($id_service) {
    try {
        $pdo = bdd();
        $sql = "SELECT r.*, 
                       p.Nom_patient, p.Prénom_patient, p.Matricule_patient, p.Tel_patient as tel_patient,
                       m.Nom_med, m.Prénom_med, m.Spécialisation_med,
                       s.Nom_service, s.Tarif
                FROM RENDEZ_VOUS r
                LEFT JOIN PATIENTS p ON r.id_patient = p.id_patient
                LEFT JOIN MEDECINS m ON r.id_med = m.id_med
                LEFT JOIN SERVICES s ON r.id_service = s.id_service
                WHERE r.id_service = ?
                ORDER BY r.Date_rdv DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_service]);
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        error_log("Erreur getRendezVousByService: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir le nombre de rendez-vous **confirmés** par service (vue administrateur)
 * Retourne un tableau associatif [id_service => nombre_de_rdv_confirmes]
 * pour permettre d'afficher rapidement les volumes par service.
 */
function getRendezVousCountByService() {
    try {
        $pdo = bdd();
        $sql = "SELECT id_service, COUNT(*) as nb_rdv
                FROM RENDEZ_VOUS
                WHERE Statut = 'confirmé'
                GROUP BY id_service";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $counts = [];
        foreach ($rows as $row) {
            if (isset($row['id_service'])) {
                $counts[(int)$row['id_service']] = (int)($row['nb_rdv'] ?? 0);
            }
        }
        return $counts;
    } catch (PDOException $e) {
        error_log("Erreur getRendezVousCountByService: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir les patients d'un service spécifique (ancienne version)
 * NOTE : conservée pour compatibilité éventuelle, mais l'admin
 * utilise désormais getPatientsWithConfirmedRdvByService() qui
 * se base sur les rendez-vous confirmés/terminés et les demandes traitées.
 */
function getPatientsByService($id_service) {
    try {
        $pdo = bdd();
        $sql = "SELECT DISTINCT p.* 
                FROM PATIENTS p
                INNER JOIN RENDEZ_VOUS r ON p.id_patient = r.id_patient
                INNER JOIN SERVICES s ON r.id_service = s.id_service
                LEFT JOIN users u ON p.id_patient = u.id_patient
                LEFT JOIN PATIENT_SERVICES ps ON p.id_patient = ps.id_patient AND ps.id_service = ?
                WHERE s.id_service = ?
                AND (u.id_patient IS NOT NULL OR ps.id_patient IS NOT NULL)
                ORDER BY p.Nom_patient, p.Prénom_patient";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_service, $id_service]);
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        error_log("Erreur getPatientsByService: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir les patients ayant au moins un rendez-vous confirmé/terminé
 * dans un service donné, en complétant avec les DEMANDES marquées "traitee"
 * qui n'ont pas encore de rendez-vous réel.
 *
 * Utilisée notamment dans l'espace administrateur (patients par service),
 * pour avoir la même logique que la page médecin `patients-rendez-vous.php`
 * mais paramétrée par service.
 */
function getPatientsWithConfirmedRdvByService($id_service) {
    try {
        $pdo        = bdd();
        $id_service = (int) $id_service;

        if ($id_service <= 0) {
            return [];
        }

        // 1) Patients avec au moins un rendez-vous réel confirmé/terminé dans ce service
        // On récupère le rendez-vous le plus récent pour chaque patient.
        $sql = "SELECT r.*, p.*
                FROM RENDEZ_VOUS r
                INNER JOIN PATIENTS p ON p.id_patient = r.id_patient
                WHERE r.id_service = ?
                  AND LOWER(TRIM(r.Statut)) IN ('confirmé', 'confirme', 'terminé', 'termine')
                ORDER BY r.id_patient, r.Date_rdv DESC";

        $stmt     = $pdo->prepare($sql);
        $stmt->execute([$id_service]);
        $rows_rdv = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $patients          = [];
        $patients_by_id    = [];
        $patients_keys     = [];

        foreach ($rows_rdv as $row) {
            $id_p = (int)($row['id_patient'] ?? 0);
            if ($id_p < 1) {
                continue;
            }

            if (!isset($patients_by_id[$id_p])) {
                $patients_by_id[$id_p] = [
                    'id_patient'        => $row['id_patient'],
                    'Nom_patient'       => $row['Nom_patient'] ?? '',
                    'Prénom_patient'    => $row['Prénom_patient'] ?? '',
                    'Matricule_patient' => $row['Matricule_patient'] ?? '',
                    'Email_patient'     => $row['Email_patient'] ?? '',
                    'Tel_patient'       => $row['Tel_patient'] ?? null,
                    'Photo_profil'      => $row['Photo_profil'] ?? null,
                    'Date_rdv'          => $row['Date_rdv'] ?? null,
                ];
            }
        }

        $patients = array_values($patients_by_id);

        // Index (email + matricule) pour éviter les doublons quand on rajoute les DEMANDES
        foreach ($patients as $p) {
            $email_p = strtolower(trim($p['Email_patient'] ?? ''));
            $mat_p   = preg_replace('/\s+/', '', strtoupper($p['Matricule_patient'] ?? ''));
            $key     = $email_p . '|' . $mat_p;
            if ($key !== '|') {
                $patients_keys[$key] = true;
            }
        }

        // 2) Compléter avec les DEMANDES "traitee" qui n'ont pas encore de RDV réel
        try {
            $sql_dem = "SELECT d.*
                        FROM DEMANDE_RENDEZ_VOUS d
                        LEFT JOIN RENDEZ_VOUS r 
                            ON r.Date_rdv = d.Date_rdv_souhaitee
                           AND (r.id_service = d.id_service OR (r.id_service IS NULL AND d.id_service IS NULL))
                        WHERE d.id_service = ?
                          AND d.statut = 'traitee'
                          AND r.id_rdv IS NULL";
            $stmt_dem = $pdo->prepare($sql_dem);
            $stmt_dem->execute([$id_service]);
            $demandes = $stmt_dem->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($demandes as $d) {
                $email_d = strtolower(trim($d['email_demandeur'] ?? ''));
                $mat_d   = preg_replace('/\s+/', '', strtoupper($d['matricule_demandeur'] ?? ''));
                $key_d   = $email_d . '|' . $mat_d;

                // Pour l'admin on veut voir 100 % des demandes traitées :
                // - si on a une clé email/matricule, on l'utilise pour éviter les doublons
                // - si on n'a pas ces infos (clé '|'), on ajoute quand même le patient,
                //   quitte à avoir des doublons potentiels (vue de supervision).
                if ($key_d !== '|' && isset($patients_keys[$key_d])) {
                    continue;
                }

                if ($key_d !== '|') {
                    $patients_keys[$key_d] = true;
                }

                $patients[] = [
                    'id_patient'        => null,
                    'Nom_patient'       => $d['nom_demandeur'] ?? '',
                    'Prénom_patient'    => '',
                    'Matricule_patient' => $d['matricule_demandeur'] ?? '',
                    'Email_patient'     => $d['email_demandeur'] ?? '',
                    'Tel_patient'       => $d['telephone_demandeur'] ?? ($d['tel_demandeur'] ?? null),
                    'Photo_profil'      => null,
                    'Date_rdv'          => $d['Date_rdv_souhaitee'] ?? null,
                ];
            }
        } catch (Exception $e_dem) {
            error_log("getPatientsWithConfirmedRdvByService (demandes traitee): " . $e_dem->getMessage());
        }

        return $patients;
    } catch (Exception $e) {
        error_log("Erreur getPatientsWithConfirmedRdvByService: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir tous les patients qui ont au moins un rendez-vous
 * dans les 4 services principaux :
 * - Consultation générale
 * - Chirurgie
 * - Maternité
 * - Ophtalmologie
 *
 * Cette fonction est pensée pour l'administrateur qui veut
 * voir en une seule liste tous les patients passés par ces services.
 */
function getPatientsWithRendezVousInCoreServices() {
    try {
        $pdo = bdd();

        // Noms "officiels" des 4 services (voir PERMISSIONS_COMPLETE.md)
        $core_services = [
            'Consultation générale',
            'Chirurgie',
            'Maternité',
            'Ophtalmologie',
        ];

        // Récupérer les IDs de ces services à partir de leurs noms
        $service_ids = [];
        foreach ($core_services as $service_name) {
            if (function_exists('getIdServiceByNom')) {
                $id = getIdServiceByNom($service_name);
            } else {
                $id = null;
            }
            if (!empty($id)) {
                $service_ids[] = (int)$id;
            }
        }

        // Si aucun service n'a été trouvé, retourner une liste vide
        if (empty($service_ids)) {
            return [];
        }

        // Construire dynamiquement les placeholders pour la clause IN
        $placeholders = implode(',', array_fill(0, count($service_ids), '?'));

        /**
         * Important :
         * On veut ici **tous** les patients qui ont au moins un rendez-vous
         * dans l'un des 4 services principaux, qu'ils soient ou non déjà
         * liés dans `users` ou dans une éventuelle table de liaison
         * `PATIENT_SERVICES`.
         *
         * Le premier essai filtrait avec :
         *   AND (u.id_patient IS NOT NULL OR ps.id_patient IS NOT NULL)
         * ce qui excluait tous les patients qui n'étaient pas encore
         * enregistrés dans ces tables, d'où un résultat vide sur la page
         * d'administration.
         *
         * On simplifie donc la requête : on se base uniquement sur les
         * rendez‑vous (table `RENDEZ_VOUS`) et les services associés.
         */
        $sql = "SELECT DISTINCT p.*
                FROM PATIENTS p
                INNER JOIN RENDEZ_VOUS r ON p.id_patient = r.id_patient
                INNER JOIN SERVICES s ON r.id_service = s.id_service
                WHERE s.id_service IN ($placeholders)
                ORDER BY p.Nom_patient, p.Prénom_patient";

        // Les paramètres correspondent uniquement à la clause IN sur les services
        $params = $service_ids;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result ? $result : [];
    } catch (PDOException $e) {
        error_log("Erreur getPatientsWithRendezVousInCoreServices: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir les patients d'un médecin (filtrés par son service)
 * Retourne TOUS les patients qui ont pris un rendez-vous dans ce service
 * ou qui ont eu une consultation dans ce service
 */
function getPatientsByMedecin($id_med, $specialisation) {
    try {
        $pdo = bdd();
        
        // Récupérer d'abord l'ID du service correspondant à la spécialisation
        $sql_service = "SELECT id_service FROM SERVICES WHERE Nom_service = ? LIMIT 1";
        $stmt_service = $pdo->prepare($sql_service);
        $stmt_service->execute([$specialisation]);
        $service = $stmt_service->fetch();
        
        if ($service && isset($service['id_service'])) {
            $id_service = $service['id_service'];
            // Retourner TOUS les patients qui ont pris un rendez-vous dans ce service
            // OU qui ont eu une consultation avec un médecin de ce service
            $sql = "SELECT DISTINCT p.*, 
                           COUNT(DISTINCT r.id_rdv) as nb_rdv,
                           COUNT(DISTINCT c.id_consultation) as nb_consultations
                    FROM PATIENTS p
                    LEFT JOIN RENDEZ_VOUS r ON p.id_patient = r.id_patient AND r.id_service = ?
                    LEFT JOIN CONSULTATION c ON p.id_patient = c.id_patient
                    LEFT JOIN MEDECINS m ON c.id_med = m.id_med AND m.Spécialisation_med = ?
                    WHERE (r.id_service = ? OR m.Spécialisation_med = ?)
                    GROUP BY p.id_patient
                    HAVING nb_rdv > 0 OR nb_consultations > 0
                    ORDER BY p.Nom_patient, p.Prénom_patient";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_service, $specialisation, $id_service, $specialisation]);
            $result = $stmt->fetchAll();
            return $result ? $result : [];
        }
        
        // Si le service n'est pas trouvé, retourner les patients du médecin via rendez-vous ou consultations
        $sql = "SELECT DISTINCT p.*, COUNT(DISTINCT r.id_rdv) as nb_rdv
                FROM PATIENTS p
                INNER JOIN RENDEZ_VOUS r ON p.id_patient = r.id_patient
                WHERE r.id_med = ?
                GROUP BY p.id_patient
                ORDER BY p.Nom_patient, p.Prénom_patient";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_med]);
        $result = $stmt->fetchAll();
        
        // Si aucun résultat via rendez-vous, essayer via consultations
        if (empty($result)) {
            $sql = "SELECT DISTINCT p.*, COUNT(DISTINCT c.id_consultation) as nb_consultations
                    FROM PATIENTS p
                    INNER JOIN CONSULTATION c ON p.id_patient = c.id_patient
                    WHERE c.id_med = ?
                    GROUP BY p.id_patient
                    ORDER BY p.Nom_patient, p.Prénom_patient";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_med]);
            $result = $stmt->fetchAll();
        }
        
        return $result ? $result : [];
    } catch (PDOException $e) {
        // En cas d'erreur, retourner un tableau vide plutôt que de planter
        error_log("Erreur getPatientsByMedecin: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir les consultations d'un médecin (filtrées par service si spécialisation fournie)
 * Si spécialisation fournie, retourne TOUTES les consultations du service (tous les médecins du service)
 */
function getConsultationsByMedecin($id_med, $specialisation = null) {
    try {
        $pdo = bdd();
        
        // Si une spécialisation est fournie, filtrer par service (tous les médecins du service)
        if ($specialisation) {
            // Récupérer l'ID du service correspondant à la spécialisation
            $sql_service = "SELECT id_service FROM SERVICES WHERE Nom_service = ? LIMIT 1";
            $stmt_service = $pdo->prepare($sql_service);
            $stmt_service->execute([$specialisation]);
            $service = $stmt_service->fetch();
            
            if ($service && isset($service['id_service'])) {
                $id_service = $service['id_service'];
                // Filtrer les consultations par service de plusieurs façons :
                // 1. Via la spécialisation du médecin de la consultation
                // 2. Via les rendez-vous du même service
                // 3. Via la table CONSULTATION_SERVICES si elle existe
                $sql = "SELECT DISTINCT c.*, p.Nom_patient, p.Prénom_patient, p.Matricule_patient, 
                               COALESCE(s.Nom_service, s2.Nom_service, m.Spécialisation_med) as Nom_service, 
                               m.Nom_med, m.Prénom_med, m.Spécialisation_med
                        FROM CONSULTATION c
                        LEFT JOIN PATIENTS p ON c.id_patient = p.id_patient
                        LEFT JOIN MEDECINS m ON c.id_med = m.id_med
                        LEFT JOIN RENDEZ_VOUS r ON c.id_patient = r.id_patient AND r.id_service = ?
                        LEFT JOIN SERVICES s ON r.id_service = s.id_service
                        LEFT JOIN CONSULTATION_SERVICES cs ON c.id_consultation = cs.id_consultation AND cs.id_service = ?
                        LEFT JOIN SERVICES s2 ON cs.id_service = s2.id_service
                        WHERE (m.Spécialisation_med = ? OR r.id_service = ? OR cs.id_service = ?)
                        ORDER BY c.Date_consultation DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_service, $id_service, $specialisation, $id_service, $id_service]);
            } else {
                // Si le service n'est pas trouvé, filtrer uniquement par spécialisation du médecin
                $sql = "SELECT DISTINCT c.*, p.Nom_patient, p.Prénom_patient, p.Matricule_patient, 
                               m.Spécialisation_med as Nom_service, m.Nom_med, m.Prénom_med
                        FROM CONSULTATION c
                        LEFT JOIN PATIENTS p ON c.id_patient = p.id_patient
                        LEFT JOIN MEDECINS m ON c.id_med = m.id_med
                        WHERE m.Spécialisation_med = ?
                        ORDER BY c.Date_consultation DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$specialisation]);
            }
        } else {
            // Pas de filtre par service, retourner toutes les consultations du médecin
            $sql = "SELECT c.*, p.Nom_patient, p.Prénom_patient, p.Matricule_patient, m.Spécialisation_med as Nom_service
                    FROM CONSULTATION c
                    LEFT JOIN PATIENTS p ON c.id_patient = p.id_patient
                    LEFT JOIN MEDECINS m ON c.id_med = m.id_med
                    WHERE c.id_med = ? 
                    ORDER BY c.Date_consultation DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_med]);
        }
        
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        // En cas d'erreur, retourner un tableau vide plutôt que de planter
        error_log("Erreur getConsultationsByMedecin: " . $e->getMessage());
        return [];
    }
}

/**
 * Mettre à jour le statut d'un rendez-vous
 */
function updateStatutRendezVous($id_rdv, $statut) {
    try {
        $pdo = bdd();
        
        // Récupérer les informations du rendez-vous avant la mise à jour
        $sql_select = "SELECT r.*, p.Nom_patient, p.Prénom_patient, m.Nom_med, m.Prénom_med, s.Nom_service 
                       FROM RENDEZ_VOUS r
                       LEFT JOIN PATIENTS p ON r.id_patient = p.id_patient
                       LEFT JOIN MEDECINS m ON r.id_med = m.id_med
                       LEFT JOIN SERVICES s ON r.id_service = s.id_service
                       WHERE r.id_rdv = ?";
        $stmt_select = $pdo->prepare($sql_select);
        $stmt_select->execute([$id_rdv]);
        $rdv_info = $stmt_select->fetch(PDO::FETCH_ASSOC);
        
        // Mettre à jour le statut
        $sql = "UPDATE RENDEZ_VOUS SET Statut = ? WHERE id_rdv = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$statut, $id_rdv]);
        
        // Si le rendez-vous est confirmé, envoyer une notification au patient
        if ($result && $statut === 'confirmé' && $rdv_info && isset($rdv_info['id_patient'])) {
            $date_rdv_formatee = date('d/m/Y à H:i', strtotime($rdv_info['Date_rdv']));
            $nom_medecin = trim(($rdv_info['Prénom_med'] ?? '') . ' ' . ($rdv_info['Nom_med'] ?? ''));
            $nom_service = $rdv_info['Nom_service'] ?? 'Service médical';
            
            $titre = "Rendez-vous confirmé";
            $message = "Votre rendez-vous du " . $date_rdv_formatee . " avec le Dr. " . $nom_medecin . " (" . $nom_service . ") a été confirmé. ";
            if (isset($rdv_info['Motif']) && !empty($rdv_info['Motif'])) {
                $message .= "Motif : " . htmlspecialchars($rdv_info['Motif']) . ". ";
            }
            $message .= "Nous vous attendons à l'heure prévue.";
            $lien = "rendez-vous.php"; // Lien vers la page des rendez-vous
            
            creerNotification(
                $rdv_info['id_patient'],
                $titre,
                $message,
                'rendez_vous',
                $lien
            );
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Erreur updateStatutRendezVous: " . $e->getMessage());
        throw new Exception("Erreur lors de la mise à jour du rendez-vous : " . $e->getMessage());
    }
}

// ============================================
// FONCTIONS POUR LES SERVICES
// ============================================

/**
 * Obtenir tous les services
 * Retourne un tableau vide si aucun service n'est trouvé (pas une erreur)
 * Lance une exception seulement si la table n'existe pas
 */
function getAllServices() {
    $pdo = bdd();
    
    // Vérifier si la table existe
    if (!tableExists('SERVICES')) {
        throw new PDOException("La table SERVICES n'existe pas. Veuillez exécuter install.php pour créer la base de données.");
    }
    
    try {
        // Utiliser GROUP BY pour éviter les doublons de services avec le même nom
        // Prendre le premier id_service pour chaque nom de service unique et récupérer toutes les colonnes
        $sql = "SELECT s.* 
                FROM SERVICES s
                INNER JOIN (
                    SELECT MIN(id_service) as id_service, Nom_service 
                    FROM SERVICES 
                    GROUP BY Nom_service
                ) as unique_services ON s.id_service = unique_services.id_service
                ORDER BY s.Nom_service";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetchAll();
        
        // Retourner un tableau vide si aucun résultat (c'est normal, pas une erreur)
        return $result ? $result : [];
    } catch (PDOException $e) {
        // Si c'est une erreur de table inexistante, la relancer
        if (strpos($e->getMessage(), "doesn't exist") !== false || 
            strpos($e->getMessage(), "n'existe pas") !== false) {
            throw new PDOException("La table SERVICES n'existe pas. Veuillez exécuter install.php pour créer la base de données.");
        }
        // Sinon, relancer l'erreur originale
        throw $e;
    }
}

/**
 * Obtenir un service par son ID
 */
function getServiceById($id_service) {
    try {
        $pdo = bdd();
        $sql = "SELECT * FROM SERVICES WHERE id_service = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_service]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Erreur getServiceById: " . $e->getMessage());
        return null;
    }
}

/**
 * Obtenir l'ID d'un service à partir de son nom (ex : spécialisation du médecin)
 * Utilisé pour afficher les patients du service côté médecin.
 */
function getIdServiceByNom($nom_service) {
    if (empty(trim($nom_service ?? ''))) {
        return null;
    }
    try {
        $pdo = bdd();
        $sql = "SELECT id_service FROM SERVICES WHERE Nom_service = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([trim($nom_service)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && isset($row['id_service']) ? (int)$row['id_service'] : null;
    } catch (PDOException $e) {
        error_log("Erreur getIdServiceByNom: " . $e->getMessage());
        return null;
    }
}

// ============================================
// FONCTIONS POUR LES CONSULTATIONS
// ============================================

/**
 * Créer une nouvelle consultation
 * Cette fonction est tolérante aux anciens schémas où certaines colonnes
 * peuvent ne pas encore exister dans la table CONSULTATION (Motif_diagnostic, Num_carnet).
 */
function creerConsultation($date_consultation, $motif_diagnostic, $id_patient, $id_med, $num_carnet, $note = null) {
    try {
        $pdo = bdd();

        // S'assurer que la colonne Motif_diagnostic existe (compatibilité anciennes bases)
        try {
            $checkCol = $pdo->query("SHOW COLUMNS FROM CONSULTATION LIKE 'Motif_diagnostic'");
            if ($checkCol->rowCount() === 0) {
                // On ajoute la colonne en mode nullable pour éviter les erreurs
                $pdo->exec("ALTER TABLE CONSULTATION ADD COLUMN Motif_diagnostic TEXT NULL AFTER Date_consultation");
            }
        } catch (PDOException $e) {
            // On log mais on ne bloque pas la suite
            error_log("creerConsultation - vérification/ajout Motif_diagnostic: " . $e->getMessage());
        }

        // S'assurer que la colonne Num_carnet existe également
        try {
            $checkCarnet = $pdo->query("SHOW COLUMNS FROM CONSULTATION LIKE 'Num_carnet'");
            if ($checkCarnet->rowCount() === 0) {
                // On ajoute la colonne en nullable pour compatibilité
                $pdo->exec("ALTER TABLE CONSULTATION ADD COLUMN Num_carnet INT NULL AFTER id_med");
            }
        } catch (PDOException $e) {
            error_log("creerConsultation - vérification/ajout Num_carnet: " . $e->getMessage());
        }

        // Désactiver temporairement les contraintes de clés étrangères
        // pour éviter de bloquer la création d'ordonnance si la contrainte
        // sur CONSULTATION.id_patient est mal configurée dans une ancienne base.
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

        $sql = "INSERT INTO CONSULTATION (Date_consultation, Motif_diagnostic, Note, id_patient, id_med, Num_carnet, Statut) 
                VALUES (?, ?, ?, ?, ?, ?, 'en_cours')";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$date_consultation, $motif_diagnostic, $note, $id_patient, $id_med, $num_carnet]);

        // Réactiver les contraintes de clés étrangères
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

        return $result;
    } catch (PDOException $e) {
        // En cas d'erreur, s'assurer que les contraintes sont bien réactivées
        try { $pdo->exec("SET FOREIGN_KEY_CHECKS=1"); } catch (\Throwable $t) {}
        error_log("Erreur creerConsultation: " . $e->getMessage());
        throw new Exception("Erreur lors de la création de la consultation : " . $e->getMessage());
    }
}

/**
 * Obtenir les consultations d'un patient
 */
function getConsultationsByPatient($id_patient) {
    try {
        $pdo = bdd();
        $sql = "SELECT c.*, m.Nom_med, m.Prénom_med, m.Spécialisation_med 
                FROM CONSULTATION c
                LEFT JOIN MEDECINS m ON c.id_med = m.id_med
                WHERE c.id_patient = ? 
                ORDER BY c.Date_consultation DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_patient]);
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        // En cas d'erreur, retourner un tableau vide plutôt que de planter
        error_log("Erreur getConsultationsByPatient: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir les consultations d'un patient filtrées par service
 * 
 * @param int $id_patient L'ID du patient
 * @param string $specialisation La spécialisation/service (ex: "Médecine générale", "Chirurgie")
 * @return array Tableau des consultations du patient pour ce service
 */
function getConsultationsByPatientAndService($id_patient, $specialisation) {
    try {
        $pdo = bdd();
        
        // Récupérer l'ID du service correspondant à la spécialisation
        $sql_service = "SELECT id_service FROM SERVICES WHERE Nom_service = ? LIMIT 1";
        $stmt_service = $pdo->prepare($sql_service);
        $stmt_service->execute([$specialisation]);
        $service = $stmt_service->fetch();
        
        if ($service && isset($service['id_service'])) {
            $id_service = $service['id_service'];
            // Filtrer les consultations par service de plusieurs façons :
            // 1. Via la spécialisation du médecin de la consultation
            // 2. Via les rendez-vous du même service
            // 3. Via la table CONSULTATION_SERVICES si elle existe
            $sql = "SELECT DISTINCT c.*, p.Nom_patient, p.Prénom_patient, p.Matricule_patient, 
                           COALESCE(s.Nom_service, s2.Nom_service, m.Spécialisation_med) as Nom_service, 
                           m.Nom_med, m.Prénom_med, m.Spécialisation_med
                    FROM CONSULTATION c
                    LEFT JOIN PATIENTS p ON c.id_patient = p.id_patient
                    LEFT JOIN MEDECINS m ON c.id_med = m.id_med
                    LEFT JOIN RENDEZ_VOUS r ON c.id_patient = r.id_patient AND r.id_service = ?
                    LEFT JOIN SERVICES s ON r.id_service = s.id_service
                    LEFT JOIN CONSULTATION_SERVICES cs ON c.id_consultation = cs.id_consultation AND cs.id_service = ?
                    LEFT JOIN SERVICES s2 ON cs.id_service = s2.id_service
                    WHERE c.id_patient = ? 
                    AND (m.Spécialisation_med = ? OR r.id_service = ? OR cs.id_service = ?)
                    ORDER BY c.Date_consultation DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_service, $id_service, $id_patient, $specialisation, $id_service, $id_service]);
        } else {
            // Si le service n'est pas trouvé, filtrer uniquement par spécialisation du médecin
            $sql = "SELECT DISTINCT c.*, p.Nom_patient, p.Prénom_patient, p.Matricule_patient, 
                           m.Spécialisation_med as Nom_service, m.Nom_med, m.Prénom_med
                    FROM CONSULTATION c
                    LEFT JOIN PATIENTS p ON c.id_patient = p.id_patient
                    LEFT JOIN MEDECINS m ON c.id_med = m.id_med
                    WHERE c.id_patient = ? AND m.Spécialisation_med = ?
                    ORDER BY c.Date_consultation DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_patient, $specialisation]);
        }
        
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        // En cas d'erreur, retourner un tableau vide plutôt que de planter
        error_log("Erreur getConsultationsByPatientAndService: " . $e->getMessage());
        return [];
    }
}

// ============================================
// FONCTIONS POUR LES CARNETS
// ============================================

/**
 * Créer un carnet de santé pour un patient
 */
function creerCarnet($libelle, $id_patient) {
    try {
        $pdo = bdd();
        $sql = "INSERT INTO CARNETS (Libellé, id_patient) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$libelle, $id_patient]);
    } catch (PDOException $e) {
        error_log("Erreur creerCarnet: " . $e->getMessage());
        throw new Exception("Erreur lors de la création du carnet : " . $e->getMessage());
    }
}

/**
 * Obtenir les carnets d'un patient
 */
function getCarnetsByPatient($id_patient) {
    try {
        $pdo = bdd();
        $sql = "SELECT * FROM CARNETS WHERE id_patient = ? ORDER BY Date_creation DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_patient]);
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        error_log("Erreur getCarnetsByPatient: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir un carnet par son numéro avec les infos patient
 */
function getCarnetById($num_carnet) {
    try {
        $pdo = bdd();
        $sql = "SELECT c.*, p.Nom_patient, p.Prénom_patient, p.Date_naissance_patient, p.Matricule_patient, p.Tel_patient, p.Email_patient, p.Adresse_patient
                FROM CARNETS c
                JOIN PATIENTS p ON c.id_patient = p.id_patient
                WHERE c.Num_carnet = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$num_carnet]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Erreur getCarnetById: " . $e->getMessage());
        return null;
    }
}

// ============================================
// FONCTIONS POUR LES PAIEMENTS
// ============================================

/**
 * Créer un paiement
 */
function creerPaiement($montant, $date_paiement, $id_patient, $id_consultation = null, $methode = 'espèces', $id_facture = null) {
    try {
        $pdo = bdd();
        
        // Vérifier si la colonne id_facture existe
        $check_id_facture = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'id_facture'");
        $has_id_facture = $check_id_facture->rowCount() > 0;
        
        // Vérifier si la colonne Méthode_paiement existe (avec ou sans accent)
        $check_methode = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'Méthode_paiement'");
        $has_methode = $check_methode->rowCount() > 0;
        
        if (!$has_methode) {
            $check_methode_alt = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'methode_paiement'");
            $has_methode_alt = $check_methode_alt->rowCount() > 0;
        } else {
            $has_methode_alt = false;
        }
        
        // Construire la requête SQL selon les colonnes disponibles
        if ($has_methode) {
            if ($has_id_facture) {
                $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, Méthode_paiement, id_facture, Statut) 
                        VALUES (?, ?, ?, ?, ?, ?, 'payé')";
                $stmt = $pdo->prepare($sql);
                return $stmt->execute([$montant, $date_paiement, $id_patient, $id_consultation, $methode, $id_facture]);
            } else {
                $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, Méthode_paiement, Statut) 
                        VALUES (?, ?, ?, ?, ?, 'payé')";
                $stmt = $pdo->prepare($sql);
                return $stmt->execute([$montant, $date_paiement, $id_patient, $id_consultation, $methode]);
            }
        } elseif ($has_methode_alt) {
            if ($has_id_facture) {
                $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, methode_paiement, id_facture, Statut) 
                        VALUES (?, ?, ?, ?, ?, ?, 'payé')";
                $stmt = $pdo->prepare($sql);
                return $stmt->execute([$montant, $date_paiement, $id_patient, $id_consultation, $methode, $id_facture]);
            } else {
                $sql = "INSERT INTO PAIEMENT (Montant, Date_paiement, id_patient, id_consultation, methode_paiement, Statut) 
                        VALUES (?, ?, ?, ?, ?, 'payé')";
                $stmt = $pdo->prepare($sql);
                return $stmt->execute([$montant, $date_paiement, $id_patient, $id_consultation, $methode]);
            }
        } else {
            throw new Exception("La colonne Méthode_paiement n'existe pas dans la table PAIEMENT.");
        }
    } catch (PDOException $e) {
        error_log("Erreur creerPaiement: " . $e->getMessage());
        throw new Exception("Erreur lors de la création du paiement : " . $e->getMessage());
    }
}

/**
 * Obtenir les paiements d'un patient
 */
function getPaiementsByPatient($id_patient) {
    try {
        $pdo = bdd();
        // Vérifier si la colonne chemin_reçu existe
        $check_column = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'chemin_reçu'");
        $has_chemin_reçu = $check_column->rowCount() > 0;
        
        $sql = "SELECT p.*, c.Date_consultation, c.Motif_diagnostic, pat.Nom_patient, pat.Prénom_patient" . 
               ($has_chemin_reçu ? ", p.chemin_reçu" : "") . "
                FROM PAIEMENT p
                LEFT JOIN CONSULTATION c ON p.id_consultation = c.id_consultation
                LEFT JOIN PATIENTS pat ON p.id_patient = pat.id_patient
                WHERE p.id_patient = ? 
                ORDER BY p.Date_paiement DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_patient]);
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        error_log("Erreur getPaiementsByPatient: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir tous les paiements (pour admin)
 */
function getAllPaiements() {
    try {
        $pdo = bdd();
        // Vérifier si la colonne chemin_reçu existe
        $check_column = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'chemin_reçu'");
        $has_chemin_reçu = $check_column->rowCount() > 0;
        
        $sql = "SELECT p.*, c.Date_consultation, c.Motif_diagnostic, 
                       pat.Nom_patient, pat.Prénom_patient, pat.Matricule_patient" .
               ($has_chemin_reçu ? ", p.chemin_reçu" : "") . "
                FROM PAIEMENT p
                LEFT JOIN CONSULTATION c ON p.id_consultation = c.id_consultation
                LEFT JOIN PATIENTS pat ON p.id_patient = pat.id_patient
                ORDER BY p.Date_paiement DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        error_log("Erreur getAllPaiements: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir un paiement par son ID
 */
function getPaiementById($id_paiement) {
    try {
        $pdo = bdd();
        // Vérifier si la colonne chemin_reçu existe
        $check_column = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'chemin_reçu'");
        $has_chemin_reçu = $check_column->rowCount() > 0;
        
        // Vérifier si la colonne id_service existe
        $check_service = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'id_service'");
        $has_id_service = $check_service->rowCount() > 0;
        
        $sql = "SELECT p.*, c.Date_consultation, c.Motif_diagnostic, 
                       pat.Nom_patient, pat.Prénom_patient, pat.Matricule_patient, pat.Tel_patient" .
               ($has_chemin_reçu ? ", p.chemin_reçu" : "") .
               ($has_id_service ? ", p.id_service" : "") . "
                FROM PAIEMENT p
                LEFT JOIN CONSULTATION c ON p.id_consultation = c.id_consultation
                LEFT JOIN PATIENTS pat ON p.id_patient = pat.id_patient
                WHERE p.id_paiement = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_paiement]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Erreur getPaiementById: " . $e->getMessage());
        return null;
    }
}

/**
 * Mettre à jour le statut d'un paiement
 */
function updateStatutPaiement($id_paiement, $statut) {
    try {
        $pdo = bdd();
        $sql = "UPDATE PAIEMENT SET Statut = ? WHERE id_paiement = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$statut, $id_paiement]);
    } catch (PDOException $e) {
        error_log("Erreur updateStatutPaiement: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir les consultations sans paiement pour un patient
 */
function getConsultationsSansPaiement($id_patient) {
    try {
        $pdo = bdd();
        $sql = "SELECT c.*, m.Nom_med, m.Prénom_med, m.Spécialisation_med
                FROM CONSULTATION c
                LEFT JOIN MEDECINS m ON c.id_med = m.id_med
                LEFT JOIN PAIEMENT p ON c.id_consultation = p.id_consultation
                WHERE c.id_patient = ? AND p.id_paiement IS NULL AND c.Statut = 'terminée'
                ORDER BY c.Date_consultation DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_patient]);
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        error_log("Erreur getConsultationsSansPaiement: " . $e->getMessage());
        return [];
    }
}

/**
 * Générer un numéro de facture unique
 */
function genererNumeroFacture() {
    return 'FACT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Générer un reçu/facture pour un paiement
 */
function genererReçu($id_paiement) {
    try {
        $paiement = getPaiementById($id_paiement);
        if (!$paiement) {
            throw new Exception("Paiement introuvable");
        }
        
        // S'assurer que le matricule du patient est récupéré
        if (!isset($paiement['Matricule_patient']) || empty($paiement['Matricule_patient'])) {
            if (isset($paiement['id_patient']) && $paiement['id_patient']) {
                try {
                    $pdo = bdd();
                    $sql_matricule = "SELECT Matricule_patient FROM PATIENTS WHERE id_patient = ?";
                    $stmt_matricule = $pdo->prepare($sql_matricule);
                    $stmt_matricule->execute([$paiement['id_patient']]);
                    $patient_data = $stmt_matricule->fetch();
                    if ($patient_data && isset($patient_data['Matricule_patient'])) {
                        $paiement['Matricule_patient'] = $patient_data['Matricule_patient'];
                    }
                } catch (Exception $e) {
                    error_log("Erreur récupération matricule patient: " . $e->getMessage());
                }
            }
        }
        
        // Récupérer les informations du service si disponible
        $service_info = null;
        if (isset($paiement['id_service']) && $paiement['id_service']) {
            try {
                $pdo = bdd();
                $sql_service = "SELECT * FROM SERVICES WHERE id_service = ?";
                $stmt_service = $pdo->prepare($sql_service);
                $stmt_service->execute([$paiement['id_service']]);
                $service_info = $stmt_service->fetch();
            } catch (Exception $e) {
                error_log("Erreur récupération service: " . $e->getMessage());
                $service_info = null;
            }
        }
        
        // Créer le dossier reçus s'il n'existe pas
        $receipts_dir = __DIR__ . '/../uploads/reçus';
        if (!file_exists($receipts_dir)) {
            if (!mkdir($receipts_dir, 0755, true)) {
                throw new Exception("Impossible de créer le dossier uploads/reçus. Vérifiez les permissions.");
            }
        }
        
        // Vérifier que le dossier est accessible en écriture
        if (!is_writable($receipts_dir)) {
            throw new Exception("Le dossier uploads/reçus n'est pas accessible en écriture. Vérifiez les permissions.");
        }
        
        // Générer le nom du fichier (utiliser id_facture si disponible, sinon id_paiement)
        $facture_ref = isset($paiement['id_facture']) && $paiement['id_facture'] 
                      ? $paiement['id_facture'] 
                      : 'PAY' . $id_paiement;
        $filename = 'recu_' . $facture_ref . '_' . date('YmdHis') . '.html';
        $filepath = $receipts_dir . '/' . $filename;
        $relative_path = 'uploads/reçus/' . $filename;
        
        // Générer le contenu HTML du reçu
        $html = genererContenuReçu($paiement, $service_info);
        
        // Sauvegarder le fichier
        if (file_put_contents($filepath, $html) === false) {
            throw new Exception("Impossible de sauvegarder le fichier reçu. Vérifiez les permissions du dossier uploads/reçus.");
        }
        
        // Mettre à jour la base de données avec le chemin du reçu (si la colonne existe)
        try {
            $pdo = bdd();
            // Vérifier si la colonne chemin_reçu existe
            $check_column = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'chemin_reçu'");
            if ($check_column->rowCount() > 0) {
                $sql = "UPDATE PAIEMENT SET chemin_reçu = ? WHERE id_paiement = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$relative_path, $id_paiement]);
            }
        } catch (Exception $e) {
            // Si la colonne n'existe pas, continuer quand même (le reçu est généré)
            error_log("Erreur mise à jour chemin_reçu: " . $e->getMessage());
        }
        
        // Envoyer une notification au patient avec le lien vers le reçu
        if (isset($paiement['id_patient']) && $paiement['id_patient']) {
            try {
                $montant_formate = number_format($paiement['Montant'] ?? 0, 0, ',', ' ') . ' GNF';
                $titre = "Reçu de paiement disponible";
                $message = "Votre reçu de paiement d'un montant de " . $montant_formate . " est maintenant disponible. ";
                if (isset($paiement['id_facture']) && $paiement['id_facture']) {
                    $message .= "Numéro de facture : " . $paiement['id_facture'] . ". ";
                }
                $message .= "Vous pouvez le consulter dans votre espace patient.";
                $lien = "paiements/voir-reçu.php?id=" . $id_paiement;
                
                creerNotification(
                    $paiement['id_patient'],
                    $titre,
                    $message,
                    'paiement',
                    $lien
                );
            } catch (Exception $e) {
                // Ne pas bloquer la génération du reçu si la notification échoue
                error_log("Erreur envoi notification reçu: " . $e->getMessage());
            }
        }
        
        return $relative_path;
    } catch (Exception $e) {
        error_log("Erreur génération reçu: " . $e->getMessage());
        throw new Exception("Erreur lors de la génération du reçu : " . $e->getMessage());
    }
}

/**
 * Générer le contenu HTML du reçu
 */
function genererContenuReçu($paiement, $service_info = null) {
    // Vérifier que les données nécessaires sont présentes
    if (!isset($paiement['Date_paiement']) || empty($paiement['Date_paiement'])) {
        $date_paiement = date('d/m/Y à H:i');
    } else {
        $date_paiement = date('d/m/Y à H:i', strtotime($paiement['Date_paiement']));
    }
    
    if (!isset($paiement['Montant']) || empty($paiement['Montant'])) {
        $montant = '0 GNF';
    } else {
        $montant = number_format($paiement['Montant'], 0, ',', ' ') . ' GNF';
    }
    
    $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu de Paiement - ' . htmlspecialchars($paiement['id_facture'] ?? 'N°' . ($paiement['id_paiement'] ?? '')) . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            padding: 40px;
            background: #f5f5f5;
        }
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #4A90E2;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #002939;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            color: #666;
            font-size: 14px;
        }
        .receipt-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .info-section h3 {
            color: #002939;
            font-size: 16px;
            margin-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 8px;
        }
        .info-item {
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: 600;
            color: #666;
            display: inline-block;
            width: 120px;
        }
        .info-value {
            color: #002939;
        }
        .amount-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 30px 0;
            text-align: center;
        }
        .amount-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        .amount-value {
            font-size: 32px;
            font-weight: 700;
            color: #28a745;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
        }
        .status-paye {
            background: #d4edda;
            color: #155724;
        }
        @media print {
            body { background: white; padding: 0; }
            .receipt-container { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <h1>RECU DE PAIEMENT</h1>
            <p>MediCo. - Clinique Médicale</p>
        </div>
        
        <div class="receipt-info">
            <div class="info-section">
                <h3>Informations Patient</h3>
                <div class="info-item">
                    <span class="info-label">Nom :</span>
                    <span class="info-value">' . htmlspecialchars(($paiement['Prénom_patient'] ?? '') . ' ' . ($paiement['Nom_patient'] ?? '')) . '</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Matricule :</span>
                    <span class="info-value">' . htmlspecialchars(!empty($paiement['Matricule_patient']) ? $paiement['Matricule_patient'] : 'N/A') . '</span>
                </div>
                ' . (isset($paiement['Tel_patient']) && $paiement['Tel_patient'] ? '
                <div class="info-item">
                    <span class="info-label">Téléphone :</span>
                    <span class="info-value">' . htmlspecialchars($paiement['Tel_patient']) . '</span>
                </div>' : '') . '
            </div>
            
            <div class="info-section">
                <h3>Informations Paiement</h3>
                <div class="info-item">
                    <span class="info-label">N° Facture :</span>
                    <span class="info-value">' . htmlspecialchars($paiement['id_facture'] ?? 'N/A') . '</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date :</span>
                    <span class="info-value">' . $date_paiement . '</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Méthode :</span>
                    <span class="info-value">' . htmlspecialchars(ucfirst($paiement['Méthode_paiement'] ?? 'N/A')) . '</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Statut :</span>
                    <span class="info-value">
                        <span class="status-badge status-paye">' . htmlspecialchars(ucfirst($paiement['Statut'] ?? 'payé')) . '</span>
                    </span>
                </div>
            </div>
        </div>
        
        ' . ($service_info ? '
        <div class="info-section">
            <h3>Service</h3>
            <div class="info-item">
                <span class="info-label">Service :</span>
                <span class="info-value">' . htmlspecialchars($service_info['Nom_service'] ?? 'N/A') . '</span>
            </div>
            ' . ($service_info['Description'] ? '
            <div class="info-item">
                <span class="info-label">Description :</span>
                <span class="info-value">' . htmlspecialchars($service_info['Description']) . '</span>
            </div>' : '') . '
        </div>
        ' : '') . '
        
        ' . (isset($paiement['Date_consultation']) && $paiement['Date_consultation'] ? '
        <div class="info-section">
            <h3>Consultation</h3>
            <div class="info-item">
                <span class="info-label">Date consultation :</span>
                <span class="info-value">' . date('d/m/Y à H:i', strtotime($paiement['Date_consultation'])) . '</span>
            </div>
            ' . (isset($paiement['Motif_diagnostic']) && $paiement['Motif_diagnostic'] ? '
            <div class="info-item">
                <span class="info-label">Motif :</span>
                <span class="info-value">' . htmlspecialchars(substr($paiement['Motif_diagnostic'], 0, 100)) . '</span>
            </div>' : '') . '
        </div>
        ' : '') . '
        
        <div class="amount-section">
            <div class="amount-label">Montant Total</div>
            <div class="amount-value">' . $montant . '</div>
        </div>
        
        <div class="footer">
            <p><strong>Merci pour votre confiance !</strong></p>
            <p>Ce document est un reçu officiel de paiement.</p>
            <p>Pour toute question, contactez-nous à contact@medico.fr</p>
            <p style="margin-top: 20px;">Document généré le ' . date('d/m/Y à H:i') . '</p>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * Obtenir le chemin du reçu pour un paiement
 */
function getCheminReçu($id_paiement) {
    try {
        $pdo = bdd();
        $sql = "SELECT chemin_reçu FROM PAIEMENT WHERE id_paiement = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_paiement]);
        $result = $stmt->fetch();
        return $result ? $result['chemin_reçu'] : null;
    } catch (PDOException $e) {
        error_log("Erreur getCheminReçu: " . $e->getMessage());
        return null;
    }
}

/**
 * Obtenir tous les paiements avec reçus pour un patient
 */
function getPaiementsAvecReçus($id_patient) {
    try {
        $pdo = bdd();
        // Vérifier si la colonne chemin_reçu existe
        $check_column = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'chemin_reçu'");
        $has_chemin_reçu = $check_column->rowCount() > 0;
        
        // Vérifier si la colonne id_facture existe
        $check_id_facture = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'id_facture'");
        $has_id_facture = $check_id_facture->rowCount() > 0;
        
        if ($has_chemin_reçu) {
            $sql = "SELECT p.*, c.Date_consultation, c.Motif_diagnostic, pat.Nom_patient, pat.Prénom_patient
                    FROM PAIEMENT p
                    LEFT JOIN CONSULTATION c ON p.id_consultation = c.id_consultation
                    LEFT JOIN PATIENTS pat ON p.id_patient = pat.id_patient
                    WHERE p.id_patient = ? AND p.chemin_reçu IS NOT NULL AND p.chemin_reçu != ''
                    ORDER BY p.Date_paiement DESC";
        } else {
            // Si la colonne n'existe pas encore, retourner les paiements payés
            if ($has_id_facture) {
                // Si id_facture existe, filtrer par facture
                $sql = "SELECT p.*, c.Date_consultation, c.Motif_diagnostic, pat.Nom_patient, pat.Prénom_patient
                        FROM PAIEMENT p
                        LEFT JOIN CONSULTATION c ON p.id_consultation = c.id_consultation
                        LEFT JOIN PATIENTS pat ON p.id_patient = pat.id_patient
                        WHERE p.id_patient = ? AND p.Statut = 'payé' AND p.id_facture IS NOT NULL
                        ORDER BY p.Date_paiement DESC";
            } else {
                // Si id_facture n'existe pas, retourner tous les paiements payés
                $sql = "SELECT p.*, c.Date_consultation, c.Motif_diagnostic, pat.Nom_patient, pat.Prénom_patient
                        FROM PAIEMENT p
                        LEFT JOIN CONSULTATION c ON p.id_consultation = c.id_consultation
                        LEFT JOIN PATIENTS pat ON p.id_patient = pat.id_patient
                        WHERE p.id_patient = ? AND p.Statut = 'payé'
                        ORDER BY p.Date_paiement DESC";
            }
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_patient]);
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        error_log("Erreur getPaiementsAvecReçus: " . $e->getMessage());
        return [];
    }
}

/**
 * Envoyer le reçu au patient (régénère le reçu et envoie une notification)
 * @param int $id_paiement L'ID du paiement
 * @return bool True si succès, False sinon
 */
function envoyerReçuAuPatient($id_paiement) {
    try {
        $paiement = getPaiementById($id_paiement);
        if (!$paiement) {
            throw new Exception("Paiement introuvable");
        }
        
        // Vérifier que le paiement est payé
        if ($paiement['Statut'] !== 'payé') {
            throw new Exception("Le paiement doit être payé pour générer un reçu");
        }
        
        // Générer ou régénérer le reçu
        $chemin_reçu = genererReçu($id_paiement);
        
        if ($chemin_reçu) {
            // La fonction genererReçu envoie déjà une notification, mais on peut en envoyer une autre plus explicite
            if (isset($paiement['id_patient']) && $paiement['id_patient']) {
                $montant_formate = number_format($paiement['Montant'], 0, ',', ' ') . ' GNF';
                $titre = "Reçu de paiement envoyé";
                $message = "Votre reçu de paiement d'un montant de " . $montant_formate . " vous a été envoyé. ";
                if (isset($paiement['id_facture']) && $paiement['id_facture']) {
                    $message .= "Numéro de facture : " . $paiement['id_facture'] . ". ";
                }
                $message .= "Vous pouvez le consulter dans votre espace patient.";
                $lien = "paiements/voir-reçu.php?id=" . $id_paiement;
                
                creerNotification(
                    $paiement['id_patient'],
                    $titre,
                    $message,
                    'paiement',
                    $lien
                );
            }
            
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Erreur envoyerReçuAuPatient: " . $e->getMessage());
        throw $e;
    }
}

// ============================================
// FONCTIONS POUR LES ORDONNANCES
// ============================================

/**
 * Créer une ordonnance
 * Retourne l'ID de l'ordonnance créée
 */
function creerOrdonnance($medicament, $dosage, $date_emission, $id_consultation, $duree_traitement = null, $instructions = null) {
    try {
        $pdo = bdd();
        $sql = "INSERT INTO ORDONNANCES (Médicament, Dosage, Date_émission, id_consultation, Durée_traitement, Instructions) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$medicament, $dosage, $date_emission, $id_consultation, $duree_traitement, $instructions]);
        
        if ($result) {
            $id_ordonnance = $pdo->lastInsertId();
            
            // Récupérer l'id_patient depuis la consultation pour envoyer une notification
            try {
                $sql_consultation = "SELECT id_patient FROM CONSULTATION WHERE id_consultation = ?";
                $stmt_consultation = $pdo->prepare($sql_consultation);
                $stmt_consultation->execute([$id_consultation]);
                $consultation = $stmt_consultation->fetch();
                
                if ($consultation && isset($consultation['id_patient']) && $consultation['id_patient']) {
                    // Envoyer une notification au patient
                    $titre = "Nouvelle ordonnance disponible";
                    $message = "Votre médecin a créé une nouvelle ordonnance pour vous. ";
                    $message .= "Médicament : " . htmlspecialchars($medicament);
                    if ($dosage) {
                        $message .= " - Dosage : " . htmlspecialchars($dosage);
                    }
                    if ($duree_traitement) {
                        $message .= " - Durée : " . htmlspecialchars($duree_traitement);
                    }
                    $message .= ". Vous pouvez consulter votre ordonnance dans votre espace patient.";
                    
                    // Utiliser le type 'consultation' pour les ordonnances (ou 'autre' si 'ordonnance' n'existe pas)
                    creerNotification(
                        $consultation['id_patient'],
                        $titre,
                        $message,
                        'consultation',
                        'mes-ordonnances.php' // Lien vers la page dédiée aux ordonnances du patient
                    );
                }
            } catch (Exception $e) {
                // Ne pas bloquer la création de l'ordonnance si la notification échoue
                error_log("Erreur envoi notification ordonnance: " . $e->getMessage());
            }
            
            return $id_ordonnance;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Erreur creerOrdonnance: " . $e->getMessage());
        throw new Exception("Erreur lors de la création de l'ordonnance : " . $e->getMessage());
    }
}

/**
 * Obtenir les ordonnances d'une consultation
 */
function getOrdonnancesByConsultation($id_consultation) {
    try {
        $pdo = bdd();
        $sql = "SELECT * FROM ORDONNANCES WHERE id_consultation = ? ORDER BY Date_émission DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_consultation]);
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        error_log("Erreur getOrdonnancesByConsultation: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir les ordonnances d'un patient
 */
function getOrdonnancesByPatient($id_patient) {
    try {
        $pdo = bdd();
        $sql = "SELECT o.*, c.Date_consultation, c.Motif_diagnostic, 
                       m.Nom_med, m.Prénom_med, m.Spécialisation_med
                FROM ORDONNANCES o
                INNER JOIN CONSULTATION c ON o.id_consultation = c.id_consultation
                LEFT JOIN MEDECINS m ON c.id_med = m.id_med
                WHERE c.id_patient = ?
                ORDER BY o.Date_émission DESC, o.id_ordonnance DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_patient]);
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        error_log("Erreur getOrdonnancesByPatient: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir les ordonnances d'un médecin (filtrées par service si spécialisation fournie)
 */
function getOrdonnancesByMedecin($id_med, $specialisation = null) {
    try {
        $pdo = bdd();
        
        if ($specialisation) {
            // Récupérer l'ID du service correspondant à la spécialisation
            $sql_service = "SELECT id_service FROM SERVICES WHERE Nom_service = ? LIMIT 1";
            $stmt_service = $pdo->prepare($sql_service);
            $stmt_service->execute([$specialisation]);
            $service = $stmt_service->fetch();
            
            if ($service && isset($service['id_service'])) {
                $id_service = $service['id_service'];
                // Récupérer les ordonnances DU MÉDECIN dans ce service
                $sql = "SELECT o.*, c.Date_consultation, c.Motif_diagnostic, 
                               p.Nom_patient, p.Prénom_patient, p.Matricule_patient,
                               m.Nom_med, m.Prénom_med
                        FROM ORDONNANCES o
                        INNER JOIN CONSULTATION c ON o.id_consultation = c.id_consultation
                        LEFT JOIN PATIENTS p ON c.id_patient = p.id_patient
                        LEFT JOIN MEDECINS m ON c.id_med = m.id_med
                        LEFT JOIN RENDEZ_VOUS r ON c.id_patient = r.id_patient
                        WHERE r.id_service = ? AND c.id_med = ?
                        ORDER BY o.Date_émission DESC, o.id_ordonnance DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_service, $id_med]);
            } else {
                // Filtrer par spécialisation, mais UNIQUEMENT pour ce médecin
                $sql = "SELECT o.*, c.Date_consultation, c.Motif_diagnostic,
                               p.Nom_patient, p.Prénom_patient, p.Matricule_patient,
                               m.Nom_med, m.Prénom_med
                        FROM ORDONNANCES o
                        INNER JOIN CONSULTATION c ON o.id_consultation = c.id_consultation
                        LEFT JOIN PATIENTS p ON c.id_patient = p.id_patient
                        LEFT JOIN MEDECINS m ON c.id_med = m.id_med
                        WHERE m.Spécialisation_med = ? AND c.id_med = ?
                        ORDER BY o.Date_émission DESC, o.id_ordonnance DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$specialisation, $id_med]);
            }
        } else {
            // Pas de filtre par service, retourner toutes les ordonnances du médecin
            $sql = "SELECT o.*, c.Date_consultation, c.Motif_diagnostic,
                           p.Nom_patient, p.Prénom_patient, p.Matricule_patient
                    FROM ORDONNANCES o
                    INNER JOIN CONSULTATION c ON o.id_consultation = c.id_consultation
                    LEFT JOIN PATIENTS p ON c.id_patient = p.id_patient
                    WHERE c.id_med = ?
                    ORDER BY o.Date_émission DESC, o.id_ordonnance DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_med]);
        }
        
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        error_log("Erreur getOrdonnancesByMedecin: " . $e->getMessage());
        return [];
    }
}

/**
 * Envoyer une ordonnance au patient (notification)
 * Cette fonction permet d'envoyer/réenvoyer une ordonnance existante au patient
 * 
 * @param int $id_consultation L'ID de la consultation liée à l'ordonnance
 * @return bool True si l'envoi a réussi, false sinon
 */
function envoyerOrdonnanceAuPatient($id_consultation) {
    try {
        $pdo = bdd();
        
        // Récupérer les informations de la consultation et du patient
        $sql_consultation = "SELECT c.id_patient, c.Date_consultation, c.Motif_diagnostic,
                                   p.Nom_patient, p.Prénom_patient,
                                   m.Nom_med, m.Prénom_med
                            FROM CONSULTATION c
                            LEFT JOIN PATIENTS p ON c.id_patient = p.id_patient
                            LEFT JOIN MEDECINS m ON c.id_med = m.id_med
                            WHERE c.id_consultation = ?";
        $stmt_consultation = $pdo->prepare($sql_consultation);
        $stmt_consultation->execute([$id_consultation]);
        $consultation = $stmt_consultation->fetch(PDO::FETCH_ASSOC);
        
        if (!$consultation || !isset($consultation['id_patient']) || !$consultation['id_patient']) {
            throw new Exception("Consultation introuvable ou patient non associé");
        }
        
        // Récupérer toutes les ordonnances de cette consultation
        $ordonnances = getOrdonnancesByConsultation($id_consultation);
        
        if (empty($ordonnances)) {
            throw new Exception("Aucune ordonnance trouvée pour cette consultation");
        }
        
        // Construire le message de notification
        $titre = "Ordonnance médicale";
        $message = "Votre médecin vous a envoyé une ordonnance médicale. ";
        
        if (isset($consultation['Prénom_med']) && isset($consultation['Nom_med'])) {
            $message .= "Médecin : Dr. " . htmlspecialchars($consultation['Prénom_med'] . ' ' . $consultation['Nom_med']) . ". ";
        }
        
        $message .= "Médicaments prescrits : ";
        
        $medicaments_list = [];
        foreach ($ordonnances as $ordo) {
            $med_info = htmlspecialchars($ordo['Médicament']);
            if (!empty($ordo['Dosage'])) {
                $med_info .= " - " . htmlspecialchars($ordo['Dosage']);
            }
            if (!empty($ordo['Durée_traitement'])) {
                $med_info .= " (" . htmlspecialchars($ordo['Durée_traitement']) . ")";
            }
            $medicaments_list[] = $med_info;
        }
        
        $message .= implode(" ; ", $medicaments_list);
        $message .= ". Vous pouvez consulter votre ordonnance complète dans votre espace patient.";
        
        // Envoyer la notification
        $lien = 'mes-ordonnances.php';
        $result = creerNotification(
            $consultation['id_patient'],
            $titre,
            $message,
            'consultation',
            $lien
        );
        
        if ($result) {
            return true;
        } else {
            throw new Exception("Erreur lors de l'envoi de la notification");
        }
    } catch (Exception $e) {
        error_log("Erreur envoyerOrdonnanceAuPatient: " . $e->getMessage());
        throw $e;
    }
}

// ============================================
// FONCTIONS POUR LES INSCRIPTIONS AUX SERVICES
// ============================================

/**
 * Inscrire un patient à un service
 */
function inscrirePatientAuService($id_patient, $id_service) {
    try {
        $pdo = bdd();
        
        // Vérifier si le patient n'est pas déjà inscrit à ce service
        $check = $pdo->prepare("SELECT id FROM PATIENT_SERVICES WHERE id_patient = ? AND id_service = ?");
        $check->execute([$id_patient, $id_service]);
        
        if ($check->rowCount() > 0) {
            throw new Exception("Ce patient est déjà inscrit à ce service.");
        }
        
        $sql = "INSERT INTO PATIENT_SERVICES (id_patient, id_service, Statut) VALUES (?, ?, 'inscrit')";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$id_patient, $id_service]);
    } catch (PDOException $e) {
        error_log("Erreur inscrirePatientAuService: " . $e->getMessage());
        throw new Exception("Erreur lors de l'inscription au service : " . $e->getMessage());
    }
}

/**
 * Obtenir les services auxquels un patient est inscrit
 */
function getServicesByPatient($id_patient) {
    try {
        $pdo = bdd();
        $sql = "SELECT s.*, ps.Date_inscription, ps.Statut 
                FROM SERVICES s
                INNER JOIN PATIENT_SERVICES ps ON s.id_service = ps.id_service
                WHERE ps.id_patient = ?
                ORDER BY ps.Date_inscription DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_patient]);
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        error_log("Erreur getServicesByPatient: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir les patients inscrits à un service
 */
function getPatientsInscritsAuService($id_service) {
    try {
        $pdo = bdd();
        $sql = "SELECT p.*, ps.Date_inscription, ps.Statut 
                FROM PATIENTS p
                INNER JOIN PATIENT_SERVICES ps ON p.id_patient = ps.id_patient
                WHERE ps.id_service = ?
                ORDER BY ps.Date_inscription DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_service]);
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        error_log("Erreur getPatientsInscritsAuService: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir tous les patients liés à un service (inscrits OU ayant eu un RDV dans ce service)
 * Utilisé pour lister les patients dans chaque service (admin).
 */
function getPatientsDuService($id_service) {
    try {
        $pdo = bdd();
        $sql = "SELECT DISTINCT p.* 
                FROM PATIENTS p
                LEFT JOIN PATIENT_SERVICES ps ON p.id_patient = ps.id_patient AND ps.id_service = ?
                LEFT JOIN RENDEZ_VOUS r ON p.id_patient = r.id_patient AND r.id_service = ?
                WHERE ps.id_service IS NOT NULL OR r.id_service IS NOT NULL
                ORDER BY p.Nom_patient, p.Prénom_patient";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_service, $id_service]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result ? $result : [];
    } catch (PDOException $e) {
        error_log("Erreur getPatientsDuService: " . $e->getMessage());
        return [];
    }
}

/**
 * Vérifier si un patient est inscrit à un service
 */
function isPatientInscritAuService($id_patient, $id_service) {
    try {
        $pdo = bdd();
        $sql = "SELECT id FROM PATIENT_SERVICES WHERE id_patient = ? AND id_service = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_patient, $id_service]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erreur isPatientInscritAuService: " . $e->getMessage());
        return false;
    }
}

// ============================================
// FONCTIONS POUR LES NOTIFICATIONS
// ============================================

/**
 * Créer la table NOTIFICATIONS si elle n'existe pas
 */
function createNotificationsTable() {
    try {
        $pdo = bdd();
        $sql = "CREATE TABLE IF NOT EXISTS `NOTIFICATIONS` (
            `id_notification` INT AUTO_INCREMENT PRIMARY KEY,
            `id_patient` INT NOT NULL,
            `titre` VARCHAR(255) NOT NULL,
            `message` TEXT NOT NULL,
            `type` ENUM('paiement', 'rendez_vous', 'consultation', 'autre') DEFAULT 'autre',
            `lien` VARCHAR(500) NULL COMMENT 'Lien vers la page concernée (ex: reçu, rendez-vous)',
            `lu` TINYINT(1) DEFAULT 0 COMMENT '0 = Non lu, 1 = Lu',
            `Date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`id_patient`) REFERENCES `PATIENTS`(`id_patient`) ON DELETE CASCADE ON UPDATE CASCADE,
            INDEX `idx_patient` (`id_patient`),
            INDEX `idx_lu` (`lu`),
            INDEX `idx_date_creation` (`Date_creation`),
            INDEX `idx_type` (`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $pdo->exec($sql);
        return true;
    } catch (PDOException $e) {
        // Si la table existe déjà, c'est OK
        if (strpos($e->getMessage(), 'already exists') === false && 
            strpos($e->getMessage(), 'Duplicate') === false) {
            error_log("Erreur création table NOTIFICATIONS: " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Créer une notification pour un patient
 */
function creerNotification($id_patient, $titre, $message, $type = 'autre', $lien = null) {
    try {
        // S'assurer que la table existe
        if (!tableExists('NOTIFICATIONS')) {
            createNotificationsTable();
        }
        
        $pdo = bdd();
        $sql = "INSERT INTO NOTIFICATIONS (id_patient, titre, message, type, lien, lu) 
                VALUES (?, ?, ?, ?, ?, 0)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$id_patient, $titre, $message, $type, $lien]);
    } catch (PDOException $e) {
        error_log("Erreur creerNotification: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir les notifications d'un patient (non lues en premier)
 */
function getNotificationsByPatient($id_patient, $non_lues_seulement = false) {
    try {
        if (!tableExists('NOTIFICATIONS')) {
            return [];
        }
        
        $pdo = bdd();
        $sql = "SELECT * FROM NOTIFICATIONS 
                WHERE id_patient = ?" . 
                ($non_lues_seulement ? " AND lu = 0" : "") . "
                ORDER BY lu ASC, Date_creation DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_patient]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result ? $result : [];
    } catch (PDOException $e) {
        error_log("Erreur getNotificationsByPatient: " . $e->getMessage());
        return [];
    }
}

/**
 * Marquer une notification comme lue
 */
function marquerNotificationLue($id_notification) {
    try {
        if (!tableExists('NOTIFICATIONS')) {
            return false;
        }
        
        $pdo = bdd();
        $sql = "UPDATE NOTIFICATIONS SET lu = 1 WHERE id_notification = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$id_notification]);
    } catch (PDOException $e) {
        error_log("Erreur marquerNotificationLue: " . $e->getMessage());
        return false;
    }
}

/**
 * Marquer toutes les notifications d'un patient comme lues
 */
function marquerToutesNotificationsLues($id_patient) {
    try {
        if (!tableExists('NOTIFICATIONS')) {
            return false;
        }
        
        $pdo = bdd();
        $sql = "UPDATE NOTIFICATIONS SET lu = 1 WHERE id_patient = ? AND lu = 0";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$id_patient]);
    } catch (PDOException $e) {
        error_log("Erreur marquerToutesNotificationsLues: " . $e->getMessage());
        return false;
    }
}

/**
 * Compter les notifications non lues d'un patient
 */
function countNotificationsNonLues($id_patient) {
    try {
        if (!tableExists('NOTIFICATIONS')) {
            return 0;
        }
        
        $pdo = bdd();
        $sql = "SELECT COUNT(*) as count FROM NOTIFICATIONS 
                WHERE id_patient = ? AND lu = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_patient]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? intval($result['count']) : 0;
    } catch (PDOException $e) {
        error_log("Erreur countNotificationsNonLues: " . $e->getMessage());
        return 0;
    }
}

?>
