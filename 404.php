<?php
// Ensure we're showing 404 status code
if (http_response_code() !== 404) {
    http_response_code(404);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Not Found - ImVidia</title>
    <link rel="icon" type="image/svg+xml" href="assets/logo.svg">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }
        .dark {
            --bg: #020617;
            --surface: #111827;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border-color: #334155;
        }
        body {
            background-color: var(--bg) !important;
            color: var(--text-primary) !important;
            -webkit-font-smoothing: antialiased;
        }
        .dark .bg-white { background-color: var(--surface) !important; }
        .dark .bg-gray-50 { background-color: #020617 !important; }
        .dark .bg-gray-100 { background-color: #0f172a !important; } 
        .dark .bg-gray-900 { background-color: #020617 !important; }
        .dark .text-gray-900,
        .dark .text-gray-800,
        .dark .text-gray-700 { color: var(--text-primary) !important; }
        .dark .text-gray-600,
        .dark .text-gray-500,
        .dark .text-gray-400 { color: var(--text-secondary) !important; }
        .dark .border-gray-100,
        .dark .border-gray-200 { border-color: var(--border-color) !important; }
    </style>
</head>

<body class="bg-fixed bg-gray-50 text-gray-800 flex flex-col min-h-screen dark:bg-slate-950 dark:text-gray-100" style="background-image: radial-gradient(circle, rgba(156, 163, 175, 0.2) 2.5px, transparent 2.5px); background-size: 40px 40px;">
    
    <!-- Navbar -->
    <?php include 'includes/navbar-customer.php'; ?>

    <!-- Main Content -->
    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-32 w-full relative z-10 flex flex-col items-center justify-center text-center">
        
        <div class="mb-8">
            <i class="fa-solid fa-triangle-exclamation text-6xl text-imvidia mb-4"></i>
        </div>

        <h1 class="text-6xl md:text-7xl font-extrabold text-gray-900 dark:text-white mb-4">
            404
        </h1>

        <h2 class="text-3xl md:text-4xl font-bold text-gray-700 dark:text-gray-200 mb-6">
            Page Not Found
        </h2>

        <p class="text-lg text-gray-500 dark:text-gray-400 mb-10 max-w-2xl">
            Sorry, the page you're looking for doesn't exist or has been removed.
        </p>

        <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
            <a href="index.php" class="bg-imvidia hover:bg-imvidia-dark text-white font-bold py-3 px-8 rounded-full transition-all duration-300 inline-block">
                <i class="fa-solid fa-home mr-2"></i> Back to Home
            </a>
        </div>

    </main>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Dark Mode Scripts -->
    <script>
        function updateLogoForMode() {
            const logo = document.getElementById('navbarLogo');
            if (!logo) return;
            logo.src = document.documentElement.classList.contains('dark') ? 'assets/logo-light.svg' : 'assets/logo.svg';
        }

        function updateDarkToggleIcon() {
            const icon = document.getElementById('dark-mode-icon');
            if (!icon) return;
            icon.className = document.documentElement.classList.contains('dark') ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        }

        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('imvidiaDarkMode', document.documentElement.classList.contains('dark') ? 'true' : 'false');
            updateLogoForMode();
            updateDarkToggleIcon();
        }

        function viewCart() {
            window.location.href = 'cart.html';
        }

        function updateCartBadge() {
            let cart = JSON.parse(localStorage.getItem('imvidia_cart')) || [];
            const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
            
            const badge = document.getElementById('cart-badge');
            if (badge) {
                badge.innerText = totalItems;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const stored = localStorage.getItem('imvidiaDarkMode');
            if (stored === 'true') {
                document.documentElement.classList.add('dark');
            }
            updateLogoForMode();
            updateDarkToggleIcon();
            updateCartBadge();
        });
    </script>

</body>
</html>
