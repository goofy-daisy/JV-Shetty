<?php
/**
 * Payment Webhook Handler
 * Dedicated endpoint for Razorpay webhook notifications.
 * Route: POST /api/webhook.php
 *
 * Configure this URL in your Razorpay dashboard:
 *   https://yourdomain.com/jv_shetty/api/webhook.php
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Product.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

try {
    $gateway = PaymentGateway::getInstance();
    $gateway->handleWebhook();
} catch (Exception $e) {
    error_log('[Webhook] Error: ' . $e->getMessage());
    http_response_code(500);
    exit('Internal Server Error');
}
