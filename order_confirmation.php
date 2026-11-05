<?php
/**
 * Order Confirmation Page
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/models/Order.php';

Auth::requireAuth();

$orderId   = (int)($_GET['order_id'] ?? 0);
$order     = new Order();
$orderData = $order->getWithDetails($orderId);

if (!$orderData || $orderData['user_id'] != Auth::user()['id']) {
    header('Location: orders.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed – <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-50">

    <?php require_once __DIR__ . '/includes/nav.php'; ?>

    <div class="page-content">
        <div class="min-h-screen flex items-center justify-center py-12 px-4">
            <div class="max-w-lg w-full text-center">

                <!-- Success Icon -->
                <div class="flex justify-center mb-6">
                    <div class="w-24 h-24 rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center shadow-2xl shadow-emerald-200">
                        <i class="bi bi-check-lg text-white text-5xl"></i>
                    </div>
                </div>

                <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Order Placed Successfully!</h1>
                <p class="text-gray-500 mb-8 text-sm">Thank you! We've received your order and will process it shortly.</p>

                <!-- Summary Card -->
                <div class="card-modern p-6 text-left mb-6">
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Order Number</p>
                            <p class="font-bold text-gray-900"><?php echo htmlspecialchars($orderData['order_number']); ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-500 mb-1">Total Amount</p>
                            <p class="font-extrabold text-purple-700 text-lg">₹<?php echo number_format($orderData['total_amount'], 2); ?></p>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">
                            <?php echo $orderData['fulfillment_type'] === 'delivery' ? 'Delivery Address' : 'Pickup Details'; ?>
                        </p>
                        <?php if ($orderData['fulfillment_type'] === 'delivery'): ?>
                            <p class="text-sm text-gray-700 font-semibold"><?php echo htmlspecialchars($orderData['hostel_name'] ?? ''); ?></p>
                            <p class="text-sm text-gray-600">Room <?php echo htmlspecialchars($orderData['room_number'] ?? ''); ?></p>
                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($orderData['address_line1'] ?? ''); ?></p>
                        <?php else: ?>
                            <p class="text-sm text-gray-700 font-semibold">JV Shetty Store</p>
                            <p class="text-sm text-gray-500">BITS Pilani Goa Campus</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="order_detail.php?id=<?php echo $orderData['id']; ?>"
                       class="flex-1 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold rounded-xl text-sm hover:from-purple-700 hover:to-indigo-700 shadow-md transition-all flex items-center justify-center gap-2">
                        <i class="bi bi-eye"></i>View Order Details
                    </a>
                    <a href="orders.php"
                       class="flex-1 py-3 border-2 border-gray-200 text-gray-700 font-semibold rounded-xl text-sm hover:border-purple-300 hover:text-purple-700 transition-all flex items-center justify-center gap-2">
                        <i class="bi bi-bag"></i>My Orders
                    </a>
                    <a href="index.php"
                       class="flex-1 py-3 border-2 border-gray-200 text-gray-700 font-semibold rounded-xl text-sm hover:border-purple-300 hover:text-purple-700 transition-all flex items-center justify-center gap-2">
                        <i class="bi bi-shop"></i>Continue Shopping
                    </a>
                </div>

                <p class="text-xs text-gray-400 mt-6 flex items-center justify-center gap-1">
                    <i class="bi bi-envelope text-purple-400"></i>
                    A confirmation email has been sent to your registered email.
                </p>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
