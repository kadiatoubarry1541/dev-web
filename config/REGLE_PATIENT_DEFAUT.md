# Règle Fondamentale : Patient par Défaut

## Principe

**TOUT compte qui n'est PAS :**
- Admin
- Médecin (4 services : Médecine générale, Chirurgie, Maternité, Ophtalmologie)
- Agent d'accueil

**Est AUTOMATIQUEMENT un compte PATIENT.**

---

## Implémentation

### 1. Dans la Base de Données

La table `users` a un champ `role` avec :
```sql
`role` ENUM('patient', 'medecin', 'admin', 'accueil') DEFAULT 'patient'
```

Le **DEFAULT 'patient'** garantit que tout nouveau compte est patient par défaut.

### 2. Dans le Code PHP

#### Fonction `inscription()` dans `config/traitement.php`

La fonction vérifie et force le rôle à 'patient' si :
- Le rôle n'est pas dans la liste autorisée
- Le rôle est 'admin' ou 'accueil' (ces comptes doivent être créés via des scripts spécifiques)

```php
// RÈGLE IMPORTANTE : Tout compte qui n'est pas admin, médecin ou accueil est automatiquement un patient
$roles_autorises = ['admin', 'medecin', 'accueil', 'patient'];
if (!in_array($role, $roles_autorises)) {
    $role = 'patient';
}

// Les comptes admin et accueil ne sont PAS créés via inscription()
if ($role === 'admin' || $role === 'accueil') {
    $role = 'patient'; // Par sécurité
}
```

### 3. Dans les Sessions

Si le rôle n'est pas défini dans la session, il est par défaut 'patient' :
```php
'role' => $_SESSION['user_role'] ?? 'patient'
```

---

## Création des Comptes

### Comptes qui sont TOUJOURS des patients

1. **Inscription publique** (`register-patient.php`)
   - Tous les comptes créés via cette page sont des patients
   - Rôle : `'patient'` (explicite)

2. **Inscription sans spécification de rôle**
   - Si aucun rôle n'est spécifié → `'patient'`
   - Si un rôle invalide est spécifié → `'patient'`

### Comptes qui NE SONT PAS des patients

1. **Admin**
   - Créé via `config/create_admin.php`
   - Rôle : `'admin'` (explicite)
   - **Ne peut PAS être créé via inscription()**

2. **Médecin**
   - Créé via `register-medecin.php` (auto-inscription) → Rôle : `'medecin'`
   - Créé via `admin/ajouter-medecin.php` (par admin) → Rôle : `'medecin'`
   - **Doit avoir une spécialisation** (Médecine générale, Chirurgie, Maternité, Ophtalmologie)

3. **Accueil**
   - Créé via `config/create_accueil.php`
   - Rôle : `'accueil'` (explicite)
   - **Ne peut PAS être créé via inscription()**

---

## Vérifications dans le Code

### Vérification du Rôle

```php
// Dans config/traitement.php
function inscription(..., $role = 'patient', ...) {
    // Si rôle invalide → patient
    if (!in_array($role, ['admin', 'medecin', 'accueil', 'patient'])) {
        $role = 'patient';
    }
    
    // Si admin ou accueil → patient (sécurité)
    if ($role === 'admin' || $role === 'accueil') {
        $role = 'patient';
    }
}
```

### Vérification dans la Base de Données

```sql
-- Tous les comptes qui ne sont pas admin, medecin ou accueil
SELECT * FROM users 
WHERE role NOT IN ('admin', 'medecin', 'accueil');
-- Résultat : Tous sont des patients

-- Vérifier qu'il n'y a pas de rôles invalides
SELECT DISTINCT role FROM users;
-- Doit retourner uniquement : 'admin', 'medecin', 'accueil', 'patient'
```

---

## Exemples

### Exemple 1 : Inscription normale
```php
inscription('Doe', 'John', 'john@mail.com', '0123456789', 'password123');
// Rôle par défaut : 'patient' ✅
```

### Exemple 2 : Inscription avec rôle invalide
```php
inscription('Doe', 'John', 'john@mail.com', '0123456789', 'password123', 'utilisateur');
// Rôle 'utilisateur' invalide → Forcé à 'patient' ✅
```

### Exemple 3 : Tentative de créer admin via inscription()
```php
inscription('Admin', 'Test', 'admin@test.com', '0123456789', 'password123', 'admin');
// Rôle 'admin' → Forcé à 'patient' (sécurité) ✅
// Les admins doivent être créés via create_admin.php
```

### Exemple 4 : Inscription médecin
```php
inscription('Dupont', 'Jean', 'jean@mail.com', '0123456789', 'password123', 'medecin', null, null, null, 1, 'Médecine générale');
// Rôle : 'medecin' ✅
// Créé dans MEDECINS ET users
```

---

## Requêtes SQL Utiles

### Compter les patients (tous les comptes qui ne sont pas admin, medecin, accueil)
```sql
SELECT COUNT(*) as nombre_patients
FROM users
WHERE role = 'patient';
```

### Lister tous les patients
```sql
SELECT u.*, p.Matricule_patient, p.Date_naissance_patient
FROM users u
INNER JOIN PATIENTS p ON u.id_patient = p.id_patient
WHERE u.role = 'patient';
```

### Vérifier qu'il n'y a pas de rôles invalides
```sql
SELECT role, COUNT(*) as nombre
FROM users
GROUP BY role;
-- Doit retourner uniquement : admin, medecin, accueil, patient
```

### Trouver les comptes qui devraient être patients mais ne le sont pas
```sql
-- Comptes avec rôle NULL (devrait être patient)
SELECT * FROM users WHERE role IS NULL;

-- Comptes avec rôle invalide (si l'ENUM permet d'autres valeurs)
-- Normalement impossible grâce à l'ENUM, mais vérification utile
```

---

## Points Importants

1. ✅ **DEFAULT 'patient'** dans la base de données
2. ✅ **Vérification dans inscription()** : Rôle invalide → patient
3. ✅ **Sécurité** : Admin et accueil ne peuvent pas être créés via inscription()
4. ✅ **Session** : Rôle par défaut 'patient' si non défini
5. ✅ **Tous les comptes non spécifiés** sont automatiquement des patients

---

## Fichiers Concernés

- `config/traitement.php` : Fonction `inscription()` avec vérification du rôle
- `config/permissions.php` : Documentation de la règle
- `config/sante1_database.sql` : Structure de la table avec DEFAULT 'patient'
- `config/session.php` : Rôle par défaut dans getUserInfo()
