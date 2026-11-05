<?php
/**
 * Email Notification System
 */

require_once __DIR__ . '/../config/app.php';

class EmailService {
    private static $instance = null;
    private $config;
    
    private function __construct() {
        $this->config = [
            'host'       => SMTP_HOST,
            'port'       => SMTP_PORT,
            'username'   => SMTP_USERNAME,
            'password'   => SMTP_PASSWORD,
            'from_email' => SMTP_FROM_EMAIL,   // was hardcoded — now uses constant
            'from_name'  => SMTP_FROM_NAME
        ];
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Send email via PHPMailer (SMTP) with fallback to native mail().
     *
     * Configure SMTP in config/app.php using the SMTP_* constants.
     * For Gmail: SMTP_HOST=smtp.gmail.com, SMTP_PORT=587,
     *            SMTP_USERNAME=you@gmail.com, SMTP_PASSWORD=<app-password>
     */
    private function sendMail(string $to, string $subject, string $body, bool $isHtml = false): bool {
        $phpmailerPath = __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';

        // ── PHPMailer path ──────────────────────────────────────────────
        if (file_exists($phpmailerPath) && !empty($this->config['username'])) {
            require_once $phpmailerPath;
            require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
            require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = $this->config['host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $this->config['username'];
                $mail->Password   = $this->config['password'];
                $mail->SMTPSecure = ($this->config['port'] == 465)
                    ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                    : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = (int) $this->config['port'];

                $mail->setFrom($this->config['from_email'], $this->config['from_name']);
                $mail->addAddress($to);
                $mail->Subject    = $subject;
                $mail->CharSet    = 'UTF-8';

                if ($isHtml) {
                    $mail->isHTML(true);
                    $mail->Body    = $body;
                    $mail->AltBody = strip_tags($body);
                } else {
                    $mail->Body = $body;
                }

                $mail->send();
                error_log("[EmailService][SMTP] Sent '{$subject}' to {$to}");
                return true;

            } catch (\Exception $e) {
                error_log("[EmailService][SMTP] FAILED '{$subject}' to {$to}: " . $e->getMessage());
                // Fall through to native mail() below
            }
        }

        // ── Native mail() fallback ──────────────────────────────────────
        $fromName  = $this->config['from_name'];
        $fromEmail = $this->config['from_email'];

        $headers = [
            "From: {$fromName} <{$fromEmail}>",
            "Reply-To: {$fromEmail}",
            'X-Mailer: PHP/' . phpversion(),
        ];

        if ($isHtml) {
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }

        $result = mail($to, $subject, $body, implode("\r\n", $headers));
        error_log("[EmailService][mail()] " . ($result ? 'Queued' : 'FAILED') . " '{$subject}' to {$to}");
        return $result;
    }

    /**
     * Send contact-form message to the store admin.
     */
    public function sendContactMessage(string $senderName, string $senderEmail, string $msgSubject, string $message): bool {
        $adminEmail = $this->config['from_email'];
        $subject    = "[Contact Form] " . ($msgSubject ?: 'Message from ' . $senderName);
        $body       = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #667eea;'>New Contact Form Submission</h2>
                <div style='background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 16px 0;'>
                    <p><strong>Name:</strong> " . htmlspecialchars($senderName) . "</p>
                    <p><strong>Email:</strong> <a href='mailto:{$senderEmail}'>{$senderEmail}</a></p>
                    <p><strong>Subject:</strong> " . htmlspecialchars($msgSubject) . "</p>
                </div>
                <div style='background: #fff; border: 1px solid #e0e0e0; padding: 20px; border-radius: 8px;'>
                    <h4>Message:</h4>
                    <p>" . nl2br(htmlspecialchars($message)) . "</p>
                </div>
                <p style='color: #888; font-size: 12px; margin-top: 20px;'>
                    Reply directly to <a href='mailto:{$senderEmail}'>{$senderEmail}</a>.
                </p>
            </div>
        ";

        // Also send auto-reply to the sender
        $autoReply = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #28a745;'>Message Received!</h2>
                <p>Hi " . htmlspecialchars($senderName) . ",</p>
                <p>Thank you for contacting JV Shetty Campus Store. We have received your message and will get back to you within 24 hours.</p>
                <div style='background: #f5f5f5; padding: 16px; border-radius: 8px; margin: 16px 0;'>
                    <p><strong>Your message:</strong></p>
                    <p>" . nl2br(htmlspecialchars($message)) . "</p>
                </div>
                <p style='color: #888; font-size: 12px;'>This is an automated confirmation. Please do not reply to this email.</p>
            </div>
        ";

        $sent = $this->sendMail($adminEmail, $subject, $body, true);
        $this->sendMail($senderEmail, 'We received your message – JV Shetty', $autoReply, true);
        return $sent;
    }
    
    /**
     * Send order confirmation email
     */
    public function sendOrderConfirmation($orderId) {
        $db = Database::getInstance();
        
        // Get order details
        $stmt = $db->prepare("
            SELECT o.*, u.email, p.first_name, p.last_name
            FROM orders o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN user_profiles p ON u.id = p.user_id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if (!$order) return false;
        
        // Get order items
        $stmt = $db->prepare("
            SELECT oi.*, pr.name
            FROM order_items oi
            JOIN products pr ON oi.product_id = pr.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll();
        
        $subject = "Order Confirmation - #" . $order['order_number'];
        $body = $this->getOrderConfirmationTemplate($order, $items);
        
        return $this->sendMail($order['email'], $subject, $body, true);
    }
    
    /**
     * Send order status update email
     */
    public function sendOrderStatusUpdate($orderId, $newStatus, $notes = '') {
        $db = Database::getInstance();
        
        $stmt = $db->prepare("
            SELECT o.*, u.email, p.first_name, p.last_name
            FROM orders o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN user_profiles p ON u.id = p.user_id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if (!$order) return false;
        
        $statusLabels = [
            'confirmed' => 'Order Confirmed',
            'packed' => 'Order Packed',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Order Delivered',
            'picked_up' => 'Order Picked Up',
            'cancelled' => 'Order Cancelled'
        ];
        
        $subject = "Order Update - #" . $order['order_number'];
        $body = $this->getOrderStatusTemplate($order, $statusLabels[$newStatus] ?? $newStatus, $notes);
        
        return $this->sendMail($order['email'], $subject, $body, true);
    }
    
    /**
     * Send low stock alert to admin
     */
    public function sendLowStockAlert($productId, $currentStock, $reorderLevel) {
        $db = Database::getInstance();
        
        $stmt = $db->prepare("SELECT name, sku FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) return false;
        
        $subject = "Low Stock Alert - " . $product['name'];
        $body = $this->getLowStockTemplate($product, $currentStock, $reorderLevel);
        
        return $this->sendMail($this->config['from_email'], $subject, $body, true);
    }
    
    /**
     * Send welcome email
     */
    public function sendWelcomeEmail($email, $firstName) {
        $subject = "Welcome to JV Shetty Campus Store";
        $body = $this->getWelcomeTemplate($firstName);
        
        return $this->sendMail($email, $subject, $body, true);
    }
    
    /**
     * Order confirmation template
     */
    private function getOrderConfirmationTemplate($order, $items) {
        $itemsHtml = '';
        foreach ($items as $item) {
            $itemsHtml .= "
                <tr>
                    <td>{$item['name']}</td>
                    <td>{$item['quantity']}</td>
                    <td>₹" . number_format($item['unit_price'], 2) . "</td>
                    <td>₹" . number_format($item['total_price'], 2) . "</td>
                </tr>
            ";
        }
        
        return "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #333;'>Order Confirmation</h2>
                <p>Thank you for your order! Your order has been received and is being processed.</p>
                
                <div style='background: #f5f5f5; padding: 20px; margin: 20px 0;'>
                    <h3>Order Details</h3>
                    <p><strong>Order Number:</strong> {$order['order_number']}</p>
                    <p><strong>Date:</strong> " . date('j F Y, g:i A', strtotime($order['created_at'])) . "</p>
                    <p><strong>Total Amount:</strong> ₹" . number_format($order['total_amount'], 2) . "</p>
                </div>
                
                <h3>Order Items</h3>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr style='background: #f0f0f0;'>
                        <th style='padding: 10px; text-align: left;'>Product</th>
                        <th style='padding: 10px; text-align: center;'>Quantity</th>
                        <th style='padding: 10px; text-align: right;'>Price</th>
                        <th style='padding: 10px; text-align: right;'>Total</th>
                    </tr>
                    $itemsHtml
                </table>
                
                <div style='margin-top: 30px; padding: 20px; background: #e8f5e8; border-left: 4px solid #28a745;'>
                    <h4>Next Steps</h4>
                    <p>You will receive email updates as your order progresses through our system.</p>
                    <p>For any questions, please contact our support team.</p>
                </div>
                
                <p style='margin-top: 30px; color: #666; font-size: 12px;'>
                    This is an automated message. Please do not reply to this email.
                </p>
            </div>
        ";
    }
    
    /**
     * Order status update template
     */
    private function getOrderStatusTemplate($order, $status, $notes) {
        return "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #333;'>Order Status Update</h2>
                <p>Your order status has been updated.</p>
                
                <div style='background: #f5f5f5; padding: 20px; margin: 20px 0;'>
                    <h3>Order Details</h3>
                    <p><strong>Order Number:</strong> {$order['order_number']}</p>
                    <p><strong>New Status:</strong> <span style='color: #28a745; font-weight: bold;'>{$status}</span></p>
                    <p><strong>Date:</strong> " . date('j F Y, g:i A') . "</p>
                </div>
                
                " . ($notes ? "<div style='background: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                    <h4>Additional Information</h4>
                    <p>{$notes}</p>
                </div>" : "") . "
                
                <p style='margin-top: 30px; color: #666; font-size: 12px;'>
                    This is an automated message. Please do not reply to this email.
                </p>
            </div>
        ";
    }
    
    /**
     * Low stock alert template
     */
    private function getLowStockTemplate($product, $currentStock, $reorderLevel) {
        return "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #dc3545;'>Low Stock Alert</h2>
                <p>The following product is running low on stock and needs to be reordered.</p>
                
                <div style='background: #f8d7da; padding: 20px; margin: 20px 0; border-left: 4px solid #dc3545;'>
                    <h3>Product Information</h3>
                    <p><strong>Name:</strong> {$product['name']}</p>
                    <p><strong>SKU:</strong> {$product['sku']}</p>
                    <p><strong>Current Stock:</strong> {$currentStock}</p>
                    <p><strong>Reorder Level:</strong> {$reorderLevel}</p>
                </div>
                
                <p>Please update the inventory as soon as possible to avoid stockouts.</p>
                
                <p style='margin-top: 30px; color: #666; font-size: 12px;'>
                    This is an automated message from the inventory management system.
                </p>
            </div>
        ";
    }
    
    /**
     * Welcome email template
     */
    private function getWelcomeTemplate($firstName) {
        return "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #28a745;'>Welcome to JV Shetty Campus Store!</h2>
                <p>Hi " . htmlspecialchars($firstName) . ",</p>
                <p>Thank you for registering with JV Shetty Campus Store. We're excited to serve you!</p>
                
                <div style='background: #e8f5e8; padding: 20px; margin: 20px 0; border-left: 4px solid #28a745;'>
                    <h3>Getting Started</h3>
                    <ul>
                        <li>Browse our wide range of products</li>
                        <li>Place orders between 4 PM and 9 PM</li>
                        <li>Choose delivery or pickup options</li>
                        <li>Track your orders in real-time</li>
                    </ul>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='" . BASE_URL . "' style='background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                        Start Shopping
                    </a>
                </div>
                
                <p style='margin-top: 30px; color: #666; font-size: 12px;'>
                    This is an automated message. Please do not reply to this email.
                </p>
            </div>
        ";
    }

    // ====================================================================
    // PASSWORD RESET
    // ====================================================================

    /**
     * Send a password reset link email.
     */
    public function sendPasswordResetEmail(string $email, string $rawToken): bool {
        $resetUrl = BASE_URL . '/reset_password.php?token=' . urlencode($rawToken);
        $subject  = 'Reset Your JV Shetty Password';
        $body     = $this->buildPasswordResetEmail($resetUrl);
        return $this->sendMail($email, $subject, $body, true);
    }

    private function buildPasswordResetEmail(string $resetUrl): string {
        return "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #667eea;'>Password Reset Request</h2>
                <p>We received a request to reset the password for your JV Shetty account.</p>
                <p>Click the button below to set a new password. This link is valid for <strong>1 hour</strong>.</p>

                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$resetUrl}'
                       style='background: #667eea; color: white; padding: 14px 36px; text-decoration: none;
                              border-radius: 8px; display: inline-block; font-size: 16px;'>
                        Reset My Password
                    </a>
                </div>

                <p style='color: #888; font-size: 13px;'>
                    If you did not request this, you can safely ignore this email.
                    Your password will not change.
                </p>
                <p style='color: #888; font-size: 12px;'>
                    Or copy this link: <a href='{$resetUrl}'>{$resetUrl}</a>
                </p>
            </div>
        ";
    }

    // ====================================================================
    // EMAIL VERIFICATION
    // ====================================================================

    /**
     * Send email address verification link.
     */
    public function sendEmailVerification(string $email, string $firstName, string $rawToken): bool {
        $verifyUrl = BASE_URL . '/verify_email.php?token=' . urlencode($rawToken);
        $subject   = 'Verify Your JV Shetty Email Address';
        $body      = $this->buildVerificationEmail($firstName, $verifyUrl);
        return $this->sendMail($email, $subject, $body, true);
    }

    private function buildVerificationEmail(string $firstName, string $verifyUrl): string {
        return "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #28a745;'>Verify Your Email Address</h2>
                <p>Hi " . htmlspecialchars($firstName) . ",</p>
                <p>Thanks for joining JV Shetty Campus Store! Please verify your email address to activate your account.</p>

                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$verifyUrl}'
                       style='background: #28a745; color: white; padding: 14px 36px; text-decoration: none;
                              border-radius: 8px; display: inline-block; font-size: 16px;'>
                        Verify Email Address
                    </a>
                </div>

                <p style='color: #888; font-size: 13px;'>
                    If you did not create an account, please ignore this email.
                </p>
                <p style='color: #888; font-size: 12px;'>
                    Or copy this link: <a href='{$verifyUrl}'>{$verifyUrl}</a>
                </p>
            </div>
        ";
    }
}
