<?php
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get user data or guest status
$user = checkCustomerOrGuest();

// Initialize receipt data
$receipt_data = null;
$checkout_success = false;

// Handle POST request (order submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and collect customer information from form
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $city = isset($_POST['city']) ? trim($_POST['city']) : '';
    $state = isset($_POST['state']) ? trim($_POST['state']) : '';
    $postcode = isset($_POST['postcode']) ? trim($_POST['postcode']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
    $cart_json = isset($_POST['cart_data']) ? $_POST['cart_data'] : '[]';
    
    // Validate required fields
    if ($email && $first_name && $last_name && $address && $city && $state && $postcode && $phone && $payment_method) {
        // Parse cart data from form
        $cart = json_decode($cart_json, true);
        
        if (is_array($cart) && count($cart) > 0) {
            // Calculate totals
            $subtotal = 0;
            foreach ($cart as $item) {
                $qty = $item['quantity'] ?? 1;
                $subtotal += ($item['price'] * $qty);
            }
            
            $tax = $subtotal * 0.06; // 6% tax
            $shipping = 0; // Free shipping
            $total = $subtotal; // Tax is included in subtotal
            
            // Generate receipt number (timestamp-based)
            $receipt_number = 'INV' . date('YmdHis');
            $purchase_date = date('Y-m-d');
            $purchase_time = date('H:i:s');
            
            // Prepare receipt data
            $receipt_data = [
                'receipt_number' => $receipt_number,
                'purchase_date' => $purchase_date,
                'purchase_time' => $purchase_time,
                'customer_name' => $first_name . ' ' . $last_name,
                'email' => $email,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'postcode' => $postcode,
                'phone' => $phone,
                'payment_method' => $payment_method,
                'items' => $cart,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping' => $shipping,
                'total' => $total
            ];
            
            $checkout_success = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - ImVidia</title>
    <link rel="icon" type="image/svg+xml" href="assets/logo.svg">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.iconify.design/iconify-icon/1.0.8/iconify-icon.min.js"></script>

    <!-- Tailwind Config for ImVidia Theme -->
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
            --border-color: #e2e8f0;
        }
        .dark {
            --bg: #020617;
            --surface: #0f172a;
            --text-primary: #f8fafc;
            --border-color: #1e293b;
        }
        body {
            background-color: var(--bg) !important;
            color: var(--text-primary) !important;
        }
        
        /* Custom Radio Button styling */
        input[type="radio"]:checked {
            background-color: #49C2FA !important;
            border-color: #49C2FA !important;
        }
    </style>
</head>

<body class="bg-fixedbg-gray-50 text-gray-800 antialiased dark:bg-slate-950 dark:text-gray-100 selection:bg-imvidia selection:text-white" style="background-image: radial-gradient(circle, rgba(156, 163, 175, 0.2) 2.5px, transparent 2.5px); background-size: 40px 40px;">

    <!-- Minimal Navbar for Checkout (Distraction Free) -->
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

    <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        
        <!-- RECEIPT DISPLAY (shown after successful checkout) -->
        <?php if ($checkout_success && $receipt_data): ?>
        <div id="receipt-container" class="max-w-3xl mx-auto">
            <!-- Success Message -->
            <div class="bg-green-50 dark:bg-green-900/20 border-2 border-green-500 dark:border-green-700 rounded-3xl p-8 mb-8 text-center shadow-lg">
                <div class="flex items-center justify-center mb-4">
                    <div class="bg-green-500 text-white rounded-full h-16 w-16 flex items-center justify-center">
                        <i class="fa-solid fa-check text-3xl"></i>
                    </div>
                </div>
                <h2 class="text-3xl font-bold text-green-700 dark:text-green-400 mb-2">Payment Successful!</h2>
                <p class="text-lg text-green-600 dark:text-green-300">Thank you for shopping with ImVidia Electronics.</p>
                <p class="text-base text-green-600 dark:text-green-300 mt-2">Your order has been placed successfully.</p>
            </div>

            <!-- Receipt -->
            <div class="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-3xl shadow-lg p-8 sm:p-12 mb-8">
                <!-- Receipt Header -->
                <div class="mb-8 pb-8 border-b border-gray-200 dark:border-slate-700">
                    <div class="flex items-start justify-between mb-6">
                        <div>
                            <h1 class="text-4xl font-bold text-gray-900 dark:text-white">Receipt</h1>
                            <p class="text-gray-500 dark:text-gray-400 mt-2">Order Confirmation</p>
                        </div>
                        <div class="text-right">
                            <img id="receiptLogo" src="assets/logo.svg" alt="ImVidia Logo" class="h-12 w-auto mb-2">
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">ImVidia Electronics</p>
                        </div>
                    </div>
                    
                    <!-- Receipt Number & Dates -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Receipt Number</p>
                            <p class="text-lg font-bold text-imvidia mt-1"><?php echo htmlspecialchars($receipt_data['receipt_number']); ?></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Date</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white mt-1"><?php echo date('d M Y', strtotime($receipt_data['purchase_date'])); ?></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Time</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white mt-1"><?php echo date('g:i A', strtotime($receipt_data['purchase_time'])); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Customer Information -->
                <div class="mb-8 pb-8 border-b border-gray-200 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-4">Customer Information</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Name</p>
                            <p class="text-base font-semibold text-gray-900 dark:text-white mt-1"><?php echo htmlspecialchars($receipt_data['customer_name']); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Email</p>
                            <p class="text-base font-semibold text-gray-900 dark:text-white mt-1"><?php echo htmlspecialchars($receipt_data['email']); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Phone</p>
                            <p class="text-base font-semibold text-gray-900 dark:text-white mt-1"><?php echo htmlspecialchars($receipt_data['phone']); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Payment Method</p>
                            <p class="text-base font-semibold text-gray-900 dark:text-white mt-1 capitalize"><?php echo htmlspecialchars($receipt_data['payment_method']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Shipping Address -->
                <div class="mb-8 pb-8 border-b border-gray-200 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-4">Shipping Address</h3>
                    <p class="text-base text-gray-900 dark:text-white"><?php echo htmlspecialchars($receipt_data['address']); ?></p>
                    <p class="text-base text-gray-900 dark:text-white"><?php echo htmlspecialchars($receipt_data['postcode'] . ' ' . $receipt_data['city']); ?></p>
                    <p class="text-base text-gray-900 dark:text-white"><?php echo htmlspecialchars($receipt_data['state']); ?>, Malaysia</p>
                </div>

                <!-- Order Items -->
                <div class="mb-8 pb-8 border-b border-gray-200 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-4">Order Items</h3>
                    <div class="space-y-4">
                        <?php foreach ($receipt_data['items'] as $item): ?>
                        <?php $qty = $item['quantity'] ?? 1; $subtotal = $item['price'] * $qty; ?>
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-slate-800 last:border-b-0 last:pb-0">
                            <div class="flex-1">
                                <h4 class="text-base font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($item['name']); ?></h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    Qty: <span class="font-medium"><?php echo $qty; ?></span> 
                                    × <span class="font-medium">RM <?php echo number_format($item['price'], 2); ?></span>
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-base font-bold text-gray-900 dark:text-white">RM <?php echo number_format($subtotal, 2); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Totals -->
                <div class="mb-8">
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-base text-gray-600 dark:text-gray-400">Subtotal</span>
                            <span class="text-base font-semibold text-gray-900 dark:text-white">RM <?php echo number_format($receipt_data['subtotal'], 2); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-base text-gray-600 dark:text-gray-400">Taxes (Included)</span>
                            <span class="text-base font-semibold text-gray-900 dark:text-white">RM <?php echo number_format($receipt_data['tax'], 2); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-base text-gray-600 dark:text-gray-400">Shipping</span>
                            <span class="text-base font-semibold text-green-600 dark:text-green-400">Free</span>
                        </div>
                    </div>
                    
                    <!-- Grand Total -->
                    <div class="border-t border-gray-200 dark:border-slate-700 mt-4 pt-4 flex justify-between items-center">
                        <span class="text-lg font-bold text-gray-900 dark:text-white">Grand Total</span>
                        <span class="text-2xl font-bold text-imvidia">RM <?php echo number_format($receipt_data['total'], 2); ?></span>
                    </div>
                </div>

                <!-- Thank You Message -->
                <div class="bg-imvidia/10 dark:bg-imvidia/20 border border-imvidia/50 rounded-2xl p-6 mb-8 text-center">
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">
                        Thank you for shopping with ImVidia Electronics.
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                        We appreciate your business and hope you enjoy your purchase.
                    </p>
                </div>

                <!-- Footer -->
                <div class="text-center pt-6 border-t border-gray-200 dark:border-slate-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                        <i class="fa-solid fa-shield-halved mr-1"></i> Secure transaction powered by ImVidia
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        Order #<?php echo htmlspecialchars($receipt_data['receipt_number']); ?> | <?php echo date('Y-m-d H:i:s'); ?>
                    </p>
                </div>
            </div>

            <!-- OK Button -->
            <div class="flex justify-center">
                <button onclick="window.location.href='index.php'" class="px-8 py-4 bg-imvidia hover:bg-imvidia-dark text-white rounded-xl shadow-lg shadow-imvidia/30 font-bold text-lg transition transform hover:-translate-y-0.5">
                    <i class="fa-solid fa-check mr-2"></i> OK
                </button>
            </div>
        </div>

        <?php else: ?>
        <!-- CHECKOUT FORM (shown on initial page load) -->
        <div id="checkout-form-container">
            <!-- Page Title -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Checkout</h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1">Complete your order securely.</p>
            </div>

            <form id="checkout-form" method="POST" class="grid grid-cols-1 lg:grid-cols-12 gap-10">
                
                <!-- LEFT COLUMN: Forms (Takes up 7 out of 12 columns on large screens) -->
                <div class="lg:col-span-7 space-y-8">
                    
                    <!-- Login / Guest Prompt Banner -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-2xl p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between shadow-sm">
                        <div class="flex items-start mb-4 sm:mb-0">
                            <i class="fa-solid fa-circle-user text-blue-500 text-xl mt-0.5 mr-3"></i>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Already have an account?</h3>
                                <p class="text-sm text-gray-600 dark:text-blue-200 mt-1">Login to save yourself the hassle! Or just continue as a guest.</p>
                            </div>
                        </div>
                        <a href="login.php" class="px-5 py-2 bg-white dark:bg-slate-800 text-sm font-bold text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-700 rounded-xl shadow-sm hover:bg-blue-100 dark:hover:bg-slate-700 transition flex-shrink-0 text-center">
                            Log In
                        </a>
                    </div>

                    <!-- Section 1: Contact Information -->
                    <section class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-5 flex items-center">
                            <span class="bg-imvidia/10 text-imvidia h-8 w-8 rounded-full flex items-center justify-center text-sm mr-3">1</span>
                            Contact Information
                        </h2>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address <span class="text-red-500">*</span></label>
                                <input type="email" name="email" required placeholder="jane.doe@example.com" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                            </div>
                            <div class="flex items-center mt-2">
                                <input type="checkbox" id="newsletter" name="newsletter" class="h-4 w-4 text-imvidia focus:ring-imvidia border-gray-300 rounded dark:border-slate-600 dark:bg-slate-800">
                                <label for="newsletter" class="ml-2 block text-sm text-gray-500 dark:text-gray-400 cursor-pointer">
                                    Email me with news and offers
                                </label>
                            </div>
                        </div>
                    </section>

                    <!-- Section 2: Shipping Address -->
                    <section class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-5 flex items-center">
                            <span class="bg-imvidia/10 text-imvidia h-8 w-8 rounded-full flex items-center justify-center text-sm mr-3">2</span>
                            Shipping Address
                        </h2>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">First Name <span class="text-red-500">*</span></label>
                                <input type="text" name="first_name" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last Name <span class="text-red-500">*</span></label>
                                <input type="text" name="last_name" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address <span class="text-red-500">*</span></label>
                                <input type="text" name="address" required placeholder="Street address, apartment, suite, etc." class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">City <span class="text-red-500">*</span></label>
                                <input type="text" name="city" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">State <span class="text-red-500">*</span></label>
                                    <select name="state" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm appearance-none cursor-pointer">
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
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ZIP / Postcode <span class="text-red-500">*</span></label>
                                    <input type="text" name="postcode" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number <span class="text-red-500">*</span></label>
                                <input type="tel" name="phone" required placeholder="+60 12-345 6789" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                            </div>
                        </div>
                    </section>

                    <!-- Section 3: Payment Method -->
                    <section class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-5 flex items-center">
                            <span class="bg-imvidia/10 text-imvidia h-8 w-8 rounded-full flex items-center justify-center text-sm mr-3">3</span>
                            Payment
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">All transactions are secure and encrypted.</p>
                        
                        <div class="space-y-4">
                            <!-- Credit Card Option -->
                            <div id="card-wrapper" class="border border-imvidia bg-imvidia/5 dark:bg-imvidia/10 rounded-xl p-4 transition">
                                <div class="flex items-center justify-between cursor-pointer" onclick="document.getElementById('pay_card').click()">
                                    <div class="flex items-center">
                                        <input type="radio" id="pay_card" name="payment_method" value="card" checked required class="h-4 w-4 text-imvidia focus:ring-imvidia border-gray-300 dark:border-slate-600">
                                        <label for="pay_card" class="ml-3 font-semibold text-gray-900 dark:text-white cursor-pointer pointer-events-none">Credit / Debit Card</label>
                                    </div>
                                    <div class="flex space-x-2 text-xl text-gray-400 dark:text-gray-500">
                                        <i class="fa-brands fa-cc-visa text-blue-600 dark:text-blue-400"></i>
                                        <i class="fa-brands fa-cc-mastercard text-red-500 dark:text-red-400"></i>
                                    </div>
                                </div>
                                
                                <!-- Card Form (Expands when selected) -->
                                <div id="card-details" class="mt-4 pt-4 border-t border-gray-200 dark:border-slate-700/50 space-y-4">
                                    <div class="relative">
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Card Number</label>
                                        <div class="absolute inset-y-0 left-0 pt-5 pl-3 flex items-center pointer-events-none text-gray-400">
                                            <i class="fa-regular fa-credit-card"></i>
                                        </div>
                                        <input type="text" id="cc_number" placeholder="0000 0000 0000 0000" class="w-full pl-10 px-4 py-2.5 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm font-mono tracking-wide">
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Expiration Date</label>
                                            <input type="text" id="cc_expiry" placeholder="MM / YY" class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm font-mono text-center sm:text-left">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Security Code (CVC)</label>
                                            <div class="relative">
                                                <input type="text" id="cc_cvc" placeholder="123" class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm font-mono">
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Name on Card</label>
                                        <input type="text" id="cc_name" placeholder="JANE DOE" class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm uppercase">
                                        
                                    </div>
                                </div>
                            </div>

                            <!-- FPX Online Banking -->
                            <div id="fpx-wrapper" class="border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 rounded-xl p-4 hover:border-gray-300 transition">
                                <div class="flex items-center justify-between cursor-pointer" onclick="document.getElementById('pay_fpx').click()">
                                    <div class="flex items-center">
                                        <input type="radio" id="pay_fpx" name="payment_method" value="fpx" class="h-4 w-4 text-imvidia focus:ring-imvidia border-gray-300 dark:border-slate-600">
                                        <label for="pay_fpx" class="ml-3 font-medium text-gray-700 dark:text-gray-300 cursor-pointer pointer-events-none">FPX Online Banking</label>
                                    </div>
                                    <div class="text-xl text-gray-400 dark:text-gray-500">
                                        <i class="fa-solid fa-building-columns"></i>
                                    </div>
                                </div>
                                
                                <!-- FPX Details -->
                                <div id="fpx-details" class="hidden mt-4 pt-4 border-t border-gray-200 dark:border-slate-700/50">
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">Select Your Bank</label>
                                    <div class="relative">
                                        <select class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm appearance-none cursor-pointer">
                                            <option value="" disabled selected>Select Bank...</option>
                                            <option value="maybank">Maybank2U</option>
                                            <option value="cimb">CIMB Clicks</option>
                                            <option value="public">Public Bank</option>
                                            <option value="rhb">RHB Now</option>
                                            <option value="hongleong">Hong Leong Connect</option>
                                            <option value="bankislam">Bank Islam</option>
                                            <option value="ambank">AmBank</option>
                                        </select>
                                        <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-500">
                                            <i class="fa-solid fa-chevron-down text-sm"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- E-Wallet Option -->
                            <div id="ewallet-wrapper" class="border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 rounded-xl p-4 hover:border-gray-300 transition">
                                <div class="flex items-center justify-between cursor-pointer" onclick="document.getElementById('pay_ewallet').click()">
                                    <div class="flex items-center">
                                        <input type="radio" id="pay_ewallet" name="payment_method" value="ewallet" class="h-4 w-4 text-imvidia focus:ring-imvidia border-gray-300 dark:border-slate-600">
                                        <label for="pay_ewallet" class="ml-3 font-medium text-gray-700 dark:text-gray-300 cursor-pointer pointer-events-none">E-Wallet</label>
                                    </div>
                                    <div class="text-xl text-gray-400 dark:text-gray-500">
                                        <i class="fa-solid fa-wallet"></i>
                                    </div>
                                </div>

                                <!-- E-Wallet Details -->
                                <div id="ewallet-details" class="hidden mt-4 pt-4 border-t border-gray-200 dark:border-slate-700/50">
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">Select Provider</label>
                                    <div class="relative">
                                        <select class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm appearance-none cursor-pointer">
                                            <option value="" disabled selected>Select E-Wallet...</option>
                                            <option value="tng">Touch 'n Go eWallet</option>
                                            <option value="shopee">ShopeePay</option>
                                            <option value="boost">Boost</option>
                                            <option value="grab">GrabPay</option>
                                            <option value="alipay">Alipay+</option>
                                        </select>
                                        <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-500">
                                            <i class="fa-solid fa-chevron-down text-sm"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- RIGHT COLUMN: Order Summary (Takes up 5 out of 12 columns) -->
                <div class="lg:col-span-5 relative">
                    <!-- Sticky wrapper so it stays on screen when scrolling down the form -->
                    <div class="sticky top-24 bg-gray-100 dark:bg-slate-800/50 p-6 sm:p-8 rounded-2xl border border-gray-200 dark:border-slate-700/50">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Order Summary</h2>
                        
                        <!-- Dynamic Cart Items Container -->
                        <div id="checkout-items-container" class="space-y-4 mb-6">
                            <!-- Items will be injected here via JavaScript -->
                        </div>

                        <div class="border-t border-gray-200 dark:border-slate-700/80 pt-4 mb-6">
                            <!-- Discount Code -->
                            <div class="flex space-x-2">
                                <input type="text" placeholder="Discount code" class="flex-1 px-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-900 dark:text-white transition shadow-sm text-sm">
                                <button type="button" class="px-4 py-2.5 bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-lg font-medium text-sm hover:bg-gray-300 dark:hover:bg-slate-600 transition">Apply</button>
                            </div>
                        </div>

                        <!-- Subtotals -->
                        <div class="space-y-3 text-sm border-t border-gray-200 dark:border-slate-700/80 pt-4 mb-6">
                            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                <span>Subtotal</span>
                                <span id="summary-subtotal" class="font-medium text-gray-900 dark:text-white">RM 0.00</span>
                            </div>
                            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                <span>Shipping</span>
                                <span class="font-medium text-green-600 dark:text-green-400">Free</span>
                            </div>
                            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                <span>Taxes (Included)</span>
                                <span id="summary-tax" class="font-medium text-gray-900 dark:text-white">RM 0.00</span>
                            </div>
                        </div>

                        <!-- Grand Total -->
                        <div class="border-t border-gray-200 dark:border-slate-700/80 pt-4 mb-8 flex justify-between items-end">
                            <span class="text-base font-medium text-gray-900 dark:text-white">Total</span>
                            <div class="text-right">
                                <span class="text-xs text-gray-500 dark:text-gray-400 mr-1">MYR</span>
                                <span id="summary-total" class="text-2xl font-bold text-gray-900 dark:text-white">RM 0.00</span>
                            </div>
                        </div>

                        <!-- Hidden input to store cart data -->
                        <input type="hidden" id="cart-data-input" name="cart_data" value="[]">

                        <!-- Submit Button -->
                        <button type="submit" id="pay-button" class="w-full py-4 px-4 bg-imvidia hover:bg-imvidia-dark text-white rounded-xl shadow-lg shadow-imvidia/30 font-bold text-lg transition transform hover:-translate-y-0.5 flex items-center justify-center group opacity-50 cursor-not-allowed" disabled>
                            <i class="fa-solid fa-lock mr-2 text-imvidia-light group-hover:animate-pulse"></i> 
                            Loading Cart...
                        </button>

                        <p class="text-center text-xs text-gray-500 dark:text-gray-400 mt-4 flex items-center justify-center">
                            <i class="fa-solid fa-shield-halved mr-1"></i> Secure Checkout powered by ImVidia
                        </p>
                    </div>
                </div>
                
            </form>
        </div>
        <?php endif; ?>
    </main>

    <!-- UI Logic: Payment Tabs and CC Formatting -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // --- 1. Payment Method Toggles ---
            const methods = ['card', 'fpx', 'ewallet'];
            
            methods.forEach(method => {
                const radio = document.getElementById('pay_' + method);
                if (!radio) return;
                
                radio.addEventListener('change', () => {
                    // Reset all wrappers to inactive state
                    methods.forEach(m => {
                        const wrapper = document.getElementById(m + '-wrapper');
                        const details = document.getElementById(m + '-details');
                        const label = wrapper?.querySelector('label');
                        
                        if (wrapper) {
                            wrapper.classList.remove('border-imvidia', 'bg-imvidia/5', 'dark:bg-imvidia/10');
                            wrapper.classList.add('border-gray-200', 'dark:border-slate-700', 'bg-white', 'dark:bg-slate-800');
                        }
                        
                        if (label) {
                            label.classList.remove('font-semibold', 'text-gray-900', 'dark:text-white');
                            label.classList.add('font-medium', 'text-gray-700', 'dark:text-gray-300');
                        }
                        
                        if(details) details.classList.add('hidden');
                    });

                    // Set active state for selected wrapper
                    if(radio.checked) {
                        const activeWrapper = document.getElementById(method + '-wrapper');
                        const activeDetails = document.getElementById(method + '-details');
                        const activeLabel = activeWrapper?.querySelector('label');
                        
                        if (activeWrapper) {
                            activeWrapper.classList.remove('border-gray-200', 'dark:border-slate-700', 'bg-white', 'dark:bg-slate-800');
                            activeWrapper.classList.add('border-imvidia', 'bg-imvidia/5', 'dark:bg-imvidia/10');
                        }
                        
                        if (activeLabel) {
                            activeLabel.classList.remove('font-medium', 'text-gray-700', 'dark:text-gray-300');
                            activeLabel.classList.add('font-semibold', 'text-gray-900', 'dark:text-white');
                        }
                        
                        if(activeDetails) activeDetails.classList.remove('hidden');
                    }
                });
            });

            // --- 2. Credit Card Input Formatting ---
            const ccNumber = document.getElementById('cc_number');
            const ccExpiry = document.getElementById('cc_expiry');
            const ccCvc = document.getElementById('cc_cvc');
            const ccName = document.getElementById('cc_name');

            if (ccNumber) {
                ccNumber.addEventListener('input', function(e) {
                    // Strip all non-digits
                    let value = e.target.value.replace(/\D/g, '');
                    // Add space every 4 digits
                    let formattedValue = value.replace(/(.{4})/g, '$1 ').trim();
                    // Enforce max length (16 digits + 3 spaces = 19)
                    e.target.value = formattedValue.substring(0, 19);
                });
            }

            if (ccExpiry) {
                ccExpiry.addEventListener('input', function(e) {
                    // Strip all non-digits
                    let value = e.target.value.replace(/\D/g, '');
                    // Auto-insert slash after month
                    if (value.length > 2) {
                        value = value.substring(0, 2) + ' / ' + value.substring(2, 4);
                    }
                    e.target.value = value;
                    if (e.target.value.substring(0, 2) > 12) {
                        alert('Invalid month!\nPlease enter value between 01 & 12.');
                        e.target.value = '';
                    }
                });
            }

            if (ccCvc) {
                ccCvc.addEventListener('input', function(e) {
                    // Strip all non-digits and cap at 4 max
                    e.target.value = e.target.value.replace(/\D/g, '').substring(0, 3);
                });
            }

            if (ccName) {
                ccName.addEventListener('input', function(e) {
                    // Strip anything that is NOT a letter (a-z, A-Z) or a space (\s)
                    e.target.value = e.target.value.replace(/[^a-zA-Z\s]/g, '').substring(0, 50).toUpperCase();
                });
            }

            // --- 3. Dynamic Cart Rendering ---
            const cartContainer = document.getElementById('checkout-items-container');
            const subtotalEl = document.getElementById('summary-subtotal');
            const taxEl = document.getElementById('summary-tax');
            const totalEl = document.getElementById('summary-total');
            const payBtn = document.getElementById('pay-button');
            const cartDataInput = document.getElementById('cart-data-input');
            const checkoutForm = document.getElementById('checkout-form');

            // Attempt to load cart from localStorage
            let cart = JSON.parse(localStorage.getItem('imvidia_cart')) || [];

            function renderCart() {
                if (cart.length === 0) {
                    if (cartContainer) cartContainer.innerHTML = '<p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">Your cart is empty.</p>';
                    if (subtotalEl) subtotalEl.innerText = 'RM 0.00';
                    if (taxEl) taxEl.innerText = 'RM 0.00';
                    if (totalEl) totalEl.innerText = 'RM 0.00';
                    if (payBtn) {
                        payBtn.innerHTML = '<i class="fa-solid fa-lock mr-2 text-imvidia-light"></i> Cart is Empty';
                        payBtn.disabled = true;
                        payBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    }
                    return;
                }

                let html = '';
                let subtotal = 0;

                cart.forEach(item => {
                    const itemQty = item.quantity || 1;
                    const itemTotal = item.price * itemQty;
                    subtotal += itemTotal;
                    
                    html += `
                        <div class="flex items-start space-x-4">
                            <div class="relative h-16 w-16 bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-700 flex items-center justify-center flex-shrink-0">
                                <i class="fa-solid fa-box text-2xl text-gray-400 dark:text-gray-500"></i>
                                <span class="absolute -top-2 -right-2 bg-gray-500 dark:bg-gray-600 text-white text-xs font-bold px-2 py-0.5 rounded-full z-10">${itemQty}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="text-sm font-bold text-gray-900 dark:text-white truncate">${item.name}</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">ImVidia Original</p>
                            </div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">RM ${itemTotal.toFixed(2)}</p>
                        </div>
                    `;
                });

                if (cartContainer) cartContainer.innerHTML = html;

                // Calculate tax (assuming 6% inclusive for display)
                const tax = subtotal * 0.06; 
                const total = subtotal; 
                
                if (subtotalEl) subtotalEl.innerText = 'RM ' + subtotal.toFixed(2);
                if (taxEl) taxEl.innerText = 'RM ' + tax.toFixed(2);
                if (totalEl) totalEl.innerText = 'RM ' + total.toFixed(2);
                if (payBtn) {
                    payBtn.innerHTML = '<i class="fa-solid fa-lock mr-2 text-imvidia-light group-hover:animate-pulse"></i> Pay RM ' + total.toFixed(2) + ' Now';
                    payBtn.disabled = false;
                    payBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                }

                // Store cart data in hidden input for form submission
                if (cartDataInput) {
                    cartDataInput.value = JSON.stringify(cart);
                }
            }

            renderCart();
        });
    </script>

    <!-- Dark mode logic -->
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
