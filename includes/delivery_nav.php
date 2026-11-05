<?php
$_deliveryUser        = Auth::user();
$_deliveryActivePage  = $activePage ?? '';
?>
<aside class="admin-sidebar fixed top-0 left-0 h-screen w-64 bg-gray-950 flex flex-col z-40 shadow-2xl">
    <!-- Header -->
    <div class="px-6 py-5 border-b border-gray-800">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-orange-500 to-pink-600 flex items-center justify-center shadow-lg">
                <i class="bi bi-truck text-white text-sm"></i>
            </div>
            <div>
                <p class="text-white font-bold text-sm">Delivery Portal</p>
                <p class="text-gray-500 text-xs"><?php echo htmlspecialchars(($_deliveryUser['first_name'] ?? '') . ' ' . ($_deliveryUser['last_name'] ?? '')); ?></p>
            </div>
        </div>
    </div>

    <nav class="flex-1 px-3 py-4 space-y-1">
        <a href="index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200
            <?php echo $_deliveryActivePage === 'dashboard' ? 'bg-gradient-to-r from-orange-500 to-pink-600 text-white shadow-lg' : 'text-gray-400 hover:text-white hover:bg-gray-800'; ?>">
            <i class="bi bi-speedometer2 text-base w-5 text-center"></i>Dashboard
        </a>
        <a href="index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200
            <?php echo $_deliveryActivePage === 'history' ? 'bg-gradient-to-r from-orange-500 to-pink-600 text-white shadow-lg' : 'text-gray-400 hover:text-white hover:bg-gray-800'; ?>">
            <i class="bi bi-clock-history text-base w-5 text-center"></i>History
        </a>
    </nav>

    <div class="px-3 py-4 border-t border-gray-800 space-y-1">
        <a href="../index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800 transition-all">
            <i class="bi bi-shop text-base w-5 text-center"></i>Back to Store
        </a>
        <a href="../logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-red-400 hover:text-red-300 hover:bg-red-950/40 transition-all">
            <i class="bi bi-box-arrow-right text-base w-5 text-center"></i>Logout
        </a>
    </div>
</aside>
