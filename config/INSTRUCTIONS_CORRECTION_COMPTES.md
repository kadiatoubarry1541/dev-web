# Instructions : Correction et Réorganisation des Comptes

## Objectif

Ce script vérifie et corrige **tous les comptes** dans la base de données pour s'assurer que chaque compte est dans sa table appropriée :

- **Admin** : uniquement dans `users` (role = 'admin')
- **Médecins** : dans `users` (role = 'medecin') **ET** dans `MEDECINS`
- **Accueil** : uniquement dans `users` (role = 'accueil')
- **Patients** : dans `users` (role = 'patient') **ET** dans `PATIENTS`

## Comment Utiliser le Script

### Méthode 1 : Via le navigateur (recommandé)

1. Ouvrez votre navigateur
2. Accédez à : `http://localhost/ProjetClinique/config/corriger_comptes.php`
3. Le script s'exécutera et affichera les résultats

### Méthode 2 : Via la ligne de commande

```bash
cd C:\xampp_new\htdocs\ProjetClinique\config
php corriger_comptes.php
```

## Ce que fait le Script

### 1. Vérification des Médecins
- ✅ Trouve les médecins dans `users` sans lien vers `MEDECINS`
- ✅ Trouve les médecins dans `MEDECINS` sans compte `users`
- ✅ Restaure les liens manquants
- ✅ Convertit en patient si le médecin n'existe pas dans `MEDECINS`

### 2. Vérification des Patients
- ✅ Trouve les patients dans `users` sans lien vers `PATIENTS`
- ✅ Trouve les patients dans `PATIENTS` sans compte `users`
- ✅ Crée les enregistrements manquants
- ✅ Restaure les liens

### 3. Correction des Rôles
- ✅ Corrige les rôles NULL ou invalides → `'patient'`
- ✅ Convertit les comptes orphelins en patients

### 4. Correction Admin/Accueil
- ✅ Supprime les liens inappropriés (id_patient, id_med) pour admin et accueil

### 5. Conversion Automatique
- ✅ Tout compte qui n'est pas admin, médecin ou accueil → **patient**

## Exemple de Sortie

```
=== CORRECTION ET RÉORGANISATION DES COMPTES ===

1. Vérification des médecins...
   ⚠️  Médecin orphelin trouvé : medecin@example.com (ID: 5)
   ✅ Lien restauré avec MEDECINS.id_med = 3

2. Vérification des patients...
   ⚠️  Patient orphelin trouvé : patient@example.com (ID: 10)
   ✅ Patient créé dans PATIENTS (ID: 15, Matricule: PAT202601240001)

3. Vérification des rôles...
   ⚠️  Compte avec rôle invalide : user@example.com (Rôle: NULL)
   ✅ Rôle corrigé à 'patient' et patient créé (Matricule: PAT202601240002)

4. Vérification des comptes admin et accueil...
   ✅ Aucun problème détecté

5. Conversion des comptes orphelins en patients...
   ✅ Aucun compte orphelin

6. Validation finale...
   ✅ Aucune incohérence détectée

=== RÉSUMÉ ===
Corrections effectuées : 3

Détails des corrections :
  1. Médecin medecin@example.com : Lien restauré
  2. Patient patient@example.com : Créé dans PATIENTS
  3. Compte user@example.com : Rôle corrigé à 'patient'

=== STATISTIQUES FINALES ===
  admin : 1
  medecin : 5
  accueil : 1
  patient : 25

✅ Correction terminée avec succès !
```

## Avant d'Exécuter le Script

### ⚠️ IMPORTANT : Faire une Sauvegarde

Avant d'exécuter le script, **faites une sauvegarde de votre base de données** :

```sql
-- Via phpMyAdmin ou ligne de commande
mysqldump -u root -p santé1 > backup_avant_correction.sql
```

### Vérifications Préalables

1. ✅ La base de données `santé1` existe
2. ✅ Les tables `users`, `PATIENTS`, `MEDECINS` existent
3. ✅ Vous avez les droits d'écriture sur la base de données

## Après l'Exécution

### Vérifier les Résultats

```sql
-- Vérifier qu'il n'y a plus d'incohérences
SELECT 
    role,
    COUNT(*) as nombre,
    SUM(CASE WHEN role = 'medecin' AND id_med IS NULL THEN 1 ELSE 0 END) as medecins_sans_lien,
    SUM(CASE WHEN role = 'patient' AND id_patient IS NULL THEN 1 ELSE 0 END) as patients_sans_lien
FROM users
GROUP BY role;
```

### Vérifier les Comptes par Type

```sql
-- Admin
SELECT COUNT(*) FROM users WHERE role = 'admin';

-- Médecins (avec lien)
SELECT COUNT(*) FROM users u 
INNER JOIN MEDECINS m ON u.id_med = m.id_med 
WHERE u.role = 'medecin';

-- Accueil
SELECT COUNT(*) FROM users WHERE role = 'accueil';

-- Patients (avec lien)
SELECT COUNT(*) FROM users u 
INNER JOIN PATIENTS p ON u.id_patient = p.id_patient 
WHERE u.role = 'patient';
```

## Problèmes Potentiels et Solutions

### Problème 1 : Erreur "Table doesn't exist"
**Solution** : Exécutez d'abord `install.php` pour créer les tables

### Problème 2 : Erreur de permissions
**Solution** : Vérifiez que l'utilisateur MySQL a les droits d'écriture

### Problème 3 : Doublons d'emails
**Solution** : Le script détecte et signale les doublons. Corrigez-les manuellement avant de réexécuter

### Problème 4 : Transaction annulée
**Solution** : Vérifiez les logs d'erreur. Le script annule toutes les modifications en cas d'erreur pour préserver l'intégrité

## Sécurité

- ✅ Le script utilise des **transactions** : si une erreur survient, toutes les modifications sont annulées
- ✅ **Aucune suppression** : le script ne supprime jamais de données, seulement des corrections
- ✅ **Vérifications multiples** : chaque correction est vérifiée avant d'être appliquée

## Fréquence d'Exécution

- **Première fois** : Exécutez le script pour corriger les comptes existants
- **Après** : Exécutez-le périodiquement (mensuellement) ou après des modifications importantes
- **En cas de problème** : Exécutez-le pour diagnostiquer et corriger

## Notes Importantes

1. ⚠️ **Mot de passe temporaire** : Les comptes créés automatiquement ont le mot de passe `temp123456`. Changez-les immédiatement !

2. ⚠️ **Matricules** : Les patients créés automatiquement reçoivent un matricule généré. Vérifiez qu'ils sont corrects.

3. ✅ **Idempotent** : Vous pouvez exécuter le script plusieurs fois sans problème. Il ne fera que corriger ce qui doit l'être.

4. ✅ **Logs** : Toutes les corrections sont affichées dans la sortie du script.

## Support

Si vous rencontrez des problèmes :
1. Vérifiez les logs d'erreur PHP
2. Vérifiez les logs MySQL
3. Consultez la documentation dans `config/README_7_TYPES_UTILISATEURS.md`
