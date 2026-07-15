<?php ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        window.IMVIDIA_CART_KEY = <?php echo json_encode('imvidia_cart_' . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'guest')); ?>;

        <?php if (isset($_SESSION['user_id'])): ?>
        // Merge any items added while browsing as a guest into this account's cart, once.
        (function() {
            const guestKey = 'imvidia_cart_guest';
            const guestCart = JSON.parse(localStorage.getItem(guestKey)) || [];

            if (guestCart.length > 0) {
                const userCart = JSON.parse(localStorage.getItem(window.IMVIDIA_CART_KEY)) || [];
                guestCart.forEach((item) => {
                    const existing = userCart.find((i) => i.name === item.name);
                    if (existing) {
                        existing.quantity = (existing.quantity || 1) + (item.quantity || 1);
                    } else {
                        userCart.push(item);
                    }
                });
                localStorage.setItem(window.IMVIDIA_CART_KEY, JSON.stringify(userCart));
                localStorage.removeItem(guestKey);
            }
        })();
        <?php endif; ?>
    </script>
    <script>
        (function() {
            const html = document.documentElement;

            function isDarkMode() {
                return html.classList.contains('dark');
            }

            function setThemeStorage(isDark) {
                localStorage.theme = isDark ? 'dark' : 'light';
                localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
                localStorage.setItem('imvidiaDarkMode', isDark ? 'true' : 'false');
            }

            function applyInitialTheme() {
                const forceDark = localStorage.getItem('darkMode') === 'enabled' || localStorage.getItem('imvidiaDarkMode') === 'true';
                const legacyDark = localStorage.theme === 'dark';
                const prefersDark = !localStorage.theme && window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (forceDark || legacyDark || prefersDark) {
                    html.classList.add('dark');
                }
            }

            window.updateDarkModeIcon = function updateDarkModeIcon() {
                const icon = document.getElementById('dark-mode-icon');
                if (!icon) {
                    return;
                }

                const isDark = isDarkMode();
                icon.classList.remove(isDark ? 'fa-moon' : 'fa-sun');
                icon.classList.add(isDark ? 'fa-sun' : 'fa-moon');
            };

            window.updateLogos = function updateLogos() {
                const isDark = isDarkMode();
                const lightSrc = 'assets/logo.svg';
                const darkSrc = 'assets/logo-light.svg';

                document.querySelectorAll('.theme-logo').forEach((logo) => {
                    if (logo.dataset && logo.dataset.dark && logo.dataset.light) {
                        logo.src = isDark ? logo.dataset.dark : logo.dataset.light;
                    }
                });

                document.querySelectorAll('.navbar-logo').forEach((el) => {
                    if (el && el.tagName === 'IMG') {
                        el.src = isDark ? darkSrc : lightSrc;
                    }
                });

                const bigLogo = document.getElementById('bigLogo');
                if (bigLogo && bigLogo.tagName === 'IMG') {
                    bigLogo.src = isDark ? darkSrc : lightSrc;
                }

                const icon = document.querySelector('link[rel="icon"]');
                if (icon) {
                    icon.href = isDark ? darkSrc : lightSrc;
                }
            };

            window.toggleDarkMode = function toggleDarkMode() {
                html.classList.toggle('dark');
                const isDark = isDarkMode();
                setThemeStorage(isDark);
                window.updateDarkModeIcon();
                window.updateLogos();
            };

            applyInitialTheme();

            document.addEventListener('DOMContentLoaded', function() {
                window.updateDarkModeIcon();
                window.updateLogos();
            });

            const observer = new MutationObserver(window.updateLogos);
            observer.observe(html, { attributes: true, attributeFilter: ['class'] });
        })();
    </script>
    <link rel="icon" type="image/svg+xml" href="assets/logo.svg">

    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <script src="https://code.iconify.design/iconify-icon/1.0.8/iconify-icon.min.js"></script>

    <link rel="stylesheet" href="includes/styles.css">

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

