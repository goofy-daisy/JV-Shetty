<?php
/**
 * Coupon API Endpoint
 * POST api/coupons.php?action=apply  { coupon_code, cart_id }
 * POST api/coupons.php?action=remove { cart_id }
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Cart.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {
    case 'apply':  handleApply($input);  break;
    case 'remove': handleRemove($input); break;
    case 'validate': handleValidate($input); break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}

// -----------------------------------------------------------------------

function handleApply(array $input): void {
    $code   = strtoupper(trim($input['coupon_code'] ?? ''));
    $cartId = (int)($input['cart_id'] ?? 0);

    if (empty($code) || $cartId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Coupon code and cart ID are required']);
        return;
    }

    $result = validateCouponCode($code, $cartId);

    if (!$result['valid']) {
        echo json_encode(['success' => false, 'error' => $result['message']]);
        return;
    }

    // Apply coupon code to cart record
    $db   = Database::getInstance();
    $stmt = $db->prepare("UPDATE carts SET coupon_code = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$code, $cartId, Auth::user()['id']]);

    // Update discount_amount and recalculate total directly
    $subtotalStmt = $db->prepare("SELECT subtotal, tax_amount, delivery_fee FROM carts WHERE id = ?");
    $subtotalStmt->execute([$cartId]);
    $cartRow = $subtotalStmt->fetch();
    $newTotal = ($cartRow['subtotal'] ?? 0) + ($cartRow['tax_amount'] ?? 0)
              + ($cartRow['delivery_fee'] ?? 0) - $result['discount'];
    $db->prepare("UPDATE carts SET discount_amount = ?, total_amount = ? WHERE id = ?")
       ->execute([$result['discount'], max(0, $newTotal), $cartId]);

    $updatedCart = (new Cart())->find($cartId);
    echo json_encode([
        'success'  => true,
        'message'  => 'Coupon applied! You saved ₹' . number_format($result['discount'], 2),
        'discount' => $result['discount'],
        'totals'   => [
            'subtotal'     => $updatedCart['subtotal']        ?? 0,
            'discount'     => $updatedCart['discount_amount'] ?? 0,
            'delivery_fee' => $updatedCart['delivery_fee']    ?? 0,
            'tax'          => $updatedCart['tax_amount']      ?? 0,
            'total'        => $updatedCart['total_amount']    ?? 0,
        ]
    ]);
}

function handleRemove(array $input): void {
    $cartId = (int)($input['cart_id'] ?? 0);
    if ($cartId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Cart ID required']);
        return;
    }

    $db   = Database::getInstance();
    $stmt = $db->prepare("UPDATE carts SET coupon_code = NULL, discount_amount = 0 WHERE id = ? AND user_id = ?");
    $stmt->execute([$cartId, Auth::user()['id']]);

    // Recalculate total without discount
    $cartRow = $db->prepare("SELECT subtotal, tax_amount, delivery_fee FROM carts WHERE id = ?");
    $cartRow->execute([$cartId]);
    $row = $cartRow->fetch();
    $newTotal = ($row['subtotal'] ?? 0) + ($row['tax_amount'] ?? 0) + ($row['delivery_fee'] ?? 0);
    $db->prepare("UPDATE carts SET total_amount = ? WHERE id = ?")->execute([$newTotal, $cartId]);

    echo json_encode(['success' => true, 'message' => 'Coupon removed']);
}

function handleValidate(array $input): void {
    $code   = strtoupper(trim($input['coupon_code'] ?? ''));
    $cartId = (int)($input['cart_id'] ?? 0);

    $result = validateCouponCode($code, $cartId);
    if ($result['valid']) {
        echo json_encode(['success' => true, 'discount' => $result['discount'], 'message' => $result['message']]);
    } else {
        echo json_encode(['success' => false, 'error' => $result['message']]);
    }
}

// -----------------------------------------------------------------------

function validateCouponCode(string $code, int $cartId): array {
    if (empty($code)) {
        return ['valid' => false, 'message' => 'Please enter a coupon code'];
    }

    $db = Database::getInstance();

    // Fetch coupon
    $stmt = $db->prepare("
        SELECT * FROM coupons
        WHERE  code      = ?
          AND  is_active = 1
          AND  (valid_from IS NULL OR valid_from <= NOW())
          AND  (expires_at IS NULL OR expires_at >= NOW())
          AND  (max_uses   IS NULL OR used_count < max_uses)
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        return ['valid' => false, 'message' => 'Invalid or expired coupon code'];
    }

    // Get cart subtotal
    $stmt = $db->prepare("SELECT subtotal FROM carts WHERE id = ? AND user_id = ?");
    $stmt->execute([$cartId, Auth::user()['id']]);
    $cart = $stmt->fetch();

    if (!$cart) {
        return ['valid' => false, 'message' => 'Cart not found'];
    }

    $subtotal = (float)$cart['subtotal'];

    if ($subtotal < (float)$coupon['min_order_amount']) {
        return [
            'valid'   => false,
            'message' => 'Minimum order of ₹' . number_format($coupon['min_order_amount'], 0) . ' required for this coupon'
        ];
    }

    // Calculate discount
    $discount = 0;
    if ($coupon['type'] === 'discount_percent') {
        $discount = $subtotal * ($coupon['value'] / 100);
        if ($coupon['max_discount']) {
            $discount = min($discount, (float)$coupon['max_discount']);
        }
    } else {
        $discount = (float)$coupon['value'];
    }

    return [
        'valid'    => true,
        'discount' => round($discount, 2),
        'message'  => 'Coupon valid! ' . htmlspecialchars($coupon['description'] ?? '')
    ];
}
