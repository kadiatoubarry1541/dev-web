# 🚀 Guide pour Utiliser l'API Orange Money Réelle (Test/Sandbox)

Ce guide vous explique comment obtenir et utiliser une **vraie API Orange Money** pour tester, pas juste une simulation !

## 📋 Étape 1 : Créer un Compte Orange Developer

### 1.1 Aller sur le Portail Développeur

1. **Ouvrez votre navigateur** et allez sur :
   ```
   https://developer.orange.com
   ```

2. **Cliquez sur "Sign Up"** ou "S'inscrire" (en haut à droite)

3. **Remplissez le formulaire** :
   - Email
   - Mot de passe
   - Nom, Prénom
   - Pays (Guinée si disponible)
   - Acceptez les conditions

4. **Vérifiez votre email** et activez votre compte

### 1.2 Se Connecter

Une fois votre compte activé, connectez-vous sur https://developer.orange.com

## 🔑 Étape 2 : Obtenir les Credentials de Test

### 2.1 Créer une Application

1. **Dans votre tableau de bord**, cherchez "My Apps" ou "Mes Applications"
2. **Cliquez sur "Create App"** ou "Créer une Application"
3. **Remplissez les informations** :
   - **Nom de l'application** : "Clinique Test" (ou ce que vous voulez)
   - **Description** : "Application de test pour paiements Orange Money"
   - **Type** : Web Application
   - **Callback URL** : `http://localhost/ProjetClinique/payment/orange_callback.php`
   - **Return URL** : `http://localhost/ProjetClinique/payment/orange_return.php`

4. **Sauvegardez** l'application

### 2.2 Souscrire à Orange Money API

1. **Cherchez "Orange Money"** dans les APIs disponibles
2. **Ou allez directement sur** :
   ```
   https://developer.orange.com/apis/orange-money-business-api-discover
   ```
   ou
   ```
   https://developer.orange.com/apis/om-webpay
   ```

3. **Cliquez sur "Subscribe"** ou "S'abonner"
4. **Choisissez le plan "Sandbox"** ou "Test" (gratuit)
5. **Acceptez les conditions**

### 2.3 Récupérer vos Credentials

Une fois abonné, vous obtiendrez :

