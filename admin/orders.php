<?php
/**
 * Admin Orders Management
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/User.php';

Auth::requirePermission('orders.manage');

$order = new Order();
$user  = new User();

$filters = [
    'status'    => $_GET['status'] ?? null,
    'search'    => $_GET['search'] ?? null,
    'date_from' => $_GET['date_from'] ?? null,
    'date_to'   => $_GET['date_to'] ?? null
];

$page   = (int)($_GET['page'] ?? 1);
$result = $order->getAllFiltered(array_filter($filters), $page, 20);
$orders = $result['data'];

$deliveryPartners = $user->getDeliveryPartners(true);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-gray-50">

<?php $activePage = 'orders'; require_once __DIR__ . '/../includes/admin_nav.php'; ?>

<div class="admin-main px-6 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-extrabold text-gray-900">Orders</h1>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <?php foreach ($statusLabels as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($_GET['status'] ?? '') === $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Order #, customer..."
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">From</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $_GET['date_from'] ?? ''; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $_GET['date_to'] ?? ''; ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($o['order_number']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($o['email'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $o['fulfillment_type'] === 'delivery' ? 'info' : 'secondary'; ?>">
                                        <?php echo ucfirst($o['fulfillment_type']); ?>
                                    </span>
                                </td>
                                <td>₹<?php echo number_format($o['total_amount'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $o['status']; ?>">
                                        <?php echo $statusLabels[$o['status']] ?? $o['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('j M Y', strtotime($o['created_at'])); ?></td>
                                <td>
                                    <a href="order_detail.php?id=<?php echo $o['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($o['status'] === 'packed'): ?>
                                        <button class="btn btn-sm btn-outline-success" onclick="assignDelivery(<?php echo $o['id']; ?>)">
                                            <i class="bi bi-truck"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($result['last_page'] > 1): ?>
            <div class="card-footer">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $result['last_page']; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $result['last_page'] ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Assign Delivery Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Delivery Partner</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignForm">
                <div class="modal-body">
                    <input type="hidden" name="order_id" id="assignOrderId">
                    <div class="mb-3">
                        <label class="form-label">Select Delivery Partner</label>
                        <select name="delivery_partner_id" class="form-select" required>
                            <option value="">Choose...</option>
                            <?php foreach ($deliveryPartners as $partner): ?>
                                <option value="<?php echo $partner['id']; ?>">
                                    <?php echo htmlspecialchars(($partner['first_name'] ?? '') . ' ' . ($partner['last_name'] ?? '')); ?>
                                    (<?php echo $partner['total_deliveries']; ?> deliveries)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const assignModal = new bootstrap.Modal(document.getElementById('assignModal'));

    function assignDelivery(orderId) {
        document.getElementById('assignOrderId').value = orderId;
        assignModal.show();
    }

    document.getElementById('assignForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(this));
        try {
            const response = await fetch('../api/orders.php?action=assign_delivery', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (result.success) { assignModal.hide(); location.reload(); }
            else { alert(result.message); }
        } catch (e) { alert('Failed to assign delivery partner'); }
    });
</script>
</body>
</html>
