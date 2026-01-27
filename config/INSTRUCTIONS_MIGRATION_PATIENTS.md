# Instructions : Migration des Patients

## Problème Résolu

**Symptôme** : Lors de la prise de rendez-vous, le système affiche "Le patient n'existe pas" même si le patient est connecté.

**Cause** : Les patients sont dans la table `users` mais **PAS dans la table `PATIENTS`**. La table `PATIENTS` est vide.

**Solution** : Migrer tous les patients de `users` vers `PATIENTS` et créer les liens appropriés.

---

## Comment Utiliser le Script

### Méthode 1 : Via le navigateur (recommandé)

1. Ouvrez votre navigateur
2. Accédez à : `http://localhost/ProjetClinique/config/migrer_patients.php`
3. Le script s'exécutera et affichera les résultats

### Méthode 2 : Via la ligne de commande

```bash
cd C:\xampp_new\htdocs\ProjetClinique\config
php migrer_patients.php
```

---

## Ce que fait le Script

### 1. Récupération des Patients
- ✅ Trouve tous les comptes avec `role = 'patient'` dans `users`

### 2. Migration vers PATIENTS
Pour chaque patient :
- ✅ Vérifie s'il existe déjà dans `PATIENTS` (par email ou id_patient)
- ✅ Si oui : Restaure le lien `id_patient` dans `users`
- ✅ Si non : Crée le patient dans `PATIENTS` avec :
  - Matricule généré automatiquement
  - Nom et prénom séparés
  - Email, téléphone, photo
  - Date de naissance par défaut (1900-01-01)

### 3. Création des Liens
- ✅ Met à jour `users.id_patient` pour pointer vers `PATIENTS.id_patient`
- ✅ S'assure que `users.id_med = NULL` pour les patients

### 4. Gestion des Orphelins
- ✅ Trouve les patients dans `PATIENTS` sans compte `users`
- ✅ Crée les comptes `users` manquants

### 5. Validation
- ✅ Vérifie que tous les patients ont un lien correct
- ✅ Affiche les statistiques finales

---

## Exemple de Sortie

```
=== MIGRATION DES PATIENTS DE users VERS PATIENTS ===

1. Récupération des patients depuis users...
   ✅ 15 patient(s) trouvé(s) dans users

2. Migration des patients vers PATIENTS...

   [1/15] Patient : john.doe@example.com
      🔄 Création du patient dans PATIENTS...
      ✅ Patient créé dans PATIENTS (ID: 1, Matricule: PAT202601240001)
      ✅ Lien créé dans users (id_patient = 1)

   [2/15] Patient : jane.smith@example.com
      ℹ️  Patient existe déjà dans PATIENTS (ID: 2)
      ✅ Lien restauré dans users (id_patient = 2)

   ...

3. Vérification des patients orphelins dans PATIENTS...
   ✅ Aucun patient orphelin

4. Validation finale...
   📊 Statistiques :
      - Patients dans users : 15
      - Patients dans PATIENTS : 15
      - Patients avec liens corrects : 15
      - Patients sans lien : 0
   ✅ Tous les patients ont un lien vers PATIENTS

=== RÉSUMÉ DE LA MIGRATION ===
Migrations effectuées : 12
Déjà migrés : 3
Erreurs : 0

✅ Migration terminée avec succès !
```

---

## Avant d'Exécuter

### ⚠️ IMPORTANT : Faire une Sauvegarde

```sql
-- Via phpMyAdmin ou ligne de commande
mysqldump -u root -p santé1 > backup_avant_migration_patients.sql
```

### Vérifications Préalables

1. ✅ La base de données `santé1` existe
2. ✅ Les tables `users` et `PATIENTS` existent
3. ✅ Vous avez les droits d'écriture

---

## Après l'Exécution

### Vérifier que la Migration a Réussi

```sql
-- Vérifier que tous les patients ont un lien
SELECT COUNT(*) as patients_avec_lien
FROM users u
INNER JOIN PATIENTS p ON u.id_patient = p.id_patient
WHERE u.role = 'patient';

-- Vérifier les patients sans lien (devrait être 0)
SELECT COUNT(*) as patients_sans_lien
FROM users
WHERE role = 'patient' AND id_patient IS NULL;

-- Lister tous les patients migrés
SELECT 
    u.id,
    u.email,
    p.id_patient,
    p.Matricule_patient,
    p.Nom_patient,
    p.Prénom_patient
FROM users u
INNER JOIN PATIENTS p ON u.id_patient = p.id_patient
WHERE u.role = 'patient'
ORDER BY p.id_patient;
```

### Tester la Prise de Rendez-vous

1. Connectez-vous avec un compte patient
2. Essayez de prendre un rendez-vous
3. Le système devrait maintenant trouver le patient dans `PATIENTS`

---

## Problèmes Potentiels

### Problème 1 : Doublons d'emails
**Symptôme** : Erreur "Duplicate entry for key 'Email_patient'"

**Solution** : 
- Le script détecte et gère les doublons
- Si un patient existe déjà avec le même email, il restaure le lien

### Problème 2 : Matricules en double
**Symptôme** : Erreur lors de la génération du matricule

**Solution** : 
- Le script vérifie l'unicité des matricules
- Si un doublon est détecté, il génère un nouveau matricule

### Problème 3 : Transaction annulée
**Symptôme** : "Transaction annulée" dans les résultats

**Solution** : 
- Vérifiez les logs d'erreur
- Le script annule toutes les modifications en cas d'erreur
- Corrigez le problème et réexécutez

---

## Notes Importantes

1. ⚠️ **Mot de passe temporaire** : Les comptes créés automatiquement ont le mot de passe `temp123456`. Changez-les immédiatement !

2. ✅ **Idempotent** : Vous pouvez exécuter le script plusieurs fois sans problème. Il ne créera pas de doublons.

3. ✅ **Sécurisé** : Le script utilise des transactions. Si une erreur survient, toutes les modifications sont annulées.

4. 📝 **Matricules** : Les matricules sont générés automatiquement au format `PAT + YYYYMMDD + 4 chiffres` (ex: `PAT202601240001`)

5. 🔄 **Mise à jour** : Si un patient existe déjà dans `PATIENTS`, le script met à jour ses informations (nom, téléphone, photo) si nécessaire.

---

## Vérification Post-Migration

### Requête de Vérification Complète

```sql
-- Vue d'ensemble complète
SELECT 
    'users' as source,
    COUNT(*) as nombre
FROM users
WHERE role = 'patient'

UNION ALL

SELECT 
    'PATIENTS' as source,
    COUNT(*) as nombre
FROM PATIENTS

UNION ALL

SELECT 
    'Liens corrects' as source,
    COUNT(*) as nombre
FROM users u
INNER JOIN PATIENTS p ON u.id_patient = p.id_patient
WHERE u.role = 'patient';
```

Tous les nombres devraient être identiques après une migration réussie.

---

## Support

Si vous rencontrez des problèmes :
1. Vérifiez les logs d'erreur PHP
2. Vérifiez les logs MySQL
3. Consultez `config/README_7_TYPES_UTILISATEURS.md` pour comprendre la structure
