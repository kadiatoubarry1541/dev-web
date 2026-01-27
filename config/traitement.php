<?php 

    require_once __DIR__ . '/bdd.php';

    // Fonction pour uploader la photo de profil
    // Contrôles simplifiés - accepte tous les formats d'image courants
    function uploadPhotoProfil($file) {
        $upload_dir = __DIR__ . '/../uploads/profiles/';
        
        // Créer le dossier s'il n'existe pas
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Vérifier les erreurs d'upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Erreur lors du téléchargement de la photo.'];
        }
        
        // Vérifier la taille (max 5MB - augmenté pour plus de flexibilité)
        if ($file['size'] > 5242880) {
            return ['success' => false, 'error' => 'La photo ne doit pas dépasser 5MB.'];
        }
        
        // Vérification simplifiée : seulement vérifier que c'est une image valide
        $image_info = @getimagesize($file['tmp_name']);
        if ($image_info === false) {
            return ['success' => false, 'error' => 'Le fichier n\'est pas une image valide.'];
        }
        
        // Récupérer l'extension du fichier (ou utiliser jpg par défaut)
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (empty($extension)) {
            // Si pas d'extension, déterminer depuis le type MIME
            $mime = $image_info['mime'] ?? '';
            if (strpos($mime, 'jpeg') !== false || strpos($mime, 'jpg') !== false) {
                $extension = 'jpg';
            } elseif (strpos($mime, 'png') !== false) {
                $extension = 'png';
            } elseif (strpos($mime, 'gif') !== false) {
                $extension = 'gif';
            } elseif (strpos($mime, 'webp') !== false) {
                $extension = 'webp';
            } else {
                $extension = 'jpg'; // Par défaut
            }
        }
        
        // Générer un nom de fichier unique
        $filename = 'profile_' . uniqid() . '_' . time() . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        // Déplacer le fichier
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => true, 'filename' => 'uploads/profiles/' . $filename];
        } else {
            return ['success' => false, 'error' => 'Impossible de sauvegarder la photo.'];
        }
    }

    // Stocker les informations d'un utilisateur dans la base de donnée à l'inscription
    // Crée un patient ou un médecin selon le type de compte
    // IMPORTANT : Tout compte qui n'est pas admin, médecin ou accueil est automatiquement un patient
    function inscription($nom, $prenom, $email, $telephone, $password, $role = 'patient', $date_naissance = null, $adresse = null, $sexe = null, $id_service = null, $specialisation = null, $photo_profil = null) {
        $pdo = bdd();
        
        try {
            // Normaliser l'email avant toutes les vérifications
            $email = trim(strtolower($email));
            
            if (empty($email)) {
                throw new Exception("L'email est requis.");
            }
            
            $pdo->beginTransaction();
            
            // RÈGLE IMPORTANTE : Tout compte qui n'est pas admin, médecin ou accueil est automatiquement un patient
            // Les rôles autorisés sont : 'admin', 'medecin', 'accueil', 'patient'
            // Si le rôle n'est pas un de ces 4, forcer le rôle à 'patient'
            $roles_autorises = ['admin', 'medecin', 'accueil', 'patient'];
            if (!in_array($role, $roles_autorises)) {
                $role = 'patient';
            }
            
            // Note : Les comptes admin et accueil ne sont PAS créés via cette fonction
            // Cette fonction est uniquement pour les patients et médecins
            // Si un rôle admin ou accueil est passé, forcer à patient (sécurité)
            if ($role === 'admin' || $role === 'accueil') {
                // Les comptes admin et accueil doivent être créés via des scripts spécifiques
                // Par sécurité, on force le rôle à patient
                $role = 'patient';
            }
            
            // Hasher le mot de passe
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $id_patient = null;
            $id_med = null;
            
            if ($role === 'patient') {
                // Vérifier que l'email n'existe pas déjà dans PATIENTS (avec normalisation)
                $check_patient_email = $pdo->prepare("SELECT id_patient FROM PATIENTS WHERE LOWER(TRIM(Email_patient)) = ?");
                $check_patient_email->execute([$email]);
                if ($check_patient_email->fetch()) {
                    throw new Exception("Cet email est déjà utilisé par un patient.");
                }
                
                // Créer le patient AVEC matricule automatique - très important pour les patients
                $date_naissance = $date_naissance ?? '1900-01-01';
                $matricule = genererMatriculePatient();
                
                $sql_patient = "INSERT INTO PATIENTS (Matricule_patient, Nom_patient, Prénom_patient, Tel_patient, Email_patient, Date_naissance_patient, Adresse_patient, Photo_profil) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_patient = $pdo->prepare($sql_patient);
                $stmt_patient->execute([$matricule, $nom, $prenom, $telephone, $email, $date_naissance, $adresse, $photo_profil]);
                $id_patient = $pdo->lastInsertId();
            } elseif ($role === 'medecin') {
                // Vérifier que l'email n'existe pas déjà dans MEDECINS (avec normalisation)
                $check_med_email = $pdo->prepare("SELECT id_med FROM MEDECINS WHERE LOWER(TRIM(Email_med)) = ?");
                $check_med_email->execute([$email]);
                if ($check_med_email->fetch()) {
                    throw new Exception("Cet email est déjà utilisé par un médecin.");
                }
                
                // Vérifier que la spécialisation est fournie
                if (empty($specialisation)) {
                    throw new Exception("La spécialisation est requise pour créer un médecin.");
                }
                
                // Créer le médecin SANS matricule - sera attribué par l'admin lors de l'approbation
                $sql_medecin = "INSERT INTO MEDECINS (Matricule_med, Nom_med, Prénom_med, Spécialisation_med, Tel_med, Email_med, Photo_profil, statut) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente')";
                $stmt_medecin = $pdo->prepare($sql_medecin);
                $result_medecin = $stmt_medecin->execute([null, $nom, $prenom, $specialisation, $telephone, $email, $photo_profil]);
                
                if (!$result_medecin) {
                    throw new Exception("Erreur lors de la création du médecin dans la base de données.");
                }
                
                $id_med = $pdo->lastInsertId();
                
                // Vérifier que l'ID a bien été récupéré
                if (empty($id_med)) {
                    throw new Exception("Erreur : Impossible de récupérer l'ID du médecin créé.");
                }
            }
            
            // Vérifier une dernière fois que l'email n'existe pas dans users (double sécurité avec normalisation)
            $check_user_email = $pdo->prepare("SELECT id FROM users WHERE LOWER(TRIM(email)) = ?");
            $check_user_email->execute([$email]);
            if ($check_user_email->fetch()) {
                throw new Exception("Cet email est déjà utilisé. Veuillez utiliser un autre email ou vous connecter.");
            }
            
            // Créer l'utilisateur
            $nom_complet = trim($nom . ' ' . $prenom);
            if (empty($nom_complet)) {
                throw new Exception("Le nom complet ne peut pas être vide.");
            }
            
            $sql_user = "INSERT INTO users (nom, email, telephone, password, photo_profil, role, id_patient, id_med) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_user = $pdo->prepare($sql_user);
            $result_user = $stmt_user->execute([$nom_complet, $email, $telephone, $password_hash, $photo_profil, $role, $id_patient, $id_med]);
            
            if (!$result_user) {
                throw new Exception("Erreur lors de la création du compte utilisateur.");
            }
            
            $pdo->commit();
            
            // Log pour le débogage
            error_log("Inscription réussie - Role: $role, Email: $email, ID Patient: " . ($id_patient ?? 'null') . ", ID Med: " . ($id_med ?? 'null'));
            
            return true;
        } catch (PDOException $e) {
            $pdo->rollBack();
            
            // Vérifier si c'est une erreur de duplication d'email
            // IMPORTANT : Ne pas confondre avec les erreurs de contrainte NOT NULL
            $error_message = $e->getMessage();
            $error_code = $e->getCode();
            $error_info = $e->errorInfo ?? [];
            $error_sqlstate = $error_info[0] ?? '';
            $error_mysql_code = $error_info[1] ?? 0;
            
            // Vérifier si c'est vraiment une duplication d'email (code 1062 pour MySQL)
            // Code 23000 peut être pour différentes contraintes (NOT NULL, UNIQUE, FOREIGN KEY, etc.)
            $is_duplicate_email = false;
            
            if ($error_code == 23000) {
                // Code 23000 = Integrity constraint violation (peut être NOT NULL, UNIQUE, etc.)
                // Code 1062 = Duplicate entry (spécifique à MySQL pour les duplications)
                if ($error_mysql_code == 1062 || 
                    strpos($error_message, 'Duplicate entry') !== false ||
                    (strpos($error_message, 'UNIQUE constraint') !== false && 
                     (strpos($error_message, 'email') !== false || 
                      strpos($error_message, 'Email') !== false ||
                      strpos($error_message, 'EMAIL') !== false))) {
                    $is_duplicate_email = true;
                } else if (strpos($error_message, 'cannot be null') !== false || 
                          strpos($error_message, 'NOT NULL') !== false) {
                    // C'est une erreur de contrainte NOT NULL, pas une duplication
                    // Re-lancer l'exception originale avec un message plus clair
                    throw new Exception("Erreur lors de l'inscription : " . $error_message . " Veuillez contacter l'administrateur.");
                }
            }
            
            if ($is_duplicate_email) {
                throw new Exception("Cet email est déjà utilisé. Veuillez utiliser un autre email ou vous connecter.");
            }
            
            throw $e;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // Vérifier si l'email existe dans users, PATIENTS ou MEDECINS
    // Normalise l'email (trim + lowercase) pour une comparaison fiable
    // AMÉLIORÉ : Vérification plus précise pour éviter les faux positifs
    function EmailExist($email, $role = null) {
        try {
            // Normaliser l'email : trim + lowercase + suppression des espaces multiples
            $email_normalized = trim(strtolower($email));
            $email_normalized = preg_replace('/\s+/', '', $email_normalized); // Supprimer tous les espaces
            
            // Si l'email est vide après normalisation, retourner false
            if (empty($email_normalized)) {
                error_log("EmailExist: Email vide après normalisation - original: " . $email);
                return false;
            }
            
            // Valider le format de l'email
            if (!filter_var($email_normalized, FILTER_VALIDATE_EMAIL)) {
                error_log("EmailExist: Format email invalide - " . $email_normalized);
                return false; // Email invalide = n'existe pas
            }
            
            $pdo = bdd();
            
            // PRIORITÉ 1 : Vérifier dans la table users (table principale)
            // C'est la table la plus importante car tous les comptes y sont
            try {
                $stmt_users = $pdo->prepare("SELECT id, role FROM users WHERE LOWER(TRIM(email)) = ? LIMIT 1");
                $stmt_users->execute([$email_normalized]);
                $user_result = $stmt_users->fetch(PDO::FETCH_ASSOC);
                
                if ($user_result) {
                    error_log("EmailExist: Email trouvé dans users - Email: " . $email_normalized . " | ID: " . $user_result['id'] . " | Role: " . ($user_result['role'] ?? 'N/A'));
                    return true; // Email existe dans users = compte existe vraiment
                }
            } catch (PDOException $e) {
                error_log("Erreur vérification email dans users: " . $e->getMessage());
                // Si la table users n'existe pas, c'est un problème grave
                throw $e;
            }
            
            // PRIORITÉ 2 : Vérifier dans les tables spécifiques UNIQUEMENT si l'email n'est pas dans users
            // On vérifie pour détecter les emails orphelins (dans PATIENTS/MEDECINS mais pas dans users)
            // Mais on ne bloque PAS l'inscription si l'email est orphelin - on laisse la contrainte UNIQUE de la DB gérer
            
            // Vérifier dans PATIENTS seulement si on s'inscrit comme patient
            if ($role === 'patient' || $role === null) {
                try {
                    $stmt_patient = $pdo->prepare("SELECT id_patient FROM PATIENTS WHERE LOWER(TRIM(Email_patient)) = ? AND Email_patient IS NOT NULL AND Email_patient != '' LIMIT 1");
                    $stmt_patient->execute([$email_normalized]);
                    $patient_result = $stmt_patient->fetch(PDO::FETCH_ASSOC);
                    
                    if ($patient_result) {
                        // Vérifier si cet email est déjà lié à un compte users
                        $check_linked = $pdo->prepare("SELECT id FROM users WHERE id_patient = ? LIMIT 1");
                        $check_linked->execute([$patient_result['id_patient']]);
                        if ($check_linked->fetch()) {
                            // Le patient a déjà un compte users, donc l'email existe vraiment
                            error_log("EmailExist: Email trouvé dans PATIENTS avec compte users lié - " . $email_normalized);
                            return true;
                        }
                        // Sinon, c'est un email orphelin - on ne bloque pas, la DB gérera
                        error_log("EmailExist: Email orphelin dans PATIENTS (pas de compte users) - " . $email_normalized);
                    }
                } catch (PDOException $e) {
                    // Table PATIENTS peut ne pas exister encore - ce n'est pas grave
                    error_log("Erreur vérification email PATIENTS (non bloquant): " . $e->getMessage());
                }
            }
            
            // Vérifier dans MEDECINS seulement si on s'inscrit comme médecin
            if ($role === 'medecin' || $role === null) {
                try {
                    $stmt_med = $pdo->prepare("SELECT id_med FROM MEDECINS WHERE LOWER(TRIM(Email_med)) = ? AND Email_med IS NOT NULL AND Email_med != '' LIMIT 1");
                    $stmt_med->execute([$email_normalized]);
                    $med_result = $stmt_med->fetch(PDO::FETCH_ASSOC);
                    
                    if ($med_result) {
                        // Vérifier si cet email est déjà lié à un compte users
                        $check_linked = $pdo->prepare("SELECT id FROM users WHERE id_med = ? LIMIT 1");
                        $check_linked->execute([$med_result['id_med']]);
                        if ($check_linked->fetch()) {
                            // Le médecin a déjà un compte users, donc l'email existe vraiment
                            error_log("EmailExist: Email trouvé dans MEDECINS avec compte users lié - " . $email_normalized);
                            return true;
                        }
                        // Sinon, c'est un email orphelin - on ne bloque pas, la DB gérera
                        error_log("EmailExist: Email orphelin dans MEDECINS (pas de compte users) - " . $email_normalized);
                    }
                } catch (PDOException $e) {
                    // Table MEDECINS peut ne pas exister encore - ce n'est pas grave
                    error_log("Erreur vérification email MEDECINS (non bloquant): " . $e->getMessage());
                }
            }
            
            // Email non trouvé dans users (table principale)
            // Les emails orphelins dans PATIENTS/MEDECINS ne bloquent pas - la contrainte UNIQUE de la DB gérera
            error_log("EmailExist: Email NON trouvé dans users - " . $email_normalized);
            return false;
            
        } catch (PDOException $e) {
            error_log("Erreur EmailExist (PDO): " . $e->getMessage() . " | Email: " . ($email_normalized ?? $email));
            // En cas d'erreur grave (table users n'existe pas), on bloque
            // Sinon, on retourne false pour permettre l'inscription
            // La contrainte UNIQUE de la DB sera le dernier rempart
            if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "n'existe pas") !== false) {
                throw $e; // Problème grave - table manquante
            }
            return false; // Erreur mineure - on laisse passer, la DB gérera
        } catch (Exception $e) {
            error_log("Erreur EmailExist (Exception): " . $e->getMessage() . " | Email: " . ($email_normalized ?? $email));
            return false; // En cas d'erreur, retourner false pour permettre l'inscription
        }
    }

    // Connexion utilisateur
    // Vérifie strictement que le compte existe dans la base de données
    function connexion($email, $password) {
        $pdo = bdd();
        
        // Vérifier d'abord si l'email existe dans la base de données
        $sql_check = "SELECT id FROM users WHERE email = ?";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([$email]);
        
        // Si l'email n'existe pas, retourner false immédiatement
        if ($stmt_check->rowCount() == 0) {
            return false;
        }
        
        // Récupérer les informations complètes de l'utilisateur
        $sql = "SELECT u.*, p.id_patient, p.Nom_patient, p.Prénom_patient, p.Matricule_patient, m.id_med, m.Spécialisation_med, m.Matricule_med, m.statut as medecin_statut
                FROM users u 
                LEFT JOIN PATIENTS p ON u.id_patient = p.id_patient 
                LEFT JOIN MEDECINS m ON u.id_med = m.id_med
                WHERE u.email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // Vérifier que l'utilisateur existe ET que le mot de passe est correct
        if ($user && isset($user['password']) && password_verify($password, $user['password'])) {
            // Vérifier le statut du médecin si c'est un médecin
            if ($user['role'] === 'medecin' && isset($user['medecin_statut'])) {
                // Refuser seulement si le statut est "refusé"
                if ($user['medecin_statut'] === 'refusé') {
                    throw new Exception("Votre demande d'inscription en tant que médecin a été refusée. Veuillez contacter l'administrateur pour plus d'informations.");
                }
                // Si le statut est "en_attente", permettre la connexion mais avec droits limités
            }
            // Vérifier que le compte est actif (pas supprimé)
            // On peut ajouter un champ 'actif' plus tard si nécessaire
            
            // PATIENT : si id_patient manquant ou invalide, le retrouver dans PATIENTS par email et lier le compte
            if (($user['role'] ?? '') === 'patient') {
                $id_pat = $user['id_patient'] ?? null;
                $email_norm = trim(strtolower($email));
                if (empty($id_pat) || $id_pat <= 0) {
                    $stmt_p = $pdo->prepare("SELECT id_patient, Matricule_patient FROM PATIENTS WHERE LOWER(TRIM(COALESCE(Email_patient,''))) = ? LIMIT 1");
                    $stmt_p->execute([$email_norm]);
                    $row = $stmt_p->fetch(PDO::FETCH_ASSOC);
                    if ($row && !empty($row['id_patient'])) {
                        $id_pat = (int)$row['id_patient'];
                        $user['id_patient'] = $id_pat;
                        $user['Matricule_patient'] = $row['Matricule_patient'] ?? null;
                        $pdo->prepare("UPDATE users SET id_patient = ? WHERE id = ?")->execute([$id_pat, (int)$user['id']]);
                    }
                } else {
                    $stmt_v = $pdo->prepare("SELECT id_patient, Matricule_patient FROM PATIENTS WHERE id_patient = ? LIMIT 1");
                    $stmt_v->execute([(int)$id_pat]);
                    $row = $stmt_v->fetch(PDO::FETCH_ASSOC);
                    if (!$row && !empty($email_norm)) {
                        $stmt_p = $pdo->prepare("SELECT id_patient, Matricule_patient FROM PATIENTS WHERE LOWER(TRIM(COALESCE(Email_patient,''))) = ? LIMIT 1");
                        $stmt_p->execute([$email_norm]);
                        $row = $stmt_p->fetch(PDO::FETCH_ASSOC);
                        if ($row && !empty($row['id_patient'])) {
                            $id_pat = (int)$row['id_patient'];
                            $user['id_patient'] = $id_pat;
                            $user['Matricule_patient'] = $row['Matricule_patient'] ?? null;
                            $pdo->prepare("UPDATE users SET id_patient = ? WHERE id = ?")->execute([$id_pat, (int)$user['id']]);
                        }
                    }
                }
            }
            
            // Démarrer la session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Régénérer l'ID de session pour éviter la fixation de session
            session_regenerate_id(true);
            
            // Stocker toutes les informations dans la session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nom'] = $user['nom'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'] ?? 'patient';
            $_SESSION['id_patient'] = $user['id_patient'] ?? null;
            $_SESSION['id_med'] = $user['id_med'] ?? null;
            $_SESSION['specialisation'] = $user['Spécialisation_med'] ?? null;
            $_SESSION['photo_profil'] = $user['photo_profil'] ?? null;
            $_SESSION['matricule_patient'] = $user['Matricule_patient'] ?? null;
            $_SESSION['matricule_med'] = $user['Matricule_med'] ?? null;
            $_SESSION['medecin_statut'] = $user['medecin_statut'] ?? null; // Stocker le statut du médecin
            
            return true;
        }
        
        // Si on arrive ici, soit l'utilisateur n'existe pas, soit le mot de passe est incorrect
        return false;
    }

    // Déconnexion
    function deconnexion() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
        return true;
    }

    /**
     * Générer un matricule unique pour un patient
     * Format: PAT + YYYYMMDD + numéro séquentiel (4 chiffres)
     */
    function genererMatriculePatient() {
        $pdo = bdd();
        
        // Récupérer le dernier numéro séquentiel du jour
        $date_prefix = date('Ymd');
        $prefix = 'PAT' . $date_prefix;
        
        // Chercher le dernier matricule avec ce préfixe
        $stmt = $pdo->prepare("SELECT Matricule_patient FROM PATIENTS 
                               WHERE Matricule_patient LIKE ? 
                               ORDER BY Matricule_patient DESC 
                               LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $last = $stmt->fetch();
        
        if ($last && !empty($last['Matricule_patient'])) {
            // Extraire le numéro séquentiel
            $last_num = intval(substr($last['Matricule_patient'], -4));
            $new_num = $last_num + 1;
        } else {
            $new_num = 1;
        }
        
        // Formater avec 4 chiffres
        $matricule = $prefix . str_pad($new_num, 4, '0', STR_PAD_LEFT);
        
        // Vérifier l'unicité (au cas où)
        $check = $pdo->prepare("SELECT id_patient FROM PATIENTS WHERE Matricule_patient = ?");
        $check->execute([$matricule]);
        if ($check->rowCount() > 0) {
            // Si existe, incrémenter
            $new_num++;
            $matricule = $prefix . str_pad($new_num, 4, '0', STR_PAD_LEFT);
        }
        
        return $matricule;
    }

    /**
     * Générer un matricule unique pour un médecin
     * Format: MED + YYYYMMDD + numéro séquentiel (4 chiffres)
     */
    function genererMatriculeMedecin() {
        $pdo = bdd();
        
        // Récupérer le dernier numéro séquentiel du jour
        $date_prefix = date('Ymd');
        $prefix = 'MED' . $date_prefix;
        
        // Chercher le dernier matricule avec ce préfixe
        $stmt = $pdo->prepare("SELECT Matricule_med FROM MEDECINS 
                               WHERE Matricule_med LIKE ? 
                               ORDER BY Matricule_med DESC 
                               LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $last = $stmt->fetch();
        
        if ($last && !empty($last['Matricule_med'])) {
            // Extraire le numéro séquentiel
            $last_num = intval(substr($last['Matricule_med'], -4));
            $new_num = $last_num + 1;
        } else {
            $new_num = 1;
        }
        
        // Formater avec 4 chiffres
        $matricule = $prefix . str_pad($new_num, 4, '0', STR_PAD_LEFT);
        
        // Vérifier l'unicité (au cas où)
        $check = $pdo->prepare("SELECT id_med FROM MEDECINS WHERE Matricule_med = ?");
        $check->execute([$matricule]);
        if ($check->rowCount() > 0) {
            // Si existe, incrémenter
            $new_num++;
            $matricule = $prefix . str_pad($new_num, 4, '0', STR_PAD_LEFT);
        }
        
        return $matricule;
    }

    /**
     * Attribuer un matricule à un patient
     */
    function attribuerMatriculePatient($id_patient, $matricule = null) {
        $pdo = bdd();
        
        // Si aucun matricule fourni, en générer un
        if ($matricule === null) {
            $matricule = genererMatriculePatient();
        }
        
        // Vérifier que le matricule n'existe pas déjà
        $check = $pdo->prepare("SELECT id_patient FROM PATIENTS WHERE Matricule_patient = ? AND id_patient != ?");
        $check->execute([$matricule, $id_patient]);
        if ($check->rowCount() > 0) {
            throw new Exception("Ce matricule est déjà utilisé.");
        }
        
        // Attribuer le matricule
        $stmt = $pdo->prepare("UPDATE PATIENTS SET Matricule_patient = ? WHERE id_patient = ?");
        $stmt->execute([$matricule, $id_patient]);
        
        return $matricule;
    }

    /**
     * Attribuer un matricule à un médecin
     *
     * IMPORTANT :
     * - Si on est déjà dans une transaction avec un $pdo existant (admin/ajouter-medecin.php,
     *   admin/approuver-medecins.php, etc.), il faut absolument réutiliser CE MÊME PDO,
     *   sinon on crée un second connexion qui tente de modifier la même ligne et provoque
     *   une erreur MySQL de type :
     *   SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded
     *
     * @param int         $id_med    ID du médecin
     * @param string|null $matricule Matricule à forcer, ou null pour auto-génération
     * @param PDO|null    $pdo       Connexion PDO existante (optionnelle)
     */
    function attribuerMatriculeMedecin($id_med, $matricule = null, $pdo = null) {
        // Réutiliser la connexion existante si fournie, sinon ouvrir une nouvelle
        if ($pdo === null) {
            $pdo = bdd();
        }
        
        // Si aucun matricule fourni, en générer un
        if ($matricule === null) {
            $matricule = genererMatriculeMedecin();
        }
        
        // Vérifier que le matricule n'existe pas déjà
        $check = $pdo->prepare("SELECT id_med FROM MEDECINS WHERE Matricule_med = ? AND id_med != ?");
        $check->execute([$matricule, $id_med]);
        if ($check->rowCount() > 0) {
            throw new Exception("Ce matricule est déjà utilisé.");
        }
        
        // Attribuer le matricule
        $stmt = $pdo->prepare("UPDATE MEDECINS SET Matricule_med = ? WHERE id_med = ?");
        $stmt->execute([$matricule, $id_med]);
        
        return $matricule;
    }