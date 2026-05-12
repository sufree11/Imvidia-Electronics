<?php
session_start();

// 🛑 THE ANTI-CACHE BOUNCER 🛑
// This forces the homepage to ACTUALLY check if you are logged in, breaking the cache loop!
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$is_logged_in = false;
$first_name = '';
$avatar_url = '';

// Check if the user is logged in
if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'customer') {
    $is_logged_in = true;
    
    // Connect to the database to grab their latest profile picture and name
    require_once 'db/database.php';
    $user_id = mysqli_real_escape_string($conn, $_SESSION['user_id']);
    
    $query = "SELECT first_name, last_name, profile_picture FROM users WHERE id = '$user_id' LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $first_name = htmlspecialchars($user['first_name']);
        
        // Check if they uploaded a picture, otherwise use the initials avatar
        if (!empty($user['profile_picture'])) {
            $avatar_url = htmlspecialchars($user['profile_picture']);
        } else {
            $avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($user['first_name'] . ' ' . $user['last_name']) . "&background=49C2FA&color=fff&size=128";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ImVidia Electronics</title>
    <link rel="icon" type="image/svg+xml" href="assets/logo.svg">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://code.iconify.design/iconify-icon/1.0.8/iconify-icon.min.js"></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        imvidia: {
                            light: '#8DFFFF',
                            DEFAULT: '#49C2FA',
                            dark: '#1F2468',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        :root {
            --bg: #f8fafc;
            --surface: #ffffff;
            --text-primary: #111827;
            --text-secondary: #475569;
            --border-color: #e2e8f0;
        }
        .dark {
            --bg: #020617;
            --surface: #111827;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --border-color: #334155;
        }
        body { background-color: var(--bg) !important; color: var(--text-primary) !important; }
        .dark .bg-white { background-color: var(--surface) !important; }
        .dark .bg-gray-50 { background-color: #020617 !important; }
        
        .hero-gradient {
            background: linear-gradient(135deg, #1F2468 0%, #49C2FA 100%);
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen dark:bg-slate-950 dark:text-gray-100">
    
    <nav class="bg-white shadow-sm sticky top-0 z-50 dark:bg-slate-950 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                
                <!-- Logo -->
                <a href="index.php" class="flex-shrink-0 flex items-center cursor-pointer hover:scale-105 transition transform duration-300">
                    <img id="navbarLogo" src="assets/logo.svg" alt="ImVidia Logo" class="h-10 w-auto mr-2">
                    <span class="font-bold text-2xl tracking-tight text-gray-900 dark:text-white">ImVidia<span class="text-imvidia">.</span></span>
                </a>
                
                <!-- Center Links -->
                <div class="hidden md:flex space-x-8 items-center absolute left-1/2 transform -translate-x-1/2">
                    <a href="index.php" class="text-imvidia font-bold transition">Home</a>
                    <a href="#catalog" class="text-gray-600 hover:text-imvidia font-medium transition dark:text-gray-300">Catalog</a>
                    <a href="#" class="text-gray-600 hover:text-imvidia font-medium transition dark:text-gray-300">Support</a>
                </div>

                <!-- Right Side Actions -->
                <div class="flex items-center space-x-3 sm:space-x-4">
                    <button id="dark-mode-toggle" type="button" class="p-2 rounded-full text-gray-600 hover:text-imvidia transition dark:text-gray-300" onclick="toggleDarkMode()">
                        <i id="dark-mode-icon" class="fa-solid fa-moon text-lg"></i>
                    </button>
                    
                    <!-- PHP DYNAMIC LOGIN/PROFILE AREA -->
                    <?php if ($is_logged_in): ?>
                        <div class="hidden md:block mr-2">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Welcome, <?php echo $first_name; ?>.</span>
                        </div>
                        <a href="profile.php" class="relative group cursor-pointer transition transform hover:scale-105" title="User Profile">
                            <img src="<?php echo $avatar_url; ?>" alt="Profile" class="w-9 h-9 rounded-full border-2 border-imvidia object-cover bg-white shadow-sm">
                        </a>
                    <?php else: ?>
                        <div class="hidden md:flex items-center space-x-4">
                            <a href="login.php" class="text-sm font-semibold text-gray-600 hover:text-imvidia transition dark:text-gray-300">Log In</a>
                            <a href="register.php" class="text-sm font-bold bg-imvidia hover:bg-imvidia-dark text-white px-4 py-2 rounded-lg shadow-md transition transform hover:-translate-y-0.5">Register</a>
                        </div>
                        <a href="login.php" class="md:hidden relative p-2 text-gray-600 hover:text-imvidia transition dark:text-gray-300">
                            <i class="fa-solid fa-user text-xl"></i>
                        </a>
                    <?php endif; ?>

                    <!-- Cart -->
                    <button class="relative p-2 text-gray-600 hover:text-imvidia transition dark:text-gray-300" onclick="viewCart()">
                        <i class="fa-solid fa-cart-shopping text-xl"></i>
                        <span id="cart-badge" class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-imvidia rounded-full transition-transform duration-200">0</span>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow">
        <!-- Hero Section -->
        <div class="relative bg-white dark:bg-slate-900 overflow-hidden">
            <div class="max-w-7xl mx-auto">
                <div class="relative z-10 pb-8 bg-white dark:bg-slate-900 sm:pb-16 md:pb-20 lg:max-w-2xl lg:w-full lg:pb-28 xl:pb-32 pt-10 sm:pt-16 lg:pt-20 px-4 sm:px-6 lg:px-8">
                    <div class="sm:text-center lg:text-left">
                        <h1 class="text-4xl tracking-tight font-extrabold text-gray-900 dark:text-white sm:text-5xl md:text-6xl">
                            <span class="block xl:inline">Next-gen tech for</span>
                            <span class="block text-imvidia">the modern home</span>
                        </h1>
                        <p class="mt-3 text-base text-gray-500 dark:text-gray-400 sm:mt-5 sm:text-lg sm:max-w-xl sm:mx-auto md:mt-5 md:text-xl lg:mx-0">
                            ImVidia brings you cutting-edge, affordable electronics designed to seamlessly integrate into your daily life. Upgrade your reality today.
                        </p>
                        <div class="mt-5 sm:mt-8 sm:flex sm:justify-center lg:justify-start">
                            <div class="rounded-md shadow">
                                <a href="#catalog" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-imvidia hover:bg-imvidia-dark md:py-4 md:text-lg transition transform hover:-translate-y-0.5">
                                    Shop Now
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="lg:absolute lg:inset-y-0 lg:right-0 lg:w-1/2 bg-gray-100 dark:bg-slate-800 flex items-center justify-center">
                <!-- Fallback hero graphic since actual image paths might differ -->
                <div class="w-full h-64 sm:h-72 md:h-96 lg:h-full hero-gradient flex items-center justify-center text-white opacity-90">
                    <i class="fa-solid fa-microchip text-9xl drop-shadow-lg"></i>
                </div>
            </div>
        </div>

        <!-- Featured Catalog Section -->
        <div id="catalog" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
            <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight mb-8">Featured Products</h2>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-y-10 gap-x-6 xl:gap-x-8">
                
                <!-- Product 1 -->
                <div class="group relative bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-4 transition hover:shadow-lg">
                    <div class="w-full min-h-60 bg-gray-100 dark:bg-slate-700 aspect-w-1 aspect-h-1 rounded-xl overflow-hidden group-hover:opacity-75 flex items-center justify-center">
                        <i class="fa-solid fa-headphones text-6xl text-gray-300 dark:text-slate-500"></i>
                    </div>
                    <div class="mt-4 flex justify-between">
                        <div>
                            <h3 class="text-sm text-gray-700 dark:text-gray-200 font-bold">
                                <a href="product.html">
                                    <span aria-hidden="true" class="absolute inset-0"></span>
                                    ImVidia Sonic Pro
                                </a>
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Noise Cancelling</p>
                        </div>
                        <p class="text-sm font-bold text-gray-900 dark:text-white">RM 299.00</p>
                    </div>
                </div>

                <!-- Product 2 -->
                <div class="group relative bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-4 transition hover:shadow-lg">
                    <div class="w-full min-h-60 bg-gray-100 dark:bg-slate-700 aspect-w-1 aspect-h-1 rounded-xl overflow-hidden group-hover:opacity-75 flex items-center justify-center">
                        <i class="fa-solid fa-stopwatch text-6xl text-gray-300 dark:text-slate-500"></i>
                    </div>
                    <div class="mt-4 flex justify-between">
                        <div>
                            <h3 class="text-sm text-gray-700 dark:text-gray-200 font-bold">
                                <a href="product.html">
                                    <span aria-hidden="true" class="absolute inset-0"></span>
                                    ImVidia Watch Series 2
                                </a>
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Midnight Black</p>
                        </div>
                        <p class="text-sm font-bold text-gray-900 dark:text-white">RM 499.00</p>
                    </div>
                </div>

                <!-- Product 3 -->
                <div class="group relative bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-4 transition hover:shadow-lg">
                    <div class="w-full min-h-60 bg-gray-100 dark:bg-slate-700 aspect-w-1 aspect-h-1 rounded-xl overflow-hidden group-hover:opacity-75 flex items-center justify-center">
                        <i class="fa-solid fa-gamepad text-6xl text-gray-300 dark:text-slate-500"></i>
                    </div>
                    <div class="mt-4 flex justify-between">
                        <div>
                            <h3 class="text-sm text-gray-700 dark:text-gray-200 font-bold">
                                <a href="product.html">
                                    <span aria-hidden="true" class="absolute inset-0"></span>
                                    ImVidia PlayPad
                                </a>
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Wireless Controller</p>
                        </div>
                        <p class="text-sm font-bold text-gray-900 dark:text-white">RM 150.00</p>
                    </div>
                </div>

                <!-- Product 4 -->
                <div class="group relative bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-4 transition hover:shadow-lg">
                    <div class="w-full min-h-60 bg-gray-100 dark:bg-slate-700 aspect-w-1 aspect-h-1 rounded-xl overflow-hidden group-hover:opacity-75 flex items-center justify-center">
                        <i class="fa-solid fa-plug text-6xl text-gray-300 dark:text-slate-500"></i>
                    </div>
                    <div class="mt-4 flex justify-between">
                        <div>
                            <h3 class="text-sm text-gray-700 dark:text-gray-200 font-bold">
                                <a href="product.html">
                                    <span aria-hidden="true" class="absolute inset-0"></span>
                                    ImVidia PowerBank 20k
                                </a>
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Fast Charging</p>
                        </div>
                        <p class="text-sm font-bold text-gray-900 dark:text-white">RM 89.00</p>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 py-12 border-t border-gray-800 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <div class="flex items-center mb-4">
                        <img src="assets/logo-light.svg" alt="ImVidia Logo" class="h-10 w-auto mr-2">
                        <span class="font-bold text-2xl tracking-tight text-white">ImVidia<span class="text-imvidia">.</span></span>
                    </div>
                    <p class="text-sm mb-4">Innovative & affordable electronics for the modern household.</p>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-4 uppercase tracking-wider text-sm">Directories</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="index.php" class="hover:text-imvidia transition">Home</a></li>
                        <li><a href="index.php#catalog" class="hover:text-imvidia transition">Product Catalog</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-4 uppercase tracking-wider text-sm">Connect With Us</h4>
                    <ul class="space-y-2 text-sm mb-6">
                        <li><i class="fa-solid fa-envelope mr-2 text-imvidia"></i> support@imvidia.com</li>
                    </ul>
                </div>
            </div>
            <div class="mt-12 pt-8 border-t border-gray-800 text-sm text-center">
                <p>&copy; 2015 ImVidia Electronics.</p>
            </div>
        </div>
    </footer>

    <!-- Global Scripts -->
    <script>
        function updateLogoForMode() {
            const logo = document.getElementById('navbarLogo');
            if (!logo) return;
            logo.src = document.documentElement.classList.contains('dark') ? 'assets/logo-light.svg' : 'assets/logo.svg';
        }

        function updateDarkToggleIcon() {
            const icon = document.getElementById('dark-mode-icon');
            if (!icon) return;
            icon.className = document.documentElement.classList.contains('dark') ? 'fa-solid fa-sun text-lg' : 'fa-solid fa-moon text-lg';
        }

        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('imvidiaDarkMode', document.documentElement.classList.contains('dark') ? 'true' : 'false');
            updateLogoForMode();
            updateDarkToggleIcon();
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('imvidiaDarkMode') === 'true') {
                document.documentElement.classList.add('dark');
            }
            updateLogoForMode();
            updateDarkToggleIcon();
            updateCartBadge();
        });

        // Cart Logic
        function updateCartBadge() {
            let cart = JSON.parse(localStorage.getItem('imvidia_cart')) || [];
            const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
            
            const badge = document.getElementById('cart-badge');
            if (badge) {
                badge.innerText = totalItems;
            }
        }

        function viewCart() {
            window.location.href = 'cart.html';
        }
    </script>
</body>
</html>