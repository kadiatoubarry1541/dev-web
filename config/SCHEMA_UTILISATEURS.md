# Schéma Visuel - Architecture des Utilisateurs

## Structure des Tables et Relations

```
┌─────────────────────────────────────────────────────────────────┐
│                        TABLE: users                             │
│  (Table centrale pour l'authentification)                      │
├─────────────────────────────────────────────────────────────────┤
│  id (PK)                                                        │
│  email (UNIQUE) ← Utilisé pour la connexion                    │
│  password (hashé)                                               │
│  nom                                                             │
│  telephone                                                      │
│  photo_profil                                                   │
│  role (ENUM: 'admin', 'medecin', 'accueil', 'patient')         │
│  id_patient (FK → PATIENTS.id_patient) [NULL si pas patient]   │
│  id_med (FK → MEDECINS.id_med) [NULL si pas médecin]           │
│  Date_creation                                                  │
└─────────────────────────────────────────────────────────────────┘
         │                    │
         │                    │
         ▼                    ▼
┌──────────────────┐  ┌──────────────────┐
│  TABLE: PATIENTS │  │  TABLE: MEDECINS  │
├──────────────────┤  ├──────────────────┤
│  id_patient (PK) │  │  id_med (PK)     │
│  Matricule_*     │  │  Matricule_*    │
│  Nom_*           │  │  Nom_*          │
│  Prénom_*        │  │  Prénom_*       │
│  Email_*         │  │  Email_*        │
│  Tel_*           │  │  Tel_*         │
│  ...             │  │  Spécialisation │
│                  │  │  statut         │
└──────────────────┘  └──────────────────┘
```

## Les 7 Types d'Utilisateurs - Vue Détaillée

### Type 1 : ADMINISTRATEUR
```
users
├─ id: 1
├─ email: "admin@clinique.fr"
├─ role: "admin"
├─ id_patient: NULL
└─ id_med: NULL

❌ Pas de table spécifique
✅ Toutes les données dans users uniquement
```

### Types 2-5 : MÉDECINS (4 spécialisations)

#### Type 2 : Médecin - Médecine générale
```
users                          MEDECINS
├─ id: 10                      ├─ id_med: 5
├─ email: "med1@clinique.fr"  ├─ Matricule_med: "MED202601240001"
├─ role: "medecin"             ├─ Nom_med: "Dupont"
├─ id_patient: NULL            ├─ Prénom_med: "Jean"
└─ id_med: 5 ──────────────────┼─ Spécialisation_med: "Médecine générale"
                               └─ statut: "approuvé"
```

#### Type 3 : Médecin - Chirurgie
```
users                          MEDECINS
├─ id: 11                      ├─ id_med: 6
├─ email: "chir@clinique.fr"  ├─ Matricule_med: "MED202601240002"
├─ role: "medecin"             ├─ Nom_med: "Martin"
├─ id_patient: NULL            ├─ Prénom_med: "Marie"
└─ id_med: 6 ──────────────────┼─ Spécialisation_med: "Chirurgie"
                               └─ statut: "approuvé"
```

#### Type 4 : Médecin - Maternité
```
users                          MEDECINS
├─ id: 12                      ├─ id_med: 7
├─ email: "mat@clinique.fr"   ├─ Matricule_med: "MED202601240003"
├─ role: "medecin"             ├─ Nom_med: "Bernard"
├─ id_patient: NULL            ├─ Prénom_med: "Sophie"
└─ id_med: 7 ──────────────────┼─ Spécialisation_med: "Maternité"
                               └─ statut: "approuvé"
```

#### Type 5 : Médecin - Ophtalmologie
```
users                          MEDECINS
├─ id: 13                      ├─ id_med: 8
├─ email: "opht@clinique.fr"  ├─ Matricule_med: "MED202601240004"
├─ role: "medecin"             ├─ Nom_med: "Durand"
├─ id_patient: NULL            ├─ Prénom_med: "Pierre"
└─ id_med: 8 ──────────────────┼─ Spécialisation_med: "Ophtalmologie"
                               └─ statut: "approuvé"
```

### Type 6 : ACCUEIL
```
users
├─ id: 2
├─ email: "accueil@clinique.fr"
├─ role: "accueil"
├─ id_patient: NULL
└─ id_med: NULL

❌ Pas de table spécifique
✅ Toutes les données dans users uniquement
```

