<?php
/**
 * Configuration Orange Money API
 * 
 * IMPORTANT: Pour utiliser l'API Orange Money en production, vous devez :
 * 1. Contacter Orange pour obtenir vos credentials (merchant_id, merchant_key)
 * 2. Vous inscrire comme marchand Orange Money
 * 3. Obtenir l'accès à l'API depuis le portail développeur Orange
 * 
 * Pour les tests, utilisez le mode simulation
 */

return [
    // Mode simulation (true) ou API réelle (false)
    // Mettez à false quand vous avez vos credentials du portail Orange Developer
    'simulation_mode' => true,
    
    // ============================================
    // CREDENTIALS ORANGE MONEY
    // ============================================
    // Pour obtenir ces credentials :
    // 1. Allez sur https://developer.orange.com
    // 2. Créez un compte (gratuit)
    // 3. Créez une application
    // 4. Souscrivez à Orange Money API (plan Sandbox/Test - gratuit)
    // 5. Copiez votre Client ID et Client Secret ici
    // ============================================
    'merchant_id' => 'YOUR_MERCHANT_ID',      // Remplacez par votre Client ID du portail Orange
    'merchant_key' => 'YOUR_MERCHANT_KEY',    // Remplacez par votre Client Secret du portail Orange
    
    // URLs de l'API Orange Money
    // Pour Sandbox/Test, utilisez les URLs fournies par Orange dans leur documentation
    // Exemple pour sandbox :
    // 'api_url' => 'https://api-sandbox.orange.com/orange-money-webpay/v1/webpayment',
    // 'auth_url' => 'https://api-sandbox.orange.com/oauth/v2/token',
    // Pour Production (quand vous serez prêt) :
    'api_url' => 'https://api.orange.com/orange-money-webpay/dev/v1/webpayment',
    'auth_url' => 'https://api.orange.com/oauth/v2/token',
    
    // URLs de callback (à adapter selon votre domaine)
    'callback_url' => 'http://localhost/ProjetClinique/payment/orange_callback.php',
    'return_url' => 'http://localhost/ProjetClinique/payment/orange_return.php',
    
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
