# 🔧 Dépannage GitHub Actions - Erreur "username not provided"

## ❌ Problème

L'erreur **"Erreur : champ obligatoire non fourni : nom d'utilisateur"** signifie que GitHub Actions ne trouve pas les secrets `FTP_USERNAME` et `FTP_PASSWORD`.

---

## ✅ Solutions à essayer

### Solution 1 : Vérifier que les secrets ne sont pas vides

1. Va dans ton dépôt GitHub → **Settings** → **Secrets and variables** → **Actions**
2. Clique sur **l'icône crayon** (edit) à côté de `FTP_USERNAME`
3. Vérifie que la valeur n'est **PAS vide**
4. Si c'est vide, entre ton identifiant FTP : `if0_41017295`
5. Clique sur **"Update secret"**
6. Fais la même chose pour `FTP_PASSWORD`

### Solution 2 : Vérifier que tu es dans le BON dépôt

⚠️ **TRÈS IMPORTANT** : Les secrets doivent être dans **le même dépôt** que le workflow !

- Ton workflow est dans : `kadiatoubarry1541/dev-web`
- Tes secrets doivent être dans : `kadiatoubarry1541/dev-web` (Settings → Secrets)

**Vérifie** :
1. Va sur `https://github.com/kadiatoubarry1541/dev-web`
2. Clique sur **Settings**
3. Va dans **Secrets and variables** → **Actions**
4. Tu dois voir `FTP_USERNAME` et `FTP_PASSWORD` ici

Si les secrets sont dans un **autre dépôt**, ça ne fonctionnera pas !

### Solution 3 : Recréer les secrets

Parfois, il faut les supprimer et les recréer :

1. Dans **Secrets and variables** → **Actions**
2. Clique sur l'icône **poubelle** à côté de `FTP_USERNAME` → Supprime
3. Clique sur l'icône **poubelle** à côté de `FTP_PASSWORD` → Supprime
4. Recrée-les :
   - **Nouveau secret du dépôt**
   - **Nom** : `FTP_USERNAME` (exactement comme ça, en majuscules)
   - **Secrète** : `if0_41017295` (ton identifiant FTP)
   - **Ajouter un secret**
   - Répète pour `FTP_PASSWORD`

### Solution 4 : Vérifier le fichier workflow

Assure-toi que le fichier `.github/workflows/deploy.yml` est bien dans ton dépôt GitHub :

1. Va sur `https://github.com/kadiatoubarry1541/dev-web`
2. Clique sur l'onglet **Code**
3. Cherche le fichier `.github/workflows/deploy.yml`
4. Si il n'existe pas, tu dois le pousser :

```bash
cd C:\xampp_new\htdocs\ProjetClinique
git add .github/workflows/deploy.yml
git commit -m "Ajout workflow déploiement"
git push
```

### Solution 5 : Vérifier la branche

Le workflow se déclenche sur les branches `main` ou `master`. Vérifie :

1. Dans ton dépôt GitHub, quelle est ta branche principale ?
2. Si c'est `principal` (comme dans l'erreur), modifie le workflow :

Change cette ligne dans `.github/workflows/deploy.yml` :
```yaml
branches: [ main, master ]
```

Par :
```yaml
branches: [ main, master, principal ]
```

Puis pousse :
```bash
git add .github/workflows/deploy.yml
git commit -m "Ajout branche principal"
git push
```

---

## 🔍 Vérification étape par étape

### Checklist

- [ ] Les secrets sont dans le dépôt `kadiatoubarry1541/dev-web`
- [ ] Le nom du secret est **exactement** `FTP_USERNAME` (pas `secrets.FTP_USERNAME`)
- [ ] La valeur de `FTP_USERNAME` n'est **PAS vide**
- [ ] Le nom du secret est **exactement** `FTP_PASSWORD`
- [ ] La valeur de `FTP_PASSWORD` n'est **PAS vide**
- [ ] Le fichier `.github/workflows/deploy.yml` existe dans le dépôt GitHub
- [ ] La branche dans le workflow correspond à ta branche (`principal`)

---

## 🆘 Si rien ne fonctionne

### Alternative : Utiliser des variables d'environnement

Si les secrets ne fonctionnent toujours pas, essaie cette version du workflow :

```yaml
name: Deploy to InfinityFree

on:
  push:
    branches: [ main, master, principal ]
  workflow_dispatch:

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v3
      
    - name: Deploy via FTP
      env:
        FTP_USER: ${{ secrets.FTP_USERNAME }}
        FTP_PASS: ${{ secrets.FTP_PASSWORD }}
      uses: SamKirkland/FTP-Deploy-Action@4.3.4
      with:
        server: ftpupload.net
        username: ${{ env.FTP_USER }}
        password: ${{ env.FTP_PASS }}
        local-dir: ./
        server-dir: /htdocs/
        exclude: |
          **/.git*
          **/.git*/**
          **/node_modules/**
          **/.env
          **/.env.*
          **/export_for_render.sql
          **/.import_done*
```

---

## 📝 Après correction

1. Pousse les modifications :
   ```bash
   git add .github/workflows/deploy.yml
   git commit -m "Correction workflow"
   git push
   ```

2. Relance le workflow :
   - Va dans **Actions**
   - Clique sur **Run workflow**
   - Sélectionne la branche `principal`
   - Clique sur **Run workflow**

---

## 💡 Astuce

Pour déboguer, tu peux ajouter une étape de test dans le workflow :

```yaml
- name: Debug secrets
  run: |
    echo "FTP_USERNAME is set: ${{ secrets.FTP_USERNAME != '' }}"
    echo "FTP_PASSWORD is set: ${{ secrets.FTP_PASSWORD != '' }}"
```

Cela t'aidera à voir si les secrets sont bien détectés.

---

Dites-moi quelle solution vous avez essayée et si ça fonctionne ! 🚀
