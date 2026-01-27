# 📘 Trouver et utiliser l'API Orange Money – Guide pour étudiants

Ce guide t’aide à **trouver une vraie API Orange Money** pour tes tests et ton apprentissage, en privilégiant les options **gratuites** et **accessibles sans entreprise**.

---

## 🎯 Deux possibilités selon ta situation

| Option | Portail | Sandbox gratuit ? | Idéal pour |
|--------|---------|-------------------|------------|
| **1. Orange Sonatel (Sénégal)** | developer.orange-sonatel.com | ✅ **Oui** | **Étudiants, tests, apprentissage** |
| **2. Orange (international)** | developer.orange.com | ❌ Sur demande (contact Orange) | Projets structurés, multi‑pays |

En tant qu’étudiant, **commence par l’option 1 (Orange Sonatel)** : inscription gratuite et sandbox disponible tout de suite.

---

## ✅ Option 1 : Orange Sonatel (Sénégal) – Recommandé pour toi

**C’est la solution la plus simple pour un étudiant** : compte gratuit, sandbox par défaut, doc en français.

### Étape 1 – Créer ton compte développeur

1. Ouvre :  
   **https://developer.orange-sonatel.com/user/register**
2. Remplis le formulaire (email valide, mot de passe, etc.).
3. Valide ton email pour activer le compte.
4. Connecte-toi sur : **https://developer.orange-sonatel.com**

### Étape 2 – Créer une application Sandbox

1. Dans ton compte : **Profil** → **Mes applications** (ou équivalent).
2. **Créer une application**.
3. Choisis l’environnement **Sandbox** (test).  
   Le sandbox est disponible sans démarche commerciale.
4. Tu obtiendras :
   - **client_id** (identifiant client)
   - **client_secret** (clé secrète)

Conserve-les pour la suite (équivalent de ton `merchant_id` / `merchant_key` dans ton projet).

### Étape 3 – Obtenir un token (pour appeler l’API)

En ligne de commande (PowerShell, bash, etc.) :

```bash
# Remplace {your_client_id} et {your_secret} par tes vrais identifiants
curl -k -d client_id=VOTRE_CLIENT_ID -d client_secret=VOTRE_CLIENT_SECRET -d grant_type=client_credentials https://api.sandbox.orange-sonatel.com/oauth/token
```

Le token est valable **5 minutes**. Tu l’utiliseras dans l’en-tête `Authorization: Bearer ...` de tes requêtes.

### Étape 4 – APIs Orange Money disponibles sur ce portail

Sur **developer.orange-sonatel.com** tu as notamment :

