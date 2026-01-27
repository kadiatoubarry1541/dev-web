<?php
/**
 * Classe pour gérer l'API Orange Money
 * Pour tester et s'entraîner avec l'API Orange Money
 */
class OrangeMoneyAPI {
    private $merchant_id;
    private $merchant_key;
    private $api_url;
    private $auth_url;
    private $callback_url;
    private $return_url;
    
    /**
     * Constructeur
     * @param array $config Configuration avec merchant_id, merchant_key, api_url, etc.
     */
    public function __construct($config = []) {
        // Configuration par défaut (sandbox/test)
        $this->merchant_id = $config['merchant_id'] ?? 'YOUR_MERCHANT_ID';
        $this->merchant_key = $config['merchant_key'] ?? 'YOUR_MERCHANT_KEY';
        // Utiliser les URLs de la config (peuvent être sandbox ou production)
        $this->api_url = $config['api_url'] ?? 'https://api.orange.com/orange-money-webpay/dev/v1/webpayment';
        $this->auth_url = $config['auth_url'] ?? 'https://api.orange.com/oauth/v2/token';
        $this->callback_url = $config['callback_url'] ?? 'http://localhost/ProjetClinique/payment/orange_callback.php';
        $this->return_url = $config['return_url'] ?? 'http://localhost/ProjetClinique/payment/orange_return.php';
    }
    
    /**
     * Générer un token d'authentification
     * @return string|false Token d'authentification ou false en cas d'erreur
     */
    public function getAuthToken() {
        try {
            // Utiliser l'URL d'auth de la config (peut être sandbox ou production)
            $url = $this->auth_url ?? 'https://api.orange.com/oauth/v2/token';
            
            $credentials = base64_encode($this->merchant_id . ':' . $this->merchant_key);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Basic ' . $credentials,
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                $data = json_decode($response, true);
                return $data['access_token'] ?? false;
            } else {
                error_log("Orange Money API - Erreur authentification: HTTP $http_code - $response");
                return false;
            }
        } catch (Exception $e) {
            error_log("Orange Money API - Exception getAuthToken: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Initier un paiement Orange Money
     * @param array $payment_data Données du paiement (montant, order_id, etc.)
     * @return array|false Réponse de l'API ou false en cas d'erreur
     */
    public function initiatePayment($payment_data) {
        try {
            $token = $this->getAuthToken();
            if (!$token) {
                return [
                    'success' => false,
                    'error' => 'Impossible d\'obtenir le token d\'authentification'
                ];
            }
            
            // Préparer les données du paiement
            $order_id = $payment_data['order_id'] ?? 'ORDER_' . time();
            $amount = floatval($payment_data['amount'] ?? 0);
            $currency = $payment_data['currency'] ?? 'GNF';
            $customer_phone = $payment_data['customer_phone'] ?? '';
            $customer_name = $payment_data['customer_name'] ?? '';
            
            // Construire les données de la requête
            $post_data = [
                'merchant_key' => $this->merchant_key,
                'currency' => $currency,
                'order_id' => $order_id,
                'amount' => $amount,
                'return_url' => $this->return_url . '?order_id=' . $order_id,
                'cancel_url' => $this->return_url . '?order_id=' . $order_id . '&cancel=1',
                'notif_url' => $this->callback_url,
                'lang' => 'fr',
                'reference' => $order_id
            ];
            
            // Si disponible, ajouter les informations client
            if ($customer_phone) {
                $post_data['customer_phone'] = $customer_phone;
            }
            if ($customer_name) {
                $post_data['customer_name'] = $customer_name;
            }
            
            // Appel API (utilise l'URL de la config)
            $api_endpoint = $this->api_url;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200 || $http_code === 201) {
                $data = json_decode($response, true);
                return [
                    'success' => true,
                    'data' => $data,
                    'payment_url' => $data['payment_url'] ?? $data['pay_token'] ?? null,
                    'order_id' => $order_id
                ];
            } else {
                error_log("Orange Money API - Erreur initiation paiement: HTTP $http_code - $response");
                return [
                    'success' => false,
                    'error' => 'Erreur lors de l\'initiation du paiement',
                    'http_code' => $http_code,
                    'response' => $response
                ];
            }
        } catch (Exception $e) {
            error_log("Orange Money API - Exception initiatePayment: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Vérifier le statut d'un paiement
     * @param string $order_id ID de la commande
     * @return array|false Statut du paiement ou false en cas d'erreur
     */
    public function checkPaymentStatus($order_id) {
        try {
            $token = $this->getAuthToken();
            if (!$token) {
                return false;
            }
            
            $url = $this->api_url . '/status/' . $order_id;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                return json_decode($response, true);
            } else {
                error_log("Orange Money API - Erreur vérification statut: HTTP $http_code - $response");
                return false;
            }
        } catch (Exception $e) {
            error_log("Orange Money API - Exception checkPaymentStatus: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Valider une notification de paiement (webhook)
     * @param array $notification_data Données de la notification
     * @return array|false Données validées ou false
     */
    public function validateNotification($notification_data) {
        try {
            // Vérifier la signature si disponible
            if (isset($notification_data['signature'])) {
                // Valider la signature selon la documentation Orange Money
                // Cette partie dépend de la méthode de signature utilisée par Orange
            }
            
            return [
                'valid' => true,
                'order_id' => $notification_data['order_id'] ?? null,
                'status' => $notification_data['status'] ?? null,
                'amount' => $notification_data['amount'] ?? null,
                'transaction_id' => $notification_data['txnid'] ?? $notification_data['transaction_id'] ?? null
            ];
        } catch (Exception $e) {
            error_log("Orange Money API - Exception validateNotification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mode simulation pour les tests (sans appeler l'API réelle)
     * @param array $payment_data Données du paiement
     * @return array Réponse simulée
     */
    public function simulatePayment($payment_data) {
        // Mode simulation pour les tests
        $order_id = $payment_data['order_id'] ?? 'ORDER_' . time();
        
        return [
            'success' => true,
            'data' => [
                'payment_url' => 'payment/orange_simulate.php?order_id=' . $order_id,
                'order_id' => $order_id,
                'status' => 'pending'
            ],
            'payment_url' => 'payment/orange_simulate.php?order_id=' . $order_id,
            'order_id' => $order_id,
            'simulation' => true
        ];
    }
}
