<?php
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
require_once 'includes/db-helpers.php';
require_once 'includes/order-helpers.php';
require_once 'includes/cart-helpers.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get user data or guest status
$user = checkCustomerOrGuest();

ensureOrdersSchemaV2();
ensureCartSchema();

// Initialize receipt data
$receipt_data = null;
$checkout_success = false;
$error_message = '';

/**
 * Saves one checkout as a single order header row (with the shipping/contact
 * details captured on the form) plus one order_items row per distinct cart
 * line (preserving quantity), instead of one orders row per unit purchased.
 */
function saveOrder(?int $user_id, array $cart, string $payment_method, string $payment_detail, array $shipping, string $order_date): int|false {
    global $conn;

    if (empty($cart)) {
        return false;
    }

    beginTransaction();

    $delivery_time = date('Y-m-d H:i:s', strtotime($order_date . ' +3 days'));
    $order_progress = 'Pending';

    $header_saved = executeStatement(
        "INSERT INTO orders (user_id, order_date, payment_method, payment_detail, delivery_time, order_progress, email, first_name, last_name, phone, address, city, state, postcode)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $user_id, $order_date, $payment_method, $payment_detail, $delivery_time, $order_progress,
            $shipping['email'], $shipping['first_name'], $shipping['last_name'], $shipping['phone'],
            $shipping['address'], $shipping['city'], $shipping['state'], $shipping['postcode'],
        ],
        'isssss' . str_repeat('s', 8)
    );

    if (!$header_saved) {
        rollbackTransaction();
        return false;
    }

    $order_id = getLastInsertId();

    foreach ($cart as $item) {
        $product_name = trim((string) ($item['name'] ?? ''));
        $quantity = max(1, (int) ($item['quantity'] ?? 1));

        if ($product_name === '') {
            rollbackTransaction();
            return false;
        }

        // Never trust the client-submitted price: resolve the product and use
        // the current DB price as the authoritative unit_price.
        $product = getRow("SELECT product_id, price FROM product WHERE name = ? LIMIT 1", [$product_name], 's');
        if (!$product) {
            rollbackTransaction();
            return false;
        }
        $unit_price = (float) $product['price'];

        $item_saved = executeStatement(
            "INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)",
            [$order_id, (int) $product['product_id'], $quantity, $unit_price],
            'iiid'
        );

        if (!$item_saved) {
            rollbackTransaction();
            return false;
        }
    }

    commitTransaction();
    return $order_id;
}