1. **Client ID** (ou Merchant ID)
2. **Client Secret** (ou Merchant Key)
3. **API Endpoint** (URL de l'API)
4. **Sandbox URL** (URL de test)

**⚠️ IMPORTANT :** Notez ces informations dans un endroit sûr !

## ⚙️ Étape 3 : Configurer Votre Système

### 3.1 Mettre à Jour la Configuration

Ouvrez le fichier : `payment/orange_config.php`

Remplacez les valeurs par vos credentials réels :

```php
return [
    // DÉSACTIVER le mode simulation
    'simulation_mode' => false,  // ← Changez à false
    
    // Vos credentials réels du portail Orange
    'merchant_id' => 'VOTRE_CLIENT_ID_ICI',      // ← Remplacez
    'merchant_key' => 'VOTRE_CLIENT_SECRET_ICI',  // ← Remplacez
    
    // URLs de l'API (sandbox pour les tests)
    'api_url' => 'https://api.orange.com/orange-money-webpay/dev/v1/webpayment',
    // OU l'URL sandbox fournie par Orange
    // 'api_url' => 'https://api-sandbox.orange.com/orange-money-webpay/v1/webpayment',
    
    'auth_url' => 'https://api.orange.com/oauth/v2/token',
    // OU pour sandbox :
    // 'auth_url' => 'https://api-sandbox.orange.com/oauth/v2/token',
    
    // URLs de callback (doivent être accessibles publiquement pour les tests)
    'callback_url' => 'http://localhost/ProjetClinique/payment/orange_callback.php',
    'return_url' => 'http://localhost/ProjetClinique/payment/orange_return.php',
    
    // ... reste de la config
];
```

### 3.2 Vérifier les URLs de Callback

**Pour les tests locaux**, vous avez deux options :

#### Option 1 : Utiliser ngrok (Recommandé pour tester)

1. **Téléchargez ngrok** : https://ngrok.com/download
2. **Installez ngrok**
3. **Lancez votre serveur local** (XAMPP)
4. **Dans un terminal**, exécutez :
   ```bash
   ngrok http 80
   ```
   (ou le port de votre serveur)

5. **Copiez l'URL HTTPS** fournie par ngrok (ex: `https://abc123.ngrok.io`)
6. **Mettez à jour** `orange_config.php` :
   ```php
   'callback_url' => 'https://abc123.ngrok.io/ProjetClinique/payment/orange_callback.php',
   'return_url' => 'https://abc123.ngrok.io/ProjetClinique/payment/orange_return.php',
   ```
7. **Mettez aussi à jour** dans votre application Orange Developer

#### Option 2 : Utiliser un serveur de test en ligne

- Utilisez un service comme Heroku, Vercel, ou un VPS
- Déployez votre application
- Utilisez l'URL publique pour les callbacks

## 🧪 Étape 4 : Tester l'API Réelle

### 4.1 Préparer la Base de Données

Assurez-vous d'avoir exécuté le script SQL :

```sql
ALTER TABLE PAIEMENT 
MODIFY COLUMN Méthode_paiement ENUM('espèces', 'carte', 'chèque', 'virement', 'orange_money') DEFAULT 'espèces';

ALTER TABLE PAIEMENT 
ADD COLUMN IF NOT EXISTS orange_order_id VARCHAR(100) NULL AFTER id_facture;
```

### 4.2 Tester avec la Page de Test

1. **Allez sur** : `http://localhost/ProjetClinique/payment/test_orange.php`
2. **Remplissez le formulaire**
3. **Cliquez sur "Tester Orange Money"**
4. **Observez le résultat** :
   - Si vous voyez une URL de paiement → ✅ Ça fonctionne !
   - Si vous voyez une erreur → Vérifiez vos credentials

### 4.3 Tester le Flux Complet

1. **Allez sur** : `http://localhost/ProjetClinique/payment/create.php`
2. **Sélectionnez "Orange Money"**
3. **Remplissez les informations**
4. **Soumettez**
5. **Vous serez redirigé** vers la vraie page de paiement Orange Money
6. **Suivez les instructions** pour effectuer le paiement de test

## 🔍 Étape 5 : Vérifier que Ça Fonctionne

### 5.1 Vérifier les Logs

Les logs sont dans : `logs/orange_money.log`

Ou vérifiez les logs PHP de votre serveur.

### 5.2 Vérifier dans la Base de Données

```sql
SELECT * FROM PAIEMENT 
WHERE Méthode_paiement = 'orange_money' 
ORDER BY Date_creation DESC;
```

Vous devriez voir :
- `orange_order_id` rempli
- `Statut` mis à jour automatiquement
- `id_facture` généré si payé

### 5.3 Vérifier les Callbacks

1. **Allez sur** : `http://localhost/ProjetClinique/payment/orange_callback.php`
2. **Vérifiez les logs** pour voir les notifications reçues

## 📚 Ressources Utiles

### Documentation Orange

- **Portail Développeur** : https://developer.orange.com
- **Orange Money Web Payment** : https://developer.orange.com/apis/om-webpay
- **Orange Money Business API** : https://developer.orange.com/apis/orange-money-business-api-discover
- **Documentation** : https://docs.developer.orange.com

### Outils de Test

- **ngrok** (pour exposer localhost) : https://ngrok.com
- **Postman** (pour tester les APIs) : https://www.postman.com

## ⚠️ Notes Importantes

1. **Sandbox vs Production** :
   - **Sandbox** : Pour les tests, gratuit, données fictives
   - **Production** : Pour le vrai, nécessite validation Orange

2. **Callbacks** :
   - Les callbacks doivent être accessibles publiquement
   - Utilisez HTTPS en production
   - Testez avec ngrok en local

3. **Credentials** :
   - Ne partagez JAMAIS vos credentials
   - Ne les commitez pas dans Git
   - Utilisez des variables d'environnement en production

4. **Limites Sandbox** :
   - Les paiements sont fictifs
   - Pas d'argent réel impliqué
   - Parfait pour apprendre et tester

## 🐛 Dépannage

### Erreur : "Invalid credentials"

- Vérifiez que `merchant_id` et `merchant_key` sont corrects
- Vérifiez qu'ils sont bien ceux du sandbox (pas production)
- Vérifiez qu'il n'y a pas d'espaces avant/après

### Erreur : "Callback URL not accessible"

- Utilisez ngrok pour exposer votre localhost
- Vérifiez que l'URL est bien enregistrée dans Orange Developer
- Testez l'URL dans votre navigateur

### Erreur : "API endpoint not found"

- Vérifiez l'URL de l'API dans `orange_config.php`
- Utilisez l'URL sandbox fournie par Orange
- Vérifiez la documentation Orange pour la bonne URL

## 🎉 Félicitations !

Vous utilisez maintenant une **vraie API Orange Money** en mode test !

C'est beaucoup mieux qu'une simple simulation car :
- ✅ Vous testez avec la vraie API
- ✅ Vous voyez comment ça fonctionne réellement
- ✅ Vous apprenez les vraies réponses de l'API
- ✅ Vous êtes prêt pour la production quand vous voudrez

Bon test ! 🚀
