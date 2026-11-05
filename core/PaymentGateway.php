<?php
/**
 * Payment Gateway Integration (Placeholder)
 */

require_once __DIR__ . '/../config/app.php';

class PaymentGateway {
    private static $instance = null;
    private $config;
    
    private function __construct() {
        $this->config = [
            'gateway' => PAYMENT_GATEWAY, // 'razorpay', 'stripe', 'paypal'
            'key' => PAYMENT_GATEWAY_KEY,
            'secret' => PAYMENT_GATEWAY_SECRET,
            'webhook_secret' => PAYMENT_WEBHOOK_SECRET,
            'test_mode' => defined('PAYMENT_TEST_MODE') && PAYMENT_TEST_MODE
        ];
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Create payment order
     */
    public function createOrder($orderId, $amount, $currency = 'INR', $receipt = null) {
        $orderData = [
            'amount' => $amount * 100, // Convert to paise/cents
            'currency' => $currency,
            'receipt' => $receipt ?: "order_$orderId",
            'notes' => [
                'order_id' => $orderId,
                'merchant_order_id' => $orderId
            ]
        ];
        
        // Log payment attempt
        $this->logPayment('create_order', $orderId, $orderData);
        
        // Return mock response for development
        if ($this->config['test_mode']) {
            return [
                'success' => true,
                'order_id' => 'test_order_' . time(),
                'amount' => $amount,
                'currency' => $currency,
                'key' => $this->config['key']
            ];
        }
        
        // Implement actual gateway integration here
        return $this->createRazorpayOrder($orderData);
    }
    
    /**
     * Verify payment signature
     */
    public function verifyPayment($orderId, $paymentId, $signature) {
        $data = $orderId . '|' . $paymentId;
        $expectedSignature = hash_hmac('sha256', $data, $this->config['secret']);
        
        $isValid = hash_equals($expectedSignature, $signature);
        
        $this->logPayment('verify_signature', $orderId, [
            'payment_id' => $paymentId,
            'signature' => $signature,
            'valid' => $isValid
        ]);
        
        return $isValid;
    }
    
    /**
     * Process refund
     */
    public function processRefund($paymentId, $amount, $reason = '') {
        $refundData = [
            'payment_id' => $paymentId,
            'amount' => $amount * 100, // Convert to paise/cents
            'notes' => [
                'reason' => $reason
            ]
        ];
        
        $this->logPayment('refund', $paymentId, $refundData);
        
        // Return mock response for development
        if ($this->config['test_mode']) {
            return [
                'success' => true,
                'refund_id' => 'test_refund_' . time(),
                'amount' => $amount,
                'status' => 'processed'
            ];
        }
        
        return $this->createRazorpayRefund($refundData);
    }
    
    /**
     * Get payment details
     */
    public function getPaymentDetails($paymentId) {
        $this->logPayment('get_details', $paymentId);
        
        // Return mock response for development
        if ($this->config['test_mode']) {
            return [
                'id' => $paymentId,
                'amount' => 10000, // ₹100 in paise
                'currency' => 'INR',
                'status' => 'captured',
                'method' => 'card',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        return $this->getRazorpayPayment($paymentId);
    }
    
    /**
     * Razorpay specific methods (to be implemented with actual SDK)
     */
    private function createRazorpayOrder($orderData) {
        // Placeholder for Razorpay SDK integration
        // $razorpay = new Razorpay\Api\Api($this->config['key'], $this->config['secret']);
        // return $razorpay->order->create($orderData);
        
        return [
            'success' => false,
            'error' => 'Razorpay SDK not integrated'
        ];
    }
    
    private function createRazorpayRefund($refundData) {
        // Placeholder for Razorpay SDK integration
        // $razorpay = new Razorpay\Api\Api($this->config['key'], $this->config['secret']);
        // return $razorpay->refund->create($refundData);
        
        return [
            'success' => false,
            'error' => 'Razorpay SDK not integrated'
        ];
    }
    
    private function getRazorpayPayment($paymentId) {
        // Placeholder for Razorpay SDK integration
        // $razorpay = new Razorpay\Api\Api($this->config['key'], $this->config['secret']);
        // return $razorpay->payment->fetch($paymentId);
        
        return [
            'success' => false,
            'error' => 'Razorpay SDK not integrated'
        ];
    }
    
    /**
     * Log payment activities
     */
    private function logPayment($action, $reference, $data = []) {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO payment_logs (action, reference_id, request_data, response_data, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $action,
            $reference,
            json_encode($data),
            json_encode(['timestamp' => date('Y-m-d H:i:s')])
        ]);
    }
    
    /**
     * Handle webhook notifications
     */
    public function handleWebhook() {
        $webhookSecret = $this->config['webhook_secret'];
        $signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
        $payload = file_get_contents('php://input');
        
        // Verify webhook signature
        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
        if (!hash_equals($expectedSignature, $signature)) {
            http_response_code(401);
            exit('Unauthorized');
        }
        
        $event = json_decode($payload, true);
        $this->logPayment('webhook', $event['id'] ?? 'unknown', $event);
        
        // Process webhook events
        switch ($event['event'] ?? '') {
            case 'payment.captured':
                $this->processPaymentSuccess($event['payload']['payment']['entity']);
                break;
                
            case 'payment.failed':
                $this->processPaymentFailure($event['payload']['payment']['entity']);
                break;
                
            case 'refund.processed':
                $this->processRefundSuccess($event['payload']['refund']['entity']);
                break;
        }
        
        http_response_code(200);
        echo 'Webhook processed';
    }
    
    /**
     * Process successful payment
     */
    private function processPaymentSuccess($payment) {
        $db = Database::getInstance();
        
        // Get order from payment notes
        $orderId = $payment['notes']['order_id'] ?? null;
        if (!$orderId) return;
        
        // Update payment record
        $stmt = $db->prepare("
            UPDATE payments 
            SET status = 'completed', transaction_id = ?, gateway_response = ?, processed_at = NOW()
            WHERE order_id = ?
        ");
        $stmt->execute([
            $payment['id'],
            json_encode($payment),
            $orderId
        ]);
        
        // Update order status
        $stmt = $db->prepare("
            UPDATE orders 
            SET status = 'paid', payment_status = 'completed'
            WHERE id = ? AND status = 'payment_pending'
        ");
        $stmt->execute([$orderId]);
        
        // Send confirmation email
        $emailService = EmailService::getInstance();
        $emailService->sendOrderConfirmation($orderId);
    }
    
    /**
     * Process payment failure
     */
    private function processPaymentFailure($payment) {
        $db = Database::getInstance();
        
        $orderId = $payment['notes']['order_id'] ?? null;
        if (!$orderId) return;
        
        // Update payment record
        $stmt = $db->prepare("
            UPDATE payments 
            SET status = 'failed', gateway_response = ?, processed_at = NOW()
            WHERE order_id = ?
        ");
        $stmt->execute([
            json_encode($payment),
            $orderId
        ]);
        
        // Update order status
        $stmt = $db->prepare("
            UPDATE orders 
            SET status = 'payment_failed', payment_status = 'failed'
            WHERE id = ? AND status = 'payment_pending'
        ");
        $stmt->execute([$orderId]);
    }
    
    /**
     * Process successful refund
     */
    private function processRefundSuccess($refund) {
        $db = Database::getInstance();
        
        $paymentId = $refund['payment_id'] ?? null;
        if (!$paymentId) return;
        
        // Create refund record
        $stmt = $db->prepare("
            INSERT INTO refunds (payment_id, amount, reason, status, gateway_response, created_at)
            VALUES (?, ?, ?, 'completed', ?, NOW())
        ");
        $stmt->execute([
            $paymentId,
            $refund['amount'] / 100, // Convert back to rupees
            $refund['notes']['reason'] ?? 'Customer request',
            json_encode($refund)
        ]);
        
        // Update payment status
        $stmt = $db->prepare("
            UPDATE payments 
            SET status = 'refunded'
            WHERE transaction_id = ?
        ");
        $stmt->execute([$paymentId]);
        
        // Update order status
        $stmt = $db->prepare("
            UPDATE orders 
            SET status = 'refunded'
            WHERE id = (SELECT order_id FROM payments WHERE transaction_id = ?)
        ");
        $stmt->execute([$paymentId]);
    }
}
?>