// Handle POST request (order submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfOrFail();
    // Validate and collect customer information from form
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $city = isset($_POST['city']) ? trim($_POST['city']) : '';
    $state = isset($_POST['state']) ? trim($_POST['state']) : '';
    $postcode = isset($_POST['postcode']) ? trim($_POST['postcode']) : '';
    $phone = isset($_POST['phone']) ? formatMalaysianPhone(trim($_POST['phone'])) : '';
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
    $cart_json = isset($_POST['cart_data']) ? $_POST['cart_data'] : '[]';

    // Never trust the client to have actually filled in payment details - the
    // "loading" screen is client-side theatre, so this is the one check that
    // actually gates a purchase. Only a masked card last-4 / bank code / wallet
    // code crosses the wire (never a full card number or CVC).
    $allowed_payment_methods = ['card', 'fpx', 'ewallet'];
    $payment_detail = '';
    $payment_valid = in_array($payment_method, $allowed_payment_methods, true);

    if ($payment_valid) {
        switch ($payment_method) {
            case 'card':
                $card_last4 = preg_replace('/\D/', '', (string) ($_POST['card_last4'] ?? ''));
                $payment_valid = strlen($card_last4) === 4;
                $payment_detail = $card_last4;
                break;
            case 'fpx':
                $fpx_bank = trim((string) ($_POST['fpx_bank'] ?? ''));
                $payment_valid = array_key_exists($fpx_bank, getFpxBankOptions());
                $payment_detail = $fpx_bank;
                break;
            case 'ewallet':
                $ewallet_provider = trim((string) ($_POST['ewallet_provider'] ?? ''));
                $payment_valid = array_key_exists($ewallet_provider, getEwalletOptions());
                $payment_detail = $ewallet_provider;
                break;
        }
    }

    // Validate required fields
    if ($email && $first_name && $last_name && $address && $city && $state && $postcode && $phone && $payment_valid) {
        // Parse cart data from form
        $cart = json_decode($cart_json, true);

        if (is_array($cart) && count($cart) > 0) {
            // Calculate totals from authoritative DB prices, never the prices
            // the client submitted in cart_data.
            $db_price_map = [];
            foreach (getRows("SELECT name, price FROM product") as $price_row) {
                $db_price_map[$price_row['name']] = (float) $price_row['price'];
            }

            $subtotal = 0;
            foreach ($cart as &$item) {
                $qty = max(1, (int) ($item['quantity'] ?? 1));
                $price = $db_price_map[$item['name'] ?? ''] ?? 0;
                $item['price'] = $price; // overwrite client price for the receipt
                $subtotal += ($price * $qty);
            }
            unset($item);

            $tax = $subtotal * 0.06; // 6% tax
            $shipping = 0; // Free shipping
            $total = $subtotal + $tax;

            // One timestamp shared by the order row and the receipt, so a
            // later PDF re-download derives the exact same receipt number.
            $now = date('Y-m-d H:i:s');
            $purchase_date = date('Y-m-d', strtotime($now));
            $purchase_time = date('H:i:s', strtotime($now));

            [$payment_method_label, $payment_detail_label] = formatPaymentMethodDisplay($payment_method, $payment_detail);

            // Persist the order regardless of login status - guests are
            // identified by the shipping details they just filled in, since
            // there's no account to attach the order to.
            $logged_in_user_id = (!empty($user['is_logged_in']) && isset($user['user_id'])) ? (int) $user['user_id'] : null;
            $shipping_info = [
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone' => $phone,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'postcode' => $postcode,
            ];
            $order_id = saveOrder($logged_in_user_id, $cart, $payment_method, $payment_detail, $shipping_info, $now);
            if ($order_id === false) {
                error_log('Checkout persistence failed for user_id=' . ($logged_in_user_id ?? 'guest') . '.');
                $error_message = "We couldn't save your order due to a system error. You have not been charged - please try again, or contact support if this keeps happening.";
            } else {
                $checkout_success = true;

                // Guests get PDF/receipt access via this session only; logged-in
                // users can also always reach their own orders via ownership.
                grantReceiptAccess($order_id);

                // Prepare receipt data
                $receipt_data = [
                    'order_id' => $order_id,
                    'receipt_number' => getOrderReceiptNumber(['order_date' => $now, 'order_id' => $order_id]),
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
                    'payment_method_label' => $payment_method_label,
                    'payment_detail_label' => $payment_detail_label,
                    'items' => $cart,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'shipping' => $shipping,
                    'total' => $total
                ];

                // Only the items the customer selected for this checkout were
                // purchased - leave any deselected items sitting in the cart.
                if ($logged_in_user_id !== null) {
                    executeStatement("DELETE FROM cart_items WHERE user_id = ? AND selected = 1", [$logged_in_user_id], 'i');
                }
            }
        } else {
            $error_message = 'Your cart appears to be empty. Please add items to your cart before checking out.';
        }
    } elseif (!$payment_valid) {
        $error_message = 'Please complete your payment details before checking out.';
    } else {
        $error_message = 'Please fill in all required fields before checking out.';
    }
}

// Prefill contact/shipping fields: prefer resubmitted POST values (e.g. after
// a validation error), then fall back to the logged-in user's saved details.
$prefill = [
    'email' => $_POST['email'] ?? ($user['email'] ?? ''),
    'first_name' => $_POST['first_name'] ?? ($user['first_name'] ?? ''),
    'last_name' => $_POST['last_name'] ?? ($user['last_name'] ?? ''),
    'phone' => $_POST['phone'] ?? ($user['phone'] ?? ''),
    'address' => $_POST['address'] ?? ($user['address_street'] ?? ''),
    'city' => $_POST['city'] ?? ($user['address_city'] ?? ''),
    'state' => $_POST['state'] ?? ($user['address_state'] ?? ''),
    'postcode' => $_POST['postcode'] ?? ($user['address_zip'] ?? ''),
];

