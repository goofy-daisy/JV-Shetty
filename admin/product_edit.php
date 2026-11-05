<?php
/**
 * Product Edit Page (Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/Product.php';

Auth::requirePermission('products.manage');

$productId = (int)($_GET['id'] ?? 0);
$product = new Product();
$productData = $product->find($productId);

if (!$productData) {
    header('Location: admin/products.php');
    exit;
}

// Get categories
$db = Database::getInstance();
$stmt = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY display_order");
$categories = $stmt->fetchAll();

// Get inventory
$stmt = $db->prepare("SELECT * FROM inventory WHERE product_id = ?");
$stmt->execute([$productId]);
$inventory = $stmt->fetch();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <h5 class="px-3 mb-3 text-white"><?php echo APP_NAME; ?> Admin</h5>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i>Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php"><i class="bi bi-bag"></i>Orders</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="products.php"><i class="bi bi-box"></i>Products</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="inventory.php"><i class="bi bi-clipboard-data"></i>Inventory</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="customers.php"><i class="bi bi-people"></i>Customers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-right"></i>Logout</a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2">Edit Product</h1>
                    <a href="products.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Back to Products
                    </a>
                </div>
                
                <form method="POST" action="product_update.php">
                    <input type="hidden" name="id" value="<?php echo $productData['id']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Product Name</label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($productData['name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SKU</label>
                            <input type="text" name="sku" class="form-control" value="<?php echo htmlspecialchars($productData['sku']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $productData['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Brand</label>
                            <input type="text" name="brand" class="form-control" value="<?php echo htmlspecialchars($productData['brand'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Base Price (₹)</label>
                            <input type="number" name="base_price" class="form-control" step="0.01" value="<?php echo $productData['base_price']; ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Sale Price (₹)</label>
                            <input type="number" name="sale_price" class="form-control" step="0.01" value="<?php echo $productData['sale_price']; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Current Stock</label>
                            <input type="number" name="stock" class="form-control" value="<?php echo $inventory['quantity_available'] ?? 0; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Short Description</label>
                        <textarea name="short_description" class="form-control" rows="2"><?php echo htmlspecialchars($productData['short_description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Full Description</label>
                        <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($productData['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Weight (kg)</label>
                            <input type="number" name="weight" class="form-control" step="0.01" value="<?php echo $productData['weight']; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Dimensions (L×W×H cm)</label>
                            <input type="text" name="dimensions" class="form-control" value="<?php echo htmlspecialchars($productData['dimensions'] ?? ''); ?>" placeholder="10×5×8">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nutritional Information</label>
                        <textarea name="nutritional_info" class="form-control" rows="3"><?php echo htmlspecialchars($productData['nutritional_info'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Allergens</label>
                        <input type="text" name="allergens" class="form-control" value="<?php echo htmlspecialchars($productData['allergens'] ?? ''); ?>" placeholder="e.g., Nuts, Dairy">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Storage Instructions</label>
                        <textarea name="storage_instructions" class="form-control" rows="2"><?php echo htmlspecialchars($productData['storage_instructions'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" <?php echo $productData['is_active'] ? 'checked' : ''; ?> id="isActive">
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_featured" value="1" <?php echo $productData['is_featured'] ? 'checked' : ''; ?> id="isFeatured">
                        <label class="form-check-label" for="isFeatured">Featured Product</label>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update Product</button>
                        <a href="products.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
