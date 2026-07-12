<?php

require_once __DIR__ . '/db-helpers.php';

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

$wishlist_count = 0;
if ($is_logged_in && !$is_admin && !empty($user['user_id'])) {
    $wishlist_count = (int) getValue("SELECT COUNT(*) FROM wishlist WHERE user_id = ?", [$user['user_id']], 'i');
}
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
                <a href="about.php#support" class="text-gray-600 hover:text-imvidia font-medium transition dark:text-gray-300">Support</a>
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
                    <button class="relative p-2 text-gray-600 hover:text-imvidia transition dark:text-gray-300" onclick="viewWishlist()" title="Wishlist">
                        <i class="fa-solid fa-heart text-xl"></i>
                        <span id="wishlist-badge" class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-imvidia rounded-full transition-transform duration-200">
                            <?php echo $wishlist_count; ?>
                        </span>
                    </button>
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
                    <button class="relative p-2 text-gray-600 hover:text-imvidia transition dark:text-gray-300" onclick="viewWishlist()" title="Wishlist">
                        <i class="fa-solid fa-heart text-xl"></i>
                        <span id="wishlist-badge" class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-imvidia rounded-full transition-transform duration-200">
                            0
                        </span>
                    </button>
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

<script>
    function viewCart() {
        window.location.href = 'cart.php';
    }

    function viewWishlist() {
        window.location.href = 'wishlist.php';
    }

    // Android-notification-style toast used for cart/wishlist feedback.
    function showToast(message, iconClass = 'fa-solid fa-circle-check') {
        const existing = document.getElementById('imvidia-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.id = 'imvidia-toast';
        toast.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 z-[9999] flex items-center space-x-3 bg-gray-900 dark:bg-slate-800 text-white text-sm font-semibold px-5 py-3 rounded-full shadow-2xl opacity-0 translate-y-3 transition-all duration-300 ease-out';
        toast.innerHTML = `<i class="${iconClass} text-imvidia-light"></i><span>${message}</span>`;
        document.body.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.remove('opacity-0', 'translate-y-3');
        });

        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-3');
            setTimeout(() => toast.remove(), 300);
        }, 2200);
    }

    function updateWishlistBadge(count) {
        const badge = document.getElementById('wishlist-badge');
        if (badge) {
            badge.innerText = count;
            badge.classList.add('scale-150');
            setTimeout(() => badge.classList.remove('scale-150'), 200);
        }
    }

    // Toggles a product's wishlist membership for the logged-in customer.
    // iconEl is the <i> element clicked, so its class can be flipped immediately.
    async function toggleWishlist(productId, iconEl) {
        try {
            const response = await fetch('wishlist-action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'product_id=' + encodeURIComponent(productId)
            });
            const data = await response.json();

            if (data.require_login) {
                window.location.href = 'login.php';
                return;
            }

            if (!data.success) {
                showToast(data.message || 'Something went wrong.', 'fa-solid fa-triangle-exclamation');
                return;
            }

            if (iconEl) {
                if (data.in_wishlist) {
                    iconEl.classList.remove('fa-regular', 'text-gray-400');
                    iconEl.classList.add('fa-solid', 'text-imvidia-light');
                } else {
                    iconEl.classList.remove('fa-solid', 'text-imvidia-light');
                    iconEl.classList.add('fa-regular', 'text-gray-400');
                }
            }

            updateWishlistBadge(data.wishlist_count);
            showToast(data.in_wishlist ? 'Wishlisted!' : 'Removed from wishlist', 'fa-solid fa-heart');
        } catch (err) {
            showToast('Network error. Please try again.', 'fa-solid fa-triangle-exclamation');
        }
    }
</script>
