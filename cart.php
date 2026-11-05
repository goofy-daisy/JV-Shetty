<?php
/**
 * Shopping Cart Page
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/models/Cart.php';

Auth::requireAuth();

$user    = Auth::user();
$cart    = new Cart();
$items   = $cart->getItems($user['id']);
$totals  = $cart->getTotals($user['id']);
$storeStatus = StoreHours::getStatus();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart – <?php echo APP_NAME; ?></title>
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
                <i class="bi bi-bag2 text-purple-600"></i>
                Shopping Cart
                <?php if (!empty($items)): ?>
                    <span class="text-base font-normal text-gray-500">(<?php echo count($items); ?> item<?php echo count($items) !== 1 ? 's' : ''; ?>)</span>
                <?php endif; ?>
            </h1>

            <?php if (!$storeStatus['is_open']): ?>
                <div class="flex items-center gap-3 p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 text-sm mb-6">
                    <i class="bi bi-clock-fill text-amber-500 flex-shrink-0"></i>
                    <span><strong>Store is currently closed.</strong> You can still browse your cart, but orders can only be placed during open hours (4:00 PM – 9:00 PM).</span>
                </div>
            <?php endif; ?>

            <?php if (empty($items)): ?>
                <!-- Empty State -->
                <div class="flex flex-col items-center justify-center py-20 text-center bg-white rounded-3xl shadow-sm border border-gray-100">
                    <div class="w-24 h-24 rounded-3xl bg-purple-50 flex items-center justify-center mb-6">
                        <i class="bi bi-bag2 text-4xl text-purple-300"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Your cart is empty</h3>
                    <p class="text-gray-500 mb-8 max-w-sm">Looks like you haven't added anything yet. Explore our store!</p>
                    <a href="products.php" class="px-7 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold rounded-xl shadow-md hover:shadow-lg transition-all text-sm">
                        <i class="bi bi-grid mr-2"></i>Browse Products
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Items List -->
                    <div class="lg:col-span-2 space-y-4">
                        <?php foreach ($items as $item): ?>
                            <div class="card-modern p-4 flex gap-4 items-start" id="cart-item-<?php echo $item['id']; ?>">
                                <!-- Image -->
                                <a href="product.php?id=<?php echo $item['product_id']; ?>" class="flex-shrink-0">
                                    <img src="<?php echo $item['image'] ?? 'assets/images/product-default.jpg'; ?>"
                                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                                         class="w-20 h-20 rounded-2xl object-cover bg-gray-50 border border-gray-100"
                                         onerror="this.src='https://via.placeholder.com/80x80/f3f4f6/9ca3af?text=No+Img'">
                                </a>

                                <!-- Details -->
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-semibold text-gray-900 text-sm sm:text-base line-clamp-2 leading-snug mb-1">
                                        <a href="product.php?id=<?php echo $item['product_id']; ?>" class="hover:text-purple-700 transition-colors">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </a>
                                    </h3>
                                    <p class="text-xs text-gray-500 mb-3"><?php echo htmlspecialchars($item['category_name'] ?? ''); ?></p>

                                    <div class="flex items-center justify-between flex-wrap gap-3">
                                        <!-- Quantity Stepper -->
                                        <div class="flex items-center border-2 border-gray-200 rounded-xl overflow-hidden">
                                            <button type="button" class="px-3 py-1.5 text-gray-600 hover:bg-purple-50 hover:text-purple-700 transition-colors font-bold text-base"
                                                    onclick="updateCart(<?php echo $item['id']; ?>, <?php echo $item['quantity'] - 1; ?>)">
                                                <i class="bi bi-dash"></i>
                                            </button>
                                            <span class="w-12 text-center text-sm font-bold text-gray-900 py-1.5 border-x-2 border-gray-200"
                                                  id="qty-<?php echo $item['id']; ?>"><?php echo $item['quantity']; ?></span>
                                            <button type="button" class="px-3 py-1.5 text-gray-600 hover:bg-purple-50 hover:text-purple-700 transition-colors font-bold text-base"
                                                    onclick="updateCart(<?php echo $item['id']; ?>, <?php echo $item['quantity'] + 1; ?>)">
                                                <i class="bi bi-plus"></i>
                                            </button>
                                        </div>

                                        <!-- Price -->
                                        <div class="text-right">
                                            <span class="text-base font-bold text-purple-700" id="item-price-<?php echo $item['id']; ?>">
                                                ₹<?php echo number_format($item['total_price'], 2); ?>
                                            </span>
                                            <span class="block text-xs text-gray-400">₹<?php echo number_format($item['unit_price'], 2); ?> each</span>
                                        </div>

                                        <!-- Remove -->
                                        <button title="Remove" class="text-gray-400 hover:text-red-500 transition-colors p-1"
                                                onclick="removeFromCart(<?php echo $item['id']; ?>)">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Coupon -->
                        <div class="card-modern p-4">
                            <h3 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
                                <i class="bi bi-tag-fill text-purple-500"></i>Have a coupon?
                            </h3>
                            <div class="flex gap-2" id="couponSection">
                                <input type="text" id="couponCode" placeholder="Enter coupon code"
                                       class="flex-1 px-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 transition-all">
                                <button onclick="applyCoupon()" class="px-4 py-2.5 bg-purple-600 text-white font-semibold rounded-xl text-sm hover:bg-purple-700 transition-colors shadow-sm">
                                    Apply
                                </button>
                            </div>
                            <?php if ($totals['coupon_code'] ?? null): ?>
                                <div class="mt-2 text-xs text-emerald-600 font-medium flex items-center gap-1">
                                    <i class="bi bi-check-circle-fill"></i>
                                    Coupon "<?php echo htmlspecialchars($totals['coupon_code']); ?>" applied – saving ₹<?php echo number_format($totals['discount'], 2); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="lg:col-span-1">
                        <div class="card-modern p-6 sticky top-20">
                            <h2 class="text-lg font-bold text-gray-900 mb-5">Order Summary</h2>

                            <div class="space-y-3 mb-5">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Subtotal</span>
                                    <span class="font-semibold text-gray-900" id="cart-subtotal">₹<?php echo number_format($totals['subtotal'], 2); ?></span>
                                </div>
                                <?php if ($totals['discount'] > 0): ?>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-emerald-600">Coupon Discount</span>
                                        <span class="font-semibold text-emerald-600">-₹<?php echo number_format($totals['discount'], 2); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Tax (18% GST)</span>
                                    <span class="font-semibold text-gray-900">₹<?php echo number_format($totals['tax'], 2); ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Delivery Fee</span>
                                    <span class="font-semibold text-emerald-600">Calculated at checkout</span>
                                </div>
                            </div>

                            <div class="border-t-2 border-gray-100 pt-4 mb-5">
                                <div class="flex justify-between">
                                    <span class="text-base font-bold text-gray-900">Estimated Total</span>
                                    <span class="text-base font-extrabold text-purple-700" id="cart-total">₹<?php echo number_format($totals['total'], 2); ?></span>
                                </div>
                            </div>

                            <?php if ($storeStatus['is_open']): ?>
                                <a href="checkout.php" class="block w-full py-3.5 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold rounded-xl text-center text-sm hover:from-purple-700 hover:to-indigo-700 shadow-md hover:shadow-lg transition-all mb-3">
                                    <i class="bi bi-credit-card mr-2"></i>Proceed to Checkout
                                </a>
                            <?php else: ?>
                                <button disabled class="block w-full py-3.5 bg-gray-200 text-gray-500 font-bold rounded-xl text-sm cursor-not-allowed mb-3">
                                    <i class="bi bi-clock mr-2"></i>Opens at 4:00 PM
                                </button>
                            <?php endif; ?>

                            <a href="products.php" class="block w-full py-3 border-2 border-gray-200 text-gray-600 font-semibold rounded-xl text-center text-sm hover:border-purple-300 hover:text-purple-700 transition-all">
                                Continue Shopping
                            </a>

                            <!-- Security Note -->
                            <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-center gap-2 text-xs text-gray-400">
                                <i class="bi bi-shield-check text-emerald-500"></i>
                                Secure checkout
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <div class="toast-container"></div>
    <script src="assets/js/main.js"></script>
    <script>
        async function updateCart(itemId, newQty) {
            if (newQty < 1) { removeFromCart(itemId); return; }
            try {
                const r = await fetch('api/cart.php?action=update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ item_id: itemId, quantity: newQty })
                });
                const d = await r.json();
                if (d.success) {
                    document.getElementById('qty-' + itemId).textContent = newQty;
                    if (d.item_total) document.getElementById('item-price-' + itemId).textContent = '₹' + parseFloat(d.item_total).toFixed(2);
                    if (d.subtotal)   document.getElementById('cart-subtotal').textContent = '₹' + parseFloat(d.subtotal).toFixed(2);
                    if (d.total)      document.getElementById('cart-total').textContent    = '₹' + parseFloat(d.total).toFixed(2);
                    updateCartCount();
                } else { showToast(d.message, 'error'); }
            } catch (e) { showToast('Failed to update cart', 'error'); }
        }

        async function removeFromCart(itemId) {
            try {
                const r = await fetch('api/cart.php?action=remove', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ item_id: itemId })
                });
                const d = await r.json();
                if (d.success) {
                    const el = document.getElementById('cart-item-' + itemId);
                    if (el) { el.style.opacity = '0'; el.style.transform = 'translateX(-10px)'; el.style.transition = 'all .3s'; setTimeout(() => { el.remove(); location.reload(); }, 300); }
                    updateCartCount();
                } else { showToast(d.message, 'error'); }
            } catch (e) { showToast('Failed to remove item', 'error'); }
        }

        async function applyCoupon() {
            const code = document.getElementById('couponCode').value.trim();
            if (!code) { showToast('Please enter a coupon code', 'warning'); return; }
            try {
                const r = await fetch('api/coupons.php?action=apply', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ code: code })
                });
                const d = await r.json();
                if (d.success) { showToast('Coupon applied!'); setTimeout(() => location.reload(), 800); }
                else           { showToast(d.message, 'error'); }
            } catch (e) { showToast('Failed to apply coupon', 'error'); }
        }
    </script>
</body>
</html>
