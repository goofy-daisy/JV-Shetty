<?php
/**
 * Product Update Handler (Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../models/Product.php';

Auth::requirePermission('products.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin/products.php');
    exit;
}

$productId = (int)($_POST['id'] ?? 0);
if (!$productId) {
    header('Location: admin/products.php');
    exit;
}

$product = new Product();
$validator = new Validator();

// Validate input
$rules = [
    'name' => 'required|min:3|max:255',
    'sku' => 'required|min:3|max:100',
    'category_id' => 'required|numeric',
    'base_price' => 'required|numeric|min:0',
    'sale_price' => 'numeric|min:0',
    'stock' => 'numeric|min:0',
    'brand' => 'max:100',
    'short_description' => 'max:500',
    'description' => 'max:5000'
];

$validation = $validator->validate($_POST, $rules);

if (!$validation['valid']) {
    $_SESSION['error'] = 'Please fix the errors below';
    $_SESSION['validation_errors'] = $validation['errors'];
    $_SESSION['old_input'] = $_POST;
    header("Location: admin/product_edit.php?id=$productId");
    exit;
}

// Check if SKU already exists (excluding current product)
$existingProduct = $product->findBy('sku', $_POST['sku']);
if ($existingProduct && $existingProduct['id'] != $productId) {
    $_SESSION['error'] = 'SKU already exists';
    $_SESSION['old_input'] = $_POST;
    header("Location: admin/product_edit.php?id=$productId");
    exit;
}

// Update product
$productData = [
    'name' => $_POST['name'],
    'slug' => strtolower(preg_replace('/[^a-z0-9]+/', '-', $_POST['name'])),
    'sku' => $_POST['sku'],
    'category_id' => $_POST['category_id'],
    'brand' => $_POST['brand'] ?? '',
    'base_price' => $_POST['base_price'],
    'sale_price' => $_POST['sale_price'] ?? null,
    'short_description' => $_POST['short_description'] ?? '',
    'description' => $_POST['description'] ?? '',
    'weight' => $_POST['weight'] ?? 0,
    'dimensions' => $_POST['dimensions'] ?? '',
    'nutritional_info' => $_POST['nutritional_info'] ?? '',
    'allergens' => $_POST['allergens'] ?? '',
    'storage_instructions' => $_POST['storage_instructions'] ?? '',
    'is_active' => isset($_POST['is_active']) ? 1 : 0,
    'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
    'updated_at' => date('Y-m-d H:i:s')
];

$success = $product->update($productId, $productData);

if ($success) {
    // Update inventory
    $db = Database::getInstance();
    $stmt = $db->prepare("
        INSERT INTO inventory (product_id, quantity_available, quantity_reserved, reorder_level, reorder_quantity, last_updated)
        VALUES (?, ?, 0, 5, 20, NOW())
        ON DUPLICATE KEY UPDATE
        quantity_available = VALUES(quantity_available),
        last_updated = VALUES(last_updated)
    ");
    $stmt->execute([$productId, $_POST['stock'] ?? 0]);
    
    $_SESSION['success'] = 'Product updated successfully';
    header('Location: admin/products.php');
} else {
    $_SESSION['error'] = 'Failed to update product';
    header("Location: admin/product_edit.php?id=$productId");
}
exit;
