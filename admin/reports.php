<?php
/**
 * Reports and Analytics (Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Auth.php';

Auth::requirePermission('reports.view');

$db = Database::getInstance();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date']   ?? date('Y-m-d');

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="orders_' . $startDate . '_to_' . $endDate . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($out, [
        'Order Number','Date','Customer Name','Customer Email',
        'Fulfillment','Status','Subtotal','Tax','Delivery Fee','Discount','Total Amount'
    ]);

    $stmt = $db->prepare("
        SELECT o.order_number,
               DATE_FORMAT(o.created_at, '%d/%m/%Y %H:%i') as order_date,
               CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,'')) as customer_name,
               u.email, o.fulfillment_type, o.status,
               o.subtotal, o.tax_amount, o.delivery_fee, o.discount_amount, o.total_amount
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN user_profiles p ON u.id = p.user_id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$startDate, $endDate]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { fputcsv($out, $row); }
    fclose($out);
    exit;
}

// Sales Overview
$stmt = $db->prepare("
    SELECT COUNT(*) as total_orders,
           COALESCE(SUM(total_amount),0) as total_sales,
           COALESCE(AVG(total_amount),0) as avg_order_value,
           COUNT(CASE WHEN status IN ('delivered','picked_up') THEN 1 END) as completed_orders
    FROM orders WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$salesOverview = $stmt->fetch();

// Top Products
$stmt = $db->prepare("
    SELECT p.name, SUM(oi.quantity) as total_sold, SUM(oi.total_price) as revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE DATE(o.created_at) BETWEEN ? AND ?
    AND o.status NOT IN ('cancelled','refunded')
    GROUP BY p.id, p.name ORDER BY total_sold DESC LIMIT 10
");
$stmt->execute([$startDate, $endDate]);
$topProducts = $stmt->fetchAll();

// Top Customers
$stmt = $db->prepare("
    SELECT u.email, up.first_name, up.last_name,
           COUNT(o.id) as order_count, SUM(o.total_amount) as total_spent
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE DATE(o.created_at) BETWEEN ? AND ?
    AND o.status NOT IN ('cancelled','refunded')
    GROUP BY u.id ORDER BY total_spent DESC LIMIT 10
");
$stmt->execute([$startDate, $endDate]);
$topCustomers = $stmt->fetchAll();

// Daily Sales
$stmt = $db->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as orders,
           COALESCE(SUM(total_amount),0) as sales
    FROM orders
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND status NOT IN ('cancelled','refunded')
    GROUP BY DATE(created_at) ORDER BY date ASC
");
$stmt->execute([$startDate, $endDate]);
$dailySales = $stmt->fetchAll();

// Category Performance
$stmt = $db->prepare("
    SELECT c.name, COUNT(oi.id) as items_sold, SUM(oi.total_price) as revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    WHERE DATE(o.created_at) BETWEEN ? AND ?
    AND o.status NOT IN ('cancelled','refunded')
    GROUP BY c.id ORDER BY revenue DESC
");
$stmt->execute([$startDate, $endDate]);
$categoryPerformance = $stmt->fetchAll();

$rate = $salesOverview['total_orders'] > 0
    ? ($salesOverview['completed_orders'] / $salesOverview['total_orders']) * 100
    : 0;

$activePage = 'reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics – <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-gray-50">

<?php require_once __DIR__ . '/../includes/admin_nav.php'; ?>

<div class="admin-main px-6 py-8">

    <!-- Header -->
    <div class="flex items-center justify-between gap-4 mb-8 flex-wrap">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900">Reports & Analytics</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                <?php echo date('j M Y', strtotime($startDate)); ?> – <?php echo date('j M Y', strtotime($endDate)); ?>
            </p>
        </div>
        <form method="get" class="flex items-center gap-2 flex-wrap">
            <input type="date" name="start_date" value="<?php echo $startDate; ?>"
                   class="px-3 py-2 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 bg-white">
            <input type="date" name="end_date" value="<?php echo $endDate; ?>"
                   class="px-3 py-2 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 bg-white">
            <button type="submit" class="px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-semibold rounded-xl text-sm shadow-sm">
                Apply
            </button>
            <a href="?start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&export=csv"
               class="px-4 py-2 bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-semibold rounded-xl text-sm flex items-center gap-1.5 no-underline">
                <i class="bi bi-download"></i> Export CSV
            </a>
        </form>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
        <div class="dashboard-card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Total Orders</p>
                    <p class="text-3xl font-extrabold text-gray-900"><?php echo $salesOverview['total_orders']; ?></p>
                </div>
                <div class="stat-icon bg-purple-100 text-purple-600"><i class="bi bi-bag"></i></div>
            </div>
        </div>
        <div class="dashboard-card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Total Sales</p>
                    <p class="text-3xl font-extrabold text-gray-900">₹<?php echo number_format($salesOverview['total_sales'], 0); ?></p>
                </div>
                <div class="stat-icon bg-emerald-100 text-emerald-600"><i class="bi bi-currency-rupee"></i></div>
            </div>
        </div>
        <div class="dashboard-card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Avg Order Value</p>
                    <p class="text-3xl font-extrabold text-gray-900">₹<?php echo number_format($salesOverview['avg_order_value'], 0); ?></p>
                </div>
                <div class="stat-icon bg-blue-100 text-blue-600"><i class="bi bi-calculator"></i></div>
            </div>
        </div>
        <div class="dashboard-card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Completion Rate</p>
                    <p class="text-3xl font-extrabold text-gray-900"><?php echo round($rate, 1); ?>%</p>
                </div>
                <div class="stat-icon bg-amber-100 text-amber-600"><i class="bi bi-check-circle"></i></div>
            </div>
        </div>
    </div>

    <!-- Charts — FIX: wrap canvas in a div with explicit pixel height; without this
         Chart.js with maintainAspectRatio:false reads the parent's height on every
         resize tick, finds it growing (because the canvas itself is the parent's only
         child), and creates an infinite growth loop. -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-2 dashboard-card p-5">
            <h3 class="font-bold text-gray-900 mb-4">Daily Sales Trend</h3>
            <div style="position:relative; height:260px;">
                <canvas id="salesChart"></canvas>
            </div>
        </div>
        <div class="dashboard-card p-5">
            <h3 class="font-bold text-gray-900 mb-4">Category Sales</h3>
            <div style="position:relative; height:260px;">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Tables -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top Products -->
        <div class="dashboard-card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-gray-900">Top Products</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50">
                            <th class="text-left px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Product</th>
                            <th class="text-left px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Sold</th>
                            <th class="text-left px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProducts as $product): ?>
                            <tr class="border-b border-gray-50 hover:bg-purple-50/30">
                                <td class="px-4 py-3 font-medium text-gray-900 max-w-xs truncate"><?php echo htmlspecialchars($product['name']); ?></td>
                                <td class="px-4 py-3 text-gray-600"><?php echo $product['total_sold']; ?></td>
                                <td class="px-4 py-3 font-bold text-purple-700">₹<?php echo number_format($product['revenue'], 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($topProducts)): ?>
                            <tr><td colspan="3" class="px-4 py-8 text-center text-gray-400 text-sm">No data for this period</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Customers -->
        <div class="dashboard-card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-gray-900">Top Customers</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50">
                            <th class="text-left px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Customer</th>
                            <th class="text-left px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Orders</th>
                            <th class="text-left px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Spent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topCustomers as $customer): ?>
                            <tr class="border-b border-gray-50 hover:bg-purple-50/30">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')); ?></p>
                                    <p class="text-xs text-gray-500 truncate max-w-xs"><?php echo htmlspecialchars($customer['email']); ?></p>
                                </td>
                                <td class="px-4 py-3 text-gray-600"><?php echo $customer['order_count']; ?></td>
                                <td class="px-4 py-3 font-bold text-purple-700">₹<?php echo number_format($customer['total_spent'], 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($topCustomers)): ?>
                            <tr><td colspan="3" class="px-4 py-8 text-center text-gray-400 text-sm">No data for this period</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- end admin-main -->

<script>
    // Sales Chart
    new Chart(document.getElementById('salesChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($dailySales, 'date')); ?>,
            datasets: [{
                label: 'Sales (₹)',
                data: <?php echo json_encode(array_column($dailySales, 'sales')); ?>,
                borderColor: '#8b5cf6',
                backgroundColor: 'rgba(139,92,246,0.1)',
                tension: 0.4, fill: true,
                pointBackgroundColor: '#8b5cf6', pointRadius: 4
            }, {
                label: 'Orders',
                data: <?php echo json_encode(array_column($dailySales, 'orders')); ?>,
                borderColor: '#06b6d4',
                backgroundColor: 'rgba(6,182,212,0.1)',
                tension: 0.4, fill: true,
                pointBackgroundColor: '#06b6d4', pointRadius: 4,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: true, position: 'top' } },
            scales: {
                y:  { type: 'linear', display: true, position: 'left',  grid: { color: 'rgba(0,0,0,.05)' } },
                y1: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false } }
            }
        }
    });

    // Category Chart
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($categoryPerformance, 'name')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($categoryPerformance, 'revenue')); ?>,
                backgroundColor: ['#8b5cf6','#06b6d4','#f59e0b','#10b981','#ef4444','#f97316'],
                borderWidth: 2, borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
            },
            cutout: '60%'
        }
    });
</script>
</body>
</html>