### Type 7 : PATIENT
```
users                          PATIENTS
├─ id: 20                      ├─ id_patient: 15
├─ email: "patient@mail.fr"    ├─ Matricule_patient: "PAT202601240001"
├─ role: "patient"             ├─ Nom_patient: "Doe"
├─ id_patient: 15 ─────────────┼─ Prénom_patient: "John"
└─ id_med: NULL                ├─ Email_patient: "patient@mail.fr"
                               ├─ Date_naissance_patient: "1990-01-15"
                               └─ Adresse_patient: "123 Rue..."
```

## Règles de Distinction

### Comment identifier le type d'utilisateur ?

1. **Par le champ `role` dans `users`** :
   ```sql
   SELECT role, COUNT(*) FROM users GROUP BY role;
   ```

2. **Par la présence de liens** :
   - `id_patient IS NOT NULL` → Patient
   - `id_med IS NOT NULL` → Médecin
   - `id_patient IS NULL AND id_med IS NULL` → Admin ou Accueil

3. **Par la spécialisation (pour les médecins)** :
   ```sql
   SELECT u.*, m.Spécialisation_med 
   FROM users u 
   JOIN MEDECINS m ON u.id_med = m.id_med 
   WHERE u.role = 'medecin';
   ```

## Matrice de Distinction

| Type | role | id_patient | id_med | Table Spécifique | Spécialisation |
|------|------|------------|--------|-----------------|----------------|
| 1. Admin | `admin` | NULL | NULL | ❌ | - |
| 2. Médecin MG | `medecin` | NULL | NOT NULL | ✅ MEDECINS | Médecine générale |
| 3. Médecin Chir | `medecin` | NULL | NOT NULL | ✅ MEDECINS | Chirurgie |
| 4. Médecin Mat | `medecin` | NULL | NOT NULL | ✅ MEDECINS | Maternité |
| 5. Médecin Opht | `medecin` | NULL | NOT NULL | ✅ MEDECINS | Ophtalmologie |
| 6. Accueil | `accueil` | NULL | NULL | ❌ | - |
| 7. Patient | `patient` | NOT NULL | NULL | ✅ PATIENTS | - |

## Requêtes SQL Utiles

### Lister tous les types avec leurs informations complètes

```sql
-- Vue complète de tous les utilisateurs
SELECT 
    u.id,
    u.email,
    u.role,
    CASE 
        WHEN u.role = 'admin' THEN 'Administrateur'
        WHEN u.role = 'accueil' THEN 'Accueil'
        WHEN u.role = 'medecin' THEN CONCAT('Médecin - ', m.Spécialisation_med)
        WHEN u.role = 'patient' THEN 'Patient'
    END as type_complet,
    CASE 
        WHEN u.role = 'medecin' THEN m.Matricule_med
        WHEN u.role = 'patient' THEN p.Matricule_patient
        ELSE NULL
    END as matricule,
    u.Date_creation
FROM users u
LEFT JOIN MEDECINS m ON u.id_med = m.id_med
LEFT JOIN PATIENTS p ON u.id_patient = p.id_patient
ORDER BY u.role, u.Date_creation;
```

### Compter par type

```sql
SELECT 
    role,
    CASE 
        WHEN role = 'medecin' THEN 
            (SELECT COUNT(*) FROM MEDECINS m 
             JOIN users u2 ON m.id_med = u2.id_med 
             WHERE u2.role = 'medecin' 
             AND m.Spécialisation_med = 'Médecine générale')
        ELSE COUNT(*)
    END as nombre
FROM users
GROUP BY role;
```

### Vérifier la cohérence

```sql
-- Médecins sans compte users
SELECT m.* FROM MEDECINS m 
LEFT JOIN users u ON m.id_med = u.id_med 
WHERE u.id IS NULL;

-- Patients sans compte users
SELECT p.* FROM PATIENTS p 
LEFT JOIN users u ON p.id_patient = u.id_patient 
WHERE u.id IS NULL;

-- Users avec incohérences
SELECT u.* FROM users u 
WHERE (u.role = 'medecin' AND u.id_med IS NULL)
   OR (u.role = 'patient' AND u.id_patient IS NULL)
   OR (u.role = 'admin' AND (u.id_med IS NOT NULL OR u.id_patient IS NOT NULL))
   OR (u.role = 'accueil' AND (u.id_med IS NOT NULL OR u.id_patient IS NOT NULL));
```
