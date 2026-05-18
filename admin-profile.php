<?php
require_once 'db/session.php';
require_once 'db/database.php';
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 1. Security Bouncer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$msg_type = '';

// 2. Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = mysqli_real_escape_string($conn, $_POST['fname'] ?? '');
    $lname = mysqli_real_escape_string($conn, $_POST['lname'] ?? '');
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    
    // Password Logic
    $new_password = $_POST['new_password'] ?? '';
    $password_query_part = "";
    if (!empty($new_password)) {
        if (strlen($new_password) < 8) {
            $message = "New password must be at least 8 characters.";
            $msg_type = "error";
        } else {
            $password_query_part = ", password_hash='$new_password'";
        }
    }

    // DigitalOcean Spaces Image Upload Logic
    $avatar_query_part = "";
    if ($msg_type !== "error" && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_ext, $allowed_exts)) {
            // Save admin avatars with an 'admin_' prefix
            $new_filename = 'avatars/admin_' . $user_id . '_' . time() . '.' . $file_ext;
            
            $s3 = new S3Client([
                'version' => 'latest',
                'region'  => getenv('SPACES_REGION'),
                'endpoint' => getenv('SPACES_ENDPOINT'),
                'credentials' => [
                    'key'    => getenv('SPACES_KEY'),
                    'secret' => getenv('SPACES_SECRET'),
                ],
            ]);

            try {
                $result = $s3->putObject([
                    'Bucket'      => getenv('SPACES_BUCKET'),
                    'Key'         => $new_filename,
                    'SourceFile'  => $_FILES['avatar']['tmp_name'],
                    'ACL'         => 'public-read',
                    'ContentType' => mime_content_type($_FILES['avatar']['tmp_name'])
                ]);

                $public_url = $result['ObjectURL'];
                $avatar_query_part = ", profile_picture='$public_url'";
                
            } catch (AwsException $e) {
                $message = "Failed to upload to cloud storage.";
                $msg_type = "error";
            }
        } else {
            $message = "Invalid image format. Allowed: JPG, PNG, GIF, WEBP.";
            $msg_type = "error";
        }
    }

    // Database Update
    if ($msg_type !== "error") {
        $update_query = "UPDATE users SET 
                            first_name='$fname', 
                            last_name='$lname', 
                            phone='$phone'
                            $avatar_query_part
                            $password_query_part
                         WHERE id='$user_id'";
        
        if (mysqli_query($conn, $update_query)) {
            $message = "Admin profile updated successfully!";
            $msg_type = "success";
            $_SESSION['user_name'] = $fname . ' ' . $lname;
        } else {
            $message = "Database error: " . mysqli_error($conn);
            $msg_type = "error";
        }
    }
}

// Fetch Latest Data
$query = "SELECT * FROM users WHERE id = '$user_id' LIMIT 1";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

