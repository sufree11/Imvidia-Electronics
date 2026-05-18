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

    <!-- Dark Mode & Theme CSS Variables -->
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
        }
        
        .dark .bg-white { background-color: var(--surface) !important; }
        .dark .bg-gray-50 { background-color: #020617 !important; }
        .dark .bg-gray-100 { background-color: #17203a !important; }
        .dark .bg-gray-900 { background-color: #020617 !important; }
        .dark .bg-gray-700 { background-color: #1f2937 !important; }
        
        .dark .text-gray-900,
        .dark .text-gray-800,
        .dark .text-gray-700 { color: var(--text-primary) !important; }
        
        .dark .text-gray-600,
        .dark .text-gray-500,
        .dark .text-gray-400 { color: var(--text-secondary) !important; }
        
        .dark .border-gray-100,
        .dark .border-gray-200 { border-color: var(--border-color) !important; }
        
        .dark .shadow-sm,
        .dark .shadow-md,
        .dark .shadow-xl { box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.4), 0 4px 6px -4px rgba(15, 23, 42, 0.1) !important; }

        /* Dropdown animation for product containers */
        .dropdown-wrapper {
            display: grid;
            grid-template-rows: 0fr;
            transition: grid-template-rows 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .dropdown-wrapper.open {
            grid-template-rows: 1fr;
        }
        
        .dropdown-inner {
            overflow: hidden;
            opacity: 0;
            transform: translateY(-10px); 
            transition: opacity 0.4s ease-out, transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .dropdown-wrapper.open .dropdown-inner {
            opacity: 1;
            transform: translateY(0); 
        }
    </style>

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
