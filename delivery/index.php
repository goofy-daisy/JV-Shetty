<?php
/**
 * Delivery Partner Dashboard
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/Order.php';

Auth::requirePermission('delivery.view');

$user = Auth::user();
$db   = Database::getInstance();

// Get delivery partner record
$stmt = $db->prepare("SELECT id FROM delivery_partners WHERE user_id = ?");
$stmt->execute([$user['id']]);
$partner = $stmt->fetch();

if (!$partner) {
    die('Delivery partner record not found. Please contact admin.');
}

$partnerId = $partner['id'];
$order     = new Order();

$assignedOrders = $order->getForDeliveryPartner($partnerId);

$activeOrders = array_filter($assignedOrders, function($o) {
    return !in_array($o['status'], ['delivered', 'picked_up', 'cancelled']);
});

$completedOrders = array_filter($assignedOrders, function($o) {
    return in_array($o['status'], ['delivered', 'picked_up']);
});

$stmt = $db->prepare("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status IN ('delivered','picked_up') THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status IN ('assigned','out_for_delivery') THEN 1 ELSE 0 END) as pending
    FROM orders
    WHERE delivery_partner_id = ?
");
$stmt->execute([$partnerId]);
$stats = $stmt->fetch();

$statusLabels = [
    'placed'           => 'Placed',
    'created'          => 'Created',
    'payment_pending'  => 'Payment Pending',
    'paid'             => 'Paid',
    'confirmed'        => 'Confirmed',
    'packed'           => 'Packed',
    'assigned'         => 'Assigned',
    'out_for_delivery' => 'Out for Delivery',
    'delivered'        => 'Delivered',
    'picked_up'        => 'Picked Up',
    'cancelled'        => 'Cancelled',
    'refunded'         => 'Refunded'
];

$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Dashboard - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-gray-50">

<?php require_once __DIR__ . '/../includes/delivery_nav.php'; ?>

<div class="admin-main px-6 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900">Delivery Dashboard</h1>
            <p class="text-sm text-gray-500 mt-0.5">Welcome, <?php echo htmlspecialchars($user['first_name'] ?? 'Partner'); ?></p>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-4 mb-6">
        <div class="col-md-4">
            <div class="dashboard-card p-5 text-center">
                <p class="text-3xl font-extrabold text-blue-600"><?php echo (int)$stats['pending']; ?></p>
                <p class="text-sm text-gray-500 mt-1">Active Deliveries</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dashboard-card p-5 text-center">
                <p class="text-3xl font-extrabold text-green-600"><?php echo (int)$stats['completed']; ?></p>
                <p class="text-sm text-gray-500 mt-1">Completed</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dashboard-card p-5 text-center">
                <p class="text-3xl font-extrabold text-purple-600"><?php echo (int)$stats['total']; ?></p>
                <p class="text-sm text-gray-500 mt-1">Total Assigned</p>
            </div>
        </div>
    </div>

    <!-- Active Deliveries -->
    <div class="card mb-6">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-truck me-2"></i>Active Deliveries</h5>
        </div>
        <div class="card-body">
            <?php if (empty($activeOrders)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-check-circle display-1 text-success"></i>
                    <p class="mt-3 text-muted">No active deliveries. Great job!</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($activeOrders as $o): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <span class="badge bg-<?php echo $o['status'] === 'out_for_delivery' ? 'warning' : 'info'; ?>">
                                            <?php echo $statusLabels[$o['status']] ?? $o['status']; ?>
                                        </span>
                                        <small class="text-muted">#<?php echo htmlspecialchars(substr($o['order_number'], -6)); ?></small>
                                    </div>

                                    <h6 class="mb-1"><?php echo htmlspecialchars(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')); ?></h6>
                                    <p class="text-muted small mb-2">
                                        <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($o['user_phone'] ?? 'N/A'); ?>
                                    </p>

                                    <hr>

                                    <?php if ($o['fulfillment_type'] === 'delivery'): ?>
                                        <p class="mb-1"><strong><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($o['hostel_name'] ?? 'Address'); ?></strong></p>
                                        <p class="text-muted small mb-0">Room <?php echo htmlspecialchars($o['room_number'] ?? 'N/A'); ?></p>
                                    <?php else: ?>
                                        <p class="mb-1"><strong><i class="bi bi-shop me-1"></i>Store Pickup</strong></p>
                                    <?php endif; ?>

                                    <p class="fw-bold mt-3 mb-3">₹<?php echo number_format($o['total_amount'], 2); ?></p>

                                    <div class="d-grid gap-2">
                                        <?php if ($o['status'] === 'assigned'): ?>
                                            <button class="btn btn-primary" onclick="updateStatus(<?php echo $o['id']; ?>, 'out_for_delivery')">
                                                <i class="bi bi-box-arrow-right me-1"></i>Start Delivery
                                            </button>
                                        <?php elseif ($o['status'] === 'out_for_delivery'): ?>
                                            <button class="btn btn-success" onclick="markDelivered(<?php echo $o['id']; ?>, '<?php echo $o['fulfillment_type']; ?>')">
                                                <i class="bi bi-check-circle me-1"></i>Mark <?php echo $o['fulfillment_type'] === 'delivery' ? 'Delivered' : 'Picked Up'; ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recently Completed -->
    <?php if (!empty($completedOrders)): ?>
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-check-circle me-2"></i>Recently Completed</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Total</th>
                                <th>Completed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($completedOrders, 0, 5) as $o): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($o['order_number']); ?></td>
                                    <td><?php echo htmlspecialchars(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $o['fulfillment_type'] === 'delivery' ? 'info' : 'secondary'; ?>">
                                            <?php echo ucfirst($o['fulfillment_type']); ?>
                                        </span>
                                    </td>
                                    <td>₹<?php echo number_format($o['total_amount'], 2); ?></td>
                                    <td><?php echo !empty($o['delivered_at']) ? date('j M, g:i A', strtotime($o['delivered_at'])) : 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Mark Delivered Modal -->
<div class="modal fade" id="deliveredModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delivery</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="deliveredForm">
                <div class="modal-body">
                    <input type="hidden" name="order_id" id="deliveredOrderId">
                    <input type="hidden" name="status" id="deliveredStatus">
                    <p>Please confirm that you have:</p>
                    <ul>
                        <li>Delivered the order to the customer</li>
                        <li>Collected payment if applicable</li>
                    </ul>
                    <div class="mb-3">
                        <label class="form-label">Delivery Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Any notes about the delivery..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm Delivery</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const deliveredModal = new bootstrap.Modal(document.getElementById('deliveredModal'));

    async function updateStatus(orderId, status) {
        if (!confirm('Are you sure you want to update the status?')) return;
        try {
            const response = await fetch('../api/orders.php?action=update_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId, status: status })
            });
            const result = await response.json();
            if (result.success) { location.reload(); }
            else { alert(result.message || 'Failed to update status'); }
        } catch (e) { alert('Failed to update status'); }
    }

    function markDelivered(orderId, type) {
        document.getElementById('deliveredOrderId').value = orderId;
        document.getElementById('deliveredStatus').value  = type === 'delivery' ? 'delivered' : 'picked_up';
        deliveredModal.show();
    }

    document.getElementById('deliveredForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(this));
        try {
            const response = await fetch('../api/orders.php?action=update_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (result.success) { deliveredModal.hide(); location.reload(); }
            else { alert(result.message || 'Failed to confirm delivery'); }
        } catch (e) { alert('Failed to confirm delivery'); }
    });
</script>
</body>
</html>
