<?php ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        window.IMVIDIA_CART_KEY = <?php echo json_encode('imvidia_cart_' . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'guest')); ?>;
        window.IMVIDIA_LOGGED_IN = <?php echo (($_SESSION['user_role'] ?? '') === 'customer' && isset($_SESSION['user_id'])) ? 'true' : 'false'; ?>;
        window.IMVIDIA_CSRF = <?php echo json_encode(csrfToken()); ?>;

        <?php if (($_SESSION['user_role'] ?? '') === 'customer' && isset($_SESSION['user_id'])): ?>
        // Fold any items added while browsing as a guest into this account's
        // DB-backed cart, once, then clear the guest-only localStorage cart.
        (function() {
            const guestKey = 'imvidia_cart_guest';
            const guestCart = JSON.parse(localStorage.getItem(guestKey)) || [];

            if (guestCart.length > 0) {
                fetch('cart-action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': window.IMVIDIA_CSRF },
                    body: 'action=merge_guest&items=' + encodeURIComponent(JSON.stringify(guestCart))
                }).then(() => {
                    localStorage.removeItem(guestKey);
                }).catch(() => {
                    // Leave the guest cart in place if the merge request failed,
                    // so a retry on the next page load can pick it up.
                });
            }
        })();
        <?php endif; ?>
    </script>
    <script>
        // Live phone formatter: normalizes any typed/prefilled value into
        // "+60 XX XXXXXXX" as the user types. Mirrors formatMalaysianPhone()
        // in includes/helpers.php so the display always matches what's saved.
        // Applies to any <input class="phone-input">.
        function formatPhoneValue(raw) {
            let digits = (raw || '').replace(/\D/g, '');
            if (!digits) return '';

            if (digits.startsWith('60')) {
                digits = digits.slice(2);
            } else if (digits.startsWith('0')) {
                digits = digits.slice(1);
            }

            if (digits.length < 3) return digits;

            const prefix = digits.slice(0, 2);
            const rest = digits.slice(2);
            return '+60 ' + prefix + (rest ? ' ' + rest : '');
        }

        function attachPhoneFormatting() {
            document.querySelectorAll('.phone-input').forEach((el) => {
                if (el.value) {
                    el.value = formatPhoneValue(el.value);
                }
                el.addEventListener('input', () => {
                    el.value = formatPhoneValue(el.value);
                });
            });
        }

        document.addEventListener('DOMContentLoaded', attachPhoneFormatting);
    </script>
    <script>
        // dark mode bootstrap and toggles
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

    <style>
        /* Custom password visibility toggle (see includes/password-toggle.php) */
        /* Hide the browser's built-in reveal/clear icons (Edge/Chrome on Windows)
           so only our custom eye shows. */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear { display: none; }
        .pw-eye-off { display: none; }
        [data-password-toggle].is-visible .pw-eye-open { display: none; }
        [data-password-toggle].is-visible .pw-eye-off { display: block; }
    </style>
    <script>
        (function() {
            // Delegated handler so every password field on the page shares one
            // toggle. The button lives in a `.relative` wrapper next to its input.
            document.addEventListener('click', function(e) {
                const btn = e.target.closest('[data-password-toggle]');
                if (!btn) return;
                const wrap = btn.closest('.relative') || btn.parentElement;
                const input = wrap && wrap.querySelector('input');
                if (!input) return;
                const reveal = input.type === 'password';
                input.type = reveal ? 'text' : 'password';
                btn.classList.toggle('is-visible', reveal);
                btn.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
            });
        })();
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <script src="https://code.iconify.design/iconify-icon/1.0.8/iconify-icon.min.js"></script>

    <link rel="stylesheet" href="includes/styles.css">

    <script>
        // tailwind theme configuration
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