| API | Usage | Lien doc |
|-----|--------|----------|
| **OTP Payment** | Paiement marchand avec code OTP (USSD #144# ou app Orange Money) | [OTP Payment – OM](https://developer.orange-sonatel.com/merchant-payment) |
| **QR Code – OM** | Paiement par QR Code | [QR Code – OM](https://developer.orange-sonatel.com/qr-code) |
| **Cash In – OM** | Dépôt d’argent sur un wallet | [Cash In – OM](https://developer.orange-sonatel.com/cash-in) |

Pour un projet “paiement clinique”, **OTP Payment** est le plus proche de ton cas (client paie avec son numéro + OTP).

### Liens utiles Orange Sonatel

- **Inscription** : https://developer.orange-sonatel.com/user/register  
- **Premiers pas** : https://developer.orange-sonatel.com/getting-started  
- **Liste des APIs** : https://developer.orange-sonatel.com/categories  
- **OTP Payment (paiement marchand)** : https://developer.orange-sonatel.com/merchant-payment  
- **Sandbox (base)** : `https://api.sandbox.orange-sonatel.com`  
- **Production (plus tard)** : `https://api.orange-sonatel.com`  

En sandbox, toutes les URLs d’API commencent par `https://api.sandbox.orange-sonatel.com/...`.

---

## Option 2 : Orange (international) – developer.orange.com

Cette API couvre **plusieurs pays** Orange Money (dont certains en Afrique). Pour un **étudiant**, l’accès est **moins direct** : pas de sandbox public ouvert à tous, il faut **contacter Orange**.

### Ce qu’Orange propose

- **Orange Money for Business**  
  - Paiements, encaissement, remboursements, etc.  
  - Plusieurs pays, un seul point d’intégration.  
- **Orange Money Web Payment / M Payment**  
  - Paiement Orange Money depuis un site ou une app.

### Comment demander l’accès (y compris pour des tests)

1. **Créer un compte** : https://developer.orange.com/signup  
2. **Se connecter** puis aller sur :  
   **https://developer.orange.com/apply-orange-money/**  
3. **Remplir la demande** (projet, usage, environnement souhaité : test / sandbox si proposé).  
4. **Préciser que tu es étudiant** et que c’est pour l’apprentissage / les tests – ils peuvent parfois proposer un accès sandbox ou des explications.

### Pages utiles

- **Portail** : https://developer.orange.com  
- **Orange Money for Business** : https://developer.orange.com/apis/orange-money-business-api-discover  
- **Demande d’accès Orange Money** : https://developer.orange.com/apply-orange-money/ (après connexion)  
- **Démarrage rapide** : https://developer.orange.com/resources/quickstart  

---

## 🔗 Adapter ton projet ProjetClinique à une vraie API

Ton code actuel (`orange_config.php`, `orange_money_api.php`, etc.) est pensé pour un style d’API “générique” (URL de paiement, callback, etc.).  
Les APIs réelles d’Orange **ne sont pas toutes identiques** :

- **Orange Sonatel (OTP Payment)** :  
  - Pas de “lien de paiement” comme en webpay.  
  - Tu envoies une requête avec : montant, numéro client (MSISDN), code OTP, etc.  
  - Il faudra donc **adapter** `orange_money_api.php` (nouveaux endpoints, nouveau format de requête/réponse) en suivant la doc Sonatel.
- **Orange Money Business / Web Payment** (developer.orange.com) :  
  - Plus proche du flux “lien de redirection” que tu as déjà en simulation.  
  - Si Orange te donne un accès sandbox, tu pourras réutiliser une partie de ta logique en changeant surtout les URLs et le format des appels.

En pratique :

1. **Pour tester vite et gratuitement** :  
   - Inscris-toi sur **Orange Sonatel**, crée une app Sandbox, récupère `client_id` / `client_secret`.  
   - Utilise la doc **OTP Payment** pour voir les vrais endpoints et le format des requêtes.  
   - Ensuite, modifie ton `orange_money_api.php` (ou crée un `OrangeSonatelAPI.php`) pour appeler `https://api.sandbox.orange-sonatel.com/...` avec le token que tu obtiens via `/oauth/token`.

2. **Si tu vises plutôt un pays hors Sénégal** :  
   - Utilise **developer.orange.com** + la page “Apply for Orange Money”, en indiquant que c’est pour l’apprentissage.  
   - Quand tu auras un accès (sandbox ou doc), tu adapteras `orange_config.php` et les URLs selon ce qu’Orange te fournit.

---

## 📋 Résumé – Par où commencer (étudiant)

1. Va sur **https://developer.orange-sonatel.com/user/register** et crée un compte.
2. Crée une **application Sandbox** et note ton **client_id** et **client_secret**.
3. Lis la page **Premiers pas** : https://developer.orange-sonatel.com/getting-started  
4. Ouvre la doc **OTP Payment** : https://developer.orange-sonatel.com/merchant-payment  
5. Teste d’abord le **token** avec une requête vers `https://api.sandbox.orange-sonatel.com/oauth/token`.
6. Ensuite, adapte ton projet pour appeler les vrais endpoints (paiement, statut, etc.) décrits dans cette doc.

Si tu me dis sur quel portail tu veux te baser (Sonatel ou Orange international), je peux te proposer des modifications concrètes pour `orange_config.php` et `orange_money_api.php` (noms de paramètres, URLs, exemples de requêtes).

---

## 📚 Références

| Ressource | URL |
|-----------|-----|
| **Orange Sonatel – Inscription** | https://developer.orange-sonatel.com/user/register |
| **Orange Sonatel – Premiers pas** | https://developer.orange-sonatel.com/getting-started |
| **Orange Sonatel – OTP Payment** | https://developer.orange-sonatel.com/merchant-payment |
| **Orange Sonatel – Catégories d’API** | https://developer.orange-sonatel.com/categories |
| **Orange (international) – Portail** | https://developer.orange.com |
| **Orange (international) – Orange Money Business** | https://developer.orange.com/apis/orange-money-business-api-discover |
| **Orange (international) – Demande d’accès** | https://developer.orange.com/apply-orange-money/ |
| **Orange Côte d’Ivoire – infos générales** | https://business.orange.ci/fr/orange-money/api-orange-money.html |

Tu peux utiliser ce guide comme base pour “trouver l’API Orange” et voir comment l’intégrer dans ton module de paiement pour les tests et l’apprentissage.
