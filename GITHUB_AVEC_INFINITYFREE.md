# 🚀 Utiliser GitHub avec InfinityFree

Même si GitHub Pages ne peut pas héberger votre projet PHP + MySQL, vous pouvez utiliser GitHub pour :
- ✅ Stocker votre code
- ✅ Gérer les versions
- ✅ Déployer automatiquement vers InfinityFree

---

## 🎯 Option 1 : GitHub + Déploiement FTP automatique (RECOMMANDÉ)

### Avantages
- ✅ Code sur GitHub (gestion de versions)
- ✅ Déploiement automatique vers InfinityFree
- ✅ Historique de vos modifications
- ✅ Collaboration facilitée

### Comment faire

#### Étape 1 : Créer un dépôt GitHub

1. Allez sur [github.com](https://github.com)
2. Créez un nouveau dépôt (ex: `ProjetClinique`)
3. **Ne cochez PAS** "Initialize with README" (vous avez déjà des fichiers)

#### Étape 2 : Pousser votre code sur GitHub

Dans votre terminal (PowerShell) :

```bash
cd C:\xampp_new\htdocs\ProjetClinique
git init
git add .
git commit -m "Initial commit - ProjetClinique"
git branch -M main
git remote add origin https://github.com/VOTRE_USERNAME/ProjetClinique.git
git push -u origin main
```

#### Étape 3 : Configurer le déploiement FTP automatique

**Option A : Utiliser GitHub Actions (Gratuit)**

Créez le fichier `.github/workflows/deploy.yml` :

```yaml
name: Deploy to InfinityFree

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v3
      
    - name: Deploy via FTP
      uses: SamKirkland/FTP-Deploy-Action@4.3.0
      with:
        server: ftpupload.net
        username: ${{ secrets.FTP_USERNAME }}
        password: ${{ secrets.FTP_PASSWORD }}
        local-dir: ./
        server-dir: /htdocs/
        exclude: |
          **/.git*
          **/.git*/**
          **/node_modules/**
          **/.env
          **/export_for_render.sql
          **/.import_done*
```

#### Étape 4 : Ajouter les secrets GitHub

1. Dans votre dépôt GitHub, allez dans **Settings** → **Secrets** → **Actions**
2. Cliquez sur **"New repository secret"**
3. Ajoutez :
   - **Name** : `FTP_USERNAME`
   - **Value** : Votre identifiant FTP InfinityFree (ex: `if0_41017295`)
4. Ajoutez un autre secret :
   - **Name** : `FTP_PASSWORD`
   - **Value** : Votre mot de passe FTP InfinityFree

#### Étape 5 : Tester le déploiement

1. Faites une modification dans votre code
2. Committez et poussez :
   ```bash
   git add .
   git commit -m "Test déploiement"
   git push
   ```
3. Allez dans l'onglet **"Actions"** de votre dépôt GitHub
4. Vous verrez le déploiement en cours
5. Une fois terminé, vos fichiers seront sur InfinityFree !

---

## 🎯 Option 2 : GitHub + Déploiement manuel

### Si vous préférez déployer manuellement

1. **Code sur GitHub** : Gardez votre code sur GitHub pour la gestion de versions
2. **Déploiement manuel** : Quand vous voulez mettre à jour InfinityFree :
   - Téléchargez les fichiers depuis GitHub
   - Uploadez-les via FTP ou File Manager vers InfinityFree

---

## 🎯 Option 3 : Utiliser un service de déploiement automatique

### Services qui connectent GitHub à InfinityFree

#### DeployHQ (Gratuit jusqu'à 1 projet)
1. Créez un compte sur [deployhq.com](https://www.deployhq.com)
2. Connectez votre dépôt GitHub
3. Configurez le déploiement FTP vers InfinityFree
4. Déploiement automatique à chaque push !

#### Buddy (Gratuit jusqu'à 5 projets)
1. Créez un compte sur [buddy.works](https://buddy.works)
2. Connectez GitHub
3. Configurez le pipeline de déploiement FTP
4. Déploiement automatique !

---

## 📋 Configuration pour InfinityFree

### Informations FTP nécessaires

Depuis votre dashboard InfinityFree :

1. Allez dans **"FTP Details"** (dans le menu de gauche)
2. Notez :
   - **FTP Server** : `ftpupload.net` (ou celui fourni)
   - **FTP Username** : `if0_41017295` (votre identifiant)
   - **FTP Password** : (votre mot de passe)
   - **FTP Port** : `21`
   - **Remote Directory** : `/htdocs/` ou `/public_html/`

---

## 🔧 Workflow recommandé

### Développement local
1. Modifiez votre code en local (XAMPP)
2. Testez en local
3. Committez vos changements :
   ```bash
   git add .
   git commit -m "Description des modifications"
   git push
   ```

### Déploiement automatique
1. GitHub Actions détecte le push
2. Déploie automatiquement vers InfinityFree via FTP
3. Votre site est mis à jour automatiquement !

---

## ⚠️ Fichiers à exclure du déploiement

Assurez-vous que `.gitignore` contient :

```
# Fichiers sensibles
.env
config/bdd.local.php
export_for_render.sql
.import_done*

# Base de données locale
*.sql
!config/database_render.sql

# Logs
*.log
```

---

## 📝 Checklist

- [ ] Dépôt GitHub créé
- [ ] Code poussé sur GitHub
- [ ] Secrets FTP configurés dans GitHub
- [ ] Fichier `.github/workflows/deploy.yml` créé
- [ ] Premier déploiement testé
- [ ] Site accessible sur `projetclinique.great-site.net`

---

## 🎉 Avantages de cette approche

1. ✅ **Code versionné** sur GitHub
2. ✅ **Déploiement automatique** vers InfinityFree
3. ✅ **Historique** de toutes vos modifications
4. ✅ **Collaboration** facilitée (plusieurs développeurs)
5. ✅ **Sauvegarde** automatique de votre code

---

## 🆘 Besoin d'aide ?

Si vous voulez que je crée le fichier `.github/workflows/deploy.yml` pour vous, dites-moi et je le ferai avec vos identifiants FTP InfinityFree !

---

## 💡 Résumé

| Élément | Où |
|---------|-----|
| **Code source** | GitHub (gestion de versions) |
| **Hébergement réel** | InfinityFree (PHP + MySQL) |
| **Déploiement** | Automatique via GitHub Actions |
| **Votre site** | `projetclinique.great-site.net` |

**C'est le meilleur des deux mondes !** 🚀
