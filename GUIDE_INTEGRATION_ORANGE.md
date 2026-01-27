# Guide d'Intégration Orange Money API

Ce guide vous explique comment intégrer et utiliser l'API Orange Money dans votre projet de gestion clinique.

## 📋 Vue d'ensemble

L'intégration Orange Money permet aux patients de payer leurs consultations et services médicaux via Orange Money. Le système est déjà configuré et prêt à être utilisé.

## 🔧 Configuration Initiale

### 1. Mise à jour de la Base de Données

Exécutez le script SQL suivant dans votre base de données pour ajouter le support Orange Money :

```sql
-- Ajouter "orange_money" à la méthode de paiement
ALTER TABLE PAIEMENT 
MODIFY COLUMN Méthode_paiement ENUM('espèces', 'carte', 'chèque', 'virement', 'orange_money') DEFAULT 'espèces';

-- Si vous avez la colonne methode_paiement (sans accent)
-- ALTER TABLE PAIEMENT 
-- MODIFY COLUMN methode_paiement ENUM('espèces', 'carte', 'chèque', 'virement', 'orange_money') DEFAULT 'espèces';

-- Ajouter la colonne orange_order_id pour stocker l'ID de commande Orange Money
ALTER TABLE PAIEMENT 
ADD COLUMN IF NOT EXISTS orange_order_id VARCHAR(100) NULL AFTER id_facture;

-- Ajouter un index pour améliorer les performances
CREATE INDEX IF NOT EXISTS idx_orange_order_id ON PAIEMENT(orange_order_id);
```

**Ou utilisez le fichier fourni :**
```bash
# Exécutez le script SQL
mysql -u votre_utilisateur -p votre_base_de_donnees < payment/add_orange_money_method.sql
```

### 2. Configuration des Credentials Orange Money

Éditez le fichier `payment/orange_config.php` :

```php
return [
    // Mode simulation (true) ou API réelle (false)
    'simulation_mode' => true,  // Mettre à false pour l'API réelle
    
    // Credentials Orange Money (à remplir avec vos vraies valeurs)
    'merchant_id' => 'VOTRE_MERCHANT_ID',
    'merchant_key' => 'VOTRE_MERCHANT_KEY',
    
    // URLs de l'API Orange Money
    'api_url' => 'https://api.orange.com/orange-money-webpay/dev/v1/webpayment',
    'auth_url' => 'https://api.orange.com/oauth/v2/token',
    
    // URLs de callback (à adapter selon votre domaine)
    'callback_url' => 'https://votre-domaine.com/ProjetClinique/payment/orange_callback.php',
    'return_url' => 'https://votre-domaine.com/ProjetClinique/payment/orange_return.php',
    
    // Devise par défaut
    'currency' => 'GNF',
    
    // Langue par défaut
    'language' => 'fr',
    
    // Timeout pour les requêtes API (en secondes)
    'timeout' => 30,
    
    // Activer les logs
    'enable_logs' => true,
    'log_file' => __DIR__ . '/../logs/orange_money.log'
];
```

### 3. Créer le Dossier de Logs

Créez le dossier pour les logs (si nécessaire) :

```bash
mkdir -p logs
chmod 755 logs
```

## 🧪 Mode Simulation (Tests)

Par défaut, le système est en **mode simulation** pour les tests sans credentials réels.

### Utilisation du mode simulation :

1. Dans `orange_config.php`, gardez `simulation_mode => true`
2. Accédez à `paiements/creer-paiement.php`
3. Sélectionnez "Orange Money" comme méthode de paiement
4. Entrez le numéro de téléphone Orange Money du patient
5. Cliquez sur "Enregistrer le Paiement"
6. Vous serez redirigé vers la page de simulation
7. Cliquez sur "Simuler Paiement Réussi" ou "Simuler Annulation"

## 🚀 Utilisation en Production

### 1. Obtenir les Credentials Orange Money

Pour utiliser l'API Orange Money en production, vous devez :

1. **Contacter Orange** pour devenir un marchand Orange Money
2. **Obtenir vos credentials** :
   - `merchant_id` : Votre identifiant marchand
   - `merchant_key` : Votre clé secrète marchand
3. **Accéder au portail développeur** : https://developer.orange.com/
4. **Activer l'accès à l'API** Orange Money Web Payment

### 2. Configurer l'API en Production

1. Mettez à jour `orange_config.php` avec vos vraies credentials
2. Mettez `simulation_mode => false`
3. Configurez les URLs de callback :
   - `callback_url` : URL publique accessible par Orange (webhook)
   - `return_url` : URL de retour après paiement
4. Assurez-vous que votre serveur utilise HTTPS (requis pour la production)

### 3. Tester avec l'API Réelle

1. Utilisez d'abord l'environnement de test/sandbox d'Orange
2. Testez avec de petits montants
3. Vérifiez les callbacks et retours
4. Une fois validé, passez en production

## 📱 Comment Utiliser Orange Money

### Pour le Personnel (Admin/Accueil)

1. Accédez à **Paiements > Créer un Paiement**
2. Sélectionnez le patient
3. Sélectionnez le service
4. Le montant sera automatiquement rempli
5. Dans "Méthode de paiement", sélectionnez **"Orange Money"**
6. Un champ apparaîtra pour le numéro de téléphone Orange Money
7. Entrez le numéro de téléphone du patient (format : +224 XX XX XX XX)
8. Cliquez sur "Enregistrer le Paiement"
9. Le patient sera redirigé vers la page de paiement Orange Money
10. Une fois le paiement effectué, le statut sera mis à jour automatiquement

