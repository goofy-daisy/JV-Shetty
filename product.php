<?php
/**
 * Product Detail Page
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/models/Product.php';

$productId   = (int)($_GET['id'] ?? 0);
$product     = new Product();
$productData = $product->getWithDetails($productId);

if (!$productData) {
    header('Location: products.php');
    exit;
}

$related = $product->getRelated($productId, 4);
$reviews = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($productData['name']); ?> – <?php echo APP_NAME; ?></title>
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
            <nav class="flex items-center gap-2 text-sm text-gray-500 mb-6 flex-wrap">
                <a href="index.php" class="hover:text-purple-600 transition-colors">Home</a>
                <i class="bi bi-chevron-right text-xs"></i>
                <a href="products.php" class="hover:text-purple-600 transition-colors">Products</a>
                <i class="bi bi-chevron-right text-xs"></i>
                <span class="text-gray-900 font-medium truncate max-w-xs"><?php echo htmlspecialchars($productData['name']); ?></span>
            </nav>

            <!-- Product Detail Card -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden mb-8">
                <div class="grid grid-cols-1 lg:grid-cols-5 gap-0">

                    <!-- Images (left) -->
                    <div class="lg:col-span-2 bg-gray-50 p-6 flex flex-col items-center justify-center border-b lg:border-b-0 lg:border-r border-gray-100">
                        <?php if (!empty($productData['images'])): ?>
                            <div id="productCarousel" class="carousel slide w-full" data-bs-ride="carousel">
                                <div class="carousel-inner rounded-2xl overflow-hidden">
                                    <?php foreach ($productData['images'] as $index => $image): ?>
                                        <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                            <img src="<?php echo $image['image_url']; ?>"
                                                 alt="<?php echo htmlspecialchars($image['alt_text']); ?>"
                                                 class="d-block w-100"
                                                 style="height:380px; object-fit:contain; background:#f9fafb;">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($productData['images']) > 1): ?>
                                    <button class="carousel-control-prev" type="button" data-bs-target="#productCarousel" data-bs-slide="prev">
                                        <span class="carousel-control-prev-icon"></span>
                                    </button>
                                    <button class="carousel-control-next" type="button" data-bs-target="#productCarousel" data-bs-slide="next">
                                        <span class="carousel-control-next-icon"></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php if (count($productData['images']) > 1): ?>
                                <div class="flex gap-2 mt-4 overflow-x-auto pb-1">
                                    <?php foreach ($productData['images'] as $index => $image): ?>
                                        <img src="<?php echo $image['image_url']; ?>"
                                             class="w-16 h-16 rounded-xl object-cover border-2 border-gray-200 hover:border-purple-400 cursor-pointer transition-all flex-shrink-0"
                                             onclick="document.querySelector('#productCarousel').querySelector('.carousel').carousel(<?php echo $index; ?>)"
                                             alt="Thumbnail <?php echo $index+1; ?>">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <img src="assets/images/product-default.jpg"
                                 alt="<?php echo htmlspecialchars($productData['name']); ?>"
                                 class="w-full rounded-2xl object-contain bg-gray-50"
                                 style="height:380px;"
                                 onerror="this.src='https://via.placeholder.com/400x380/f3f4f6/9ca3af?text=No+Image'">
                        <?php endif; ?>
                    </div>

                    <!-- Info (right) -->
                    <div class="lg:col-span-3 p-8">
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div>
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-700 mb-2">
                                    <?php echo htmlspecialchars($productData['category_name']); ?>
                                </span>
                                <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 leading-tight">
                                    <?php echo htmlspecialchars($productData['name']); ?>
                                </h1>
                                <?php if ($productData['brand']): ?>
                                    <p class="text-sm text-gray-500 mt-1">by <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($productData['brand']); ?></span></p>
                                <?php endif; ?>
                            </div>
                            <button class="wishlist-btn flex-shrink-0 w-10 h-10 rounded-xl border-2 border-gray-200 flex items-center justify-center text-gray-400 hover:border-red-300 hover:text-red-500 hover:bg-red-50 transition-all"
                                    data-product-id="<?php echo $productData['id']; ?>" title="Add to wishlist">
                                <i class="bi bi-heart text-base"></i>
                            </button>
                        </div>

                        <!-- Price -->
                        <div class="flex items-center gap-3 mb-5">
                            <?php if ($productData['sale_price'] && $productData['sale_price'] < $productData['base_price']): ?>
                                <span class="text-3xl font-extrabold text-purple-700">₹<?php echo number_format($productData['sale_price'], 2); ?></span>
                                <span class="text-xl text-gray-400 line-through">₹<?php echo number_format($productData['base_price'], 2); ?></span>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-600">
                                    -<?php echo round((1 - $productData['sale_price'] / $productData['base_price']) * 100); ?>% OFF
                                </span>
                            <?php else: ?>
                                <span class="text-3xl font-extrabold text-purple-700">₹<?php echo number_format($productData['base_price'], 2); ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Stock Status -->
                        <div class="mb-5">
                            <?php if ($productData['quantity_available'] > 10): ?>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">
                                    <i class="bi bi-check-circle-fill"></i>In Stock (<?php echo $productData['quantity_available']; ?> available)
                                </span>
                            <?php elseif ($productData['quantity_available'] > 0): ?>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-amber-100 text-amber-700">
                                    <i class="bi bi-exclamation-circle-fill"></i>Only <?php echo $productData['quantity_available']; ?> left!
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-gray-100 text-gray-600">
                                    <i class="bi bi-x-circle-fill"></i>Out of Stock
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Short Description -->
                        <?php if ($productData['short_description']): ?>
                            <p class="text-gray-600 leading-relaxed mb-6"><?php echo htmlspecialchars($productData['short_description']); ?></p>
                        <?php endif; ?>

                        <!-- Add to Cart -->
                        <?php if ($productData['quantity_available'] > 0): ?>
                            <div class="flex gap-3 mb-6">
                                <div class="flex items-center border-2 border-gray-200 rounded-xl overflow-hidden">
                                    <button type="button" class="px-4 py-3 text-gray-600 hover:bg-gray-100 hover:text-purple-700 transition-colors font-bold"
                                            onclick="const i=document.getElementById('quantity');i.value=Math.max(1,+i.value-1)">
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <input type="number" id="quantity" value="1" min="1" max="<?php echo $productData['quantity_available']; ?>"
                                           class="w-16 text-center py-3 border-0 focus:outline-none text-sm font-bold text-gray-900">
                                    <button type="button" class="px-4 py-3 text-gray-600 hover:bg-gray-100 hover:text-purple-700 transition-colors font-bold"
                                            onclick="const i=document.getElementById('quantity');i.value=Math.min(<?php echo $productData['quantity_available']; ?>,+i.value+1)">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>
                                <button class="flex-1 flex items-center justify-center gap-2 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold rounded-xl hover:from-purple-700 hover:to-indigo-700 shadow-md hover:shadow-lg transition-all duration-200 text-sm add-to-cart-detail"
                                        data-product-id="<?php echo $productData['id']; ?>">
                                    <i class="bi bi-cart-plus text-base"></i>Add to Cart
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="mb-6">
                                <button disabled class="w-full py-3 bg-gray-100 text-gray-400 font-semibold rounded-xl cursor-not-allowed text-sm">
                                    Out of Stock
                                </button>
                            </div>
                        <?php endif; ?>

                        <!-- Meta Info -->
                        <div class="border-t border-gray-100 pt-5 space-y-2 text-sm">
                            <div class="flex gap-2 text-gray-600"><span class="font-semibold text-gray-800 w-28">SKU:</span><?php echo htmlspecialchars($productData['sku']); ?></div>
                            <?php if ($productData['weight_kg']): ?>
                                <div class="flex gap-2 text-gray-600"><span class="font-semibold text-gray-800 w-28">Weight:</span><?php echo $productData['weight_kg']; ?> kg</div>
                            <?php endif; ?>
                            <?php if ($productData['expiry_date']): ?>
                                <div class="flex gap-2 text-gray-600"><span class="font-semibold text-gray-800 w-28">Best Before:</span><?php echo date('j M Y', strtotime($productData['expiry_date'])); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Description Tabs -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden mb-8">
                <div class="border-b border-gray-100 px-6">
                    <ul class="flex gap-0 -mb-px" id="productTabs" role="tablist">
                        <li role="presentation">
                            <button class="px-5 py-4 text-sm font-semibold border-b-2 border-purple-600 text-purple-700 transition-all"
                                    id="desc-tab" data-bs-toggle="tab" data-bs-target="#desc" type="button">
                                Description
                            </button>
                        </li>
                        <?php if ($productData['nutritional_info']): ?>
                            <li role="presentation">
                                <button class="px-5 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-purple-700 hover:border-purple-300 transition-all"
                                        id="nutrition-tab" data-bs-toggle="tab" data-bs-target="#nutrition" type="button">
                                    Nutritional Info
                                </button>
                            </li>
                        <?php endif; ?>
                        <?php if ($productData['allergens']): ?>
                            <li role="presentation">
                                <button class="px-5 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-purple-700 hover:border-purple-300 transition-all"
                                        id="allergens-tab" data-bs-toggle="tab" data-bs-target="#allergens" type="button">
                                    Allergens
                                </button>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="tab-content p-6" id="productTabsContent">
                    <div class="tab-pane fade show active text-gray-600 text-sm leading-relaxed" id="desc">
                        <?php echo nl2br(htmlspecialchars($productData['description'] ?? 'No description available.')); ?>
                    </div>
                    <?php if ($productData['nutritional_info']): ?>
                        <div class="tab-pane fade" id="nutrition">
                            <pre class="bg-gray-50 p-4 rounded-xl text-sm text-gray-700 overflow-x-auto"><?php echo htmlspecialchars($productData['nutritional_info']); ?></pre>
                        </div>
                    <?php endif; ?>
                    <?php if ($productData['allergens']): ?>
                        <div class="tab-pane fade" id="allergens">
                            <div class="flex items-start gap-3 p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                                <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-0.5 text-amber-500"></i>
                                <span><strong>Contains:</strong> <?php echo htmlspecialchars($productData['allergens']); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Related Products -->
            <?php if (!empty($related)): ?>
                <div class="mb-8">
                    <h2 class="text-xl font-extrabold text-gray-900 mb-6">You Might Also Like</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <?php foreach ($related as $rel): ?>
                            <div class="product-card group">
                                <div class="overflow-hidden" style="height:160px;">
                                    <a href="product.php?id=<?php echo $rel['id']; ?>">
                                        <img src="<?php echo $rel['primary_image'] ?? 'assets/images/product-default.jpg'; ?>"
                                             alt="<?php echo htmlspecialchars($rel['name']); ?>"
                                             class="w-full h-full object-cover"
                                             onerror="this.src='https://via.placeholder.com/300x200/f3f4f6/9ca3af?text=No+Image'">
                                    </a>
                                </div>
                                <div class="p-3">
                                    <h3 class="text-sm font-semibold text-gray-900 line-clamp-2 mb-1">
                                        <a href="product.php?id=<?php echo $rel['id']; ?>" class="hover:text-purple-700 transition-colors">
                                            <?php echo htmlspecialchars($rel['name']); ?>
                                        </a>
                                    </h3>
                                    <p class="text-sm font-bold text-purple-700">₹<?php echo number_format($rel['sale_price'] ?? $rel['base_price'], 2); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <div class="toast-container"></div>
    <script src="assets/js/main.js"></script>
    <script>
        // Add to cart with quantity
        document.querySelector('.add-to-cart-detail')?.addEventListener('click', async function() {
            const productId = this.dataset.productId;
            const quantity  = document.getElementById('quantity')?.value || 1;
            this.disabled = true;
            this.innerHTML = '<span class="animate-spin inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full mr-2"></span>Adding...';
            try {
                const response = await fetch('api/cart.php?action=add', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: productId, quantity: parseInt(quantity) })
                });
                const data = await response.json();
                if (data.success) {
                    showToast('Added to cart');
                    updateCartCount();
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                showToast('Failed to add to cart', 'error');
            } finally {
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-cart-plus text-base"></i>Add to Cart';
            }
        });
    </script>
</body>
</html>
