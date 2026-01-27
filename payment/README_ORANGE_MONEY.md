# Intégration Orange Money API

Ce guide explique comment utiliser l'API Orange Money pour les paiements dans le système.

## 📋 Prérequis

1. **Compte Orange Money Merchant** : Vous devez être enregistré comme marchand Orange Money
2. **Credentials API** : Obtenir `merchant_id` et `merchant_key` depuis Orange
3. **Accès au portail développeur** : https://developer.orange.com/

## 🔧 Configuration

### 1. Mettre à jour la base de données

Exécutez le script SQL pour ajouter la méthode "orange_money" :

```sql
-- Exécuter payment/add_orange_money_method.sql
```

Ou manuellement :

```sql
ALTER TABLE PAIEMENT 
MODIFY COLUMN Méthode_paiement ENUM('espèces', 'carte', 'chèque', 'virement', 'orange_money') DEFAULT 'espèces';

ALTER TABLE PAIEMENT 
ADD COLUMN IF NOT EXISTS orange_order_id VARCHAR(100) NULL AFTER id_facture;
```

### 2. Configurer les credentials

Éditez le fichier `payment/orange_config.php` :

```php
return [
    'simulation_mode' => false,  // Mettre à false pour l'API réelle
    'merchant_id' => 'VOTRE_MERCHANT_ID',
    'merchant_key' => 'VOTRE_MERCHANT_KEY',
    // ... autres configurations
];
```

## 🧪 Mode Simulation (Tests)

Par défaut, le système est en **mode simulation** pour les tests sans credentials réels.

### Utilisation du mode simulation :

1. Dans `orange_config.php`, gardez `simulation_mode => true`
2. Créez un paiement avec la méthode "Orange Money"
3. Entrez le numéro de téléphone du patient
4. Vous serez redirigé vers la page de simulation
5. Cliquez sur "Simuler Paiement Réussi" ou "Simuler Annulation"

## 🚀 Utilisation en Production

### 1. Obtenir les credentials Orange Money

Contactez Orange pour :
- Obtenir votre `merchant_id`
- Obtenir votre `merchant_key`
- Activer l'accès à l'API

### 2. Configurer l'API

1. Mettez à jour `orange_config.php` avec vos vraies credentials
2. Mettez `simulation_mode => false`
3. Configurez les URLs de callback :
   - `callback_url` : URL publique accessible par Orange (webhook)
   - `return_url` : URL de retour après paiement

### 3. Tester avec l'API réelle

1. Utilisez l'environnement de test/sandbox d'Orange
2. Testez avec de petits montants
3. Vérifiez les callbacks et retours

## 📱 Flux de Paiement Orange Money

```
1. Patient sélectionne "Orange Money" dans le formulaire
2. Entrée du numéro de téléphone Orange Money
3. Soumission → orange_process.php
4. Initiation du paiement via API Orange Money
5. Redirection vers la page de paiement Orange Money
6. Patient effectue le paiement
7. Orange envoie notification → orange_callback.php (webhook)
8. Redirection patient → orange_return.php
9. Mise à jour du statut du paiement
10. Génération automatique du reçu si payé
```

## 📁 Fichiers du Système Orange Money

- **orange_money_api.php** : Classe pour gérer les appels API
- **orange_config.php** : Configuration (credentials, URLs)
- **orange_process.php** : Traitement de l'initiation du paiement
- **orange_callback.php** : Webhook pour recevoir les notifications Orange
- **orange_return.php** : Page de retour après paiement
- **orange_simulate.php** : Page de simulation pour les tests

## 🔐 Sécurité

1. **Ne commitez jamais** vos credentials dans Git
2. Utilisez des variables d'environnement pour les credentials en production
3. Validez toujours les notifications reçues (signature)
4. Utilisez HTTPS pour les callbacks en production

## 🐛 Dépannage

### Le paiement ne se lance pas

- Vérifiez que `simulation_mode` est correctement configuré
- Vérifiez les credentials dans `orange_config.php`
- Consultez les logs : `logs/orange_money.log`

### Le callback ne fonctionne pas

- Vérifiez que l'URL de callback est accessible publiquement
- Vérifiez que le serveur peut recevoir des requêtes POST d'Orange
- Consultez les logs pour voir les notifications reçues

### Erreur d'authentification

- Vérifiez que `merchant_id` et `merchant_key` sont corrects
- Vérifiez que votre compte Orange Money est actif
- Contactez le support Orange si le problème persiste

## 📚 Documentation Orange Money

- **Portail Développeur** : https://developer.orange.com/
- **API Web Payment** : https://developer.orange.com/apis/om-webpay
- **API Business** : https://developer.orange.com/apis/orange-money-business-api-discover

## 💡 Notes Importantes

- Le mode simulation est parfait pour tester sans credentials réels
- En production, assurez-vous que les URLs de callback sont accessibles publiquement
- Les paiements Orange Money sont créés avec le statut "en_attente" puis mis à jour via le callback
- Les reçus sont générés automatiquement lorsque le paiement est confirmé

## 🔄 Migration depuis les autres méthodes

Le système supporte plusieurs méthodes de paiement :
- Espèces
- Carte bancaire
- Chèque
- Virement
- **Orange Money** (nouveau)

Toutes les méthodes utilisent la même table `PAIEMENT` et le même système de gestion.
