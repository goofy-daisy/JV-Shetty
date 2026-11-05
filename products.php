<?php
/**
 * Products Listing Page
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/models/Product.php';

$product = new Product();

// Get filters from URL
$filters = [
    'category_id'    => $_GET['category'] ?? null,
    'subcategory_id' => $_GET['subcategory'] ?? null,
    'brand'          => $_GET['brand'] ?? null,
    'search'         => $_GET['search'] ?? null,
    'in_stock'       => isset($_GET['in_stock']) ? true : null,
    'price_min'      => $_GET['price_min'] ?? null,
    'price_max'      => $_GET['price_max'] ?? null,
    'sort'           => $_GET['sort'] ?? 'created_at',
    'order'          => $_GET['order'] ?? 'DESC'
];

$page    = (int)($_GET['page'] ?? 1);
$perPage = 20;

$result   = $product->getFiltered(array_filter($filters), $page, $perPage);
$products = $result['data'];
$total    = $result['total'];
$lastPage = $result['last_page'];

// Get categories for filter
$db         = Database::getInstance();
$stmt       = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY display_order");
$categories = $stmt->fetchAll();

// Get brands for filter
$brands = $product->getBrands();

// Get current category info if filtered
$currentCategory = null;
$subcategories   = [];
if ($filters['category_id']) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$filters['category_id']]);
    $currentCategory = $stmt->fetch();

    // Get subcategories
    $stmt = $db->prepare("SELECT * FROM subcategories WHERE category_id = ? AND is_active = 1 ORDER BY display_order");
    $stmt->execute([$filters['category_id']]);
    $subcategories = $stmt->fetchAll();
}

$pageTitle = $currentCategory ? $currentCategory['name'] : (empty($filters['search']) ? 'All Products' : 'Search: ' . htmlspecialchars($filters['search']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> – <?php echo APP_NAME; ?></title>
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

            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
                <a href="index.php" class="hover:text-purple-600 transition-colors">Home</a>
                <i class="bi bi-chevron-right text-xs"></i>
                <?php if ($currentCategory): ?>
                    <a href="products.php" class="hover:text-purple-600 transition-colors">Products</a>
                    <i class="bi bi-chevron-right text-xs"></i>
                    <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($currentCategory['name']); ?></span>
                <?php else: ?>
                    <span class="text-gray-900 font-medium"><?php echo $pageTitle; ?></span>
                <?php endif; ?>
            </nav>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                <!-- Filters Sidebar -->
                <div class="lg:col-span-1">
                    <!-- Mobile Filter Toggle -->
                    <button id="filterToggle" class="lg:hidden w-full flex items-center justify-between px-4 py-3 bg-white rounded-2xl shadow-sm border border-gray-100 text-sm font-semibold text-gray-700 mb-4">
                        <span class="flex items-center gap-2"><i class="bi bi-funnel text-purple-600"></i>Filters</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>

                    <div id="filterPanel" class="hidden lg:block">
                        <div class="filter-drawer sticky top-20">
                            <div class="flex items-center justify-between mb-5">
                                <h3 class="font-bold text-gray-900 flex items-center gap-2">
                                    <i class="bi bi-funnel-fill text-purple-600"></i>Filters
                                </h3>
                                <a href="products.php" class="text-xs text-purple-600 hover:underline font-medium">Clear all</a>
                            </div>

                            <form method="get" action="" class="space-y-5">
                                <?php if ($filters['category_id']): ?>
                                    <input type="hidden" name="category" value="<?php echo $filters['category_id']; ?>">
                                <?php endif; ?>

                                <!-- Search -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Search</label>
                                    <div class="relative">
                                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                        <input type="text" name="search" class="w-full pl-8 pr-3 py-2.5 text-sm border-2 border-gray-200 rounded-xl focus:outline-none focus:border-purple-500 transition-all"
                                               value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>" placeholder="Search products...">
                                    </div>
                                </div>

                                <!-- Subcategories -->
                                <?php if (!empty($subcategories)): ?>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Subcategory</label>
                                        <select name="subcategory" class="w-full px-3 py-2.5 text-sm border-2 border-gray-200 rounded-xl focus:outline-none focus:border-purple-500 transition-all bg-white">
                                            <option value="">All Subcategories</option>
                                            <?php foreach ($subcategories as $sub): ?>
                                                <option value="<?php echo $sub['id']; ?>" <?php echo $filters['subcategory_id'] == $sub['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($sub['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <!-- Brands -->
                                <?php if (!empty($brands)): ?>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Brand</label>
                                        <select name="brand" class="w-full px-3 py-2.5 text-sm border-2 border-gray-200 rounded-xl focus:outline-none focus:border-purple-500 transition-all bg-white">
                                            <option value="">All Brands</option>
                                            <?php foreach ($brands as $brand): ?>
                                                <option value="<?php echo htmlspecialchars($brand['brand']); ?>" <?php echo $filters['brand'] === $brand['brand'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($brand['brand']); ?> (<?php echo $brand['product_count']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <!-- Price Range -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Price Range (₹)</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="number" name="price_min" placeholder="Min"
                                               class="px-3 py-2.5 text-sm border-2 border-gray-200 rounded-xl focus:outline-none focus:border-purple-500 transition-all"
                                               value="<?php echo $filters['price_min'] ?? ''; ?>">
                                        <input type="number" name="price_max" placeholder="Max"
                                               class="px-3 py-2.5 text-sm border-2 border-gray-200 rounded-xl focus:outline-none focus:border-purple-500 transition-all"
                                               value="<?php echo $filters['price_max'] ?? ''; ?>">
                                    </div>
                                </div>

                                <!-- In Stock -->
                                <label class="flex items-center gap-3 cursor-pointer group">
                                    <input type="checkbox" name="in_stock" class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                                           <?php echo $filters['in_stock'] ? 'checked' : ''; ?>>
                                    <span class="text-sm font-medium text-gray-700 group-hover:text-purple-700 transition-colors">In Stock Only</span>
                                </label>

                                <!-- Sort By -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Sort By</label>
                                    <select name="sort" class="w-full px-3 py-2.5 text-sm border-2 border-gray-200 rounded-xl focus:outline-none focus:border-purple-500 transition-all bg-white">
                                        <option value="created_at" <?php echo $filters['sort'] === 'created_at' ? 'selected' : ''; ?>>Newest First</option>
                                        <option value="name"       <?php echo $filters['sort'] === 'name'       ? 'selected' : ''; ?>>Name</option>
                                        <option value="base_price" <?php echo $filters['sort'] === 'base_price' ? 'selected' : ''; ?>>Price</option>
                                    </select>
                                </div>

                                <button type="submit" class="w-full py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold rounded-xl text-sm hover:from-purple-700 hover:to-indigo-700 shadow-md transition-all duration-200">
                                    Apply Filters
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Products Grid -->
                <div class="lg:col-span-3">
                    <!-- Header row -->
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h1 class="text-2xl font-extrabold text-gray-900"><?php echo $pageTitle; ?></h1>
                            <p class="text-sm text-gray-500 mt-0.5"><?php echo number_format($total); ?> product<?php echo $total !== 1 ? 's' : ''; ?> found</p>
                        </div>
                    </div>

                    <?php if (empty($products)): ?>
                        <div class="flex flex-col items-center justify-center py-20 text-center">
                            <div class="w-24 h-24 rounded-3xl bg-gray-100 flex items-center justify-center mb-6">
                                <i class="bi bi-search text-4xl text-gray-400"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">No products found</h3>
                            <p class="text-gray-500 mb-6 max-w-sm">Try adjusting your filters or search terms</p>
                            <a href="products.php" class="px-6 py-2.5 bg-purple-600 text-white font-semibold rounded-xl text-sm hover:bg-purple-700 transition-colors">
                                Browse All Products
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
                            <?php foreach ($products as $product): ?>
                                <div class="product-card group">
                                    <div class="relative overflow-hidden" style="height:200px;">
                                        <a href="product.php?id=<?php echo $product['id']; ?>">
                                            <img src="<?php echo $product['primary_image'] ?? 'assets/images/product-default.jpg'; ?>"
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                 class="w-full h-full object-cover"
                                                 onerror="this.src='https://via.placeholder.com/300x200/f3f4f6/9ca3af?text=No+Image'">
                                        </a>
                                        <?php if ($product['stock'] <= 0): ?>
                                            <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-xs font-bold bg-gray-800/80 text-white">Out of Stock</span>
                                        <?php elseif ($product['sale_price'] && $product['sale_price'] < $product['base_price']): ?>
                                            <span class="absolute top-2 right-2 px-2 py-0.5 rounded-full text-xs font-bold bg-red-500 text-white">
                                                -<?php echo round((1 - $product['sale_price'] / $product['base_price']) * 100); ?>%
                                            </span>
                                        <?php elseif ($product['stock'] <= 10): ?>
                                            <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-xs font-bold bg-amber-500 text-white">Low Stock</span>
                                        <?php endif; ?>
                                        <button class="wishlist-btn absolute top-2 right-2 w-7 h-7 rounded-full bg-white/90 flex items-center justify-center shadow hover:bg-red-50 hover:text-red-500 transition-all opacity-0 group-hover:opacity-100 text-gray-500"
                                                data-product-id="<?php echo $product['id']; ?>">
                                            <i class="bi bi-heart text-xs"></i>
                                        </button>
                                    </div>
                                    <div class="p-3">
                                        <p class="text-xs text-purple-600 font-semibold mb-0.5 truncate"><?php echo htmlspecialchars($product['category_name']); ?></p>
                                        <h3 class="text-sm font-semibold text-gray-900 leading-snug line-clamp-2 mb-2">
                                            <a href="product.php?id=<?php echo $product['id']; ?>" class="hover:text-purple-700 transition-colors">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </a>
                                        </h3>
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <?php if ($product['sale_price']): ?>
                                                    <span class="text-xs text-gray-400 line-through block">₹<?php echo number_format($product['base_price'], 2); ?></span>
                                                    <span class="text-sm font-bold text-purple-700">₹<?php echo number_format($product['sale_price'], 2); ?></span>
                                                <?php else: ?>
                                                    <span class="text-sm font-bold text-purple-700">₹<?php echo number_format($product['base_price'], 2); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($product['stock'] > 0): ?>
                                                <button class="add-to-cart w-8 h-8 rounded-lg bg-purple-600 hover:bg-purple-700 text-white flex items-center justify-center transition-all shadow-sm"
                                                        data-product-id="<?php echo $product['id']; ?>">
                                                    <i class="bi bi-cart-plus text-sm"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($lastPage > 1): ?>
                            <div class="pagination-modern mt-8">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                                   class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <i class="bi bi-chevron-left text-sm"></i>
                                </a>
                                <?php
                                $start = max(1, $page - 2);
                                $end   = min($lastPage, $page + 2);
                                if ($start > 1) echo '<span class="page-btn text-gray-400">…</span>';
                                for ($i = $start; $i <= $end; $i++): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                       class="page-btn <?php echo $page == $i ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor;
                                if ($end < $lastPage) echo '<span class="page-btn text-gray-400">…</span>';
                                ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                                   class="page-btn <?php echo $page >= $lastPage ? 'disabled' : ''; ?>">
                                    <i class="bi bi-chevron-right text-sm"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <div class="toast-container"></div>
    <script src="assets/js/main.js"></script>
    <script>
        // Mobile filter toggle
        const filterBtn  = document.getElementById('filterToggle');
        const filterPanel = document.getElementById('filterPanel');
        if (filterBtn) filterBtn.addEventListener('click', () => filterPanel.classList.toggle('hidden'));
    </script>
</body>
</html>
