<?php
require_once 'includes/auth.php';
require_once 'includes/db-helpers.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $conn; // Access the database connection
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($email) && !empty($password)) {
        $safe_email = mysqli_real_escape_string($conn, $email);
        $query = "SELECT id, role, password_hash FROM users WHERE email = '$safe_email' LIMIT 1";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $user_row = mysqli_fetch_assoc($result);
            
            // Verify the hashed password
            if (password_verify($password, $user_row['password_hash'])) {
                // Password is correct, set the session variables
                $_SESSION['user_id'] = $user_row['id'];
                $_SESSION['user_role'] = $user_row['role']; // 'admin' or 'customer'
                
                // Redirect based on their role
                if ($user_row['role'] === 'admin') {
                    header("Location: admin.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                $error_message = "Invalid email or password.";
            }
        } else {
            $error_message = "Email is not registered.";
        }
    } else {
        $error_message = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Log In - ImVidia</title>
    <?php include 'includes/head.php'; ?>
</head>
<body class="bg-fixed bg-gray-50 text-gray-800 flex flex-col min-h-screen dark:bg-slate-950 dark:text-gray-100" style="background-image: radial-gradient(circle, rgba(156, 163, 175, 0.2) 2.5px, transparent 2.5px); background-size: 40px 40px;">
    
    <nav class="bg-white shadow-sm sticky top-0 z-50 dark:bg-slate-950">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-6">
            <div class="flex justify-between md:justify-start h-16 items-center w-full">
                <a href="index.php" class="flex-shrink-0 flex items-center cursor-pointer hover:scale-105 transition transform duration-300">
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
        <div class="max-w-md w-full bg-white dark:bg-slate-900 p-8 rounded-xl shadow-lg border border-gray-100 dark:border-slate-800">
            <h2 class="text-2xl font-bold text-center text-gray-900 dark:text-white mb-6">Welcome Back</h2>
            
            <!-- Error Message Display -->
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-50 text-red-500 p-3 rounded-lg mb-6 text-sm font-medium border border-red-100 text-center">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Corrected Form -->
            <form action="login.php" method="POST" class="space-y-5">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address</label>
                    <!-- IMPORTANT: The name="email" is what PHP reads. The value="..." makes it sticky! -->
                    <input type="email" id="email" name="email" autocomplete="email" required
                           value="<?php echo htmlspecialchars($email ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-imvidia focus:border-imvidia outline-none transition bg-white dark:bg-slate-800 text-gray-900 dark:text-white">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
                    <!-- IMPORTANT: The name="password" is required here -->
                    <input type="password" id="password" name="password" autocomplete="current-password" required
                           class="w-full px-4 py-2 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-imvidia focus:border-imvidia outline-none transition bg-white dark:bg-slate-800 text-gray-900 dark:text-white">
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
                <a href="register.php" class="font-bold text-imvidia hover:text-imvidia-dark transition">
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