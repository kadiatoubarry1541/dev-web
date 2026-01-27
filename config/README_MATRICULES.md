# Système de Gestion des Matricules

## Vue d'ensemble

Le système de gestion des matricules a été modifié pour permettre un contrôle total par l'administrateur. Les matricules ne sont plus générés automatiquement lors de l'inscription, mais sont attribués par l'administrateur après validation.

## Fonctionnement

### Pour les Patients

1. **Lors de l'inscription** :
   - Le patient s'inscrit via `register-patient.php`
   - **Le matricule est généré AUTOMATIQUEMENT** lors de l'inscription
   - Format : `PAT + YYYYMMDD + 4 chiffres séquentiels` (ex: `PAT202601230001`)
   - Le matricule est affiché immédiatement dans le message de succès
   - **C'est très important** : le patient reçoit son matricule dès l'inscription

2. **Attribution manuelle (pour anciens patients uniquement)** :
   - L'administrateur peut accéder à la page `admin/attribuer-matricules.php`
   - Cette page affiche uniquement les anciens patients créés avant cette modification
   - Les nouveaux patients ont déjà leur matricule automatiquement

### Pour les Médecins

1. **Lors de l'inscription** :
   - Le médecin s'inscrit via `register-medecin.php`
   - Aucun matricule n'est généré automatiquement
   - Le médecin est créé avec `Matricule_med = NULL` et `statut = 'en_attente'`
   - Un message indique que le matricule sera attribué lors de l'approbation

2. **Lors de l'approbation** :
   - L'administrateur approuve le médecin via `admin/approuver-medecins.php`
   - Le matricule est **automatiquement attribué** lors de l'approbation
   - Format: `MED + YYYYMMDD + 4 chiffres` (ex: `MED202601230001`)

3. **Création directe par l'admin** :
   - Si l'admin crée un médecin via `admin/ajouter-medecin.php`
   - Le matricule est **automatiquement attribué** lors de la création

## Modification de la Base de Données

Avant d'utiliser le système, vous devez exécuter le script SQL pour modifier la structure des tables :

```sql
-- Exécuter le fichier : config/modify_matricule_structure.sql
```

Ce script modifie les colonnes `Matricule_patient` et `Matricule_med` pour permettre `NULL` temporairement.

## Fonctions Disponibles

### `genererMatriculePatient()`
Génère un matricule unique pour un patient au format `PAT + YYYYMMDD + numéro séquentiel`.

### `genererMatriculeMedecin()`
Génère un matricule unique pour un médecin au format `MED + YYYYMMDD + numéro séquentiel`.

### `attribuerMatriculePatient($id_patient, $matricule = null)`
Attribue un matricule à un patient. Si `$matricule` est `null`, un matricule est généré automatiquement.

### `attribuerMatriculeMedecin($id_med, $matricule = null)`
Attribue un matricule à un médecin. Si `$matricule` est `null`, un matricule est généré automatiquement.

## Pages Administrateur

### `admin/attribuer-matricules.php`
Page principale pour gérer les matricules :
- Liste tous les patients sans matricule
- Liste tous les médecins sans matricule
- Permet d'attribuer des matricules (automatique ou personnalisé)
- Affiche un badge avec le nombre d'utilisateurs sans matricule dans le menu

### `admin/approuver-medecins.php`
Lors de l'approbation d'un médecin, le matricule est automatiquement attribué.

## Format des Matricules

- **Patients** : `PAT` + `YYYYMMDD` + `4 chiffres séquentiels`
  - Exemple : `PAT202601230001`, `PAT202601230002`, etc.

- **Médecins** : `MED` + `YYYYMMDD` + `4 chiffres séquentiels`
  - Exemple : `MED202601230001`, `MED202601230002`, etc.

## Sécurité

- Les matricules sont uniques (contrainte UNIQUE dans la base de données)
- L'administrateur peut vérifier l'unicité avant d'attribuer un matricule personnalisé
- Les fonctions de génération vérifient automatiquement l'unicité

## Notes Importantes

1. **Exécuter le script SQL** : N'oubliez pas d'exécuter `config/modify_matricule_structure.sql` avant d'utiliser le système.

2. **Patients** : 
   - **Les nouveaux patients reçoivent automatiquement leur matricule lors de l'inscription** (très important)
   - Le matricule est affiché immédiatement dans le message de succès
   - Les anciens patients sans matricule peuvent être gérés via la page admin

3. **Médecins** : 
   - Les médecins sont toujours contrôlés par l'administrateur
   - Le matricule est attribué automatiquement lors de l'approbation par l'admin
   - Les médecins créés directement par l'admin reçoivent aussi leur matricule automatiquement

4. **Messages utilisateur** : 
   - Les patients voient leur matricule immédiatement après l'inscription
   - Les médecins sont informés que leur matricule sera attribué lors de l'approbation
