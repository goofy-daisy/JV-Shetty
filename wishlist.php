<?php
/**
 * Wishlist Page
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/core/Auth.php';

Auth::requireAuth();

$user = Auth::user();
$db   = Database::getInstance();

// Get wishlist items
$stmt = $db->prepare("
    SELECT wi.*, p.name, p.slug, p.base_price, p.sale_price, p.is_active,
           (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image,
           i.quantity_available as stock
    FROM wishlist_items wi
    JOIN wishlists w ON wi.wishlist_id = w.id
    JOIN products p ON wi.product_id = p.id
    LEFT JOIN inventory i ON p.id = i.product_id
    WHERE w.user_id = ? AND p.deleted_at IS NULL
    ORDER BY wi.added_at DESC
");
$stmt->execute([$user['id']]);
$items = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist – <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-50">

    <?php require_once __DIR__ . '/includes/nav.php'; ?>

    <div class="page-content">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-8 flex items-center gap-3">
                <i class="bi bi-heart-fill text-red-500"></i>My Wishlist
                <?php if (!empty($items)): ?>
                    <span class="text-base font-normal text-gray-500">(<?php echo count($items); ?> item<?php echo count($items) !== 1 ? 's' : ''; ?>)</span>
                <?php endif; ?>
            </h1>

            <?php if (empty($items)): ?>
                <div class="flex flex-col items-center justify-center py-20 text-center bg-white rounded-3xl shadow-sm border border-gray-100">
                    <div class="w-24 h-24 rounded-3xl bg-red-50 flex items-center justify-center mb-6">
                        <i class="bi bi-heart text-4xl text-red-300"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Your wishlist is empty</h3>
                    <p class="text-gray-500 mb-8 max-w-sm">Save items you like to find them easily later!</p>
                    <a href="products.php" class="px-7 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold rounded-xl text-sm shadow-md hover:shadow-lg transition-all">
                        Browse Products
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                    <?php foreach ($items as $item): ?>
                        <div class="product-card group wishlist-item">
                            <div class="relative overflow-hidden" style="height:180px;">
                                <a href="product.php?id=<?php echo $item['product_id']; ?>">
                                    <img src="<?php echo $item['image'] ?? 'assets/images/product-default.jpg'; ?>"
                                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                                         class="w-full h-full object-cover"
                                         onerror="this.src='https://via.placeholder.com/300x200/f3f4f6/9ca3af?text=No+Image'">
                                </a>

                                <?php if ($item['stock'] <= 0): ?>
                                    <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-xs font-bold bg-gray-800/80 text-white">Out of Stock</span>
                                <?php elseif ($item['sale_price'] && $item['sale_price'] < $item['base_price']): ?>
                                    <span class="absolute top-2 right-2 px-2 py-0.5 rounded-full text-xs font-bold bg-red-500 text-white">
                                        -<?php echo round((1 - $item['sale_price'] / $item['base_price']) * 100); ?>%
                                    </span>
                                <?php endif; ?>

                                <!-- Wishlist Remove Button (active) -->
                                <button class="wishlist-btn active absolute top-2 right-2 w-7 h-7 rounded-full bg-red-50 border-2 border-red-200 flex items-center justify-center text-red-500 hover:bg-red-100 transition-all shadow-sm"
                                        data-product-id="<?php echo $item['product_id']; ?>" title="Remove from wishlist">
                                    <i class="bi bi-heart-fill text-xs"></i>
                                </button>
                            </div>
                            <div class="p-3">
                                <h3 class="text-sm font-semibold text-gray-900 line-clamp-2 leading-snug mb-2">
                                    <a href="product.php?id=<?php echo $item['product_id']; ?>" class="hover:text-purple-700 transition-colors">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </a>
                                </h3>
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <?php if ($item['sale_price']): ?>
                                            <span class="text-xs text-gray-400 line-through block">₹<?php echo number_format($item['base_price'], 2); ?></span>
                                            <span class="text-sm font-bold text-purple-700">₹<?php echo number_format($item['sale_price'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-sm font-bold text-purple-700">₹<?php echo number_format($item['base_price'], 2); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($item['stock'] > 0): ?>
                                        <button class="add-to-cart w-8 h-8 rounded-xl bg-purple-600 hover:bg-purple-700 text-white flex items-center justify-center transition-all shadow-sm flex-shrink-0"
                                                data-product-id="<?php echo $item['product_id']; ?>">
                                            <i class="bi bi-cart-plus text-sm"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <div class="toast-container"></div>
    <script src="assets/js/main.js"></script>
</body>
</html>
