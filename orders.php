<?php
/**
 * My Orders Page
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/models/Order.php';

Auth::requireAuth();

$user  = Auth::user();
$order = new Order();

$page   = (int)($_GET['page'] ?? 1);
$result = $order->getForUser($user['id'], $page, 10);
$orders = $result['data'];

// Status labels
$statusLabels = [
    'placed'            => 'Placed',
    'created'           => 'Created',
    'payment_pending'   => 'Payment Pending',
    'paid'              => 'Paid',
    'confirmed'         => 'Confirmed',
    'packed'            => 'Packed',
    'assigned'          => 'Assigned',
    'out_for_delivery'  => 'Out for Delivery',
    'delivered'         => 'Delivered',
    'picked_up'         => 'Picked Up',
    'cancelled'         => 'Cancelled',
    'refunded'          => 'Refunded'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders – <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-50">

    <?php require_once __DIR__ . '/includes/nav.php'; ?>

    <div class="page-content">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-8 flex items-center gap-3">
                <i class="bi bi-bag text-purple-600"></i>My Orders
            </h1>

            <?php if (empty($orders)): ?>
                <div class="flex flex-col items-center justify-center py-20 text-center bg-white rounded-3xl shadow-sm border border-gray-100">
                    <div class="w-24 h-24 rounded-3xl bg-purple-50 flex items-center justify-center mb-6">
                        <i class="bi bi-bag-x text-4xl text-purple-300"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">No orders yet</h3>
                    <p class="text-gray-500 mb-8">Start shopping to place your first order!</p>
                    <a href="products.php" class="px-7 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold rounded-xl text-sm shadow-md hover:shadow-lg transition-all">
                        Browse Products
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($orders as $o): ?>
                        <div class="card-modern overflow-hidden">
                            <div class="p-4 sm:p-5 border-b border-gray-50">
                                <div class="flex items-start justify-between gap-3 flex-wrap">
                                    <div>
                                        <p class="text-xs text-gray-500 mb-0.5">Order Number</p>
                                        <p class="font-bold text-gray-900 text-sm"><?php echo htmlspecialchars($o['order_number']); ?></p>
                                        <p class="text-xs text-gray-400 mt-0.5"><?php echo date('j M Y, g:i A', strtotime($o['created_at'])); ?></p>
                                    </div>
                                    <span class="status-badge status-<?php echo $o['status']; ?>">
                                        <?php echo $statusLabels[$o['status']] ?? $o['status']; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="p-4 sm:p-5">
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                                    <div>
                                        <p class="text-xs text-gray-500 mb-1">Fulfillment</p>
                                        <p class="text-sm font-semibold text-gray-900 flex items-center gap-1.5">
                                            <i class="bi bi-<?php echo $o['fulfillment_type'] === 'delivery' ? 'truck' : 'bag-check'; ?> text-purple-500"></i>
                                            <?php echo $o['fulfillment_type'] === 'delivery' ? 'Delivery' : 'Pickup'; ?>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-0.5">
                                            <?php if ($o['fulfillment_type'] === 'delivery'): ?>
                                                <?php echo htmlspecialchars($o['hostel_name'] ?? 'Address'); ?> – Room <?php echo htmlspecialchars($o['room_number'] ?? 'N/A'); ?>
                                            <?php else: ?>
                                                JV Shetty Store
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 mb-1">Total Amount</p>
                                        <p class="text-lg font-extrabold text-purple-700">₹<?php echo number_format($o['total_amount'], 2); ?></p>
                                    </div>
                                    <div class="sm:text-right flex sm:flex-col gap-2 items-center sm:items-end sm:justify-end">
                                        <a href="order_detail.php?id=<?php echo $o['id']; ?>"
                                           class="px-4 py-2 bg-purple-600 text-white text-xs font-semibold rounded-xl hover:bg-purple-700 transition-colors shadow-sm">
                                            View Details
                                        </a>
                                        <?php if (in_array($o['status'], ['created', 'payment_pending', 'paid', 'confirmed'])): ?>
                                            <button class="px-4 py-2 border-2 border-red-200 text-red-600 text-xs font-semibold rounded-xl hover:bg-red-50 transition-colors"
                                                    onclick="cancelOrder(<?php echo $o['id']; ?>)">
                                                Cancel
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($o['status'] === 'delivered' || $o['status'] === 'picked_up'): ?>
                                            <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-emerald-100 text-emerald-700 text-xs font-semibold">
                                                <i class="bi bi-check-circle-fill"></i>Completed
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($result['last_page'] > 1): ?>
                    <div class="pagination-modern mt-8">
                        <a href="?page=<?php echo $page - 1; ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <i class="bi bi-chevron-left text-sm"></i>
                        </a>
                        <?php for ($i = 1; $i <= $result['last_page']; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="page-btn <?php echo $page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="page-btn <?php echo $page >= $result['last_page'] ? 'disabled' : ''; ?>">
                            <i class="bi bi-chevron-right text-sm"></i>
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </div>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <div class="toast-container"></div>
    <script src="assets/js/main.js"></script>
    <script>
        async function cancelOrder(orderId) {
            if (!confirm('Are you sure you want to cancel this order?')) return;
            const reason = prompt('Please provide a reason for cancellation:');
            if (!reason) return;
            try {
                const response = await fetch('api/orders.php?action=cancel', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: orderId, reason: reason })
                });
                const data = await response.json();
                if (data.success) { showToast('Order cancelled successfully'); location.reload(); }
                else { showToast(data.message, 'error'); }
            } catch (error) { showToast('Failed to cancel order', 'error'); }
        }
    </script>
</body>
</html>
