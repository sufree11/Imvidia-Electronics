<?php

$admin_check = checkAdminOrGuest();

if ($admin_check['is_admin']) {
    $user = $admin_check;
} elseif (!isset($user) || !is_array($user)) {
    $user = checkCustomerOrGuest();
}

$is_logged_in = $user['is_logged_in'] ?? false;
$is_admin = $user['is_admin'] ?? false;
$first_name = $user['first_name'] ?? '';
$avatar_url = !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 
    (isset($user['first_name'], $user['last_name']) ? 
        getAvatarUrl($user['first_name'], $user['last_name']) : 
        'https://ui-avatars.com/api/?name=Guest&background=49C2FA&color=fff&size=128');
?>

<nav class="bg-white shadow-md sticky top-0 z-50 dark:bg-slate-950 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center relative">
            
            <div class="flex items-center space-x-6">
                <a href="index.php" class="flex-shrink-0 flex items-center cursor-pointer hover:opacity-80 transition">
                    <img class="theme-logo h-10 w-auto mr-2" data-light="assets/logo.svg" data-dark="assets/logo-light.svg" src="assets/logo.svg" alt="ImVidia Logo">
                    <span class="font-bold text-2xl tracking-tight text-gray-900 dark:text-white">ImVidia<span class="text-imvidia">.</span></span>
                </a>
                
                <button id="dark-mode-toggle" type="button" class="p-2 rounded-full text-gray-600 hover:text-imvidia transition dark:text-gray-300" aria-label="Toggle dark mode" onclick="toggleDarkMode()">
                    <i id="dark-mode-icon" class="fa-solid fa-moon"></i>
                </button>
            </div>

            <div class="hidden md:flex space-x-8 items-center absolute left-1/2 transform -translate-x-1/2">
                <a href="index.php" class="text-gray-600 hover:text-imvidia font-medium transition dark:text-gray-300">Home</a>
                <a href="#catalog" class="text-gray-600 hover:text-imvidia font-medium transition dark:text-gray-300">Catalog</a>
                <a href="#" class="text-gray-600 hover:text-imvidia font-medium transition dark:text-gray-300">Support</a>
            </div>

            <div class="flex items-center space-x-4">
                <?php if ($is_admin): ?>
                    <a href="admin.php" class="flex items-center cursor-pointer hover:opacity-80 transition group">
                        <img class="navbar-logo h-8 w-auto mr-2" src="assets/logo.svg" alt="Admin Panel">
                        <span class="hidden md:inline font-bold text-sm tracking-tight text-gray-900 dark:text-white group-hover:text-imvidia transition">Admin<span class="text-imvidia">Panel</span></span>
                    </a>
                <?php elseif ($is_logged_in): ?>
                    <div class="hidden md:block mr-2 text-right">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Welcome, <?php echo htmlspecialchars($first_name); ?>.</span>
                    </div>
                    <a href="profile.php" class="relative group cursor-pointer transition transform hover:scale-105" title="User Profile">
                        <img src="<?php echo $avatar_url; ?>" alt="Profile Picture" class="w-9 h-9 rounded-full border-2 border-imvidia object-cover bg-white shadow-sm">
                    </a>
                    <button class="relative p-2 text-gray-600 hover:text-imvidia transition dark:text-gray-300" onclick="viewCart()">
                        <i class="fa-solid fa-cart-shopping text-xl"></i>
                        <span id="cart-badge" class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-imvidia rounded-full transition-transform duration-200">
                            0
                        </span>
                    </button>
                <?php else: ?>
                    <div class="hidden md:flex items-center space-x-4">
                        <a href="login.php" class="text-sm font-semibold text-gray-600 hover:text-imvidia transition dark:text-gray-300">Log In</a>
                        <a href="register.php" class="text-sm font-bold bg-imvidia hover:bg-imvidia-dark text-white px-4 py-2 rounded-lg shadow-md transition transform hover:-translate-y-0.5">Register</a>
                    </div>
                    <a href="login.php" class="md:hidden relative p-2 text-gray-600 hover:text-imvidia transition dark:text-gray-300">
                        <i class="fa-solid fa-user text-xl"></i>
                    </a>
                    <button class="relative p-2 text-gray-600 hover:text-imvidia transition dark:text-gray-300" onclick="viewCart()">
                        <i class="fa-solid fa-cart-shopping text-xl"></i>
                        <span id="cart-badge" class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-imvidia rounded-full transition-transform duration-200">
                            0
                        </span>
                    </button>
                <?php endif; ?>
        </div>
    </div>
</nav>
