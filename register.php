<?php
session_start();
require_once 'db/database.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = mysqli_real_escape_string($conn, $_POST['fname']);
    $lname = mysqli_real_escape_string($conn, $_POST['lname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $address_street = mysqli_real_escape_string($conn, $_POST['address_street']);
    $address_city = mysqli_real_escape_string($conn, $_POST['address_city']);
    $address_state = mysqli_real_escape_string($conn, $_POST['address_state']);
    $address_zip = mysqli_real_escape_string($conn, $_POST['address_zip']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $confirm_password = password_hash($_POST['confirm_password'], PASSWORD_DEFAULT);

    if ($password !== $confirm_password) {
        $error_message = "Your passwords do not match!";
    } else {
        $check_query = "SELECT id FROM users WHERE email = '$email' LIMIT 1";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            $error_message = "An account with that email address already exists.";
        } else {
            $insert_query = "INSERT INTO users (role, first_name, last_name, email, password_hash, address_street, address_city, address_state, address_zip) 
                             VALUES ('customer', '$fname', '$lname', '$email', '$password', '$address_street', '$address_city', '$address_state', '$address_zip')";
            
            if (mysqli_query($conn, $insert_query)) {
                $_SESSION['user_id'] = mysqli_insert_id($conn);
                $_SESSION['user_role'] = 'customer';
                $_SESSION['user_name'] = $fname;
                
                header("Location: index.html");
                exit();
            } else {
                $error_message = "Database error: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ImVidia</title>
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
        body { background-color: var(--bg) !important; color: var(--text-primary) !important; }
        .dark .bg-white { background-color: var(--surface) !important; }
        .dark .bg-gray-50 { background-color: #020617 !important; }
        .dark .bg-gray-100 { background-color: #17203a !important; }
        .dark .bg-gray-900 { background-color: #020617 !important; }
        .dark .bg-gray-700 { background-color: #1f2937 !important; }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease-out forwards;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
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
        
        <div class="max-w-2xl w-full bg-white dark:bg-slate-900 p-8 sm:p-10 rounded-2xl shadow-xl border border-gray-100 dark:border-slate-700 relative z-10 transition-all duration-500" id="main-card">
            
            <div id="customer-form-container" class="animate-fade-in-up">
                
                <div class="text-center mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Customer Registration</h2>
                    <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Fill in your details to start shopping.</p>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-6 text-sm text-center font-medium">
                        <i class="fa-solid fa-circle-exclamation mr-1"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <form action="register.php" method="POST" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">First Name</label>
                            <input type="text" name="fname" required class="w-full dark:bg-slate-800 dark:border-slate-600 dark:placeholder:text-slate-400 dark:text-white px-3 py-2 border border-gray-300 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm transition" placeholder="Jane">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last Name</label>
                            <input type="text" name="lname" required class="w-full dark:bg-slate-800 dark:border-slate-600 dark:placeholder:text-slate-400 dark:text-white px-3 py-2 border border-gray-300 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm transition" placeholder="Doe">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address</label>
                        <input type="email" name="email" required class="w-full dark:bg-slate-800 dark:border-slate-600 dark:placeholder:text-slate-400 dark:text-white px-3 py-2 border border-gray-300 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm transition" placeholder="jane@example.com">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Street Address</label>
                        <input type="text" name="address_street" required class="w-full dark:bg-slate-800 dark:border-slate-600 dark:placeholder:text-slate-400 dark:text-white px-3 py-2 border border-gray-300 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm transition" placeholder="123 Example Street, Apt 4B">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">City</label>
                            <input type="text" name="address_city" required class="w-full dark:bg-slate-800 dark:border-slate-600 dark:placeholder:text-slate-400 dark:text-white px-3 py-2 border border-gray-300 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm transition" placeholder="Kuala Lumpur">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">State</label>
                            <select name="address_state" required class="w-full dark:bg-slate-800 dark:border-slate-600 dark:placeholder:text-slate-400 dark:text-white px-3 py-2 border border-gray-300 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm transition appearance-none cursor-pointer">
                                <option value="" disabled selected>Select...</option>
                                <option value="JHR">Johor</option>
                                <option value="KDH">Kedah</option>
                                <option value="KEL">Kelantan</option>
                                <option value="KUL">Kuala Lumpur</option>
                                <option value="MLK">Melaka</option>
                                <option value="NSN">Negeri Sembilan</option>
                                <option value="PHG">Pahang</option>
                                <option value="PEN">Penang</option>
                                <option value="PRK">Perak</option>
                                <option value="PJY">Putrajaya</option>
                                <option value="SBH">Sabah</option>
                                <option value="SRW">Sarawak</option>
                                <option value="SGR">Selangor</option>
                                <option value="TRG">Terengganu</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ZIP / Postal</label>
                            <input type="text" name="address_zip" required class="w-full dark:bg-slate-800 dark:border-slate-600 dark:placeholder:text-slate-400 dark:text-white px-3 py-2 border border-gray-300 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm transition" placeholder="50000">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
                            <input type="password" name="password" required minlength="8" maxlength="20" class="w-full dark:bg-slate-800 dark:border-slate-600 dark:placeholder:text-slate-400 dark:text-white px-3 py-2 border border-gray-300 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm Password</label>
                            <input type="password" name="confirm_password" required minlength="8" maxlength="20" class="w-full dark:bg-slate-800 dark:border-slate-600 dark:placeholder:text-slate-400 dark:text-white px-3 py-2 border border-gray-300 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm transition">
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3 px-4 rounded-lg shadow-md text-sm font-bold text-white bg-imvidia hover:bg-imvidia-dark transition mt-4">
                        Create Account
                    </button>
                    
                    <div class="mt-8 text-center text-sm text-gray-600 dark:text-gray-400 border-t border-gray-100 dark:border-slate-700 pt-6">
                        Already have an account? 
                        <a href="login.php" class="font-bold text-imvidia hover:text-imvidia-dark transition">
                            Log in here
                        </a>
                    </div>
                </form>
            </div>

        </div>
    </main>

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