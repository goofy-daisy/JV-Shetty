<?php
/**
 * CSV Product Importer
 * Import products in bulk from CSV file
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Auth.php';

// Require admin access
Auth::requirePermission('products.manage');

$message = '';
$errors = [];
$successCount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($file['tmp_name'], 'r');
        
        if ($handle !== false) {
            $db = Database::getInstance();
            
            // Read header row
            $headers = fgetcsv($handle);
            
            // Validate headers
            $requiredHeaders = ['name', 'sku', 'category_id', 'base_price', 'stock'];
            $missingHeaders = array_diff($requiredHeaders, $headers);
            
            if (!empty($missingHeaders)) {
                $errors[] = "Missing required columns: " . implode(', ', $missingHeaders);
            } else {
                $lineNumber = 1;
                
                while (($data = fgetcsv($handle)) !== false) {
                    $lineNumber++;
                    
                    // Map CSV row to associative array
                    $row = array_combine($headers, $data);
                    
                    try {
                        $db->beginTransaction();
                        
                        // Prepare product data
                        $stmt = $db->prepare("
                            INSERT INTO products (
                                name, slug, sku, category_id, description, short_description,
                                base_price, sale_price, stock, brand, manufacturer,
                                is_veg, is_featured, is_active, created_by
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        // Generate slug from name
                        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($row['name'])));
                        
                        $stmt->execute([
                            $row['name'],
                            $slug,
                            $row['sku'],
                            $row['category_id'],
                            $row['description'] ?? null,
                            $row['short_description'] ?? null,
                            $row['base_price'],
                            !empty($row['sale_price']) ? $row['sale_price'] : null,
                            $row['stock'],
                            $row['brand'] ?? null,
                            $row['manufacturer'] ?? null,
                            isset($row['is_veg']) ? $row['is_veg'] : 1,
                            isset($row['is_featured']) ? $row['is_featured'] : 0,
                            isset($row['is_active']) ? $row['is_active'] : 1,
                            $_SESSION['user_id']
                        ]);
                        
                        $productId = $db->lastInsertId();
                        
                        // Add product image if provided
                        if (!empty($row['image_url'])) {
                            $stmt = $db->prepare("
                                INSERT INTO product_images (product_id, image_url, is_primary, display_order)
                                VALUES (?, ?, 1, 1)
                            ");
                            $stmt->execute([$productId, $row['image_url']]);
                        }
                        
                        $db->commit();
                        $successCount++;
                        
                    } catch (Exception $e) {
                        $db->rollBack();
                        $errors[] = "Line $lineNumber: " . $e->getMessage();
                    }
                }
                
                if ($successCount > 0) {
                    $message = "Successfully imported $successCount products!";
                }
            }
            
            fclose($handle);
        } else {
            $errors[] = "Failed to open CSV file.";
        }
    } else {
        $errors[] = "File upload error.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Products - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="admin/">
                <i class="bi bi-arrow-left me-2"></i>Back to Admin
            </a>
        </div>
    </nav>
    
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-file-earmark-arrow-up me-2"></i>Import Products from CSV</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="bi bi-check-circle me-2"></i><?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <h6><i class="bi bi-exclamation-triangle me-2"></i>Errors:</h6>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-4">
                            <h5>Instructions:</h5>
                            <ol>
                                <li>Download the CSV template below</li>
                                <li>Fill in your product data (one product per row)</li>
                                <li>Save the file as CSV</li>
                                <li>Upload the file using the form below</li>
                            </ol>
                        </div>
                        
                        <div class="mb-4">
                            <h6>CSV Format:</h6>
                            <p class="small text-muted">Required columns: name, sku, category_id, base_price, stock</p>
                            <p class="small text-muted">Optional columns: description, short_description, sale_price, brand, manufacturer, is_veg, is_featured, is_active, image_url</p>
                            
                            <h6 class="mt-3">Category IDs:</h6>
                            <ul class="small">
                                <li>1 - Snacks & Beverages</li>
                                <li>2 - Daily Essentials</li>
                                <li>3 - Stationery</li>
                                <li>4 - Personal Care</li>
                                <li>5 - Groceries</li>
                                <li>6 - Electronics</li>
                            </ul>
                        </div>
                        
                        <div class="mb-4">
                            <a href="products_template.csv" class="btn btn-outline-primary" download>
                                <i class="bi bi-download me-2"></i>Download CSV Template
                            </a>
                        </div>
                        
                        <hr>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Upload CSV File</label>
                                <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-upload me-2"></i>Import Products
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