### Flux de Paiement Complet

```
1. Personnel crée un paiement avec méthode "Orange Money"
2. Entrée du numéro de téléphone Orange Money
3. Soumission → orange_process.php
4. Initiation du paiement via API Orange Money
5. Redirection vers la page de paiement Orange Money
6. Patient effectue le paiement sur la plateforme Orange
7. Orange envoie notification → orange_callback.php (webhook)
8. Redirection patient → orange_return.php
9. Mise à jour automatique du statut du paiement
10. Génération automatique du reçu si payé
```

## 📁 Structure des Fichiers

```
ProjetClinique/
├── payment/
│   ├── orange_money_api.php      # Classe pour gérer les appels API
│   ├── orange_config.php          # Configuration (credentials, URLs)
│   ├── orange_process.php         # Traitement de l'initiation du paiement
│   ├── orange_callback.php        # Webhook pour recevoir les notifications Orange
│   ├── orange_return.php          # Page de retour après paiement
│   ├── orange_simulate.php        # Page de simulation pour les tests
│   ├── add_orange_money_method.sql # Script SQL pour la base de données
│   └── README_ORANGE_MONEY.md     # Documentation détaillée
├── paiements/
│   ├── creer-paiement.php         # Formulaire principal (intégré avec Orange Money)
│   └── liste-paiements.php        # Liste des paiements
└── GUIDE_INTEGRATION_ORANGE.md    # Ce fichier
```

## 🔐 Sécurité

### Bonnes Pratiques

1. **Ne commitez jamais** vos credentials dans Git
2. Utilisez des variables d'environnement pour les credentials en production
3. Validez toujours les notifications reçues (signature)
4. Utilisez HTTPS pour les callbacks en production
5. Limitez l'accès au fichier `orange_config.php` (chmod 600)
6. Surveillez les logs pour détecter les tentatives d'intrusion

### Protection des Credentials

Créez un fichier `.env` (non versionné) :

```env
ORANGE_MERCHANT_ID=votre_merchant_id
ORANGE_MERCHANT_KEY=votre_merchant_key
```

Puis modifiez `orange_config.php` pour lire depuis `.env` :

```php
$merchant_id = getenv('ORANGE_MERCHANT_ID') ?: 'YOUR_MERCHANT_ID';
$merchant_key = getenv('ORANGE_MERCHANT_KEY') ?: 'YOUR_MERCHANT_KEY';
```

## 🐛 Dépannage

### Le paiement ne se lance pas

- ✅ Vérifiez que `simulation_mode` est correctement configuré
- ✅ Vérifiez les credentials dans `orange_config.php`
- ✅ Consultez les logs : `logs/orange_money.log`
- ✅ Vérifiez que la base de données supporte `orange_money` dans la colonne `Méthode_paiement`

### Le callback ne fonctionne pas

- ✅ Vérifiez que l'URL de callback est accessible publiquement
- ✅ Vérifiez que le serveur peut recevoir des requêtes POST d'Orange
- ✅ Consultez les logs pour voir les notifications reçues
- ✅ Vérifiez que le firewall autorise les connexions depuis Orange

### Erreur d'authentification

- ✅ Vérifiez que `merchant_id` et `merchant_key` sont corrects
- ✅ Vérifiez que votre compte Orange Money est actif
- ✅ Vérifiez que vous utilisez les bonnes URLs d'API (dev vs prod)
- ✅ Contactez le support Orange si le problème persiste

### Le champ téléphone n'apparaît pas

- ✅ Vérifiez que JavaScript est activé dans le navigateur
- ✅ Vérifiez la console du navigateur pour les erreurs
- ✅ Assurez-vous que le fichier `creer-paiement.php` a été correctement mis à jour

## 📚 Documentation Orange Money

- **Portail Développeur** : https://developer.orange.com/
- **API Web Payment** : https://developer.orange.com/apis/om-webpay
- **API Business** : https://developer.orange.com/apis/orange-money-business-api-discover
- **Support** : Contactez Orange pour obtenir de l'aide

## 💡 Notes Importantes

- Le mode simulation est parfait pour tester sans credentials réels
- En production, assurez-vous que les URLs de callback sont accessibles publiquement
- Les paiements Orange Money sont créés avec le statut "en_attente" puis mis à jour via le callback
- Les reçus sont générés automatiquement lorsque le paiement est confirmé
- Le système supporte plusieurs méthodes de paiement : Espèces, Carte, Chèque, Virement, **Orange Money**

## ✅ Checklist d'Intégration

- [ ] Base de données mise à jour (script SQL exécuté)
- [ ] Fichier `orange_config.php` configuré
- [ ] Dossier `logs/` créé avec les permissions appropriées
- [ ] Test en mode simulation réussi
- [ ] Credentials Orange Money obtenus (pour production)
- [ ] URLs de callback configurées et accessibles publiquement
- [ ] HTTPS activé (pour production)
- [ ] Tests avec l'API réelle effectués
- [ ] Documentation de l'équipe mise à jour

## 🆘 Support

Si vous rencontrez des problèmes :

1. Consultez les logs : `logs/orange_money.log`
2. Vérifiez la documentation Orange Money
3. Contactez le support Orange
4. Vérifiez que tous les fichiers sont correctement configurés

---

**Dernière mise à jour** : Janvier 2026
