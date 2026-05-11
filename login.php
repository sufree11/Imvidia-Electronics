<?php
session_start();
require_once 'db/database.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = mysqli_real_escape_string($conn, $_POST['identity']);
    $password = $_POST['password'];
    $role = $_POST['role']; 

    if ($role === 'admin') {
        $query = "SELECT * FROM users WHERE admin_id = '$identity' AND role = 'admin' LIMIT 1";
    } else {
        $query = "SELECT * FROM users WHERE email = '$identity' AND role = 'customer' LIMIT 1";
    }
    
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        if ($role === 'user') {
            if ($password === $user['password_hash'] && $identity === $user['email']) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['first_name'];

                header("Location: index.html");
                exit();
            } else if ($identity !== $user['email']) {
                $error_message = "Invalid password.";
            } else {
                $error_message = "No account found with that email address.";
            }
        } else if ($role === 'admin') {
        if ($password === $user['password_hash'] &&  $identity === $user['admin_id'] ) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['first_name'];

            header("Location: admin.html");
            exit();
        } else if ($identity === $user['admin_id']){
            $error_message = "Invalid password.";
        }
    } else {
        $error_message = "no admin account found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<!-- 
  INTERNAL DEV NOTE: 
  Please stop asking if we are related to NVIDIA. 
-->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In - ImVidia</title>
    <link rel="icon" type="image/svg+xml" href="assets/logo.svg">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

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
    </style>
</head>
<body class="bg-fixed bg-gray-50 text-gray-800 flex flex-col min-h-screen dark:bg-slate-950 dark:text-gray-100" style="background-image: radial-gradient(circle, rgba(156, 163, 175, 0.2) 2.5px, transparent 2.5px); background-size: 40px 40px;">
    
    <nav class="bg-white shadow-sm sticky top-0 z-50 dark:bg-slate-950">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-6">
            <div class="flex justify-between md:justify-start h-16 items-center w-full">
                <a href="index.html" class="flex-shrink-0 flex items-center cursor-pointer hover:scale-105 transition transform duration-300">
                    <img id="navbarLogo" src="assets/logo.svg" alt="ImVidia Logo" class="h-10 w-auto mr-2">
                    <span class="font-bold text-2xl tracking-tight text-gray-900 dark:text-white" >ImVidia<span class="text-imvidia">.</span></span>
                </a>
                <button id="dark-mode-toggle" type="button" class="p-2 rounded-full text-gray-600 hover:text-imvidia transition dark:text-gray-300" aria-label="Toggle dark mode" onclick="toggleDarkMode()">
                    <i id="dark-mode-icon" class="fa-solid fa-moon"></i>
                </button>
            </div>
        </div>
    </nav> 

    <main class="flex-grow flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">

        <div class="max-w-md w-full bg-white dark:bg-slate-900 px-8 pb-8 pt-14 rounded-2xl shadow-xl border border-gray-100 dark:border-slate-700 relative mt-8 z-10">
            
            <div class="absolute -top-12 left-1/2 transform -translate-x-1/2 w-24 h-24 bg-white dark:bg-slate-800 rounded-full shadow-lg overflow-hidden flex items-center justify-center">
                <img id="bigLogo" src="assets/logo.svg" alt="Logo" class="w-full h-full object-cover">
            </div>

            <div class="text-center mb-6">
                <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white mb-2">Welcome Back</h2>
                <p class="text-gray-500 dark:text-gray-400 text-sm">Please sign in to your account.</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-6 text-sm text-center font-medium">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="flex bg-gray-100 dark:bg-slate-800 p-1 rounded-lg mb-8 relative">
                <button id="tab-customer" onclick="switchTab('customer')" class="flex-1 py-2 text-sm font-semibold rounded-md transition-all duration-300 bg-white dark:bg-slate-700 text-gray-900 dark:text-white shadow-sm">
                    Customer
                </button>
                <button id="tab-admin" onclick="switchTab('admin')" class="flex-1 py-2 text-sm font-semibold rounded-md transition-all duration-300 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                    Administrator
                </button>
            </div>

            <form id="loginForm" method="POST" action="login.php" class="space-y-6">
                
                <input type="hidden" name="role" id="role-input" value="customer">

                <div>
                    <label id="identity-label" for="identity-input" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Email Address
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i id="identity-icon" class="fa-solid fa-envelope text-gray-400"></i>
                        </div>
                        <input id="identity-input" name="identity" type="text" required 
                            class="appearance-none block w-full pl-10 px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-imvidia focus:border-imvidia sm:text-sm dark:bg-slate-800 dark:border-slate-600 dark:placeholder:text-slate-400 dark:text-white transition duration-200" 
                            placeholder="e.g. six@seven.com">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Password
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-lock text-gray-400"></i>
                        </div>
                        <input id="password" name="password" type="password" required minlength="8" maxlength="20"
                            class="appearance-none block w-full pl-10 px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-imvidia focus:border-imvidia sm:text-sm dark:bg-slate-800 dark:border-slate-600 dark:placeholder:text-slate-400 dark:text-white transition duration-200" >
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-imvidia focus:ring-imvidia border-gray-300 rounded cursor-pointer dark:bg-slate-800 dark:border-slate-600">
                        <label for="remember-me" class="ml-2 block text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                            Remember me
                        </label>
                    </div>

                    <div class="text-sm">
                        <a href="#" class="font-medium text-imvidia hover:text-imvidia-dark transition">
                            Forgot your password?
                        </a>
                    </div>
                </div>

                <div>
                    <button type="submit" id="submit-btn" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-md text-sm font-bold text-white bg-imvidia hover:bg-imvidia-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-imvidia transition transform hover:-translate-y-0.5">
                        Sign In as Customer
                    </button>
                </div>
            </form> 

            <div class="mt-8 text-center text-sm text-gray-600 dark:text-gray-400 border-t border-gray-100 dark:border-slate-700 pt-6">
                Don't have an account? 
                <a href="register.html" class="font-bold text-imvidia hover:text-imvidia-dark transition">
                    Register here
                </a>
            </div>

        </div>
    </main>

    <script>
        const tabCustomer = document.getElementById('tab-customer');
        const tabAdmin = document.getElementById('tab-admin');
        const identityLabel = document.getElementById('identity-label');
        const identityInput = document.getElementById('identity-input');
        const identityIcon = document.getElementById('identity-icon');
        const submitBtn = document.getElementById('submit-btn');
        const roleInput = document.getElementById('role-input');

        function switchTab(role) {
            roleInput.value = role;

            if (role === 'admin') {
                tabAdmin.classList.add('bg-white', 'dark:bg-slate-700', 'text-gray-900', 'dark:text-white', 'shadow-sm');
                tabAdmin.classList.remove('text-gray-500', 'dark:text-gray-400', 'hover:text-gray-700', 'dark:hover:text-gray-200');
                
                tabCustomer.classList.remove('bg-white', 'dark:bg-slate-700', 'text-gray-900', 'dark:text-white', 'shadow-sm');
                tabCustomer.classList.add('text-gray-500', 'dark:text-gray-400', 'hover:text-gray-700', 'dark:hover:text-gray-200');

                identityLabel.innerText = "Admin ID";
                identityInput.type = "text";
                identityInput.placeholder = "ADMIN-000";
                identityIcon.className = "fa-solid fa-id-badge text-gray-400";
                submitBtn.innerText = "Sign In as Admin";
                submitBtn.classList.replace('bg-imvidia', 'bg-imvidia-dark');
                submitBtn.classList.replace('hover:bg-imvidia-dark', 'hover:bg-gray-800');

            } else {
                tabCustomer.classList.add('bg-white', 'dark:bg-slate-700', 'text-gray-900', 'dark:text-white', 'shadow-sm');
                tabCustomer.classList.remove('text-gray-500', 'dark:text-gray-400', 'hover:text-gray-700', 'dark:hover:text-gray-200');

                tabAdmin.classList.remove('bg-white', 'dark:bg-slate-700', 'text-gray-900', 'dark:text-white', 'shadow-sm');
                tabAdmin.classList.add('text-gray-500', 'dark:text-gray-400', 'hover:text-gray-700', 'dark:hover:text-gray-200');

                identityLabel.innerText = "Email Address";
                identityInput.type = "email";
                identityInput.placeholder = "e.g. six@seven.com";
                identityIcon.className = "fa-solid fa-envelope text-gray-400";
                submitBtn.innerText = "Sign In as Customer";
                submitBtn.classList.replace('bg-gray-900', 'bg-imvidia');
                submitBtn.classList.replace('hover:bg-gray-800', 'hover:bg-imvidia-dark');
            }
        }

        function updateLogoForMode() {
            const logo = document.getElementById('navbarLogo');
            if (logo) logo.src = document.documentElement.classList.contains('dark') ? 'assets/logo-light.svg' : 'assets/logo.svg';
            const bigLogo = document.getElementById('bigLogo');
            if (bigLogo) bigLogo.src = document.documentElement.classList.contains('dark') ? 'assets/logo-light.svg' : 'assets/logo.svg';
        }

        function updateDarkToggleIcon() {
            const icon = document.getElementById('dark-mode-icon');
            if (icon) icon.className = document.documentElement.classList.contains('dark') ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
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