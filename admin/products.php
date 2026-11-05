<?php
/**
 * Admin Products Management
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/Product.php';

Auth::requirePermission('products.manage');

$product = new Product();

$filters = [
    'category_id' => $_GET['category'] ?? null,
    'search'      => $_GET['search'] ?? null
];

$page    = (int)($_GET['page'] ?? 1);
$result  = $product->getFiltered(array_filter($filters), $page, 20);
$products = $result['data'];

$db         = Database::getInstance();
$stmt       = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY display_order");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-gray-50">

<?php $activePage = 'products'; require_once __DIR__ . '/../includes/admin_nav.php'; ?>

<div class="admin-main px-6 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-extrabold text-gray-900">Products</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
            <i class="bi bi-plus me-2"></i>Add Product
        </button>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($_GET['category'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search products..."
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="products.php" class="btn btn-outline-secondary w-100">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Products Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo htmlspecialchars($p['primary_image'] ?? '../assets/images/product-default.jpg'); ?>"
                                         style="width:50px;height:50px;object-fit:cover;" class="rounded">
                                </td>
                                <td><?php echo htmlspecialchars($p['name']); ?></td>
                                <td><?php echo htmlspecialchars($p['category_name']); ?></td>
                                <td>
                                    <?php if ($p['sale_price']): ?>
                                        <span class="text-decoration-line-through text-muted">₹<?php echo number_format($p['base_price'], 2); ?></span>
                                        <span class="text-primary fw-bold">₹<?php echo number_format($p['sale_price'], 2); ?></span>
                                    <?php else: ?>
                                        ₹<?php echo number_format($p['base_price'], 2); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $p['stock'] > 10 ? 'success' : ($p['stock'] > 0 ? 'warning' : 'danger'); ?>">
                                        <?php echo $p['stock']; ?> in stock
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $p['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $p['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="product_edit.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteProduct(<?php echo $p['id']; ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="product_save.php">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Product Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SKU</label>
                            <input type="text" name="sku" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Brand</label>
                            <input type="text" name="brand" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Base Price (₹)</label>
                            <input type="number" name="base_price" class="form-control" step="0.01" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Sale Price (₹)</label>
                            <input type="number" name="sale_price" class="form-control" step="0.01">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Initial Stock</label>
                            <input type="number" name="stock" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Short Description</label>
                        <textarea name="short_description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Description</label>
                        <textarea name="description" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" checked id="isActive">
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    async function deleteProduct(id) {
        if (!confirm('Are you sure you want to delete this product?')) return;
        try {
            const response = await fetch('../api/products.php?action=delete&id=' + id, { method: 'POST' });
            const result   = await response.json();
            if (result.success) { location.reload(); } else { alert(result.message); }
        } catch (e) { alert('Failed to delete product'); }
    }
</script>
</body>
</html>
