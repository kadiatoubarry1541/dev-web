# Déploiement ProjetClinique : GitHub + Render + Base de données

Ce guide explique comment envoyer le projet sur **GitHub** puis le déployer sur **Render** avec la base de données MySQL.

---

## Étape 1 : Pousser le projet sur GitHub

### 1.1 Créer un dépôt sur GitHub

1. Allez sur [github.com](https://github.com) et connectez-vous.
2. Cliquez sur **"New repository"** (ou **"+"** → **"New repository"**).
3. Donnez un nom, par exemple : **ProjetClinique**.
4. Choisissez **Public** (ou Privé si vous préférez).
5. Ne cochez pas "Add a README" si le projet existe déjà en local.
6. Cliquez sur **"Create repository"**.

### 1.2 Préparer et pousser le code (en ligne de commande)

Ouvrez un terminal dans le dossier du projet (`C:\xampp_new\htdocs\ProjetClinique`) et exécutez :

```bash
# Initialiser Git si ce n’est pas déjà fait
git init

# Ajouter tous les fichiers (le .gitignore exclut déjà .env, .idea, etc.)
git add .

# Premier commit
git commit -m "Initial commit - ProjetClinique avec base de données"

# Remplacer VOTRE_UTILISATEUR et VOTRE_REPO par vos vraies valeurs
git remote add origin https://github.com/VOTRE_UTILISATEUR/VOTRE_REPO.git

# Pousser sur la branche main
git branch -M main
git push -u origin main
```

**Exemple concret** si votre compte GitHub est `dupont` et le repo `ProjetClinique` :

```bash
git remote add origin https://github.com/dupont/ProjetClinique.git
git push -u origin main
```

---

## Étape 2 : Créer la base MySQL sur Render

Render ne propose pas MySQL comme base managée (contrairement à PostgreSQL). Il faut déployer MySQL via Docker.

### 2.1 Créer une base MySQL avec le template Render

1. Allez sur [github.com/render-examples/mysql](https://github.com/render-examples/mysql).
2. Cliquez sur **"Use this template"** → **"Create a new repository"**.
3. Donnez un nom, par exemple **projetclinique-mysql**, puis créez le repo.
4. Sur [dashboard.render.com](https://dashboard.render.com), cliquez **"New +"** → **"Private Service"**.
5. Connectez votre compte GitHub si besoin, puis choisissez le repo **projetclinique-mysql**.
6. Configurez :
   - **Name** : `projetclinique-mysql`
   - **Runtime** : **Docker**
   - **Region** : même que l’app (ex. Frankfurt).
7. Dans **Environment**, ajoutez :
   - `MYSQL_DATABASE` = `sante1`
   - `MYSQL_USER` = un nom d’utilisateur (ex. `appuser`)
   - `MYSQL_PASSWORD` = un mot de passe fort (à sauvegarder)
   - `MYSQL_ROOT_PASSWORD` = un autre mot de passe fort
8. Dans **Disks**, ajoutez un disque :
   - **Mount Path** : `/var/lib/mysql`
   - **Size** : au moins **10 GB**
9. Créez le service. Une fois démarré, notez le **hostname interne** (ex. `projetclinique-mysql`) : c’est votre **MYSQL_HOST** pour l’app PHP.

### 2.2 Importer le schéma et les données

1. Dans le dashboard Render, ouvrez le service MySQL.
2. Utilisez la section **"Shell"** (ou un client MySQL externe si vous avez l’URL externe).
3. Ou bien : créez un **one-off job** / script qui importe le fichier SQL.

**Méthode simple** : depuis votre machine, si vous avez l’URL MySQL externe fournie par Render :

```bash
mysql -h VOTRE_HOST_RENDER -P 3306 -u appuser -p sante1 < config/database_render.sql
```

Sinon, copiez le contenu de `config/database_render.sql` et exécutez-le dans le Shell MySQL de Render (ou via un outil comme MySQL Workbench, DBeaver, etc.).

Le fichier **`config/database_render.sql`** contient déjà :
- toutes les tables (PATIENTS, MEDECINS, PAIEMENT, etc.) ;
- Orange Money et `chemin_reçu` ;
- la table NOTIFICATIONS ;
- des données de base (services, médecins).

---

## Étape 3 : Déployer l’application PHP sur Render

### 3.1 Créer un Web Service à partir du repo GitHub

1. Sur [dashboard.render.com](https://dashboard.render.com), cliquez **"New +"** → **"Web Service"**.
2. Connectez GitHub et sélectionnez le repo **ProjetClinique** (celui que vous avez poussé à l’étape 1).
3. Configurez :
   - **Name** : `projetclinique` (ou comme vous voulez).
   - **Region** : même que MySQL (ex. Frankfurt).
   - **Runtime** : **Docker** (le `Dockerfile` à la racine sera utilisé).
   - **Instance Type** : Free si disponible, sinon Starter.

### 3.2 Variables d’environnement (connexion MySQL)

Dans **Environment** du Web Service, ajoutez :

| Clé             | Valeur                                                                 |
|-----------------|------------------------------------------------------------------------|
| `MYSQL_HOST`    | Hostname interne du service MySQL (ex. `projetclinique-mysql`)        |
| `MYSQL_DATABASE`| `sante1`                                                               |
| `MYSQL_USER`    | L’utilisateur défini sur MySQL (ex. `appuser`)                         |
| `MYSQL_PASSWORD`| Le mot de passe défini pour cet utilisateur                            |
| `MYSQL_PORT`    | `3306`                                                                |

Sur Render, les services d’un même compte peuvent se parler via le **réseau privé** en utilisant le **nom du service** comme hostname. Donc souvent : **MYSQL_HOST** = nom du service MySQL (ex. `projetclinique-mysql`).

### 3.3 Déploiement

1. Cliquez sur **"Create Web Service"**.
2. Render va builder l’image Docker avec le `Dockerfile` puis lancer l’app.
3. Une fois le déploiement terminé, l’URL sera du type :  
   **`https://projetclinique-xxxx.onrender.com`**

---

## Étape 4 : Vérifications

- **Page d’accueil** : `https://votre-app.onrender.com/`
- **Connexion** : `https://votre-app.onrender.com/login.php`
- **Paiements** : `https://votre-app.onrender.com/payment/`

Si une page blanche ou une erreur 500 apparaît, consultez les **Logs** du Web Service dans le dashboard Render pour voir les erreurs PHP ou de connexion MySQL.

---

## Récapitulatif des fichiers utiles

| Fichier / Dossier        | Rôle |
|--------------------------|------|
| `.gitignore`             | Fichiers exclus du dépôt Git |
| `config/bdd.php`         | Connexion MySQL (lit `MYSQL_*` en production) |
| `config/database_render.sql` | Schéma complet + données pour la BDD sur Render |
| `Dockerfile`             | Image Docker PHP + nginx pour Render |
| `.dockerignore`          | Fichiers exclus de l’image Docker |
| `render.yaml`            | Configuration Render (Web Service, variables) |

---

## Option : tout déployer via le Blueprint (render.yaml)

Si vous préférez tout lier depuis le dépôt :

1. Poussez un fichier **`render.yaml`** à la racine (déjà présent dans ce projet).
2. Sur Render : **"New +"** → **"Blueprint"**.
3. Sélectionnez le repo **ProjetClinique**.
4. Render proposera de créer le Web Service décrit dans `render.yaml`.
5. Vous devrez quand même **créer à part** le service MySQL (template render-examples/mysql) et remplir **à la main** dans le Web Service :
   - `MYSQL_HOST`
   - `MYSQL_USER`
   - `MYSQL_PASSWORD`  
   comme indiqué plus haut.

---

## Envoi de la base de données : ce qui est “envoyé”

- **Dans GitHub** : vous envoyez le **schéma** et un jeu de données de base via **`config/database_render.sql`** (structure + quelques lignes pour SERVICES, MEDECINS, etc.).
- **Sur Render** : vous créez une base MySQL (template ou service Docker), puis vous **importez** ce fichier (`database_render.sql`) pour avoir les tables et les données de base.

Si vous voulez aussi envoyer **vos vraies données** (patients, rendez-vous, etc.) :

1. Exportez votre base locale (phpMyAdmin ou `mysqldump`) :
   ```bash
   mysqldump -u root -p santé1 > ma_base_complete.sql
   ```
2. Adaptez le fichier si besoin (noms de BDD, encodage).
3. Importez ce dump dans la base MySQL hébergée sur Render (Shell, client MySQL, ou one-off job).

Après ça, votre lien “parfait” sera l’URL Render du Web Service (ex. `https://projetclinique-xxxx.onrender.com`).