// Generate Avatar URL
$avatar_url = !empty($user['profile_picture']) 
    ? htmlspecialchars($user['profile_picture']) 
    // Darker default placeholder for admins!
    : "[https://ui-avatars.com/api/?name=](https://ui-avatars.com/api/?name=)" . urlencode($user['first_name'] . ' ' . $user['last_name']) . "&background=1F2937&color=fff&size=128";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - ImVidia Panel</title>
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
        .dark .bg-gray-100 { background-color: #17203a !important; }
        .dark .bg-gray-900 { background-color: #020617 !important; }
        .dark .bg-gray-800 { background-color: #1e293b !important; }
        .dark .bg-gray-700 { background-color: #1f2937 !important; }
        .dark .text-gray-900, .dark .text-gray-800, .dark .text-gray-700 { color: var(--text-primary) !important; }
        .dark .text-gray-600, .dark .text-gray-500, .dark .text-gray-400 { color: var(--text-secondary) !important; }
        .dark .border-gray-100, .dark .border-gray-200 { border-color: var(--border-color) !important; }
        .dark .shadow-sm, .dark .shadow-md, .dark .shadow-xl {
            box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.4), 0 4px 6px -4px rgba(15, 23, 42, 0.1) !important;
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease-out forwards;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body class="bg-fixed bg-gray-50 text-gray-800 flex h-screen overflow-hidden dark:bg-slate-950 dark:text-gray-100" style="background-image: radial-gradient(circle, rgba(156, 163, 175, 0.2) 2.5px, transparent 2.5px); background-size: 40px 40px;">

    <aside class="w-64 bg-white dark:bg-slate-900 shadow-xl border-r border-gray-100 dark:border-slate-800 hidden md:flex flex-col z-20 transition-all duration-300 relative">
        <div class="h-16 flex items-center px-6 border-b border-gray-100 dark:border-slate-800 w-full">
            <a href="index.php" class="flex items-center cursor-pointer hover:scale-105 transition transform duration-300">
                <img id="navbarLogo" src="assets/logo.svg" alt="ImVidia Logo" class="h-8 w-auto mr-2">
                <span class="font-bold text-xl tracking-tight text-gray-900 dark:text-white">Admin<span class="text-imvidia">Panel</span></span>
            </a>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="admin.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-imvidia dark:hover:text-imvidia rounded-lg transition">
                <i class="fa-solid fa-chart-pie w-6"></i>
                <span class="font-medium">Overview</span>
            </a>
            <a href="admin-products.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-imvidia dark:hover:text-imvidia rounded-lg transition">
                <i class="fa-solid fa-box-open w-6"></i>
                <span class="font-medium">Products</span>
            </a>
            <a href="#" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-imvidia dark:hover:text-imvidia rounded-lg transition">
                <i class="fa-solid fa-cart-shopping w-6"></i>
                <span class="font-medium">Orders</span>
                <span id="order-count-badge" class="ml-auto bg-gray-200 dark:bg-slate-700 text-gray-500 dark:text-gray-400 text-xs font-bold px-2 py-0.5 rounded-full">0</span>
            </a>
            <a href="#" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-imvidia dark:hover:text-imvidia rounded-lg transition">
                <i class="fa-solid fa-users w-6"></i>
                <span class="font-medium">Customers</span>
            </a>
        </nav>

        <div class="p-4 border-t border-gray-100 dark:border-slate-800">
            <a href="logout.php" class="flex items-center px-4 py-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition group">
                <i class="fa-solid fa-arrow-right-from-bracket w-6 group-hover:-translate-x-1 transition"></i>
                <span class="font-medium">Log Out</span>
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-screen overflow-hidden relative">
        
        <header class="h-16 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md shadow-sm border-b border-gray-100 dark:border-slate-800 flex items-center justify-between px-6 z-10">
            <button class="md:hidden text-gray-600 dark:text-gray-300 hover:text-imvidia transition">
                <i class="fa-solid fa-bars text-xl"></i>
            </button>

            <div class="hidden sm:flex items-center font-bold text-gray-800 dark:text-gray-200">
                Profile
            </div>

            <div class="flex items-center space-x-4 ml-auto">
                <button id="dark-mode-toggle" type="button" class="p-2 rounded-full text-gray-600 hover:text-imvidia transition dark:text-gray-300" aria-label="Toggle dark mode" onclick="toggleDarkMode()">
                    <i id="dark-mode-icon" class="fa-solid fa-moon text-lg"></i>
                </button>
                
                <div class="flex items-center space-x-3 border-l border-gray-200 dark:border-slate-700 pl-4 transition">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-bold text-gray-900 dark:text-white leading-tight"><?php echo $full_name; ?></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Administrator</p>
                    </div>
                    <a href="admin-profile.php" class="h-9 w-9 rounded-full bg-imvidia border-2 border-imvidia flex items-center justify-center overflow-hidden hover:scale-105 transition transform shadow-sm">
                        <?php if ($avatar_url): ?>
                            <img src="<?php echo $avatar_url; ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fa-solid fa-user text-white text-sm"></i>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 lg:p-8 animate-fade-in-up">
            <div class="max-w-6xl mx-auto relative">
                
                <nav class="flex text-xs font-medium text-gray-400 dark:text-slate-500 mb-8 uppercase tracking-widest" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-2">
                        <li><a href="admin.php" class="hover:text-imvidia transition">Dashboard</a></li>
                        <li><span class="mx-1">/</span></li>
                        <li><span class="text-gray-600 dark:text-gray-300">Admin Profile</span></li>
                    </ol>
                </nav>

                <?php if ($message): ?>
                    <div class="mb-6 px-4 py-3 rounded-lg text-sm font-medium text-center <?php echo $msg_type === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Admin Profile</h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">View and update your administrative details and system permissions.</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
                    
                    <div class="lg:col-span-4 xl:col-span-3 space-y-6">
                        <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800 text-center">
                            
                            <div class="relative w-32 h-32 mx-auto mb-4 group cursor-pointer" onclick="document.getElementById('avatar-upload').click()">
                                <div class="w-full h-full rounded-full overflow-hidden border-4 border-white dark:border-slate-800 shadow-md bg-imvidia flex items-center justify-center relative">
                                    <?php if ($avatar_url): ?>
                                        <img id="avatar-preview" src="<?php echo $avatar_url; ?>" alt="Profile Picture" class="w-full h-full object-cover z-10">
                                    <?php else: ?>
                                        <i id="avatar-icon" class="fa-solid fa-user-tie text-5xl text-white absolute"></i>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="absolute inset-0 bg-black/40 rounded-full opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center backdrop-blur-sm z-20">
                                    <i class="fa-solid fa-camera text-white text-2xl"></i>
                                </div>
                                
                                <form id="admin-profile-form" action="admin-profile.php" method="POST" enctype="multipart/form-data">
                                    <input type="file" name="avatar" id="avatar-upload" accept="image/*" class="hidden" onchange="previewAvatar(event)">
                            </div>

                            <h2 class="text-xl font-bold text-gray-900 dark:text-white"><?php echo $full_name; ?></h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>

                        <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800 overflow-hidden">
                            <nav class="flex flex-col">
                                <div class="px-6 py-4 flex items-center bg-gray-50 dark:bg-slate-800/50 border-l-4 border-imvidia text-imvidia font-semibold cursor-default">
                                    <i class="fa-regular fa-id-badge w-6"></i> Profile Details
                                </div>
                            </nav>
                        </div>
                    </div>

                    <div class="lg:col-span-8 xl:col-span-9 space-y-8">
                        
                            <section class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-5 border-b border-gray-100 dark:border-slate-800 pb-3">
                                    Personal Information
                                </h3>
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1 flex items-center justify-between">
                                            First Name
                                        </label>
                                        <input type="text" name="fname" value="<?php echo htmlspecialchars($user['first_name']); ?>" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1 flex items-center justify-between">
                                            Last Name
                                        </label>
                                        <input type="text" name="lname" value="<?php echo htmlspecialchars($user['last_name']); ?>" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1 flex items-center justify-between">
                                            Email Address <i class="fa-solid fa-lock text-xs text-gray-400"></i>
                                        </label>
                                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly class="w-full px-4 py-3 border border-gray-200 dark:border-slate-700/50 bg-gray-50 dark:bg-slate-800/50 rounded-xl text-gray-500 dark:text-gray-400 font-medium cursor-not-allowed focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1 flex items-center justify-between">
                                            Employee ID <i class="fa-solid fa-lock text-xs text-gray-400"></i>
                                        </label>
                                        <input type="text" value="<?php echo htmlspecialchars($user['admin_id']); ?>" readonly class="w-full px-4 py-3 border border-gray-200 dark:border-slate-700/50 bg-gray-50 dark:bg-slate-800/50 rounded-xl text-gray-500 dark:text-gray-400 font-medium font-mono text-sm cursor-not-allowed focus:outline-none">
                                    </div>
                                    

                                    <div class="sm:col-span-2 border-t border-gray-100 dark:border-slate-800 pt-6 mt-2">
                                        <h4 class="text-sm font-bold text-gray-900 dark:text-white mb-4 uppercase tracking-wider">Editable Information</h4>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact Number</label>
                                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Update Password</label>
                                        <div class="relative">
                                            <input type="password" name="new_password" id="admin-password" placeholder="Leave blank to keep current" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm tracking-wider pr-12">
                                            <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 px-4 flex items-center text-gray-400 hover:text-imvidia transition" title="Show/Hide Password">
                                                <i id="password-eye" class="fa-solid fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-5 border-b border-gray-100 dark:border-slate-800 pb-3 flex items-center justify-between">
                                    System Permissions
                                    <i class="fa-solid fa-lock text-gray-300 dark:text-gray-600 text-sm" title="Read Only"></i>
                                </h3>
                                
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Your current role dictates your access across the ImVidia backend infrastructure. Permissions cannot be modified here.</p>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="flex items-start space-x-3 p-3 rounded-lg bg-green-50 dark:bg-green-900/10 border border-green-100 dark:border-green-800/30 transition">
                                        <i class="fa-solid fa-circle-check text-green-500 mt-0.5 text-lg"></i>
                                        <div>
                                            <p class="text-sm font-bold text-gray-900 dark:text-white">Product Management</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Add, edit, or remove catalog items</p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-start space-x-3 p-3 rounded-lg bg-green-50 dark:bg-green-900/10 border border-green-100 dark:border-green-800/30 transition">
                                        <i class="fa-solid fa-circle-check text-green-500 mt-0.5 text-lg"></i>
                                        <div>
                                            <p class="text-sm font-bold text-gray-900 dark:text-white">Order Processing</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">View and update customer orders</p>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <div class="flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-4 pt-2">
                                <button type="button" onclick="window.location.reload();" class="mt-3 sm:mt-0 px-6 py-3 bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-slate-600 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-700 transition font-medium">
                                    Discard Changes
                                </button>
                                <button type="submit" class="px-8 py-3 bg-imvidia hover:bg-imvidia-dark text-white rounded-xl shadow-md font-bold transition transform hover:-translate-y-0.5">
                                    Save Profile
                                </button>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        function previewAvatar(event) {
            const file = event.target.files[0];
            if (file) {
                if (!file.type.startsWith('image/')) {
                    alert("Please select a valid image file.");
                    return;
                }
                if (file.size > 2 * 1024 * 1024) {
                    alert("Profile picture must be less than 2MB.");
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewImg = document.getElementById('avatar-preview');
                    const defaultIcon = document.getElementById('avatar-icon');
                    
                    previewImg.src = e.target.result;
                    previewImg.classList.remove('hidden');
                    if (defaultIcon) defaultIcon.classList.add('hidden');
                }
                reader.readAsDataURL(file);
            }
        }

        function togglePassword() {
            const passInput = document.getElementById('admin-password');
            const eyeIcon = document.getElementById('password-eye');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                eyeIcon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passInput.type = 'password';
                eyeIcon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            const isDark = document.documentElement.classList.contains('dark');
            localStorage.setItem('imvidiaDarkMode', isDark ? 'true' : 'false');
            document.getElementById('dark-mode-icon').className = isDark ? 'fa-solid fa-sun text-lg' : 'fa-solid fa-moon text-lg';
            document.getElementById('navbarLogo').src = isDark ? 'assets/logo-light.svg' : 'assets/logo.svg';
        }

        document.addEventListener('DOMContentLoaded', () => {
            const stored = localStorage.getItem('imvidiaDarkMode');
            if (stored === 'true') {
                document.documentElement.classList.add('dark');
                document.getElementById('dark-mode-icon').className = 'fa-solid fa-sun text-lg';
                document.getElementById('navbarLogo').src = 'assets/logo-light.svg';
            }
        });
    </script>
</body>
</html>