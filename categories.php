<?php
/**
 * Categories Page
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/models/Product.php';

$db = Database::getInstance();

$stmt = $db->query("
    SELECT c.*, 
           COUNT(p.id) as product_count,
           (SELECT image_url FROM product_images 
            JOIN products ON product_images.product_id = products.id 
            WHERE products.category_id = c.id AND products.is_active = 1 
            ORDER BY products.created_at DESC LIMIT 1) as sample_image
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1 AND p.deleted_at IS NULL
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY c.display_order ASC
");
$categories = $stmt->fetchAll();

$storeStatus = StoreHours::getStatus();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-shop me-2"></i><?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="products.php">Products</a></li>
                    <li class="nav-item"><a class="nav-link active" href="categories.php">Categories</a></li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <a href="cart.php" class="btn btn-outline-light position-relative">
                        <i class="bi bi-cart3"></i>
                        <span id="cart-count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display: none;">0</span>
                    </a>
                    <?php if (Auth::check()): ?>
                        <a href="profile.php" class="btn btn-outline-light">
                            <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars(Auth::user()['first_name'] ?? 'User'); ?>
                        </a>
                        <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div style="margin-top: 56px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 60px 0;" class="text-white">
        <div class="container">
            <h1 class="fw-bold"><i class="bi bi-grid me-2"></i>All Categories</h1>
            <p class="lead mb-0">Browse products by category</p>
        </div>
    </div>

    <!-- Categories Grid -->
    <section class="py-5">
        <div class="container">
            <?php if (empty($categories)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-grid text-muted" style="font-size: 4rem;"></i>
                    <h4 class="mt-3 text-muted">No categories available yet</h4>
                    <a href="products.php" class="btn btn-primary mt-3">View All Products</a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($categories as $cat): ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <a href="products.php?category=<?php echo $cat['id']; ?>" class="text-decoration-none">
                                <div class="card category-card h-100 border-0 shadow-sm text-center">
                                    <div style="height: 180px; overflow: hidden;">
                                        <img src="<?php echo htmlspecialchars($cat['sample_image'] ?? 'assets/images/category-default.jpg'); ?>"
                                             class="card-img-top" alt="<?php echo htmlspecialchars($cat['name']); ?>"
                                             style="height: 100%; object-fit: cover;"
                                             onerror="this.src='assets/images/category-default.jpg'">
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title text-dark"><?php echo htmlspecialchars($cat['name']); ?></h5>
                                        <?php if ($cat['description']): ?>
                                            <p class="text-muted small"><?php echo htmlspecialchars($cat['description']); ?></p>
                                        <?php endif; ?>
                                        <span class="badge bg-primary"><?php echo $cat['product_count']; ?> Products</span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="toast-container position-fixed bottom-0 end-0 p-3"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
