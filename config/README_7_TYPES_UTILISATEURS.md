# Les 7 Types d'Utilisateurs - Système MediCo.

## Vue d'ensemble

Le système gère **7 types d'utilisateurs distincts** qui sont tous stockés dans la table `users` mais liés à des tables spécifiques selon leur rôle.

---

## Architecture des Comptes Utilisateurs

### Table Centralisée : `users`

Tous les utilisateurs sont stockés dans la table `users` avec :
- `id` : Identifiant unique
- `email` : Email unique (utilisé pour la connexion)
- `password` : Mot de passe hashé
- `role` : Type d'utilisateur (`admin`, `medecin`, `accueil`, `patient`)
- `id_patient` : Lien vers la table `PATIENTS` (si rôle = patient)
- `id_med` : Lien vers la table `MEDECINS` (si rôle = medecin)
- `nom`, `telephone`, `photo_profil` : Informations communes

### Tables Spécifiques

Selon le rôle, l'utilisateur est aussi enregistré dans :
- **PATIENTS** : Pour les patients (avec matricule, date de naissance, adresse)
- **MEDECINS** : Pour les médecins (avec matricule, spécialisation, statut)
- **users uniquement** : Pour admin et accueil (pas de table spécifique)

---

## Les 7 Types d'Utilisateurs

### 1. **ADMINISTRATEUR (admin)**

**Table** : `users` uniquement (pas de table spécifique)

**Caractéristiques** :
- Stocké uniquement dans `users` avec `role = 'admin'`
- `id_patient = NULL` et `id_med = NULL`
- Contrôle total sur le système
- Peut créer, modifier, supprimer tous les autres types d'utilisateurs

**Création** :
- Via `config/create_admin.php` ou manuellement
- Un seul compte admin par défaut

**Permissions** : Toutes les permissions (voir `config/permissions.php`)

---

### 2-5. **MÉDECINS (medecin)** - 4 Types selon la Spécialisation

**Tables** : `users` + `MEDECINS`

**Caractéristiques** :
- Stocké dans `users` avec `role = 'medecin'` ET dans `MEDECINS`
- `id_med` dans `users` pointe vers `MEDECINS.id_med`
- `id_patient = NULL` dans `users`
- Chaque médecin a une spécialisation qui détermine son service

**Les 4 Types de Médecins** :

#### 2. **Médecin - Médecine Générale**
- `Spécialisation_med = 'Médecine générale'`
- Gère les consultations générales

#### 3. **Médecin - Chirurgie**
- `Spécialisation_med = 'Chirurgie'`
- Gère les interventions chirurgicales

#### 4. **Médecin - Maternité**
- `Spécialisation_med = 'Maternité'`
- Gère les suivis de grossesse et accouchements

#### 5. **Médecin - Ophtalmologie**
- `Spécialisation_med = 'Ophtalmologie'`
- Gère les examens et soins oculaires

**Création** :
- Par l'admin : `admin/ajouter-medecin.php` → Statut `'approuvé'` directement
- Par le médecin lui-même : `register-medecin.php` → Statut `'en_attente'` (nécessite approbation)

**Permissions** : Limitées à leur service uniquement (voir `config/permissions.php`)

**Important** : Un médecin en attente peut se connecter mais avec des droits limités.

---

### 6. **ACCUEIL (accueil)**

**Table** : `users` uniquement (pas de table spécifique)

**Caractéristiques** :
- Stocké uniquement dans `users` avec `role = 'accueil'`
- `id_patient = NULL` et `id_med = NULL`
- Gère les patients et les paiements
- Ne peut pas créer de consultations ou ordonnances

**Création** :
- Via `config/create_accueil.php` ou par l'admin

**Permissions** :
- ✅ Créer et modifier les patients
- ✅ Créer des rendez-vous
- ✅ Gérer les paiements
- ❌ Ne peut pas gérer les consultations
- ❌ Ne peut pas créer d'ordonnances

---

### 7. **PATIENT (patient)**

**Tables** : `users` + `PATIENTS`

**Caractéristiques** :
- Stocké dans `users` avec `role = 'patient'` ET dans `PATIENTS`
- `id_patient` dans `users` pointe vers `PATIENTS.id_patient`
- `id_med = NULL` dans `users`
- Chaque patient a un matricule unique

**Création** :
- Par le patient lui-même : `register-patient.php`
- Par l'accueil : `admin/ajouter-patient.php` (si existe)
- Matricule généré automatiquement lors de l'inscription

**Permissions** :
- ✅ Voir son propre profil
- ✅ Prendre des rendez-vous
- ✅ Voir ses consultations et ordonnances
- ❌ Ne peut pas gérer d'autres utilisateurs

---

## Distinction des Comptes selon le Rôle

### Comment Distinguer les Comptes ?

1. **Par le champ `role` dans `users`** :
   ```sql
   SELECT * FROM users WHERE role = 'admin';
   SELECT * FROM users WHERE role = 'medecin';
   SELECT * FROM users WHERE role = 'accueil';
   SELECT * FROM users WHERE role = 'patient';
   ```

