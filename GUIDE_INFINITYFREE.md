# 🚀 Guide Complet : Héberger sur InfinityFree

Guide étape par étape pour héberger votre projet ProjetClinique sur InfinityFree.

---

## 📋 Prérequis

- ✅ Votre projet fonctionne en local (XAMPP)
- ✅ Une adresse email valide
- ✅ Votre base de données exportée (`export_for_render.sql` ou similaire)

---

## 🎯 Étape 1 : Créer un compte sur InfinityFree

### 1.1 Aller sur le site

1. Ouvrez votre navigateur
2. Allez sur [https://www.infinityfree.net](https://www.infinityfree.net)
3. Cliquez sur **"Sign Up"** ou **"Create Account"** (en haut à droite)

### 1.2 Remplir le formulaire

1. **Username** : Choisissez un nom d'utilisateur (ex: `projetclinique`)
2. **Email** : Entrez votre adresse email valide
3. **Password** : Créez un mot de passe fort
4. **Confirm Password** : Confirmez le mot de passe
5. Acceptez les conditions d'utilisation
6. Cliquez sur **"Create Account"**

### 1.3 Vérifier votre email

1. Allez dans votre boîte email
2. Ouvrez l'email de confirmation d'InfinityFree
3. Cliquez sur le lien de vérification
4. Votre compte est maintenant activé !

---

## 🎯 Étape 2 : Créer votre site web

### 2.1 Se connecter au panel

1. Allez sur [https://www.infinityfree.net](https://www.infinityfree.net)
2. Cliquez sur **"Login"**
3. Entrez votre nom d'utilisateur et mot de passe
4. Cliquez sur **"Login"**

### 2.2 Créer un nouveau compte d'hébergement

1. Dans le panel, cliquez sur **"Create Account"** ou **"Add Website"**
2. Cliquez sur **"Create Free Account"**

### 2.3 Configurer votre site

Remplissez le formulaire :

- **Domain** : Choisissez un nom (ex: `projetclinique`)
- **Domain Extension** : Choisissez `.infinityfreeapp.com` (gratuit)
- **Site Name** : `ProjetClinique` (ou votre choix)
- **Email** : Votre email (déjà rempli)
- **Password** : Créez un mot de passe pour FTP/cPanel

### 2.4 Créer le site

1. Cliquez sur **"Create Account"**
2. Attendez quelques secondes
3. **Félicitations !** Votre site est créé

### 2.5 Noter vos informations

Vous recevrez un email avec :
- **Votre lien** : `https://projetclinique.infinityfreeapp.com`
- **Identifiants FTP**
- **Identifiants cPanel**

⚠️ **SAUVEGARDEZ CES INFORMATIONS !**

---

## 🎯 Étape 3 : Accéder à cPanel

### 3.1 Se connecter à cPanel

1. Dans le panel InfinityFree, cliquez sur **"Manage"** à côté de votre site
2. Cliquez sur **"Login to cPanel"**
3. Ou allez directement sur : `https://projetclinique.infinityfreeapp.com:2083`
4. Connectez-vous avec vos identifiants cPanel

### 3.2 Explorer cPanel

Vous verrez plusieurs sections :
- **Files** : Gestionnaire de fichiers
- **Databases** : Bases de données MySQL
- **Software** : Installateurs (Softaculous, etc.)

---

## 🎯 Étape 4 : Créer la base de données MySQL

### 4.1 Créer la base de données

1. Dans cPanel, trouvez la section **"Databases"**
2. Cliquez sur **"MySQL Databases"**
3. Dans **"Create New Database"** :
   - Nom : `projetclinique_db` (ou votre choix)
   - Cliquez sur **"Create Database"**

### 4.2 Créer un utilisateur MySQL

1. Dans la même page, allez à **"MySQL Users"**
2. Remplissez :
   - **Username** : `projetclinique_user` (ou votre choix)
   - **Password** : Créez un mot de passe fort
   - Cliquez sur **"Create User"**

⚠️ **NOTEZ LE MOT DE PASSE !**

### 4.3 Assigner l'utilisateur à la base

1. Dans **"Add User To Database"** :
   - Sélectionnez l'utilisateur : `projetclinique_user`
   - Sélectionnez la base : `projetclinique_db`
   - Cliquez sur **"Add"**
2. Cochez **"ALL PRIVILEGES"**
3. Cliquez sur **"Make Changes"**

### 4.4 Noter les informations de connexion

Vous verrez maintenant :
- **Database Name** : `votrenom_projetclinique_db`
- **Username** : `votrenom_projetclinique_user`
- **Host** : `localhost` (ou l'host fourni)
- **Password** : (celui que vous avez créé)

⚠️ **SAUVEGARDEZ CES INFORMATIONS !**

---

## 🎯 Étape 5 : Uploader vos fichiers

### Option A : Via cPanel File Manager (RECOMMANDÉ)

#### 5.1 Accéder au File Manager

1. Dans cPanel, cliquez sur **"File Manager"**
2. Sélectionnez **"public_html"** comme répertoire de base
3. Cliquez sur **"Go"**

#### 5.2 Uploader vos fichiers

1. Cliquez sur **"Upload"** en haut
2. Glissez-déposez tous vos fichiers du projet
   - Ou cliquez sur **"Select Files"** et choisissez vos fichiers
3. Attendez que l'upload se termine
4. Retournez au File Manager

#### 5.3 Vérifier la structure

Vos fichiers doivent être dans `public_html` :
```
public_html/
  ├── index.php
  ├── login.php
  ├── config/
  ├── assets/
  ├── uploads/
  └── ...
```

### Option B : Via FTP

#### 5.1 Configurer un client FTP

1. Téléchargez **FileZilla** (gratuit) : [filezilla-project.org](https://filezilla-project.org)
2. Installez FileZilla

#### 5.2 Se connecter via FTP

1. Ouvrez FileZilla
2. Dans **"Fichier"** → **"Gestionnaire de sites"**
3. Cliquez sur **"Nouveau site"**
4. Remplissez :
   - **Hôte** : `ftpupload.net` (ou l'host FTP fourni)
   - **Protocole** : FTP - File Transfer Protocol
   - **Type de connexion** : Normal
   - **Identifiant** : Votre identifiant FTP
   - **Mot de passe** : Votre mot de passe FTP
5. Cliquez sur **"Connexion"**

#### 5.3 Uploader vos fichiers

1. Dans FileZilla, naviguez vers `public_html` (à droite)
2. À gauche, naviguez vers votre projet local
3. Sélectionnez tous les fichiers
4. Glissez-déposez vers `public_html`
5. Attendez que l'upload se termine

---

## 🎯 Étape 6 : Configurer votre application

### 6.1 Modifier config/bdd.php

Vous devez adapter `config/bdd.php` pour InfinityFree.

**Option 1 : Modifier directement le fichier**

Ouvrez `config/bdd.php` et modifiez :

```php
function bdd() {
    // Pour InfinityFree
    $server = 'localhost'; // ou l'host fourni par InfinityFree
    $dbname = 'votrenom_projetclinique_db'; // Nom complet de la base
    $port = '3306';
    $username = 'votrenom_projetclinique_user'; // Nom complet de l'utilisateur
    $password = 'VotreMotDePasse'; // Le mot de passe que vous avez créé
    
    try {
        $pdo = new PDO("mysql:host=$server;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        throw new PDOException('La connexion à la base de données a échoué : ' . $e->getMessage());
    }
}
```

**Option 2 : Créer un fichier de configuration séparé**

Créez `config/bdd_infinityfree.php` :

```php
<?php
// Configuration pour InfinityFree
define('DB_HOST', 'localhost');
define('DB_NAME', 'votrenom_projetclinique_db');
define('DB_USER', 'votrenom_projetclinique_user');
define('DB_PASS', 'VotreMotDePasse');
define('DB_PORT', '3306');
?>
```

Puis modifiez `config/bdd.php` pour utiliser ces constantes.

### 6.2 Uploader le fichier modifié

1. Sauvegardez `config/bdd.php` modifié
2. Uploadez-le via File Manager ou FTP
3. Remplacez l'ancien fichier

---

## 🎯 Étape 7 : Importer votre base de données

### Option A : Via phpMyAdmin (RECOMMANDÉ)

#### 7.1 Accéder à phpMyAdmin

1. Dans cPanel, trouvez **"Databases"**
2. Cliquez sur **"phpMyAdmin"**
3. Sélectionnez votre base de données dans la liste de gauche

#### 7.2 Importer le fichier SQL

1. Cliquez sur l'onglet **"Import"** en haut
2. Cliquez sur **"Choose File"**
3. Sélectionnez votre fichier `export_for_render.sql` (ou votre export local)
4. Vérifiez que **"SQL"** est sélectionné comme format
5. Cliquez sur **"Go"** ou **"Importer"** en bas
6. Attendez que l'import se termine

#### 7.3 Vérifier l'import

1. Cliquez sur l'onglet **"Structure"**
2. Vous devriez voir toutes vos tables :
   - `users`
   - `PATIENTS`
   - `MEDECINS`
   - `RENDEZ_VOUS`
   - etc.

### Option B : Via script PHP

Si l'import via phpMyAdmin ne fonctionne pas, créez `import_infinityfree.php` :

```php
<?php
require_once __DIR__ . '/config/bdd.php';

$sqlFile = __DIR__ . '/export_for_render.sql';

if (!file_exists($sqlFile)) {
    die("Fichier SQL non trouvé : $sqlFile");
}

try {
    $pdo = bdd();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = file_get_contents($sqlFile);
    $sql = str_replace(["\r\n", "\r"], "\n", $sql);
    
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    
    $statements = array_filter(
        array_map('trim', explode(";\n", $sql)),
        function ($s) { return $s !== '' && strpos($s, '--') !== 0; }
    );
    
    foreach ($statements as $stmt) {
        $stmt = preg_replace('/^--.*$/m', '', $stmt);
        $stmt = trim($stmt);
        if ($stmt === '' || $stmt === 'SET NAMES utf8mb4' || 
            $stmt === 'SET FOREIGN_KEY_CHECKS=0' || 
            $stmt === 'SET FOREIGN_KEY_CHECKS=1') {
            continue;
        }
        if (preg_match('/^SET\s+/i', $stmt)) {
            $pdo->exec($stmt);
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') === false && 
                strpos($e->getMessage(), 'already exists') === false) {
                echo "Erreur : " . $e->getMessage() . "<br>";
            }
        }
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    
    echo "Import terminé avec succès !";
    echo "<br><a href='login.php'>Aller à la connexion</a>";
    
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
?>
```

1. Uploadez ce fichier à la racine
2. Uploadez aussi `export_for_render.sql`
3. Accédez à : `https://projetclinique.infinityfreeapp.com/import_infinityfree.php`
4. Attendez que l'import se termine
5. **SUPPRIMEZ** le fichier `import_infinityfree.php` après l'import (sécurité)

---

## 🎯 Étape 8 : Configurer les permissions

### 8.1 Permissions pour le dossier uploads

1. Dans cPanel File Manager, allez dans `public_html/uploads`
2. Clic droit sur `uploads` → **"Change Permissions"**
3. Cochez :
   - **Read** : Owner, Group, Public
   - **Write** : Owner
   - **Execute** : Owner, Group, Public
4. Valeur numérique : `755`
5. Cliquez sur **"Change Permissions"**

Répétez pour `uploads/profiles` et `uploads/reçus`.

---

## 🎯 Étape 9 : Tester votre site

### 9.1 Tester la page d'accueil

1. Ouvrez votre navigateur
2. Allez sur : `https://projetclinique.infinityfreeapp.com`
3. Vous devriez voir votre page d'accueil

### 9.2 Tester la connexion

1. Allez sur : `https://projetclinique.infinityfreeapp.com/login.php`
2. Essayez de vous connecter avec un compte existant
3. Si ça fonctionne, ✅ **Tout est bon !**

### 9.3 Vérifier les erreurs

Si vous voyez des erreurs :

1. Vérifiez les logs dans cPanel → **"Errors"**
2. Vérifiez que `config/bdd.php` a les bons identifiants
3. Vérifiez que la base de données est bien importée
4. Vérifiez les permissions des dossiers

---

## 🔧 Dépannage

### Problème : Erreur de connexion à la base de données

**Solution :**
1. Vérifiez que le nom de la base est complet : `votrenom_projetclinique_db`
2. Vérifiez que le nom d'utilisateur est complet : `votrenom_projetclinique_user`
3. Vérifiez le mot de passe
4. Vérifiez que l'utilisateur est assigné à la base

### Problème : Page blanche

**Solution :**
1. Activez l'affichage des erreurs dans `config/bdd.php`
2. Vérifiez les logs dans cPanel → **"Errors"**
3. Vérifiez que tous les fichiers sont uploadés

### Problème : Upload d'images ne fonctionne pas

**Solution :**
1. Vérifiez les permissions du dossier `uploads` (doit être 755)
2. Vérifiez que le dossier existe
3. Vérifiez les logs d'erreur PHP

### Problème : SSL/HTTPS ne fonctionne pas

**Solution :**
1. Dans cPanel, allez dans **"SSL/TLS Status"**
2. Activez SSL pour votre domaine
3. Attendez quelques minutes
4. Testez avec `https://` au lieu de `http://`

---

## 📝 Checklist Finale

- [ ] Compte InfinityFree créé
- [ ] Site créé (lien obtenu)
- [ ] Base MySQL créée
- [ ] Utilisateur MySQL créé et assigné
- [ ] Fichiers uploadés (via File Manager ou FTP)
- [ ] `config/bdd.php` modifié avec les bons identifiants
- [ ] Base de données importée (via phpMyAdmin)
- [ ] Permissions configurées (dossier uploads)
- [ ] Site testé et fonctionnel
- [ ] Connexion testée avec succès

---

## 🎉 Félicitations !

Votre site est maintenant hébergé sur InfinityFree !

**Votre lien** : `https://projetclinique.infinityfreeapp.com`

Vous pouvez maintenant :
- ✅ Partager ce lien avec vos utilisateurs
- ✅ Tester toutes les fonctionnalités
- ✅ Utiliser votre application en ligne

---

## 🆘 Besoin d'aide ?

Si vous rencontrez des problèmes :

1. Consultez les logs dans cPanel → **"Errors"**
2. Vérifiez la documentation InfinityFree
3. Contactez le support communautaire InfinityFree

---

## 💡 Astuces

- **Domaine personnalisé** : Vous pouvez ajouter votre propre domaine plus tard
- **Sauvegardes** : Faites régulièrement des sauvegardes de votre base via phpMyAdmin
- **Performance** : InfinityFree peut être un peu lent au début, c'est normal

Bon hébergement ! 🚀
