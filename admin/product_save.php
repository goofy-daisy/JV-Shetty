<?php
/**
 * Product Save Handler (Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../models/Product.php';

Auth::requirePermission('products.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/products.php');
    exit;
}

$product   = new Product();
$validator = new Validator();

$rules = [
    'name'              => 'required|min:3|max:255',
    'sku'               => 'required|min:3|max:100',
    'category_id'       => 'required|numeric',
    'base_price'        => 'required|numeric|min:0',
    'sale_price'        => 'numeric|min:0',
    'stock'             => 'numeric|min:0',
    'brand'             => 'max:100',
    'short_description' => 'max:500',
    'description'       => 'max:5000'
];

if (!$validator->validate($_POST, $rules)) {
    $_SESSION['error']     = $validator->firstError();
    $_SESSION['old_input'] = $_POST;
    header('Location: ' . BASE_URL . '/admin/products.php');
    exit;
}

$existingProduct = $product->findBy('sku', $_POST['sku']);
if ($existingProduct) {
    $_SESSION['error']     = 'SKU already exists';
    $_SESSION['old_input'] = $_POST;
    header('Location: ' . BASE_URL . '/admin/products.php');
    exit;
}

// Only pass fields in Product->fillable that match real DB column names.
// 'nutritional_info' is JSON NULL in DB — empty string '' causes a MySQL error, so omit it.
// 'weight_kg' is nullable, omit it. 'dimensions'/'storage_instructions' not in fillable, omit them.
$productData = [
    'name'              => $_POST['name'],
    'slug'              => strtolower(preg_replace('/[^a-z0-9]+/', '-', $_POST['name'])),
    'sku'               => $_POST['sku'],
    'category_id'       => $_POST['category_id'],
    'brand'             => $_POST['brand'] ?? '',
    'base_price'        => $_POST['base_price'],
    'sale_price'        => !empty($_POST['sale_price']) ? $_POST['sale_price'] : null,
    'short_description' => $_POST['short_description'] ?? '',
    'description'       => $_POST['description'] ?? '',
    'is_active'         => isset($_POST['is_active']) ? 1 : 0,
    'is_featured'       => 0,
    'created_by'        => Auth::user()['id'],
];

$productId = $product->create($productData);

if ($productId) {
    if (!empty($_FILES['image']['tmp_name'])) {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $maxBytes     = 5 * 1024 * 1024;
        $fileMime     = mime_content_type($_FILES['image']['tmp_name']);
        $fileSize     = $_FILES['image']['size'];

        if (!in_array($fileMime, $allowedMimes, true)) {
            $_SESSION['error'] = 'Invalid image format. Use JPEG, PNG, or WebP.';
            header('Location: ' . BASE_URL . '/admin/products.php');
            exit;
        }
        if ($fileSize > $maxBytes) {
            $_SESSION['error'] = 'Image is too large. Maximum size is 5 MB.';
            header('Location: ' . BASE_URL . '/admin/products.php');
            exit;
        }

        $ext      = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = 'product_' . $productId . '_' . time() . '.' . strtolower($ext);
        $destDir  = __DIR__ . '/../uploads/products/';
        $destPath = $destDir . $filename;

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        if (move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
            $db = Database::getInstance();
            $db->prepare("
                INSERT INTO product_images (product_id, image_url, is_primary, display_order)
                VALUES (?, ?, 1, 1)
            ")->execute([$productId, 'uploads/products/' . $filename]);
        }
    }

    // Inventory column is 'last_stock_update' (not 'last_updated')
    $db = Database::getInstance();
    $db->prepare("
        INSERT INTO inventory (product_id, quantity_in_stock, quantity_reserved, reorder_level, reorder_quantity, last_stock_update)
        VALUES (?, ?, 0, 5, 20, NOW())
    ")->execute([$productId, $_POST['stock'] ?? 0]);

    $_SESSION['success'] = 'Product created successfully';
    header('Location: ' . BASE_URL . '/admin/products.php');
} else {
    $_SESSION['error'] = 'Failed to create product';
    header('Location: ' . BASE_URL . '/admin/products.php');
}
exit;
