<?php
$_adminNav = [
    ['href' => 'index.php',     'icon' => 'speedometer2',    'label' => 'Dashboard',  'page' => 'dashboard'],
    ['href' => 'orders.php',    'icon' => 'bag',             'label' => 'Orders',     'page' => 'orders'],
    ['href' => 'products.php',  'icon' => 'box-seam',        'label' => 'Products',   'page' => 'products'],
    ['href' => 'inventory.php', 'icon' => 'clipboard-data',  'label' => 'Inventory',  'page' => 'inventory'],
    ['href' => 'customers.php', 'icon' => 'people',          'label' => 'Customers',  'page' => 'customers'],
    ['href' => 'reports.php',   'icon' => 'graph-up',        'label' => 'Reports',    'page' => 'reports'],
];
$_adminCurrentPage = $activePage ?? '';
?>
<aside class="admin-sidebar fixed top-0 left-0 h-screen w-64 bg-gray-950 flex flex-col z-40 shadow-2xl">
    <!-- Header -->
    <div class="px-6 py-5 border-b border-gray-800">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center shadow-lg">
                <i class="bi bi-shop text-white text-sm"></i>
            </div>
            <div>
                <p class="text-white font-bold text-sm"><?php echo APP_NAME; ?></p>
                <p class="text-gray-500 text-xs">Admin Panel</p>
            </div>
        </div>
    </div>

    <!-- Nav Items -->
    <nav class="flex-1 px-3 py-4 overflow-y-auto space-y-1">
        <?php foreach ($_adminNav as $item): ?>
            <?php $isActive = $_adminCurrentPage === $item['page']; ?>
            <a href="<?php echo $item['href']; ?>"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200
               <?php echo $isActive
                   ? 'bg-gradient-to-r from-purple-600 to-indigo-600 text-white shadow-lg shadow-purple-900/40'
                   : 'text-gray-400 hover:text-white hover:bg-gray-800'; ?>">
                <i class="bi bi-<?php echo $item['icon']; ?> text-base w-5 text-center"></i>
                <?php echo $item['label']; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Bottom Links -->
    <div class="px-3 py-4 border-t border-gray-800 space-y-1">
        <a href="../index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800 transition-all duration-200">
            <i class="bi bi-shop text-base w-5 text-center"></i>Back to Store
        </a>
        <a href="../logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-red-400 hover:text-red-300 hover:bg-red-950/40 transition-all duration-200">
            <i class="bi bi-box-arrow-right text-base w-5 text-center"></i>Logout
        </a>
    </div>
</aside>
