<?php
/**
 * Admin Dashboard - index.php
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Auth.php';

Auth::requirePermission('orders.manage');

$db   = Database::getInstance();
$user = Auth::user();

// Get stats
$today = date('Y-m-d');

// Today's orders
$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ? AND status NOT IN ('cancelled', 'refunded')");
$stmt->execute([$today]);
$todayOrders = $stmt->fetchColumn();

// Today's sales
$stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = ? AND status IN ('placed', 'paid', 'confirmed', 'packed', 'assigned', 'out_for_delivery', 'delivered', 'picked_up')");
$stmt->execute([$today]);
$todaySales = $stmt->fetchColumn();

// Pending orders
$stmt = $db->query("SELECT COUNT(*) FROM orders WHERE status IN ('placed', 'confirmed')");
$pendingOrders = $stmt->fetchColumn();

// Low stock products
$stmt = $db->query("SELECT COUNT(*) FROM products p JOIN inventory i ON p.id = i.product_id WHERE i.quantity_available <= i.reorder_level AND p.is_active = 1");
$lowStock = $stmt->fetchColumn();

// Total customers
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE role_id = (SELECT id FROM roles WHERE name = 'customer') AND is_active = 1");
$totalCustomers = $stmt->fetchColumn();

// Recent orders
$stmt = $db->query("
    SELECT o.*, p.first_name, p.last_name, u.email
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN user_profiles p ON u.id = p.user_id
    ORDER BY o.created_at DESC
    LIMIT 10
");
$recentOrders = $stmt->fetchAll();

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

$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard – <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-gray-50">

    <?php require_once __DIR__ . '/../includes/admin_nav.php'; ?>

    <div class="admin-main px-6 py-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-900">Dashboard</h1>
                <p class="text-sm text-gray-500 mt-0.5">Welcome back, <?php echo htmlspecialchars($user['first_name'] ?? 'Admin'); ?>!</p>
            </div>
            <div class="text-sm text-gray-500 flex items-center gap-2 bg-white px-4 py-2 rounded-xl shadow-sm border border-gray-100">
                <i class="bi bi-calendar3 text-purple-500"></i>
                <?php echo date('j M Y'); ?>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-8">
            <!-- Today's Orders -->
            <div class="dashboard-card p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Today's Orders</p>
                        <p class="text-3xl font-extrabold text-gray-900"><?php echo $todayOrders; ?></p>
                    </div>
                    <div class="stat-icon bg-purple-100 text-purple-600"><i class="bi bi-bag"></i></div>
                </div>
            </div>
            <!-- Today's Sales -->
            <div class="dashboard-card p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Today's Sales</p>
                        <p class="text-3xl font-extrabold text-gray-900">₹<?php echo number_format($todaySales, 0); ?></p>
                    </div>
                    <div class="stat-icon bg-emerald-100 text-emerald-600"><i class="bi bi-currency-rupee"></i></div>
                </div>
            </div>
            <!-- Pending Orders -->
            <div class="dashboard-card p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Pending Orders</p>
                        <p class="text-3xl font-extrabold text-gray-900"><?php echo $pendingOrders; ?></p>
                    </div>
                    <div class="stat-icon bg-amber-100 text-amber-600"><i class="bi bi-hourglass-split"></i></div>
                </div>
            </div>
            <!-- Low Stock -->
            <div class="dashboard-card p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Low Stock Items</p>
                        <p class="text-3xl font-extrabold <?php echo $lowStock > 0 ? 'text-red-600' : 'text-gray-900'; ?>"><?php echo $lowStock; ?></p>
                    </div>
                    <div class="stat-icon bg-red-100 text-red-600"><i class="bi bi-exclamation-triangle"></i></div>
                </div>
            </div>
            <!-- Total Customers -->
            <div class="dashboard-card p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Total Customers</p>
                        <p class="text-3xl font-extrabold text-gray-900"><?php echo $totalCustomers; ?></p>
                    </div>
                    <div class="stat-icon bg-blue-100 text-blue-600"><i class="bi bi-people"></i></div>
                </div>
            </div>
        </div>

        <!-- Recent Orders Table -->
        <div class="dashboard-card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-bold text-gray-900">Recent Orders</h2>
                <a href="orders.php" class="text-xs font-semibold text-purple-600 hover:text-purple-700 transition-colors">View All →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50">
                            <th class="text-left px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Order #</th>
                            <th class="text-left px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Customer</th>
                            <th class="text-left px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Date</th>
                            <th class="text-left px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Total</th>
                            <th class="text-left px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Status</th>
                            <th class="text-left px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $o): ?>
                            <tr class="border-b border-gray-50 hover:bg-purple-50/30 transition-colors">
                                <td class="px-6 py-3 font-semibold text-gray-900"><?php echo htmlspecialchars($o['order_number']); ?></td>
                                <td class="px-6 py-3">
                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($o['email']); ?></p>
                                </td>
                                <td class="px-6 py-3 text-gray-500 text-xs"><?php echo date('j M Y, g:i A', strtotime($o['created_at'])); ?></td>
                                <td class="px-6 py-3 font-bold text-purple-700">₹<?php echo number_format($o['total_amount'], 2); ?></td>
                                <td class="px-6 py-3">
                                    <span class="status-badge status-<?php echo $o['status']; ?>">
                                        <?php echo $statusLabels[$o['status']] ?? $o['status']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-3">
                                    <a href="order_detail.php?id=<?php echo $o['id']; ?>"
                                       class="inline-flex items-center gap-1 px-3 py-1.5 bg-purple-100 text-purple-700 text-xs font-semibold rounded-xl hover:bg-purple-200 transition-colors">
                                        <i class="bi bi-eye"></i>View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>
