<?php
/**
 * Common HTML Head Section
 * Includes: Meta tags, Tailwind CSS, Font Awesome, Google Fonts, Dark Mode CSS
 * 
 * Usage: Include this at the beginning of <head> tag
 * Example: <?php include 'includes/head.php'; ?>
 */
?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="assets/logo.svg">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome Icon Library -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts (Inter) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- Iconify Icon Library -->
    <script src="https://code.iconify.design/iconify-icon/1.0.8/iconify-icon.min.js"></script>

    <!-- ImVidia Global Stylesheet -->
    <link rel="stylesheet" href="includes/styles.css">

    <!-- Tailwind Configuration -->
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

    <!-- Dark Mode Script -->
    <script>
        // Check for saved dark mode preference or default to light mode
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.documentElement.classList.add('dark');
        }

        function toggleDarkMode() {
            const html = document.documentElement;
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.setItem('darkMode', 'disabled');
                updateDarkModeIcon();
            } else {
                html.classList.add('dark');
                localStorage.setItem('darkMode', 'enabled');
                updateDarkModeIcon();
            }
        }

        function updateDarkModeIcon() {
            const icon = document.getElementById('dark-mode-icon');
            if (icon) {
                const isDark = document.documentElement.classList.contains('dark');
                icon.classList.remove(isDark ? 'fa-moon' : 'fa-sun');
                icon.classList.add(isDark ? 'fa-sun' : 'fa-moon');
            }
        }

        // Update icon on page load
        document.addEventListener('DOMContentLoaded', updateDarkModeIcon);
    </script>
