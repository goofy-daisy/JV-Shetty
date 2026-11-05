<?php
/**
 * Shared Navigation Bar - Customer Pages
 * Requires: Auth class, StoreHours class, APP_NAME constant
 * Variables used: $activePage (optional, string)
 */
if (!class_exists('StoreHours')) {
    require_once __DIR__ . '/../core/StoreHours.php';
}
$_navStore = StoreHours::getStatus();
$_navUser  = Auth::check() ? Auth::user() : null;
?>
<nav class="site-nav fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-md shadow-sm border-b border-gray-100" id="mainNav">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Logo -->
            <a href="/jv_shetty/index.php" class="flex items-center gap-2 group">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center shadow-md group-hover:shadow-purple-300 transition-all duration-300">
                    <i class="bi bi-shop text-white text-sm"></i>
                </div>
                <span class="font-bold text-xl text-gray-900 tracking-tight"><?php echo APP_NAME; ?></span>
                <!-- Store status badge -->
                <?php if ($_navStore['is_open']): ?>
                    <span class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 border border-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>Open
                    </span>
                <?php else: ?>
                    <span class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-600 border border-red-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>Closed
                    </span>
                <?php endif; ?>
            </a>

            <!-- Desktop Nav Links -->
            <div class="hidden md:flex items-center gap-1">
                <a href="/jv_shetty/index.php" class="nav-link px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:text-purple-700 hover:bg-purple-50 transition-all duration-200">Home</a>
                <a href="/jv_shetty/products.php" class="nav-link px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:text-purple-700 hover:bg-purple-50 transition-all duration-200">Products</a>
                <a href="/jv_shetty/categories.php" class="nav-link px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:text-purple-700 hover:bg-purple-50 transition-all duration-200">Categories</a>
            </div>

            <!-- Search Bar (desktop) -->
            <form action="/jv_shetty/products.php" method="get" class="hidden lg:flex items-center w-64">
                <div class="relative w-full">
                    <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                        class="w-full pl-9 pr-4 py-2 text-sm bg-gray-100 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-400 focus:bg-white transition-all duration-200">
                </div>
            </form>

            <!-- Right Side Icons -->
            <div class="flex items-center gap-2">
                <!-- Cart -->
                <a href="/jv_shetty/cart.php" class="relative p-2 rounded-xl text-gray-600 hover:text-purple-700 hover:bg-purple-50 transition-all duration-200" title="Cart">
                    <i class="bi bi-bag2 text-lg"></i>
                    <span id="cart-count" class="absolute -top-0.5 -right-0.5 w-5 h-5 rounded-full bg-purple-600 text-white text-xs flex items-center justify-center font-bold" style="display:none;">0</span>
                </a>

                <!-- Wishlist -->
                <a href="/jv_shetty/wishlist.php" class="hidden sm:flex p-2 rounded-xl text-gray-600 hover:text-red-500 hover:bg-red-50 transition-all duration-200" title="Wishlist">
                    <i class="bi bi-heart text-lg"></i>
                </a>

                <?php if ($_navUser): ?>
                    <!-- User Dropdown -->
                    <div class="relative" id="userDropdownWrapper">
                        <button id="userDropdownBtn" class="flex items-center gap-2 pl-2 pr-3 py-1.5 rounded-xl bg-gray-100 hover:bg-purple-50 text-gray-700 hover:text-purple-700 transition-all duration-200 text-sm font-medium">
                            <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center text-white text-xs font-bold">
                                <?php echo strtoupper(substr($_navUser['first_name'] ?? 'U', 0, 1)); ?>
                            </div>
                            <span class="hidden sm:inline"><?php echo htmlspecialchars($_navUser['first_name'] ?? 'User'); ?></span>
                            <i class="bi bi-chevron-down text-xs"></i>
                        </button>
                        <div id="userDropdown" class="hidden absolute right-0 mt-2 w-52 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50">
                            <div class="px-4 py-2 border-b border-gray-100">
                                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars(($_navUser['first_name'] ?? '') . ' ' . ($_navUser['last_name'] ?? '')); ?></p>
                                <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($_navUser['email'] ?? ''); ?></p>
                            </div>
                            <a href="/jv_shetty/profile.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition-colors">
                                <i class="bi bi-person w-4"></i>My Profile
                            </a>
                            <a href="/jv_shetty/orders.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition-colors">
                                <i class="bi bi-bag w-4"></i>My Orders
                            </a>
                            <?php if (Auth::hasPermission('orders.manage')): ?>
                                <a href="/jv_shetty/admin/index.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition-colors">
                                    <i class="bi bi-shield-check w-4"></i>Admin Panel
                                </a>
                            <?php endif; ?>
                            <?php if (Auth::hasPermission('delivery.view')): ?>
                                <a href="/jv_shetty/delivery/index.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition-colors">
                                    <i class="bi bi-truck w-4"></i>Delivery Portal
                                </a>
                            <?php endif; ?>
                            <div class="border-t border-gray-100 mt-1 pt-1">
                                <a href="/jv_shetty/logout.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                    <i class="bi bi-box-arrow-right w-4"></i>Logout
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="/jv_shetty/login.php" class="px-4 py-2 text-sm font-semibold text-white bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl hover:from-purple-700 hover:to-indigo-700 shadow-sm hover:shadow-md transition-all duration-200">
                        Login
                    </a>
                <?php endif; ?>

                <!-- Mobile Menu Toggle -->
                <button id="mobileMenuBtn" class="md:hidden p-2 rounded-xl text-gray-600 hover:text-purple-700 hover:bg-purple-50 transition-all duration-200">
                    <i class="bi bi-list text-xl"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div id="mobileMenu" class="hidden md:hidden bg-white border-t border-gray-100 px-4 pb-4 space-y-1">
        <!-- Mobile Search -->
        <form action="/jv_shetty/products.php" method="get" class="pt-3 pb-2">
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="search" placeholder="Search..." class="w-full pl-9 pr-4 py-2 text-sm bg-gray-100 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-400">
            </div>
        </form>
        <a href="/jv_shetty/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition-colors">
            <i class="bi bi-house"></i>Home
        </a>
        <a href="/jv_shetty/products.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition-colors">
            <i class="bi bi-grid"></i>Products
        </a>
        <a href="/jv_shetty/categories.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition-colors">
            <i class="bi bi-tags"></i>Categories
        </a>
        <a href="/jv_shetty/wishlist.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition-colors">
            <i class="bi bi-heart"></i>Wishlist
        </a>
        <?php if ($_navUser): ?>
            <a href="/jv_shetty/profile.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition-colors">
                <i class="bi bi-person"></i>Profile
            </a>
            <a href="/jv_shetty/orders.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition-colors">
                <i class="bi bi-bag"></i>My Orders
            </a>
            <a href="/jv_shetty/logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-red-600 hover:bg-red-50 transition-colors">
                <i class="bi bi-box-arrow-right"></i>Logout
            </a>
        <?php else: ?>
            <a href="/jv_shetty/login.php" class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-semibold text-sm mt-2">
                <i class="bi bi-person-check"></i>Login / Register
            </a>
        <?php endif; ?>
    </div>
</nav>

<script>
    // Dropdown toggle
    const btn = document.getElementById('userDropdownBtn');
    const dd  = document.getElementById('userDropdown');
    if (btn && dd) {
        btn.addEventListener('click', e => { e.stopPropagation(); dd.classList.toggle('hidden'); });
        document.addEventListener('click', () => dd.classList.add('hidden'));
    }
    // Mobile menu toggle
    const mBtn = document.getElementById('mobileMenuBtn');
    const mMenu = document.getElementById('mobileMenu');
    if (mBtn && mMenu) {
        mBtn.addEventListener('click', () => mMenu.classList.toggle('hidden'));
    }
</script>
