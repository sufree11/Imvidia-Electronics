<?php
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

requireCustomerLogin();

$user = checkCustomerOrGuest();
$user_id = $_SESSION['user_id'];
$message = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = mysqli_real_escape_string($conn, $_POST['fname'] ?? '');
    $lname = mysqli_real_escape_string($conn, $_POST['lname'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $address_street = mysqli_real_escape_string($conn, $_POST['address_street'] ?? '');
    $address_city = mysqli_real_escape_string($conn, $_POST['address_city'] ?? '');
    $address_state = mysqli_real_escape_string($conn, $_POST['address_state'] ?? '');
    $address_zip = mysqli_real_escape_string($conn, $_POST['address_zip'] ?? '');
    
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';
    
    $avatar_query_part = "";
    $password_query_part = "";
    
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_new_password)) {
        $pass_check_query = "SELECT password_hash FROM users WHERE id = '$user_id' LIMIT 1";
        $pass_check_result = mysqli_query($conn, $pass_check_query);
        $user_data = mysqli_fetch_assoc($pass_check_result);
        
        if ($current_password !== $user_data['password_hash'] && !password_verify($current_password, $user_data['password_hash'])) {
            $message = "Current password is incorrect.";
            $msg_type = "error";
        } else if ($new_password !== $confirm_new_password) {
            $message = "New passwords do not match.";
            $msg_type = "error";
        } else if (strlen($new_password) < 8) {
            $message = "New password must be at least 8 characters.";
            $msg_type = "error";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $password_query_part = ", password_hash='$hashed_password'";
        }
    }
    
    if ($msg_type !== "error" && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $upload_dir_absolute = __DIR__ . '/uploads/avatars/';
        $upload_dir_relative = 'uploads/avatars/';
        
        if (!is_dir($upload_dir_absolute)) {
            mkdir($upload_dir_absolute, 0777, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_ext, $allowed_exts)) {
            $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
            
            $target_path_absolute = $upload_dir_absolute . $new_filename;
            $target_path_relative = $upload_dir_relative . $new_filename;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_path_absolute)) {
                $avatar_query_part = ", profile_picture='$target_path_relative'";
            } else {
                $message = "Failed to upload image.";
                $msg_type = "error";
            }
        } else {
            $message = "Invalid image format. Allowed: JPG, PNG, GIF, WEBP.";
            $msg_type = "error";
        }
    }

    if ($msg_type !== "error") {
        $update_query = "UPDATE users SET 
                            first_name='$fname', 
                            last_name='$lname', 
                            email='$email', 
                            phone='$phone',
                            address_street='$address_street', 
                            address_city='$address_city', 
                            address_state='$address_state', 
                            address_zip='$address_zip'
                            $avatar_query_part
                            $password_query_part
                         WHERE id='$user_id'";
        
        if (mysqli_query($conn, $update_query)) {
            $message = "Profile updated successfully!";
            $msg_type = "success";
            $_SESSION['user_name'] = $fname; 
        } else {
            $message = "Database error: " . mysqli_error($conn);
            $msg_type = "error";
        }
    }
}

$query = "SELECT * FROM users WHERE id = '$user_id' LIMIT 1";
$result = mysqli_query($conn, $query);


$db_user = mysqli_fetch_assoc($result);
if ($db_user) {
    $user = array_merge($user, $db_user);
}

$states = [
    'JHR' => 'Johor', 'KDH' => 'Kedah', 'KEL' => 'Kelantan', 'KUL' => 'Kuala Lumpur',
    'MLK' => 'Melaka', 'NSN' => 'Negeri Sembilan', 'PHG' => 'Pahang', 'PEN' => 'Penang',
    'PRK' => 'Perak', 'PJY' => 'Putrajaya', 'SBH' => 'Sabah', 'SRW' => 'Sarawak',
    'SGR' => 'Selangor', 'TRG' => 'Terengganu'
];

