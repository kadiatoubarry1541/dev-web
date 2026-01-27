# Guide de Débogage - Prise de Rendez-vous

## ✅ Vérifications Effectuées

### 1. Base de Données
- **Base utilisée** : `santé1`
- **Table PATIENTS** : ✅ Existe
- **Patient test** : ✅ Existe (ID: 6, Matricule: PAT202601240004)

### 2. Modifications Apportées
- ✅ Recherche insensible à la casse (UPPER/TRIM)
- ✅ Nettoyage des espaces dans les matricules
- ✅ Logs de débogage détaillés
- ✅ Vérification systématique dans la table PATIENTS

## 🔍 Comment Déboguer

### Étape 1 : Vérifier les Logs PHP
Les logs PHP contiennent toutes les informations de débogage. Localisez-les :
- **Windows XAMPP** : `C:\xampp_new\php\logs\php_error_log`
- **Ou** : `C:\xampp_new\apache\logs\error.log`

Recherchez les lignes contenant :
- `🔍 RECHERCHE PATIENT`
- `✅ Patient trouvé`
- `❌ Patient NON trouvé`

### Étape 2 : Tester avec le Script de Test
Ouvrez dans votre navigateur :
```
http://localhost/ProjetClinique/test-patient-db.php
```

Ce script affiche :
- Tous les patients dans la base
- Si le patient existe
- La structure de la table

### Étape 3 : Vérifier le Formulaire
1. Ouvrez `rendez-vous.php` dans votre navigateur
2. Remplissez le formulaire avec :
   - **Nom** : sorry bah
   - **Matricule** : PAT202601240004
   - **Service** : Sélectionnez un service
   - **Date/Heure** : Format jj/mm/aaaa hh:mm (ex: 25/01/2026 14:30)

### Étape 4 : Vérifier les Erreurs
Si vous voyez une erreur, notez :
- Le message d'erreur exact
- À quelle étape ça bloque (validation, recherche patient, création RDV)
- Les informations affichées dans les logs

## 🐛 Problèmes Courants et Solutions

### Problème 1 : "Patient non trouvé"
**Cause possible** : Matricule avec espaces ou casse différente
**Solution** : Le code nettoie maintenant automatiquement les matricules

### Problème 2 : "Aucun médecin disponible"
**Cause possible** : Aucun médecin approuvé dans le service sélectionné
**Solution** : Vérifiez dans la base de données qu'il y a des médecins avec `statut = 'approuvé'` pour ce service

### Problème 3 : "Erreur de contrainte de clé étrangère"
**Cause possible** : Le patient ou le médecin n'existe pas vraiment
**Solution** : Utilisez `test-patient-db.php` pour vérifier

### Problème 4 : Erreur de format de date
**Cause possible** : Format de date incorrect
**Solution** : Utilisez le format `jj/mm/aaaa hh:mm` (ex: 25/01/2026 14:30)

## 📋 Checklist de Vérification

Avant de créer un rendez-vous, vérifiez :

- [ ] Le patient existe dans la table PATIENTS (utilisez `test-patient-db.php`)
- [ ] Le matricule est exact (sans espaces, casse correcte)
- [ ] Il y a au moins un service dans la base de données
- [ ] Il y a au moins un médecin approuvé pour le service choisi
- [ ] Le format de date est correct (jj/mm/aaaa hh:mm)
- [ ] MySQL/XAMPP est démarré
- [ ] La base de données `santé1` existe

## 🔧 Commandes Utiles

### Vérifier la connexion à la base
```php
<?php
require_once 'config/bdd.php';
$pdo = bdd();
$db = $pdo->query("SELECT DATABASE()")->fetchColumn();
echo "Base de données : " . $db;
?>
```

### Compter les patients
```sql
SELECT COUNT(*) FROM PATIENTS;
```

### Vérifier les médecins approuvés
```sql
SELECT * FROM MEDECINS WHERE statut = 'approuvé';
```

### Vérifier les services
```sql
SELECT * FROM SERVICES;
```

## 📞 Si le Problème Persiste

1. **Copiez le message d'erreur exact**
2. **Vérifiez les logs PHP** (dernières 50 lignes)
3. **Testez avec `test-patient-db.php`**
4. **Notez** :
   - Le matricule utilisé
   - Le service sélectionné
   - Le message d'erreur complet

## ✅ Test Rapide

Pour tester rapidement si tout fonctionne :

1. Allez sur `rendez-vous.php`
2. Connectez-vous en tant que patient (matricule: PAT202601240004)
3. Remplissez le formulaire :
   - Nom : sorry bah
   - Matricule : PAT202601240004
   - Service : Choisissez un service
   - Date : 25/01/2026 14:30
4. Soumettez le formulaire

Si ça fonctionne, vous verrez : "✅ Votre demande de rendez-vous a été envoyée avec succès !"
