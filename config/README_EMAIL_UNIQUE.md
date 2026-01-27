# Protection contre les Emails Dupliqués

## Système de Protection Multi-Niveaux

Le système garantit qu'**aucun email ne peut être dupliqué** dans la base de données grâce à plusieurs niveaux de protection :

### 1. Contraintes UNIQUE au niveau de la Base de Données

Toutes les tables qui contiennent des emails ont une contrainte UNIQUE :

- **Table `users`** : `email VARCHAR(100) UNIQUE NOT NULL`
- **Table `PATIENTS`** : `Email_patient VARCHAR(100) UNIQUE` avec index `idx_email_patient`
- **Table `MEDECINS`** : `Email_med VARCHAR(100) UNIQUE` avec index `idx_email_med`

Ces contraintes empêchent MySQL d'insérer des emails en double, même si le code PHP a un bug.

### 2. Vérification Avant l'Inscription

La fonction `EmailExist()` vérifie l'email dans **toutes les tables** :
- Table `users`
- Table `PATIENTS`
- Table `MEDECINS`

Si l'email existe dans n'importe quelle table, l'inscription est refusée.

### 3. Double Vérification dans la Fonction `inscription()`

Avant de créer un patient ou un médecin, la fonction vérifie :
- Si c'est un patient : vérifie que l'email n'existe pas dans `PATIENTS`
- Si c'est un médecin : vérifie que l'email n'existe pas dans `MEDECINS`
- Avant de créer l'utilisateur : vérifie une dernière fois dans `users`

### 4. Gestion des Erreurs de Duplication

Si malgré tout une tentative de duplication se produit (race condition, etc.), le système :
- Capture l'erreur PDO avec le code 23000 (violation de contrainte UNIQUE)
- Détecte les messages contenant "Duplicate entry" ou "UNIQUE constraint"
- Affiche un message d'erreur clair à l'utilisateur

## Utilisation

### Pour vérifier si un email existe :

```php
require_once 'config/traitement.php';

if (EmailExist('email@example.com')) {
    echo "Cet email est déjà utilisé";
} else {
    echo "Cet email est disponible";
}
```

### Messages d'Erreur

- **Si l'email existe dans users** : "Cet email existe déjà. Veuillez vous connecter ou utiliser un autre email."
- **Si l'email existe dans PATIENTS** : "Cet email est déjà utilisé par un patient."
- **Si l'email existe dans MEDECINS** : "Cet email est déjà utilisé par un médecin."
- **Si erreur de base de données** : "Cet email est déjà utilisé dans notre système. Veuillez utiliser un autre email ou vous connecter."

## Mise à Jour des Tables Existantes

Si vous avez déjà des tables créées sans les contraintes UNIQUE, exécutez :

1. Via `install.php` : Les contraintes seront ajoutées automatiquement
2. Via le script SQL : `config/add_unique_email_constraints.sql`
3. Via phpMyAdmin : Importez le fichier `add_unique_email_constraints.sql`

## Sécurité

✅ **Protection au niveau base de données** : Impossible d'insérer un email en double même avec un accès direct à MySQL

✅ **Protection au niveau application** : Vérifications multiples avant insertion

✅ **Messages clairs** : L'utilisateur sait exactement pourquoi son inscription a échoué

✅ **Gestion des erreurs** : Capture et gestion appropriée des erreurs de duplication
