<?php
require_once 'includes/auth.php';
require_once 'includes/db-helpers.php';
require_once 'includes/helpers.php';
require_once 'includes/order-helpers.php';

requireCustomerLogin();

$user = checkCustomerOrGuest();
$user_id = (int) $_SESSION['user_id'];

ensureOrdersSchemaV2();

$db_user = getUserData($user_id);
if ($db_user) {
    $user = array_merge($user, $db_user);
}

$placeholder_image = 'https://ui-avatars.com/api/?name=No+Image&background=f1f5f9&color=94a3b8';

$orders = getOrdersForUser($user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Order History - ImVidia</title>
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
    </style>
</head>

<body class="bg-fixed bg-gray-50 text-gray-800 flex flex-col min-h-screen dark:bg-slate-950 dark:text-gray-100">

    <?php include 'includes/navbar-customer.php'; ?>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full relative z-10">

        <nav class="flex text-xs font-medium text-gray-400 dark:text-slate-500 mb-8 uppercase tracking-widest" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-2">
                <li><a href="index.php" class="hover:text-imvidia transition">Home</a></li>
                <li><span class="mx-1">/</span></li>
                <li><span class="text-gray-600 dark:text-gray-300">Order History</span></li>
            </ol>
        </nav>

        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Order History</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Track and review your past orders.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">

            <div class="lg:col-span-4 xl:col-span-3 space-y-6">
                <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800 text-center">
                    <?php $avatar_url = getAvatarUrl($user['first_name'] ?? '', $user['last_name'] ?? '', $user['profile_picture'] ?? ''); ?>
                    <div class="relative w-32 h-32 mx-auto mb-4">
                        <div class="w-full h-full rounded-full overflow-hidden border-4 border-white dark:border-slate-800 shadow-md">
                            <img src="<?php echo $avatar_url; ?>" alt="Profile Picture" class="w-full h-full object-cover bg-white">
                        </div>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>

                <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800 overflow-hidden">
                    <nav class="flex flex-col">
                        <a href="profile.php" class="px-6 py-4 flex items-center text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-800/50 hover:text-imvidia dark:hover:text-imvidia transition border-l-4 border-transparent">
                            <i class="fa-regular fa-id-badge w-6"></i> Profile Details
                        </a>
                        <a href="order-history.php" class="px-6 py-4 flex items-center bg-gray-50 dark:bg-slate-800/50 border-l-4 border-imvidia text-imvidia font-semibold transition">
                            <i class="fa-solid fa-box-open w-6"></i> Order History
                        </a>
                        <a href="wishlist.php" class="px-6 py-4 flex items-center text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-800/50 hover:text-imvidia dark:hover:text-imvidia transition border-l-4 border-transparent">
                            <i class="fa-regular fa-heart w-6"></i> Wishlist
                        </a>
                        <div class="border-t border-gray-100 dark:border-slate-800 my-1"></div>
                        <a href="logout.php" class="px-6 py-4 flex items-center text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition border-l-4 border-transparent">
                            <i class="fa-solid fa-arrow-right-from-bracket w-6"></i> Log Out
                        </a>
                    </nav>
                </div>
            </div>

            <div class="lg:col-span-8 xl:col-span-9 space-y-6">

                <?php if (empty($orders)): ?>
                    <div class="flex flex-col items-center justify-center py-24 bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-dashed border-gray-300 dark:border-slate-700">
                        <i class="fa-solid fa-box-open text-6xl text-gray-300 dark:text-slate-600 mb-6"></i>
                        <h3 class="text-2xl font-bold text-gray-500 dark:text-gray-400">No orders yet</h3>
                        <p class="text-gray-400 dark:text-gray-500 mt-2 mb-8 text-sm">Your past orders will show up here once you check out.</p>
                        <a href="index.php#catalog" class="px-6 py-3 bg-imvidia hover:bg-imvidia-dark text-white rounded-xl shadow-md font-bold text-sm transition transform hover:-translate-y-0.5">
                            <i class="fa-solid fa-store mr-2"></i> Browse Catalog
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <?php
                            $order_date = !empty($order['order_date']) ? date('d M Y, h:i A', strtotime($order['order_date'])) : 'N/A';
                            $delivery_time = !empty($order['delivery_time']) ? date('d M Y', strtotime($order['delivery_time'])) : 'N/A';
                            $payment_method = !empty($order['payment_method']) ? ucfirst($order['payment_method']) : 'N/A';
                            $order_progress = !empty($order['order_progress']) ? $order['order_progress'] : 'Pending';
                            $order_total = getOrderTotal($order);
                        ?>
                        <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800 overflow-hidden">
                            <div class="px-6 py-4 bg-gray-50 dark:bg-slate-800/50 border-b border-gray-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-semibold">Order #<?php echo (int) $order['order_id']; ?> &middot; Placed</p>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($order_date); ?></p>
                                </div>
                                <div class="flex items-center gap-6 text-sm">
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-semibold">Payment</p>
                                        <p class="font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($payment_method); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-semibold">Est. Delivery</p>
                                        <p class="font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($delivery_time); ?></p>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?php echo getOrderProgressClass($order_progress); ?>">
                                        <?php echo htmlspecialchars($order_progress); ?>
                                    </span>
                                </div>
                            </div>

                            <?php if (strtolower($order_progress) === 'cancelled' && !empty($order['cancel_reason'])): ?>
                                <div class="px-6 py-3 bg-red-50 dark:bg-red-900/10 border-b border-red-100 dark:border-red-900/30 text-sm text-red-600 dark:text-red-400">
                                    <i class="fa-solid fa-circle-info mr-1"></i> Cancellation reason: <?php echo htmlspecialchars($order['cancel_reason']); ?>
                                </div>
                            <?php endif; ?>

                            <div class="divide-y divide-gray-100 dark:divide-slate-800">
                                <?php foreach ($order['items'] as $item): ?>
                                    <?php $item_name = !empty($item['product_name']) ? $item['product_name'] : ('Product #' . (int) $item['product_id']); ?>
                                    <div class="px-6 py-4 flex items-center gap-4">
                                        <div class="w-16 h-16 bg-gray-50 dark:bg-slate-800 rounded-xl flex items-center justify-center flex-shrink-0 border border-gray-200 dark:border-slate-700">
                                            <img src="<?php echo htmlspecialchars(!empty($item['image_url']) ? $item['image_url'] : $placeholder_image); ?>" alt="<?php echo htmlspecialchars($item_name); ?>" class="max-w-full max-h-full object-contain rounded-md">
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h4 class="font-bold text-gray-900 dark:text-white text-sm sm:text-base truncate"><?php echo htmlspecialchars($item_name); ?></h4>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                                Qty: <?php echo (int) $item['quantity']; ?> &times; RM <?php echo number_format($item['unit_price'], 2); ?>
                                            </p>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <p class="font-bold text-gray-900 dark:text-white text-sm sm:text-base">RM <?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="px-6 py-4 bg-gray-50 dark:bg-slate-800/50 border-t border-gray-100 dark:border-slate-800 flex justify-end items-center">
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-400 mr-3">Order Total</span>
                                <span class="text-lg font-extrabold text-gray-900 dark:text-white">RM <?php echo number_format($order_total, 2); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        function updateCartBadge() {
            // Logged-in users get their count server-rendered from the DB
            // cart (includes/navbar-customer.php) - don't stomp it here.
            if (window.IMVIDIA_LOGGED_IN) return;

            let cart = JSON.parse(localStorage.getItem(window.IMVIDIA_CART_KEY)) || [];
            const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 1), 0);

            const badge = document.getElementById('cart-badge');
            if (badge) {
                badge.innerText = totalItems;
            }
        }

        document.addEventListener('DOMContentLoaded', updateCartBadge);
    </script>

</body>
</html>