$avatar_url = getAvatarUrl($user['first_name'] ?? '', $user['last_name'] ?? '', $user['profile_picture'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Profile - ImVidia</title>
    <?php include 'includes/head.php'; ?>

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
            --surface: #0f172a;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --border-color: #1e293b;
        }
        body {
            background-color: var(--bg) !important;
            color: var(--text-primary) !important;
            -webkit-font-smoothing: antialiased;
        }
        .dark .bg-white { background-color: var(--surface) !important; }
        .dark .bg-gray-50 { background-color: #020617 !important; }
        .dark .bg-gray-900 { background-color: #020617 !important; }
        .dark .border-gray-100, .dark .border-gray-200 { border-color: var(--border-color) !important; }
    </style>
</head>

<body class="bg-fixed bg-gray-50 text-gray-800 flex flex-col min-h-screen dark:bg-slate-950 dark:text-gray-100">
    
    <?php include 'includes/navbar-customer.php'; ?>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full relative z-10">
        
        <nav class="flex text-xs font-medium text-gray-400 dark:text-slate-500 mb-8 uppercase tracking-widest" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-2">
                <li><a href="index.php" class="hover:text-imvidia transition">Home</a></li>
                <li><span class="mx-1">/</span></li>
                <li><span class="text-gray-600 dark:text-gray-300">My Profile</span></li>
            </ol>
        </nav>

        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">My Profile</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Manage your personal details and security.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="mb-6 px-4 py-3 rounded-lg text-sm font-medium text-center <?php echo $msg_type === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="profile.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-12 gap-10">
            
            <div class="lg:col-span-4 xl:col-span-3 space-y-6">
                <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800 text-center">
                    
                    <div class="relative w-32 h-32 mx-auto mb-4 group cursor-pointer" onclick="document.getElementById('avatar-upload').click()">
                        <div class="w-full h-full rounded-full overflow-hidden border-4 border-white dark:border-slate-800 shadow-md">
                            <img id="avatar-preview" src="<?php echo $avatar_url; ?>" alt="Profile Picture" class="w-full h-full object-cover bg-white">
                        </div>
                        
                        <div class="absolute inset-0 bg-black/40 rounded-full opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center backdrop-blur-sm">
                            <i class="fa-solid fa-camera text-white text-2xl"></i>
                        </div>
                        
                        <input type="file" name="avatar" id="avatar-upload" accept="image/*" class="hidden" onchange="previewAvatar(event)">
                    </div>

                    <h2 class="text-xl font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>

                <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800 overflow-hidden">
                    <nav class="flex flex-col">
                        <a href="profile.php" class="px-6 py-4 flex items-center bg-gray-50 dark:bg-slate-800/50 border-l-4 border-imvidia text-imvidia font-semibold transition">
                            <i class="fa-regular fa-id-badge w-6"></i> Profile Details
                        </a>
                        <a href="#" class="px-6 py-4 flex items-center text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-800/50 hover:text-imvidia dark:hover:text-imvidia transition border-l-4 border-transparent">
                            <i class="fa-solid fa-box-open w-6"></i> Order History
                        </a>
                        <a href="#" class="px-6 py-4 flex items-center text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-800/50 hover:text-imvidia dark:hover:text-imvidia transition border-l-4 border-transparent">
                            <i class="fa-regular fa-heart w-6"></i> Wishlist
                        </a>
                        <div class="border-t border-gray-100 dark:border-slate-800 my-1"></div>
                        <a href="logout.php" class="px-6 py-4 flex items-center text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition border-l-4 border-transparent">
                            <i class="fa-solid fa-arrow-right-from-bracket w-6"></i> Log Out
                        </a>
                    </nav>
                </div>
            </div>

            <div class="lg:col-span-8 xl:col-span-9 space-y-8">
                
                <section class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-5 border-b border-gray-100 dark:border-slate-800 pb-3">Personal Information</h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">First Name</label>
                            <input type="text" name="fname" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last Name</label>
                            <input type="text" name="lname" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                        </div>
                    </div>
                </section>

                <section class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-5 border-b border-gray-100 dark:border-slate-800 pb-3">Default Shipping Address</h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Street Address</label>
                            <input type="text" name="address_street" value="<?php echo htmlspecialchars($user['address_street'] ?? ''); ?>" placeholder="123 Example Street, Apt 4B" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">City</label>
                            <input type="text" name="address_city" value="<?php echo htmlspecialchars($user['address_city'] ?? ''); ?>" placeholder="Kuala Lumpur" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">State / Province</label>
                            <select name="address_state" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm appearance-none cursor-pointer">
                                <option value="" disabled <?php echo empty($user['address_state']) ? 'selected' : ''; ?>>Select State...</option>
                                <?php foreach($states as $code => $name): ?>
                                    <option value="<?php echo $code; ?>" <?php echo ($user['address_state'] === $code) ? 'selected' : ''; ?>>
                                        <?php echo $name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ZIP / Postal Code</label>
                            <input type="text" name="address_zip" value="<?php echo htmlspecialchars($user['address_zip'] ?? ''); ?>" placeholder="50000" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                        </div>
                    </div>
                </section>

                <section class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-5 border-b border-gray-100 dark:border-slate-800 pb-3">Security & Password</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Leave these fields blank if you do not wish to change your password.</p>
                    
                    <div class="grid grid-cols-1 gap-6 max-w-md">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Password</label>
                            <input type="password" name="current_password" placeholder="••••••••" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Password</label>
                            <input type="password" name="new_password" minlength="8" placeholder="••••••••" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm New Password</label>
                            <input type="password" name="confirm_new_password" minlength="8" placeholder="••••••••" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                        </div>
                    </div>
                </section>

                <div class="flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-4 pt-4">
                    <button type="button" onclick="window.location.reload();" class="mt-3 sm:mt-0 px-6 py-3 bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-slate-600 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-700 transition font-medium">
                        Discard Changes
                    </button>
                    <button type="submit" class="px-8 py-3 bg-imvidia hover:bg-imvidia-dark text-white rounded-xl shadow-md font-bold transition transform hover:-translate-y-0.5">
                        Save Profile
                    </button>
                </div>

            </div>
        </form>
    </main>

    <footer class="bg-gray-900 text-gray-400 py-12 border-t border-gray-800 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <div class="flex items-center mb-4">
                        <img src="assets/logo-light.svg" alt="ImVidia Logo" class="h-10 w-auto mr-2">
                        <span class="font-bold text-2xl tracking-tight text-white">ImVidia<span class="text-imvidia">.</span></span>
                    </div>
                    <p class="text-sm mb-4">Innovative & affordable electronics for the modern household.</p>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-4 uppercase tracking-wider text-sm">Directories</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="index.php" class="hover:text-imvidia transition">Home</a></li>
                        <li><a href="index.php#catalog" class="hover:text-imvidia transition">Product Catalog</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-4 uppercase tracking-wider text-sm">Connect With Us</h4>
                    <ul class="space-y-2 text-sm mb-6">
                        <li><i class="fa-solid fa-envelope mr-2 text-imvidia"></i> support@imvidia.com</li>
                    </ul>
                </div>
            </div>
            <div class="mt-12 pt-8 border-t border-gray-800 text-sm text-center">
                <p>&copy; 2015 ImVidia Electronics.</p>
            </div>
        </div>
    </footer>

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
                    document.getElementById('avatar-preview').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }

        function updateCartBadge() {
            let cart = JSON.parse(localStorage.getItem('imvidia_cart')) || [];
            const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
            
            const badge = document.getElementById('cart-badge');
            if (badge) {
                badge.innerText = totalItems;
            }
        }

        function viewCart() {
            window.location.href = 'cart.html';
        }

        document.addEventListener('DOMContentLoaded', updateCartBadge);
    </script>

</body>
</html>