2. **Par les liens (`id_patient` ou `id_med`)** :
   ```sql
   -- Patients
   SELECT * FROM users WHERE role = 'patient' AND id_patient IS NOT NULL;
   
   -- Médecins
   SELECT * FROM users WHERE role = 'medecin' AND id_med IS NOT NULL;
   
   -- Admin et Accueil
   SELECT * FROM users WHERE role IN ('admin', 'accueil') 
   AND id_patient IS NULL AND id_med IS NULL;
   ```

3. **Par la table spécifique** :
   ```sql
   -- Médecins avec leur spécialisation
   SELECT u.*, m.Spécialisation_med, m.statut 
   FROM users u 
   INNER JOIN MEDECINS m ON u.id_med = m.id_med;
   
   -- Patients avec leur matricule
   SELECT u.*, p.Matricule_patient 
   FROM users u 
   INNER JOIN PATIENTS p ON u.id_patient = p.id_patient;
   ```

---

## Règles de Création des Comptes

### Règle 1 : Un Email = Un Compte
- Chaque email est unique dans `users.email`
- Un email ne peut pas être utilisé pour plusieurs rôles
- Vérification effectuée avant création

### Règle 2 : Double Enregistrement pour Patients et Médecins
- **Patient** : Créé dans `PATIENTS` ET `users`
- **Médecin** : Créé dans `MEDECINS` ET `users`
- **Admin/Accueil** : Créé uniquement dans `users`

### Règle 3 : Liens de Clés Étrangères
- `users.id_patient` → `PATIENTS.id_patient` (si patient)
- `users.id_med` → `MEDECINS.id_med` (si médecin)
- Si un patient/médecin est supprimé, le lien est mis à NULL (ON DELETE SET NULL)

---

## Exemples de Requêtes Utiles

### Lister tous les types d'utilisateurs avec leurs informations

```sql
-- Admin
SELECT 'Admin' as type, u.id, u.nom, u.email, u.role 
FROM users u 
WHERE u.role = 'admin';

-- Accueil
SELECT 'Accueil' as type, u.id, u.nom, u.email, u.role 
FROM users u 
WHERE u.role = 'accueil';

-- Médecins avec spécialisation
SELECT 'Médecin' as type, u.id, u.nom, u.email, u.role, 
       m.Spécialisation_med, m.statut, m.Matricule_med
FROM users u 
INNER JOIN MEDECINS m ON u.id_med = m.id_med
WHERE u.role = 'medecin';

-- Patients avec matricule
SELECT 'Patient' as type, u.id, u.nom, u.email, u.role, 
       p.Matricule_patient
FROM users u 
INNER JOIN PATIENTS p ON u.id_patient = p.id_patient
WHERE u.role = 'patient';
```

### Compter les utilisateurs par type

```sql
SELECT 
    role,
    COUNT(*) as nombre
FROM users
GROUP BY role;
```

### Vérifier la cohérence des données

```sql
-- Médecins sans lien dans users
SELECT m.* FROM MEDECINS m 
LEFT JOIN users u ON m.id_med = u.id_med 
WHERE u.id IS NULL;

-- Patients sans lien dans users
SELECT p.* FROM PATIENTS p 
LEFT JOIN users u ON p.id_patient = u.id_patient 
WHERE u.id IS NULL;

-- Users avec rôle médecin mais sans lien MEDECINS
SELECT u.* FROM users u 
WHERE u.role = 'medecin' AND u.id_med IS NULL;

-- Users avec rôle patient mais sans lien PATIENTS
SELECT u.* FROM users u 
WHERE u.role = 'patient' AND u.id_patient IS NULL;
```

---

## Résumé des 7 Types

| # | Type | Table users | Table Spécifique | Lien | Spécialisation |
|---|------|-------------|------------------|------|----------------|
| 1 | Admin | ✅ | ❌ | - | - |
| 2 | Médecin - Médecine générale | ✅ | ✅ MEDECINS | `id_med` | Médecine générale |
| 3 | Médecin - Chirurgie | ✅ | ✅ MEDECINS | `id_med` | Chirurgie |
| 4 | Médecin - Maternité | ✅ | ✅ MEDECINS | `id_med` | Maternité |
| 5 | Médecin - Ophtalmologie | ✅ | ✅ MEDECINS | `id_med` | Ophtalmologie |
| 6 | Accueil | ✅ | ❌ | - | - |
| 7 | Patient | ✅ | ✅ PATIENTS | `id_patient` | - |

---

## Points Importants à Retenir

1. **Tous les utilisateurs sont dans `users`** : C'est la table centrale pour l'authentification
2. **Les médecins et patients sont aussi dans leurs tables spécifiques** : Pour les données métier
3. **Les 4 types de médecins sont distingués par leur spécialisation** : Même rôle `'medecin'`, mais spécialisation différente
4. **Admin et Accueil n'ont pas de table spécifique** : Toutes leurs données sont dans `users`
5. **Un email = un compte unique** : Impossible d'avoir le même email pour plusieurs rôles
6. **Les liens sont optionnels mais recommandés** : `id_patient` et `id_med` permettent de joindre les données

---

## Fichiers de Référence

- `config/permissions.php` : Définition des permissions par rôle
- `config/traitement.php` : Fonctions de création de comptes
- `config/session.php` : Gestion des sessions utilisateurs
- `config/sante1_database.sql` : Structure de la base de données
