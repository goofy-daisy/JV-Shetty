<?php
/**
 * Main entry point - index.php
 * Landing page for JV Shetty Store
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/models/Product.php';

$db = Database::getInstance();

$stmt = $db->query("
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.is_featured = 1
    AND p.is_active = 1
    ORDER BY p.created_at DESC
    LIMIT 4
");

$featured = $stmt->fetchAll(PDO::FETCH_ASSOC);
$storeStatus = StoreHours::getStatus();

// Get categories
$stmt = $db->query("
    SELECT c.*,
           (SELECT image_url FROM product_images
            JOIN products ON product_images.product_id = products.id
            WHERE products.category_id = c.id AND products.is_active = 1
            ORDER BY products.created_at DESC LIMIT 1) as sample_image
    FROM categories c
    WHERE c.is_active = 1
    ORDER BY c.display_order ASC
    LIMIT 6
");
$categories = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="JV Shetty - Your campus supermarket at BITS Pilani Goa. Shop snacks, essentials and daily needs online with hostel delivery.">
    <title><?php echo APP_NAME; ?> - BITS Pilani Goa Campus Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-50">

    <?php require_once __DIR__ . '/includes/nav.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section page-content flex items-center relative">
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-white/5 blur-3xl"></div>
            <div class="absolute bottom-0 -left-24 w-80 h-80 rounded-full bg-purple-300/10 blur-3xl"></div>
        </div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 w-full relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <!-- Text -->
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/15 border border-white/25 text-white text-sm font-medium mb-6 backdrop-blur-sm">
                        <i class="bi bi-geo-alt-fill text-yellow-300"></i>
                        BITS Pilani Goa Campus
                    </div>
                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white leading-tight mb-6">
                        Your Campus<br>
                        <span class="text-yellow-300">Supermarket</span>
                    </h1>
                    <p class="text-lg text-white/80 mb-8 max-w-md leading-relaxed">
                        Snacks, essentials, and daily needs — ordered online & delivered to your hostel door. Only for BITSians. 🎓
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <a href="products.php" class="inline-flex items-center gap-2 px-7 py-3.5 bg-white text-purple-700 font-bold rounded-2xl hover:bg-yellow-50 shadow-xl hover:shadow-2xl transition-all duration-300 text-sm">
                            <i class="bi bi-bag2-fill"></i>Shop Now
                        </a>
                        <?php if (!$storeStatus['is_open']): ?>
                            <div class="inline-flex items-center gap-2 px-5 py-3.5 bg-red-500/20 border border-red-400/40 text-white rounded-2xl text-sm backdrop-blur-sm">
                                <i class="bi bi-clock-fill text-red-300"></i>
                                Opens at 4:00 PM
                            </div>
                        <?php else: ?>
                            <div class="inline-flex items-center gap-2 px-5 py-3.5 bg-emerald-500/20 border border-emerald-400/40 text-white rounded-2xl text-sm backdrop-blur-sm">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                Open now — Closes 9 PM
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Hero Image -->
                <div class="hidden lg:flex justify-center items-center">
                    <div class="relative">
                        <div class="absolute inset-0 rounded-3xl bg-white/10 blur-2xl scale-95"></div>
                        <img src="assets/images/hero-store.png"
                             alt="JV Shetty Store"
                             class="relative w-full max-w-md rounded-3xl shadow-2xl object-cover"
                             onerror="this.style.display='none'; this.parentElement.innerHTML += '<div class=\'w-80 h-64 rounded-3xl bg-white/15 flex items-center justify-center border border-white/20 backdrop-blur-sm\'><i class=\'bi bi-shop text-8xl text-white/60\'></i></div>'">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Store Info Strip -->
    <section class="bg-gray-900 py-5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-0 md:divide-x md:divide-gray-700">
                <div class="flex items-center gap-3 justify-center px-4 py-2">
                    <div class="w-10 h-10 rounded-xl bg-purple-600/20 flex items-center justify-center flex-shrink-0">
                        <i class="bi bi-clock-fill text-purple-400"></i>
                    </div>
                    <div>
                        <p class="text-white font-semibold text-sm">Open Daily</p>
                        <p class="text-gray-400 text-xs">4:00 PM – 9:00 PM</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 justify-center px-4 py-2">
                    <div class="w-10 h-10 rounded-xl bg-blue-600/20 flex items-center justify-center flex-shrink-0">
                        <i class="bi bi-truck-fill text-blue-400"></i>
                    </div>
                    <div>
                        <p class="text-white font-semibold text-sm">Campus Delivery</p>
                        <p class="text-gray-400 text-xs">To your hostel door</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 justify-center px-4 py-2">
                    <div class="w-10 h-10 rounded-xl bg-emerald-600/20 flex items-center justify-center flex-shrink-0">
                        <i class="bi bi-bag-check-fill text-emerald-400"></i>
                    </div>
                    <div>
                        <p class="text-white font-semibold text-sm">In-Store Pickup</p>
                        <p class="text-gray-400 text-xs">Skip the queue</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 justify-center px-4 py-2">
                    <div class="w-10 h-10 rounded-xl bg-yellow-600/20 flex items-center justify-center flex-shrink-0">
                        <i class="bi bi-shield-check-fill text-yellow-400"></i>
                    </div>
                    <div>
                        <p class="text-white font-semibold text-sm">BITSians Only</p>
                        <p class="text-gray-400 text-xs">Campus email required</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories -->
    <?php if (!empty($categories)): ?>
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-10">
                <div>
                    <h2 class="text-3xl font-extrabold text-gray-900">Shop by Category</h2>
                    <p class="text-gray-500 mt-1 text-sm">Find what you need, fast</p>
                </div>
                <a href="categories.php" class="text-purple-600 hover:text-purple-700 text-sm font-semibold flex items-center gap-1 transition-colors">
                    View all <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <?php foreach ($categories as $category): ?>
                    <a href="products.php?category=<?php echo $category['id']; ?>" class="category-card group">
                        <div class="aspect-square bg-gradient-to-br from-purple-50 to-indigo-50 overflow-hidden">
                            <?php if ($category['sample_image']): ?>
                                <img src="<?php echo htmlspecialchars($category['sample_image']); ?>"
                                     alt="<?php echo htmlspecialchars($category['name']); ?>"
                                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                                     onerror="this.style.display='none'">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <i class="bi bi-grid text-4xl text-purple-300"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-3 text-center">
                            <p class="text-sm font-semibold text-gray-800 group-hover:text-purple-700 transition-colors truncate">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Featured Products -->
    <?php if (!empty($featured)): ?>
    <section class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-10">
                <div>
                    <h2 class="text-3xl font-extrabold text-gray-900">Featured Products</h2>
                    <p class="text-gray-500 mt-1 text-sm">Handpicked favorites for you</p>
                </div>
                <a href="products.php" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700 transition-colors shadow-sm">
                    View All <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($featured as $product): ?>
                    <div class="product-card group">
                        <div class="relative overflow-hidden" style="height:220px;">
                            <a href="product.php?id=<?php echo $product['id']; ?>">
                                <img src="<?php echo $product['primary_image'] ?? 'assets/images/product-default.jpg'; ?>"
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     class="w-full h-full object-cover"
                                     onerror="this.src='https://via.placeholder.com/400x300/f3f4f6/9ca3af?text=No+Image'">
                            </a>
                            <!-- Badges -->
                            <?php if ($product['stock'] <= 0): ?>
                                <span class="absolute top-2 left-2 px-2.5 py-1 rounded-full text-xs font-bold bg-gray-800/80 text-white backdrop-blur-sm">Out of Stock</span>
                            <?php elseif ($product['sale_price'] && $product['sale_price'] < $product['base_price']): ?>
                                <span class="absolute top-2 right-2 px-2.5 py-1 rounded-full text-xs font-bold bg-red-500 text-white shadow">
                                    -<?php echo round((1 - $product['sale_price'] / $product['base_price']) * 100); ?>%
                                </span>
                            <?php elseif ($product['stock'] <= 10): ?>
                                <span class="absolute top-2 left-2 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-500 text-white shadow">Low Stock</span>
                            <?php endif; ?>
                            <!-- Wishlist Btn -->
                            <button class="wishlist-btn absolute top-2 right-2 w-8 h-8 rounded-full bg-white/90 backdrop-blur-sm flex items-center justify-center shadow-md hover:bg-red-50 hover:text-red-500 transition-all duration-200 opacity-0 group-hover:opacity-100"
                                    data-product-id="<?php echo $product['id']; ?>" title="Add to wishlist">
                                <i class="bi bi-heart text-sm"></i>
                            </button>
                        </div>
                        <div class="p-4">
                            <p class="text-xs text-purple-600 font-semibold uppercase tracking-wide mb-1"><?php echo htmlspecialchars($product['category_name']); ?></p>
                            <h3 class="font-semibold text-gray-900 text-sm leading-snug mb-3 line-clamp-2">
                                <a href="product.php?id=<?php echo $product['id']; ?>" class="hover:text-purple-700 transition-colors">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </a>
                            </h3>
                            <div class="flex items-center justify-between">
                                <div>
                                    <?php if ($product['sale_price'] && $product['sale_price'] < $product['base_price']): ?>
                                        <span class="text-xs text-gray-400 line-through block">₹<?php echo number_format($product['base_price'], 2); ?></span>
                                        <span class="text-lg font-bold text-purple-700">₹<?php echo number_format($product['sale_price'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="text-lg font-bold text-purple-700">₹<?php echo number_format($product['base_price'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($product['stock'] > 0): ?>
                                    <button class="add-to-cart w-9 h-9 rounded-xl bg-purple-600 hover:bg-purple-700 text-white flex items-center justify-center shadow-sm hover:shadow-md transition-all duration-200"
                                            data-product-id="<?php echo $product['id']; ?>">
                                        <i class="bi bi-cart-plus text-sm"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400 font-medium">Unavailable</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- How It Works -->
    <section class="py-16 bg-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-extrabold text-gray-900 mb-3">How It Works</h2>
            <p class="text-gray-500 mb-12">Order in 4 easy steps</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 md:gap-4 relative">
                <!-- Connecting line (desktop) -->
                <div class="hidden md:block absolute top-8 left-1/4 right-1/4 h-px bg-gradient-to-r from-purple-300 via-indigo-300 to-purple-300" style="left:13%;right:13%;"></div>
                <?php
                $steps = [
                    ['icon'=>'grid', 'title'=>'Browse', 'desc'=>'Find your favourites from our curated selection'],
                    ['icon'=>'bag-check', 'title'=>'Checkout', 'desc'=>'Add to cart and place your order securely'],
                    ['icon'=>'box-seam', 'title'=>'We Pack', 'desc'=>'Our team picks & packs your order with care'],
                    ['icon'=>'house-check', 'title'=>'Receive', 'desc'=>'Get it delivered to your hostel or pick up in store'],
                ];
                foreach ($steps as $i => $step):
                ?>
                    <div class="flex flex-col items-center gap-3 relative">
                        <div class="step-circle">
                            <i class="bi bi-<?php echo $step['icon']; ?>"></i>
                        </div>
                        <div class="absolute -top-2 -right-2 md:hidden w-6 h-6 rounded-full bg-purple-100 text-purple-700 text-xs font-bold flex items-center justify-center"><?php echo $i+1; ?></div>
                        <h3 class="font-bold text-gray-900 text-base"><?php echo $step['title']; ?></h3>
                        <p class="text-gray-500 text-sm leading-relaxed"><?php echo $step['desc']; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA Banner -->
    <section class="py-14 bg-gradient-to-r from-purple-700 via-indigo-700 to-purple-800">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <h2 class="text-3xl font-extrabold text-white mb-3">Ready to order?</h2>
            <p class="text-purple-200 mb-8">Join hundreds of BITSians who shop smarter.</p>
            <div class="flex flex-wrap gap-3 justify-center">
                <a href="products.php" class="px-7 py-3.5 bg-white text-purple-700 font-bold rounded-2xl hover:shadow-xl transition-all">
                    Browse Products
                </a>
                <?php if (!Auth::check()): ?>
                    <a href="register.php" class="px-7 py-3.5 bg-transparent border-2 border-white text-white font-bold rounded-2xl hover:bg-white/10 transition-all">
                        Create Account
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <!-- Toast Container -->
    <div class="toast-container"></div>

    <script src="assets/js/main.js"></script>
</body>
</html>
