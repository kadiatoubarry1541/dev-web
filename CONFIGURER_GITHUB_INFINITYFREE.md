# 🔗 Connecter votre dépôt GitHub existant à InfinityFree

Guide pour configurer le déploiement automatique depuis votre dépôt GitHub vers InfinityFree.

---

## ✅ Ce qui est déjà prêt

- ✅ Fichier `.github/workflows/deploy.yml` créé
- ✅ Configuration de déploiement FTP prête

---

## 🎯 Étape 1 : Pousser le fichier de workflow sur GitHub

### 1.1 Vérifier que le fichier existe

Le fichier `.github/workflows/deploy.yml` doit être dans votre projet local.

### 1.2 Ajouter et pousser sur GitHub

Dans PowerShell, depuis votre dossier projet :

```bash
cd C:\xampp_new\htdocs\ProjetClinique

# Vérifier le statut
git status

# Ajouter le fichier de workflow
git add .github/workflows/deploy.yml

# Committer
git commit -m "Ajout déploiement automatique vers InfinityFree"

# Pousser sur GitHub
git push
```

---

## 🎯 Étape 2 : Trouver vos identifiants FTP InfinityFree

### 2.1 Se connecter à InfinityFree

1. Allez sur [https://dash.infinityfree.com](https://dash.infinityfree.com)
2. Connectez-vous avec votre compte
3. Vous devriez voir votre site `projetclinique.great-site.net`

### 2.2 Récupérer les identifiants FTP

1. Dans le menu de gauche, cliquez sur **"FTP Details"**
2. Vous verrez :
   - **FTP Server** : `ftpupload.net` (ou un autre serveur)
   - **FTP Username** : `if0_41017295` (votre identifiant)
   - **FTP Password** : (cliquez sur l'icône œil pour voir)
   - **FTP Port** : `21`
   - **Remote Directory** : `/htdocs/` ou `/public_html/`

⚠️ **NOTEZ CES INFORMATIONS !**

---

## 🎯 Étape 3 : Configurer les secrets GitHub

### 3.1 Aller dans les paramètres du dépôt

1. Allez sur votre dépôt GitHub (ex: `https://github.com/VOTRE_USERNAME/ProjetClinique`)
2. Cliquez sur **"Settings"** (en haut à droite)

### 3.2 Accéder aux secrets

1. Dans le menu de gauche, cliquez sur **"Secrets and variables"**
2. Cliquez sur **"Actions"**

### 3.3 Ajouter le secret FTP_USERNAME

1. Cliquez sur **"New repository secret"**
2. **Name** : `FTP_USERNAME`
3. **Secret** : Votre identifiant FTP InfinityFree (ex: `if0_41017295`)
4. Cliquez sur **"Add secret"**

### 3.4 Ajouter le secret FTP_PASSWORD

1. Cliquez à nouveau sur **"New repository secret"**
2. **Name** : `FTP_PASSWORD`
3. **Secret** : Votre mot de passe FTP InfinityFree
4. Cliquez sur **"Add secret"**

### 3.5 Vérifier

Vous devriez maintenant avoir 2 secrets :
- ✅ `FTP_USERNAME`
- ✅ `FTP_PASSWORD`

---

## 🎯 Étape 4 : Vérifier le chemin FTP

### 4.1 Vérifier le répertoire distant

Dans InfinityFree, le répertoire peut être :
- `/htdocs/` (le plus courant)
- `/public_html/`
- Ou un autre chemin

### 4.2 Modifier si nécessaire

Si votre répertoire est différent de `/htdocs/`, modifiez `.github/workflows/deploy.yml` :

Ouvrez le fichier et changez la ligne :
```yaml
server-dir: /htdocs/
```

Par votre chemin (ex: `/public_html/`)

Puis poussez à nouveau :
```bash
git add .github/workflows/deploy.yml
git commit -m "Correction chemin FTP"
git push
```

---

## 🎯 Étape 5 : Tester le déploiement

### 5.1 Déclencher manuellement (pour tester)

1. Allez sur votre dépôt GitHub
2. Cliquez sur l'onglet **"Actions"**
3. Cliquez sur **"Deploy to InfinityFree"** (votre workflow)
4. Cliquez sur **"Run workflow"** (bouton en haut à droite)
5. Sélectionnez la branche `main` (ou `master`)
6. Cliquez sur **"Run workflow"**

### 5.2 Vérifier le déploiement

1. Attendez quelques secondes
2. Cliquez sur le workflow qui vient de démarrer
3. Vous verrez les étapes en cours :
   - ✅ Checkout code
   - ✅ Deploy via FTP
4. Si tout est vert ✅, le déploiement a réussi !

### 5.3 Vérifier sur InfinityFree

1. Allez sur votre site : `https://projetclinique.great-site.net`
2. Vérifiez que vos fichiers sont bien là
3. Testez votre application

---

## 🎯 Étape 6 : Déploiement automatique

Maintenant, à chaque fois que vous poussez du code sur GitHub :

```bash
git add .
git commit -m "Description des modifications"
git push
```

Le déploiement se déclenchera automatiquement !

---

## 🔧 Dépannage

### Problème : "FTP connection failed"

**Solutions :**
1. Vérifiez que `FTP_USERNAME` et `FTP_PASSWORD` sont corrects
2. Vérifiez que le serveur FTP est `ftpupload.net` (ou celui fourni)
3. Vérifiez que le port est `21`

### Problème : "Directory not found"

**Solutions :**
1. Vérifiez le chemin `server-dir` dans `deploy.yml`
2. Essayez `/htdocs/` ou `/public_html/`
3. Vérifiez dans InfinityFree → FTP Details → Remote Directory

### Problème : "Files not uploaded"

**Solutions :**
1. Vérifiez les logs dans GitHub Actions
2. Vérifiez que les fichiers ne sont pas dans `.gitignore`
3. Vérifiez les permissions FTP

---

## 📋 Checklist

- [ ] Fichier `.github/workflows/deploy.yml` poussé sur GitHub
- [ ] Secret `FTP_USERNAME` configuré dans GitHub
- [ ] Secret `FTP_PASSWORD` configuré dans GitHub
- [ ] Chemin FTP vérifié (`/htdocs/` ou `/public_html/`)
- [ ] Premier déploiement testé manuellement
- [ ] Déploiement réussi ✅
- [ ] Site vérifié sur InfinityFree

---

## 🎉 C'est prêt !

Une fois configuré :

1. ✅ **Code sur GitHub** : Gestion de versions
2. ✅ **Déploiement automatique** : À chaque push
3. ✅ **Site sur InfinityFree** : `projetclinique.great-site.net`

---

## 💡 Astuce

Pour voir l'historique des déploiements :
- Allez dans votre dépôt GitHub → **"Actions"**
- Vous verrez tous les déploiements passés et en cours

---

## 🆘 Besoin d'aide ?

Si vous rencontrez des problèmes :
1. Vérifiez les logs dans GitHub Actions
2. Vérifiez vos identifiants FTP dans InfinityFree
3. Vérifiez que le fichier `deploy.yml` est bien dans `.github/workflows/`

Dites-moi où vous en êtes et je vous aiderai ! 🚀
