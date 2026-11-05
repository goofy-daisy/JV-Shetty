<?php
/**
 * Order Detail Page
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/models/Order.php';

Auth::requireAuth();

$orderId   = (int)($_GET['id'] ?? 0);
$order     = new Order();
$orderData = $order->getWithDetails($orderId);

if (!$orderData) {
    header('Location: orders.php');
    exit;
}

$user = Auth::user();
if ($orderData['user_id'] != $user['id'] && !Auth::hasPermission('orders.manage')) {
    header('Location: orders.php');
    exit;
}

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

$timelineStatuses = ['placed', 'confirmed', 'packed', 'assigned', 'out_for_delivery', 'delivered'];
if ($orderData['fulfillment_type'] === 'pickup') {
    $timelineStatuses = ['placed', 'confirmed', 'packed', 'picked_up'];
}

$currentStatusIndex = array_search($orderData['status'], $timelineStatuses);
if ($currentStatusIndex === false) {
    $currentStatusIndex = -1;
}

// Cancellation window: set to 5 min for customers; -1 = no limit (admin)
$cancelDeadlineTs = -1;
$isCancellable    = false;
$cancellableStatuses = ['placed', 'created', 'payment_pending', 'paid', 'confirmed'];

if (in_array($orderData['status'], $cancellableStatuses)) {
    if ($user['role_name'] === 'customer') {
        $placedAt = strtotime($orderData['placed_at'] ?: $orderData['created_at']);
        $cancelDeadlineTs = $placedAt + 300; // 5 minutes in seconds
        $isCancellable = (time() < $cancelDeadlineTs);
    } else {
        // Admins can always cancel
        $isCancellable = true;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?php echo htmlspecialchars($orderData['order_number']); ?> – <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-50">

    <?php require_once __DIR__ . '/includes/nav.php'; ?>

    <div class="page-content">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <!-- Header -->
            <div class="flex items-center justify-between mb-8 flex-wrap gap-3">
                <div>
                    <a href="orders.php" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-purple-600 transition-colors mb-2">
                        <i class="bi bi-arrow-left text-xs"></i>Back to Orders
                    </a>
                    <h1 class="text-2xl font-extrabold text-gray-900">Order #<?php echo htmlspecialchars($orderData['order_number']); ?></h1>
                    <p class="text-sm text-gray-500">Placed on <?php echo date('j M Y, g:i A', strtotime($orderData['created_at'])); ?></p>
                </div>
                <span class="status-badge status-<?php echo $orderData['status']; ?> px-4 py-2">
                    <?php echo $statusLabels[$orderData['status']] ?? $orderData['status']; ?>
                </span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left: Main -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Timeline -->
                    <?php if (!in_array($orderData['status'], ['cancelled', 'refunded'])): ?>
                        <div class="card-modern p-6">
                            <h2 class="font-bold text-gray-900 mb-5 flex items-center gap-2">
                                <i class="bi bi-arrow-right-circle text-purple-500"></i>Order Progress
                            </h2>
                            <div class="timeline">
                                <?php foreach ($timelineStatuses as $index => $status):
                                    $isCompleted = $index <= $currentStatusIndex;
                                    $isActive    = $index === $currentStatusIndex;
                                    $statusTime  = null;
                                    $timeField   = str_replace(['out_for_delivery', 'picked_up'], ['out_for_delivery_at', 'delivered_at'], $status) . '_at';
                                    if (isset($orderData[$timeField]) && $orderData[$timeField]) {
                                        $statusTime = date('j M, g:i A', strtotime($orderData[$timeField]));
                                    } elseif ($status === 'created') {
                                        $statusTime = date('j M, g:i A', strtotime($orderData['created_at']));
                                    }
                                ?>
                                    <div class="timeline-item <?php echo $isCompleted ? 'completed' : ''; ?> <?php echo $isActive ? 'active' : ''; ?>">
                                        <div class="flex justify-between items-start">
                                            <span class="font-semibold text-sm text-gray-900"><?php echo $statusLabels[$status] ?? $status; ?></span>
                                            <?php if ($statusTime): ?>
                                                <span class="text-xs text-gray-400 ml-2 flex-shrink-0"><?php echo $statusTime; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Order Items -->
                    <div class="card-modern overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-50 flex items-center gap-2">
                            <i class="bi bi-box-seam text-purple-500"></i>
                            <h2 class="font-bold text-gray-900">Order Items</h2>
                        </div>
                        <div>
                            <?php foreach ($orderData['items'] as $item): ?>
                                <div class="flex gap-4 items-start p-4 border-b border-gray-50 last:border-0">
                                    <img src="<?php echo $item['image'] ?? 'assets/images/product-default.jpg'; ?>"
                                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                                         class="w-16 h-16 rounded-xl object-cover bg-gray-50 border border-gray-100 flex-shrink-0"
                                         onerror="this.src='https://via.placeholder.com/64x64/f3f4f6/9ca3af?text=?'">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold text-gray-900 text-sm line-clamp-2"><?php echo htmlspecialchars($item['name']); ?></h3>
                                        <p class="text-xs text-gray-500 mt-0.5">Qty: <?php echo $item['quantity']; ?> × ₹<?php echo number_format($item['unit_price'], 2); ?></p>
                                    </div>
                                    <p class="font-bold text-purple-700 text-sm flex-shrink-0">₹<?php echo number_format($item['total_price'], 2); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Status History -->
                    <div class="card-modern p-6">
                        <h2 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="bi bi-clock-history text-purple-500"></i>Status History
                        </h2>
                        <div class="space-y-3">
                            <?php foreach ($orderData['status_history'] as $history): ?>
                                <div class="flex items-start justify-between gap-4 pb-3 border-b border-gray-50 last:border-0">
                                    <div>
                                        <div class="flex items-center gap-2 text-sm">
                                            <?php if ($history['status_from']): ?>
                                                <span class="text-gray-500"><?php echo $statusLabels[$history['status_from']] ?? $history['status_from']; ?></span>
                                                <i class="bi bi-arrow-right text-xs text-gray-400"></i>
                                            <?php endif; ?>
                                            <span class="font-bold text-gray-900"><?php echo $statusLabels[$history['status_to']] ?? $history['status_to']; ?></span>
                                        </div>
                                        <?php if ($history['notes']): ?>
                                            <p class="text-xs text-gray-500 mt-0.5"><?php echo htmlspecialchars($history['notes']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-xs text-gray-400 flex-shrink-0"><?php echo date('j M Y, g:i A', strtotime($history['created_at'])); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>

                <!-- Right: Sidebar -->
                <div class="space-y-6">

                    <!-- Delivery Info -->
                    <div class="card-modern p-5">
                        <h2 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="bi bi-<?php echo $orderData['fulfillment_type'] === 'delivery' ? 'truck' : 'bag-check'; ?> text-purple-500"></i>
                            <?php echo $orderData['fulfillment_type'] === 'delivery' ? 'Delivery Info' : 'Pickup Info'; ?>
                        </h2>
                        <?php if ($orderData['fulfillment_type'] === 'delivery'): ?>
                            <p class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($orderData['hostel_name'] ?? 'Address'); ?></p>
                            <p class="text-sm text-gray-600">Room <?php echo htmlspecialchars($orderData['room_number'] ?? 'N/A'); ?></p>
                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($orderData['address_line1']); ?></p>
                            <?php if ($orderData['instructions']): ?>
                                <p class="text-xs text-gray-400 mt-2 flex items-center gap-1">
                                    <i class="bi bi-info-circle"></i><?php echo htmlspecialchars($orderData['instructions']); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($orderData['delivery_first_name']): ?>
                                <div class="border-t border-gray-100 mt-3 pt-3">
                                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Delivery Partner</p>
                                    <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($orderData['delivery_first_name'] . ' ' . $orderData['delivery_last_name']); ?></p>
                                    <?php if ($orderData['delivery_phone']): ?>
                                        <a href="tel:<?php echo $orderData['delivery_phone']; ?>" class="text-sm text-purple-600 hover:underline"><?php echo $orderData['delivery_phone']; ?></a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="font-semibold text-gray-900 text-sm">JV Shetty Store</p>
                            <p class="text-sm text-gray-500">BITS Pilani Goa Campus</p>
                            <?php if ($orderData['pickup_date']): ?>
                                <div class="mt-3 p-3 rounded-xl bg-purple-50 border border-purple-100">
                                    <p class="text-xs font-bold text-purple-700">Scheduled Pickup</p>
                                    <p class="text-sm font-semibold text-gray-900 mt-1"><?php echo date('j M Y', strtotime($orderData['pickup_date'])); ?></p>
                                    <p class="text-sm text-gray-600">
                                        <?php
                                        $start = new DateTime($orderData['pickup_start'] ?? '16:00:00');
                                        $end   = new DateTime($orderData['pickup_end']   ?? '21:00:00');
                                        echo $start->format('g:i A') . ' – ' . $end->format('g:i A');
                                        ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Payment Summary -->
                    <div class="card-modern p-5">
                        <h2 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="bi bi-receipt text-purple-500"></i>Payment Summary
                        </h2>
                        <div class="space-y-2.5 text-sm">
                            <div class="flex justify-between text-gray-600">
                                <span>Subtotal</span><span class="font-semibold text-gray-900">₹<?php echo number_format($orderData['subtotal'], 2); ?></span>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <span>Tax</span><span class="font-semibold text-gray-900">₹<?php echo number_format($orderData['tax_amount'], 2); ?></span>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <span>Delivery Fee</span>
                                <span class="font-semibold <?php echo $orderData['delivery_fee'] > 0 ? 'text-gray-900' : 'text-emerald-600'; ?>">
                                    <?php echo $orderData['delivery_fee'] > 0 ? '₹' . number_format($orderData['delivery_fee'], 2) : 'FREE'; ?>
                                </span>
                            </div>
                            <?php if ($orderData['discount_amount'] > 0): ?>
                                <div class="flex justify-between text-emerald-600">
                                    <span>Discount</span><span class="font-semibold">-₹<?php echo number_format($orderData['discount_amount'], 2); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="border-t-2 border-gray-100 mt-3 pt-3 flex justify-between">
                            <span class="font-extrabold text-gray-900">Total</span>
                            <span class="font-extrabold text-purple-700 text-lg">₹<?php echo number_format($orderData['total_amount'], 2); ?></span>
                        </div>
                        <?php if ($orderData['payment']): ?>
                            <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between items-center text-xs">
                                <span class="text-gray-500">Payment</span>
                                <span class="px-2.5 py-1 rounded-full font-bold <?php echo $orderData['payment']['status'] === 'completed' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $orderData['payment']['payment_method'])); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <div class="space-y-2">
                        <?php if (in_array($orderData['status'], $cancellableStatuses)): ?>
                            <?php if ($user['role_name'] === 'customer'): ?>
                                <?php if ($isCancellable): ?>
                                    <!-- Within window: show active button + countdown -->
                                    <div class="rounded-xl border-2 border-red-200 p-3 text-center mb-1">
                                        <p class="text-xs text-gray-500 mb-1">Cancellation window closes in</p>
                                        <p id="cancelCountdown" class="font-extrabold text-red-600 text-lg font-mono">5:00</p>
                                    </div>
                                    <button id="cancelBtn"
                                            class="w-full py-3 border-2 border-red-200 text-red-600 font-semibold rounded-xl text-sm hover:bg-red-50 transition-colors flex items-center justify-center gap-2"
                                            onclick="cancelOrder(<?php echo $orderData['id']; ?>)">
                                        <i class="bi bi-x-circle"></i>Cancel Order
                                    </button>
                                <?php else: ?>
                                    <!-- Window expired: show disabled button + message -->
                                    <div class="rounded-xl bg-gray-50 border-2 border-gray-200 p-3 text-center">
                                        <i class="bi bi-clock-history text-gray-400 text-lg"></i>
                                        <p class="text-xs text-gray-500 mt-1">Cancellation window (5 min) has expired.</p>
                                        <p class="text-xs text-gray-400">Contact <a href="contact.php" class="text-purple-600 underline">support</a> to request cancellation.</p>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- Admin: no time limit -->
                                <button class="w-full py-3 border-2 border-red-200 text-red-600 font-semibold rounded-xl text-sm hover:bg-red-50 transition-colors flex items-center justify-center gap-2"
                                        onclick="cancelOrder(<?php echo $orderData['id']; ?>)">
                                    <i class="bi bi-x-circle"></i>Cancel Order
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <button class="w-full py-3 border-2 border-gray-200 text-gray-700 font-semibold rounded-xl text-sm hover:border-purple-300 hover:text-purple-700 transition-all flex items-center justify-center gap-2 no-print"
                                onclick="window.print()">
                            <i class="bi bi-printer"></i>Print Order
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <div class="toast-container"></div>
    <script src="assets/js/main.js"></script>
    <script>
        async function cancelOrder(orderId) {
            if (!confirm('Are you sure you want to cancel this order?')) return;
            const reason = prompt('Please provide a reason for cancellation:');
            if (!reason || reason.trim().length < 5) {
                alert('Please provide a reason of at least 5 characters.');
                return;
            }
            try {
                const response = await fetch('api/orders.php?action=cancel', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: orderId, reason: reason.trim() })
                });
                const data = await response.json();
                if (data.success) { showToast('Order cancelled successfully'); location.reload(); }
                else { showToast(data.message || 'Cancellation failed', 'error'); }
            } catch (error) { showToast('Failed to cancel order', 'error'); }
        }

        // Countdown timer for cancellation window
        const deadlineTs = <?php echo $cancelDeadlineTs; ?>;
        const countdownEl = document.getElementById('cancelCountdown');
        const cancelBtn   = document.getElementById('cancelBtn');

        if (countdownEl && deadlineTs > 0) {
            function updateCountdown() {
                const remaining = Math.max(0, deadlineTs - Math.floor(Date.now() / 1000));
                const mins = Math.floor(remaining / 60);
                const secs = remaining % 60;
                countdownEl.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
                if (remaining === 0) {
                    clearInterval(timer);
                    countdownEl.textContent = 'Expired';
                    countdownEl.classList.replace('text-red-600', 'text-gray-400');
                    if (cancelBtn) {
                        cancelBtn.disabled = true;
                        cancelBtn.classList.add('opacity-50', 'cursor-not-allowed');
                        cancelBtn.classList.remove('hover:bg-red-50');
                    }
                }
            }
            updateCountdown();
            const timer = setInterval(updateCountdown, 1000);
        }
    </script>
</body>
</html>