// For logged-in users the cart lives in the DB, so build the same
// {name, price, quantity, selected} shape the guest localStorage cart uses,
// letting the existing client-side rendering/filtering logic stay unchanged.
$server_full_cart = [];
if (!empty($user['is_logged_in'])) {
    foreach (getCartItemsForUser($user['user_id']) as $row) {
        $server_full_cart[] = [
            'name' => $row['name'],
            'price' => (float) $row['price'],
            'quantity' => (int) $row['quantity'],
            'selected' => (bool) $row['selected'],
        ];
    }
}

// Product thumbnails aren't stored on cart items themselves (guest carts live
// in localStorage with no image field), so look them up by name the same way
// cart.php does.
$placeholder_image = 'https://ui-avatars.com/api/?name=No+Image&background=f1f5f9&color=94a3b8';
$product_images = [];
foreach (getRows("SELECT name, image_url FROM product") as $row) {
    $product_images[$row['name']] = !empty($row['image_url']) ? $row['image_url'] : $placeholder_image;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Checkout - ImVidia</title>
    <?php include 'includes/head.php'; ?>

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

        /* Payment field error state (set/cleared by the checkout JS) */
        .field-error {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important;
        }

        /* ===== 3D tower loader (fake payment-processing screen) =====
           Original by csozi (www.csozi.hu), recolored to ImVidia's palette:
           top face = imvidia-dark, right face = imvidia, left face = imvidia-light.
           Extra top margin keeps it clear of the payment-method badge above it. */
        .loader {
            scale: 3;
            height: 50px;
            width: 40px;
            margin-top: 46px;
        }

        .box {
            position: relative;
            opacity: 0;
            left: 10px;
        }

        .side-left {
            position: absolute;
            background-color: #8DFFFF;
            width: 19px;
            height: 5px;
            transform: skew(0deg, -25deg);
            top: 14px;
            left: 10px;
        }

        .side-right {
            position: absolute;
            background-color: #49C2FA;
            width: 19px;
            height: 5px;
            transform: skew(0deg, 25deg);
            top: 14px;
            left: -9px;
        }

        .side-top {
            position: absolute;
            background-color: #1F2468;
            width: 20px;
            height: 20px;
            rotate: 45deg;
            transform: skew(-20deg, -20deg);
        }

        .box-1 {
            animation: from-left 4s infinite;
        }

        .box-2 {
            animation: from-right 4s infinite;
            animation-delay: 1s;
        }

        .box-3 {
            animation: from-left 4s infinite;
            animation-delay: 2s;
        }

        .box-4 {
            animation: from-right 4s infinite;
            animation-delay: 3s;
        }

        @keyframes from-left {
            0% {
                z-index: 20;
                opacity: 0;
                translate: -20px -6px;
            }
            20% {
                z-index: 10;
                opacity: 1;
                translate: 0px 0px;
            }
            40% {
                z-index: 9;
                translate: 0px 4px;
            }
            60% {
                z-index: 8;
                translate: 0px 8px;
            }
            80% {
                z-index: 7;
                opacity: 1;
                translate: 0px 12px;
            }
            100% {
                z-index: 5;
                translate: 0px 30px;
                opacity: 0;
            }
        }

        @keyframes from-right {
            0% {
                z-index: 20;
                opacity: 0;
                translate: 20px -6px;
            }
            20% {
                z-index: 10;
                opacity: 1;
                translate: 0px 0px;
            }
            40% {
                z-index: 9;
                translate: 0px 4px;
            }
            60% {
                z-index: 8;
                translate: 0px 8px;
            }
            80% {
                z-index: 7;
                opacity: 1;
                translate: 0px 12px;
            }
            100% {
                z-index: 5;
                translate: 0px 30px;
                opacity: 0;
            }
        }

        /* Printing the on-page receipt (or "Save as PDF" from the browser's
           print dialog): hide everything except the receipt card itself. */
        @media print {
            nav, footer, .print\:hidden {
                display: none !important;
            }
            body {
                background: #fff !important;
            }
            #receipt-container {
                max-width: 100% !important;
            }
            .shadow-lg, .shadow-xl, .shadow-2xl, .shadow-sm {
                box-shadow: none !important;
            }
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-800 antialiased dark:bg-slate-950 dark:text-gray-100 selection:bg-imvidia selection:text-white">

    <!-- Minimal Navbar for Checkout (Distraction Free) -->
    <nav class="bg-white shadow-sm sticky top-0 z-50 dark:bg-slate-950">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-6">
            <div class="flex justify-between md:justify-start h-16 items-center w-full">
                <a href="index.php" class="flex-shrink-0 flex items-center cursor-pointer hover:scale-105 transition transform duration-300">
                    <img class="theme-logo h-10 w-auto mr-2" data-light="assets/logo.svg" data-dark="assets/logo-light.svg" src="assets/logo.svg" alt="ImVidia Logo">
                    <span class="font-bold text-2xl tracking-tight text-gray-900 dark:text-white" >ImVidia<span class="text-imvidia">.</span></span>
                </a>
                <button id="dark-mode-toggle" type="button" class="p-2 rounded-full text-gray-600 hover:text-imvidia transition dark:text-gray-300" aria-label="Toggle dark mode" onclick="toggleDarkMode()">
                    <i id="dark-mode-icon" class="fa-solid fa-moon"></i>
                </button>
            </div>
        </div>
    </nav> 

    <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 animate-fade-in-up">
        
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
                            <p class="text-base font-semibold text-gray-900 dark:text-white mt-1"><?php echo htmlspecialchars($receipt_data['payment_method_label']); ?></p>
                            <?php if (!empty($receipt_data['payment_detail_label'])): ?>
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-mono mt-0.5"><?php echo htmlspecialchars($receipt_data['payment_detail_label']); ?></p>
                            <?php endif; ?>
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
                            <span class="text-base text-gray-600 dark:text-gray-400">Tax (6%)</span>
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

            <!-- Actions -->
            <div class="flex flex-col sm:flex-row justify-center gap-3">
                <a href="receipt-pdf.php?order_id=<?php echo (int) $receipt_data['order_id']; ?>" class="px-6 py-4 bg-white dark:bg-slate-900 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-slate-700 rounded-xl shadow-sm font-bold text-base transition transform hover:-translate-y-0.5 hover:border-imvidia flex items-center justify-center">
                    <i class="fa-solid fa-file-pdf mr-2 text-red-500"></i> Download PDF
                </a>
                <button onclick="window.location.href='index.php'" class="px-8 py-4 bg-imvidia hover:bg-imvidia-dark text-white rounded-xl shadow-lg shadow-imvidia/30 font-bold text-lg transition transform hover:-translate-y-0.5 flex items-center justify-center">
                    <i class="fa-solid fa-check mr-2"></i> OK
                </button>
            </div>
        </div>

        <?php if (empty($user['is_logged_in'])): ?>
        <script>
            if (window.localStorage) {
                // Only the items the customer selected for this checkout were
                // purchased - leave any deselected items sitting in the cart.
                let cart = JSON.parse(localStorage.getItem(window.IMVIDIA_CART_KEY)) || [];
                const remaining = cart.filter(item => item.selected === false);
                localStorage.setItem(window.IMVIDIA_CART_KEY, JSON.stringify(remaining));
            }
        </script>
        <?php endif; ?>
        <!-- Logged-in users: the purchased (selected) rows were already deleted from cart_items server-side. -->

        <?php else: ?>
        <!-- CHECKOUT FORM (shown on initial page load) -->
        <div id="checkout-form-container">
            <!-- Page Title -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Checkout</h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1">Complete your order securely.</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="mb-6 px-4 py-3 rounded-lg text-sm font-medium text-center bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form id="checkout-form" method="POST" class="grid grid-cols-1 lg:grid-cols-12 gap-10">
                <?php echo csrfField(); ?>
                <!-- LEFT COLUMN: Forms (Takes up 7 out of 12 columns on large screens) -->
                <div class="lg:col-span-7 space-y-8">
                    
                    <?php if (!empty($user['is_logged_in'])): ?>
                    <!-- Logged-in Banner -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-2xl p-5 flex items-start shadow-sm">
                        <i class="fa-solid fa-circle-user text-blue-500 text-xl mt-0.5 mr-3"></i>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white">Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h3>
                            <p class="text-sm text-gray-600 dark:text-blue-200 mt-1">Details below are from your account, you can update them for this order.</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Login / Guest Prompt Banner -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-2xl p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between shadow-sm">
                        <div class="flex items-start mb-4 sm:mb-0">
                            <i class="fa-solid fa-circle-user text-blue-500 text-xl mt-0.5 mr-3"></i>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Already have an account?</h3>
                                <p class="text-sm text-gray-600 dark:text-blue-200 mt-1">Login to save time, Or just continue as a guest.</p>
                            </div>
                        </div>
                        <a href="login.php" class="px-5 py-2 bg-white dark:bg-slate-800 text-sm font-bold text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-700 rounded-xl shadow-sm hover:bg-blue-100 dark:hover:bg-slate-700 transition flex-shrink-0 text-center">
                            Log In
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- Section 1: Contact Information -->
                    <section class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-5 flex items-center">
                            <span class="bg-imvidia/10 text-imvidia h-8 w-8 rounded-full flex items-center justify-center text-sm mr-3">1</span>
                            Contact Information
                        </h2>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address <span class="text-red-500">*</span></label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($prefill['email']); ?>" required placeholder="jane.doe@example.com" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
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
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($prefill['first_name']); ?>" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last Name <span class="text-red-500">*</span></label>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($prefill['last_name']); ?>" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address <span class="text-red-500">*</span></label>
                                <input type="text" name="address" value="<?php echo htmlspecialchars($prefill['address']); ?>" required placeholder="Street address, apartment, suite, etc." class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">City <span class="text-red-500">*</span></label>
                                <input type="text" name="city" value="<?php echo htmlspecialchars($prefill['city']); ?>" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">State <span class="text-red-500">*</span></label>
                                    <select name="state" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm appearance-none cursor-pointer">
                                        <option value="" disabled <?php echo $prefill['state'] === '' ? 'selected' : ''; ?>>Select...</option>
                                        <option value="JHR" <?php echo $prefill['state'] === 'JHR' ? 'selected' : ''; ?>>Johor</option>
                                        <option value="KDH" <?php echo $prefill['state'] === 'KDH' ? 'selected' : ''; ?>>Kedah</option>
                                        <option value="KEL" <?php echo $prefill['state'] === 'KEL' ? 'selected' : ''; ?>>Kelantan</option>
                                        <option value="KUL" <?php echo $prefill['state'] === 'KUL' ? 'selected' : ''; ?>>Kuala Lumpur</option>
                                        <option value="MLK" <?php echo $prefill['state'] === 'MLK' ? 'selected' : ''; ?>>Melaka</option>
                                        <option value="NSN" <?php echo $prefill['state'] === 'NSN' ? 'selected' : ''; ?>>Negeri Sembilan</option>
                                        <option value="PHG" <?php echo $prefill['state'] === 'PHG' ? 'selected' : ''; ?>>Pahang</option>
                                        <option value="PEN" <?php echo $prefill['state'] === 'PEN' ? 'selected' : ''; ?>>Penang</option>
                                        <option value="PRK" <?php echo $prefill['state'] === 'PRK' ? 'selected' : ''; ?>>Perak</option>
                                        <option value="PJY" <?php echo $prefill['state'] === 'PJY' ? 'selected' : ''; ?>>Putrajaya</option>
                                        <option value="SBH" <?php echo $prefill['state'] === 'SBH' ? 'selected' : ''; ?>>Sabah</option>
                                        <option value="SRW" <?php echo $prefill['state'] === 'SRW' ? 'selected' : ''; ?>>Sarawak</option>
                                        <option value="SGR" <?php echo $prefill['state'] === 'SGR' ? 'selected' : ''; ?>>Selangor</option>
                                        <option value="TRG" <?php echo $prefill['state'] === 'TRG' ? 'selected' : ''; ?>>Terengganu</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ZIP / Postcode <span class="text-red-500">*</span></label>
                                    <input type="text" name="postcode" value="<?php echo htmlspecialchars($prefill['postcode']); ?>" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number <span class="text-red-500">*</span></label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars(formatMalaysianPhone($prefill['phone'])); ?>" required placeholder="+60 12 3456789" class="phone-input w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm">
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

                        <div id="payment-error-banner" class="hidden mb-5 px-4 py-3 rounded-lg text-sm font-medium bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800 flex items-center">
                            <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                            <span id="payment-error-text"></span>
                        </div>

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
                                        <select id="fpx_bank" name="fpx_bank" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm appearance-none cursor-pointer">
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
                                        <select id="ewallet_provider" name="ewallet_provider" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm appearance-none cursor-pointer">
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
                                <span>Tax (6%)</span>
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
                        <!-- Only the last 4 digits ever leave the browser - never the full card number/CVC. -->
                        <input type="hidden" id="card_last4" name="card_last4" value="">

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

            <<!-- fake payment loader -->
            <div id="payment-loading-overlay" class="hidden fixed inset-0 z-[999] flex items-center justify-center bg-slate-950/80 backdrop-blur-sm px-4">
                <div class="w-full max-w-sm bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-gray-100 dark:border-slate-800 p-8 sm:p-10 text-center">
                    <div id="payment-method-badge" class="mx-auto h-16 w-16 rounded-2xl bg-white flex items-center justify-center text-xl font-black shadow-md overflow-hidden p-2 mb-6"></div>

                    <div class="loader-wrap flex items-center justify-center">
                        <div class="loader">
                            <div class="box box-1"><div class="side-left"></div><div class="side-right"></div><div class="side-top"></div></div>
                            <div class="box box-2"><div class="side-left"></div><div class="side-right"></div><div class="side-top"></div></div>
                            <div class="box box-3"><div class="side-left"></div><div class="side-right"></div><div class="side-top"></div></div>
                            <div class="box box-4"><div class="side-left"></div><div class="side-right"></div><div class="side-top"></div></div>
                        </div>
                    </div>

                    <p id="payment-status-title" class="text-lg font-bold text-gray-900 dark:text-white mb-1">Processing Payment</p>
                    <p id="payment-status-detail" class="text-sm text-gray-500 dark:text-gray-400 font-mono"></p>

                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-6 flex items-center justify-center">
                        <i class="fa-solid fa-lock mr-1.5"></i> Please don't close or refresh this page
                    </p>
                </div>
            </div>
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

            // Only the items the customer checked off in the cart get checked out here.
            // Logged-in users' cart lives in the DB and is rendered server-side into
            // the same {name, price, quantity, selected} shape the guest cart uses.
            const fullCart = window.IMVIDIA_LOGGED_IN
                ? <?php echo json_encode($server_full_cart); ?>
                : JSON.parse(localStorage.getItem(window.IMVIDIA_CART_KEY)) || [];
            let cart = fullCart.filter(item => item.selected !== false);

            const productImages = <?php echo json_encode($product_images); ?>;
            const placeholderImage = <?php echo json_encode($placeholder_image); ?>;
            // product image or placeholder
            function getProductImage(name) {
                return productImages[name] || placeholderImage;
            }

            // render checkout order summary
            function renderCart() {
                if (cart.length === 0) {
                    const emptyMessage = fullCart.length === 0
                        ? 'Your cart is empty.'
                        : 'No items selected. Go back to your cart and select at least one item.';
                    if (cartContainer) cartContainer.innerHTML = `<p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">${emptyMessage}</p>`;
                    if (subtotalEl) subtotalEl.innerText = 'RM 0.00';
                    if (taxEl) taxEl.innerText = 'RM 0.00';
                    if (totalEl) totalEl.innerText = 'RM 0.00';
                    if (payBtn) {
                        payBtn.innerHTML = '<i class="fa-solid fa-lock mr-2 text-imvidia-light"></i> ' + (fullCart.length === 0 ? 'Cart is Empty' : 'No Items Selected');
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
                    const thumbnail = getProductImage(item.name);

                    html += `
                        <div class="flex items-start space-x-4">
                            <div class="relative h-16 w-16 bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-700 flex items-center justify-center flex-shrink-0">
                                <img src="${thumbnail}" alt="${item.name}" class="max-w-full max-h-full object-contain rounded-md">
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

                // 6% tax added on top of subtotal
                const tax = subtotal * 0.06;
                const total = subtotal + tax;
                
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

            // --- 4. Payment validation + fake processing overlay ---
            const PAYMENT_LABELS = {
                fpx: {
                    maybank: 'Maybank2U', cimb: 'CIMB Clicks', public: 'Public Bank',
                    rhb: 'RHB Now', hongleong: 'Hong Leong Connect', bankislam: 'Bank Islam', ambank: 'AmBank'
                },
                ewallet: {
                    tng: "Touch 'n Go eWallet", shopee: 'ShopeePay', boost: 'Boost', grab: 'GrabPay', alipay: 'Alipay+'
                }
            };

            // Real provider logos, shown on the fake "contacting portal" loading
            // screen. Drop matching image files into assets/payment/ - if a file
            // is missing, the badge falls back to a generic bank/wallet icon
            // (see setBadgeLogo() below) instead of a broken image.
            const PROVIDER_LOGOS = {
                maybank: 'assets/payment/maybank.png',
                cimb: 'assets/payment/cimb.png',
                public: 'assets/payment/public.png',
                rhb: 'assets/payment/rhb.png',
                hongleong: 'assets/payment/hongleong.png',
                bankislam: 'assets/payment/bankislam.png',
                ambank: 'assets/payment/ambank.png',
                tng: 'assets/payment/tng.png',
                shopee: 'assets/payment/shopee.png',
                boost: 'assets/payment/boost.png',
                grab: 'assets/payment/grab.png',
                alipay: 'assets/payment/alipay.png'
            };

            const paymentErrorBanner = document.getElementById('payment-error-banner');
            const paymentErrorText = document.getElementById('payment-error-text');

            function showPaymentError(message, focusEl) {
                if (paymentErrorText) paymentErrorText.textContent = message;
                if (paymentErrorBanner) {
                    paymentErrorBanner.classList.remove('hidden');
                    paymentErrorBanner.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                if (focusEl) focusEl.focus();
            }

            function clearPaymentError() {
                if (paymentErrorBanner) paymentErrorBanner.classList.add('hidden');
                document.querySelectorAll('.field-error').forEach(el => el.classList.remove('field-error'));
            }

            // Validates the currently-selected payment method's own detail fields
            // (the shared shipping/contact fields are already covered by the
            // form's native `required` attributes via reportValidity()).
            // Returns the info needed to populate the loading screen, or null.
            function validatePaymentDetails() {
                clearPaymentError();
                const checkedRadio = document.querySelector('input[name="payment_method"]:checked');
                const method = checkedRadio ? checkedRadio.value : '';

                if (method === 'card') {
                    const number = (ccNumber?.value || '').replace(/\D/g, '');
                    const expiry = ccExpiry?.value || '';
                    const cvc = ccCvc?.value || '';
                    const name = (ccName?.value || '').trim();
                    let firstInvalid = null;

                    if (number.length !== 16) { ccNumber.classList.add('field-error'); firstInvalid = firstInvalid || ccNumber; }
                    if (!/^\d{2} \/ \d{2}$/.test(expiry)) { ccExpiry.classList.add('field-error'); firstInvalid = firstInvalid || ccExpiry; }
                    if (cvc.length !== 3) { ccCvc.classList.add('field-error'); firstInvalid = firstInvalid || ccCvc; }
                    if (name.length < 2) { ccName.classList.add('field-error'); firstInvalid = firstInvalid || ccName; }

                    if (firstInvalid) {
                        showPaymentError('Please complete your card number, expiry, CVC and name before continuing.', firstInvalid);
                        return null;
                    }

                    const last4 = number.slice(-4);
                    document.getElementById('card_last4').value = last4;
                    return {
                        method,
                        badgeType: 'icon',
                        badgeIcon: 'fa-credit-card',
                        title: 'Processing Card Payment',
                        detail: 'Card •••• •••• •••• ' + last4
                    };
                }

                if (method === 'fpx') {
                    const select = document.getElementById('fpx_bank');
                    const bank = select ? select.value : '';
                    if (!bank) {
                        select.classList.add('field-error');
                        showPaymentError('Please select your bank to continue with FPX Online Banking.', select);
                        return null;
                    }
                    return {
                        method,
                        badgeType: 'logo',
                        badgeSrc: PROVIDER_LOGOS[bank],
                        badgeFallbackIcon: 'fa-building-columns',
                        title: 'Contacting Bank Portal',
                        detail: 'Redirecting to ' + (PAYMENT_LABELS.fpx[bank] || bank) + '...'
                    };
                }

                if (method === 'ewallet') {
                    const select = document.getElementById('ewallet_provider');
                    const provider = select ? select.value : '';
                    if (!provider) {
                        select.classList.add('field-error');
                        showPaymentError('Please select an e-wallet provider to continue.', select);
                        return null;
                    }
                    return {
                        method,
                        badgeType: 'logo',
                        badgeSrc: PROVIDER_LOGOS[provider],
                        badgeFallbackIcon: 'fa-wallet',
                        title: 'Contacting E-Wallet',
                        detail: 'Contacting ' + (PAYMENT_LABELS.ewallet[provider] || provider) + ' portal...'
                    };
                }

                showPaymentError('Please select a payment method to continue.', null);
                return null;
            }

            // Renders a plain icon badge (used for the card payment method).
            function setBadgeIcon(badge, iconClass) {
                badge.innerHTML = `<i class="fa-solid ${iconClass} text-2xl text-imvidia"></i>`;
            }

            // Renders a provider logo image; if it fails to load (e.g. the file
            // hasn't been added to assets/payment/ yet), falls back to a generic
            // icon instead of showing a broken image.
            function setBadgeLogo(badge, src, fallbackIconClass) {
                badge.innerHTML = `<img src="${src}" alt="" class="max-w-full max-h-full object-contain">`;
                const img = badge.querySelector('img');
                if (img) {
                    img.onerror = () => setBadgeIcon(badge, fallbackIconClass);
                }
            }

            function showPaymentOverlay(info) {
                const overlay = document.getElementById('payment-loading-overlay');
                const badge = document.getElementById('payment-method-badge');
                const title = document.getElementById('payment-status-title');
                const detail = document.getElementById('payment-status-detail');
                if (!overlay || !badge || !title || !detail) return;

                if (info.badgeType === 'logo') {
                    setBadgeLogo(badge, info.badgeSrc, info.badgeFallbackIcon);
                } else {
                    setBadgeIcon(badge, info.badgeIcon);
                }

                title.textContent = info.title;
                detail.textContent = info.detail;

                overlay.classList.remove('hidden');
            }

            if (checkoutForm) {
                checkoutForm.addEventListener('submit', (e) => {
                    e.preventDefault();

                    // Covers all the plain `required` shipping/contact fields;
                    // shows the browser's own inline bubble on the first invalid one.
                    if (!checkoutForm.reportValidity()) {
                        return;
                    }

                    const info = validatePaymentDetails();
                    if (!info) {
                        return;
                    }

                    if (payBtn) payBtn.disabled = true;
                    showPaymentOverlay(info);

                    // Fake gateway delay - purely cosmetic. The order is only ever
                    // actually created after real server-side validation on submit.
                    setTimeout(() => {
                        checkoutForm.submit();
                    }, 2200);
                });
            }
        });
    </script>

</body>
</html>
