<?php
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Require admin login
requireAdminLogin();

// Get admin user data for navbar
$admin_data = getAdminUserData();
?>

<!DOCTYPE html>
<html>
<head>
    @
    <title>Admin Dashboard - ImVidia</title>
    <?php include 'includes/head.php'; ?>
</head>

<body class="bg-fixed bg-gray-50 text-gray-800 flex h-screen overflow-hidden dark:bg-slate-950 dark:text-gray-100" style="background-image: radial-gradient(circle, rgba(156, 163, 175, 0.2) 2.5px, transparent 2.5px); background-size: 40px 40px;">

    <?php include 'includes/navbar-admin.php'; ?>
            <!--mobile menu (bug) -->
           

        <!-- dashboard -->
        <main class="flex-1 overflow-y-auto p-6 lg:p-8">
            <div class="max-w-7xl mx-auto">
                
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard Overview</h1>
                    <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Waiting for database connection to load live metrics.</p>
                </div>

                <!-- widgets -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    
                    <div class="bg-white dark:bg-slate-900 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 flex items-center">
                        <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-slate-800 flex items-center justify-center text-gray-400 mr-4">
                            <i class="fa-solid fa-sack-dollar text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Total Revenue</p>
                            <h3 class="text-2xl font-bold text-gray-400 dark:text-gray-500">RM 0.00</h3>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-slate-900 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 flex items-center">
                        <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-slate-800 flex items-center justify-center text-gray-400 mr-4">
                            <i class="fa-solid fa-cart-arrow-down text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Total Orders</p>
                            <h3 class="text-2xl font-bold text-gray-400 dark:text-gray-500">0</h3>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-900 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 flex items-center">
                        <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-slate-800 flex items-center justify-center text-gray-400 mr-4">
                            <i class="fa-solid fa-box text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Total Products</p>
                            <h3 class="text-2xl font-bold text-gray-400 dark:text-gray-500">0</h3>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-900 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 flex items-center">
                        <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-slate-800 flex items-center justify-center text-gray-400 mr-4">
                            <i class="fa-solid fa-users text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Total Customers</p>
                            <h3 class="text-2xl font-bold text-gray-400 dark:text-gray-500">0</h3>
                        </div>
                    </div>
                </div>

                <!-- Recent orders -->
                <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 dark:border-slate-800 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Recent Orders</h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                            <thead class="bg-gray-50 dark:bg-slate-800/50 text-gray-500 dark:text-gray-400 font-medium uppercase text-xs">
                                <tr>
                                    <th class="px-6 py-4">Order ID</th>
                                    <th class="px-6 py-4">Customer</th>
                                    <th class="px-6 py-4">Product</th>
                                    <th class="px-6 py-4">Total</th>
                                    <th class="px-6 py-4">Status</th>
                                    <th class="px-6 py-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- database here -->
                                <tr>
                                    <td colspan="6" class="px-6 py-16 text-center">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="fa-solid fa-clipboard-list text-5xl text-gray-300 dark:text-slate-700 mb-4"></i>
                                            <h3 class="text-xl font-bold text-gray-500 dark:text-gray-400">Nothing here just yet...</h3>
                                            <p class="text-gray-400 dark:text-gray-500 mt-2 text-sm">Orders will appear here once connected to the database.</p>
                                        </div>
                                    </td>
                                </tr>
                                <!-- to here -->
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Dark mode stuff -->
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
            const stored = localStorage.getItem('imvidiaDarkMode');
            if (stored === 'true') {
                document.documentElement.classList.add('dark');
            }
            updateLogoForMode();
            updateDarkToggleIcon();
        });
    </script>

</body>
</html>