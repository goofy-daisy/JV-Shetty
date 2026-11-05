<?php
/**
 * Inventory Management (Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/Product.php';

Auth::requirePermission('inventory.manage');

$product = new Product();
$db      = Database::getInstance();

$filters = [
    'category_id'  => $_GET['category'] ?? null,
    'search'       => $_GET['search'] ?? null,
    'stock_status' => $_GET['stock_status'] ?? null
];

$page   = (int)($_GET['page'] ?? 1);
$limit  = 20;
$offset = ($page - 1) * $limit;

$where  = ['p.deleted_at IS NULL'];
$params = [];

if (!empty($filters['category_id'])) {
    $where[]  = 'p.category_id = ?';
    $params[] = $filters['category_id'];
}

if (!empty($filters['search'])) {
    $where[]    = '(p.name LIKE ? OR p.sku LIKE ?)';
    $searchTerm = '%' . $filters['search'] . '%';
    $params[]   = $searchTerm;
    $params[]   = $searchTerm;
}

if ($filters['stock_status'] === 'low') {
    $where[] = 'i.quantity_available <= i.reorder_level';
} elseif ($filters['stock_status'] === 'out') {
    $where[] = 'i.quantity_available = 0';
}

$whereClause = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT p.*, i.quantity_available, i.quantity_reserved, i.reorder_level, i.reorder_quantity,
           c.name as category_name,
           (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
    FROM products p
    LEFT JOIN inventory i ON p.id = i.product_id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE $whereClause
    ORDER BY
        CASE
            WHEN i.quantity_available = 0 THEN 1
            WHEN i.quantity_available <= i.reorder_level THEN 2
            ELSE 3
        END,
        p.name
    LIMIT ? OFFSET ?
");
$stmt->execute([...$params, $limit, $offset]);
$items = $stmt->fetchAll();

$stmt  = $db->prepare("SELECT COUNT(*) FROM products p LEFT JOIN inventory i ON p.id = i.product_id WHERE $whereClause");
$stmt->execute($params);
$total      = $stmt->fetchColumn();
$totalPages = ceil($total / $limit);

$stmt       = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY display_order");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-gray-50">

<?php $activePage = 'inventory'; require_once __DIR__ . '/../includes/admin_nav.php'; ?>

<div class="admin-main px-6 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-extrabold text-gray-900">Inventory Management</h1>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($_GET['category'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="stock_status" class="form-select">
                        <option value="">All Stock Status</option>
                        <option value="low" <?php echo ($_GET['stock_status'] ?? '') === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                        <option value="out" <?php echo ($_GET['stock_status'] ?? '') === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search products..."
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Available</th>
                            <th>Reserved</th>
                            <th>Reorder Level</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo htmlspecialchars($item['image'] ?? '../assets/images/product-default.jpg'); ?>"
                                             style="width:40px;height:40px;object-fit:cover;" class="rounded me-2">
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($item['name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($item['brand'] ?? ''); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $item['quantity_available'] > $item['reorder_level'] ? 'success' : 'warning'; ?>">
                                        <?php echo $item['quantity_available']; ?>
                                    </span>
                                </td>
                                <td><?php echo $item['quantity_reserved']; ?></td>
                                <td><?php echo $item['reorder_level']; ?></td>
                                <td>
                                    <?php if ($item['quantity_available'] == 0): ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                    <?php elseif ($item['quantity_available'] <= $item['reorder_level']): ?>
                                        <span class="badge bg-warning">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary"
                                            onclick="updateStock(<?php echo $item['id']; ?>, <?php echo (int)$item['quantity_available']; ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="product_edit.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-box"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Update Stock Modal -->
<div class="modal fade" id="updateStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="stockUpdateForm">
                <div class="modal-body">
                    <input type="hidden" name="product_id" id="stockProductId">
                    <div class="mb-3">
                        <label class="form-label">Current Stock</label>
                        <input type="text" class="form-control" id="currentStock" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Update Type</label>
                        <select name="update_type" class="form-select" required>
                            <option value="add">Add Stock</option>
                            <option value="remove">Remove Stock</option>
                            <option value="set">Set Stock Level</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" class="form-control" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="Reason for stock adjustment..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const stockModal = new bootstrap.Modal(document.getElementById('updateStockModal'));

    function updateStock(productId, currentStock) {
        document.getElementById('stockProductId').value = productId;
        document.getElementById('currentStock').value   = currentStock;
        stockModal.show();
    }

    document.getElementById('stockUpdateForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(this));
        try {
            const response = await fetch('../api/inventory.php?action=update_stock', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (result.success) { stockModal.hide(); location.reload(); }
            else { alert(result.message); }
        } catch (e) { alert('Failed to update stock'); }
    });
</script>
</body>
</html>
