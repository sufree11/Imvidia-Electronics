<?php

// resolve admin nav state
if (!isset($admin_data) || !is_array($admin_data)) {
    $admin_data = [
        'id' => null,
        'first_name' => 'Admin',
        'last_name' => 'User',
        'profile_picture' => ''
    ];
}

$full_name = htmlspecialchars(($admin_data['first_name'] ?? 'Admin') . ' ' . ($admin_data['last_name'] ?? 'User'));
$profile_pic = !empty($admin_data['profile_picture']) ? htmlspecialchars($admin_data['profile_picture']) : '';
$admin_avatar = !empty($profile_pic) ? $profile_pic : getAvatarUrl($admin_data['first_name'] ?? 'Admin', $admin_data['last_name'] ?? 'User', '', true);
$order_count_badge = isset($admin_order_count) ? (int) $admin_order_count : 0;

$current_page = basename($_SERVER['PHP_SELF']);
$admin_page_title_map = [
    'admin.php' => 'Dashboard',
    'admin-products.php' => 'Product Management',
    'admin-profile.php' => 'Admin Profile',
    'admin-orders.php' => 'Orders Management'
];
$admin_page_title = $admin_page_title_map[$current_page] ?? 'Admin Panel';
?>

<aside class="w-64 bg-white dark:bg-slate-900 shadow-xl border-r border-gray-100 dark:border-slate-800 hidden md:flex flex-col z-20 transition-all duration-300 relative">

    <div class="h-16 flex items-center px-6 border-b border-gray-100 dark:border-slate-800 w-full">
        <a href="index.php" class="flex items-center cursor-pointer hover:scale-105 transition transform duration-300" title="Go to Store">
            <img class="theme-logo h-8 w-auto mr-2" data-light="assets/logo.svg" data-dark="assets/logo-light.svg" src="assets/logo.svg" alt="ImVidia Logo">
            <span class="font-bold text-xl tracking-tight text-gray-900 dark:text-white">Admin<span class="text-imvidia">Panel</span></span>
        </a>
    </div>

    <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
        <a href="admin.php" class="flex items-center px-4 py-3 rounded-lg shadow-sm transition transform hover:-translate-y-0.5 <?php echo $current_page === 'admin.php' ? 'bg-imvidia text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-imvidia dark:hover:text-imvidia'; ?>">
            <i class="fa-solid fa-chart-pie w-6"></i>
            <span class="font-medium">Dashboard</span>
        </a>
        <a href="admin-products.php" class="flex items-center px-4 py-3 rounded-lg shadow-sm transition transform hover:-translate-y-0.5 <?php echo $current_page === 'admin-products.php' ? 'bg-imvidia text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-imvidia dark:hover:text-imvidia'; ?>">
            <i class="fa-solid fa-box-open w-6"></i>
            <span class="font-medium">Products</span>
        </a>
        <a href="admin-orders.php" class="flex items-center px-4 py-3 rounded-lg shadow-sm transition transform hover:-translate-y-0.5 <?php echo $current_page === 'admin-orders.php' ? 'bg-imvidia text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-imvidia dark:hover:text-imvidia'; ?>">
            <i class="fa-solid fa-cart-shopping w-6"></i>
            <span class="font-medium">Orders</span>
            <span id="order-count-badge" class="ml-auto bg-gray-200 dark:bg-slate-700 text-gray-500 dark:text-gray-400 text-xs font-bold px-2 py-0.5 rounded-full"><?php echo number_format($order_count_badge); ?></span>
        </a>
    </nav>

    <div class="p-4 border-t border-gray-100 dark:border-slate-800">
        <a href="logout.php" class="flex items-center px-4 py-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition group">
            <i class="fa-solid fa-arrow-right-from-bracket w-6 group-hover:-translate-x-1 transition"></i>
            <span class="font-medium">Log Out</span>
        </a>
    </div>
</aside>

<div class="flex-1 flex flex-col h-screen overflow-hidden relative">

    <header class="h-16 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md shadow-sm border-b border-gray-100 dark:border-slate-800 flex items-center justify-between px-6 z-10">

        <button class="md:hidden p-2 text-gray-600 dark:text-gray-300 hover:text-imvidia transition">
            <i class="fa-solid fa-bars text-xl"></i>
        </button>

        <div class="hidden md:flex items-center">
            <h1 class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($admin_page_title); ?></h1>
        </div>

        <div class="flex items-center space-x-6">

            <button id="dark-mode-toggle" type="button" class="p-2 rounded-full text-gray-600 hover:text-imvidia transition dark:text-gray-300" aria-label="Toggle dark mode" onclick="toggleDarkMode()">
                <i id="dark-mode-icon" class="fa-solid fa-moon"></i>
            </button>

            <!-- Admin Profile Dropdown -->
            <div class="flex items-center space-x-3 cursor-pointer group">
                <div class="hidden md:text-right">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo $full_name; ?></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Administrator</p>
                </div>
                <img src="<?php echo $admin_avatar; ?>" alt="Admin Avatar" class="w-10 h-10 rounded-full border-2 border-imvidia object-cover bg-white shadow-sm">
                
                <!-- Dropdown Menu -->
                <div class="absolute right-6 top-16 mt-2 w-48 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-gray-100 dark:border-slate-700 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                    <a href="admin-profile.php" class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-imvidia rounded-t-lg transition">
                        <i class="fa-solid fa-user mr-2"></i>My Profile
                    </a>
                    <a href="logout.php" class="block px-4 py-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-b-lg transition">
                        <i class="fa-solid fa-arrow-right-from-bracket mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT AREA -->
    <main class="flex-1 overflow-y-auto bg-gray-50 dark:bg-slate-950">
