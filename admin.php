<?php
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
require_once 'includes/order-helpers.php';

requireAdminLogin();

$admin_data = getAdminUserData();

ensureOrdersSchemaV2();

// load dashboard stat totals
$total_products = 0;

$total_orders = (int) getValue("SELECT COUNT(*) FROM orders");
$total_revenue = (float) getValue("SELECT COALESCE(SUM(quantity * unit_price), 0) FROM order_items");

$products_result = mysqli_query($conn, "SELECT COUNT(*) AS total_products FROM product");
if ($products_result && mysqli_num_rows($products_result) > 0) {
    $product_stats = mysqli_fetch_assoc($products_result);
    $total_products = (int) ($product_stats['total_products'] ?? 0);
}

$recent_orders = getOrdersForAdmin();
$recent_orders = array_slice($recent_orders, 0, 10);

$admin_order_count = $total_orders;
?>

<!DOCTYPE html>
<html>
<head>
    
    <title>Dashboard - AdminPanel</title>
    <?php include 'includes/head.php'; ?>
</head>

<body class="bg-gray-50 text-gray-800 flex h-screen overflow-hidden dark:bg-slate-950 dark:text-gray-100">

    <?php include 'includes/navbar-admin.php'; ?>

        <main class="flex-1 overflow-y-auto p-6 lg:p-8 animate-fade-in-up">
            <div class="max-w-7xl mx-auto">
                
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
                    <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Live business metrics from your store database.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    
                    <div class="bg-white dark:bg-slate-900 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 flex items-center">
                        <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-slate-800 flex items-center justify-center text-gray-400 mr-4">
                            <i class="fa-solid fa-sack-dollar text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Total Revenue</p>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">RM <?php echo number_format($total_revenue, 2); ?></h3>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-slate-900 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 flex items-center">
                        <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-slate-800 flex items-center justify-center text-gray-400 mr-4">
                            <i class="fa-solid fa-cart-arrow-down text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Total Orders</p>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo number_format($total_orders); ?></h3>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-900 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 flex items-center">
                        <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-slate-800 flex items-center justify-center text-gray-400 mr-4">
                            <i class="fa-solid fa-box text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Total Products</p>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo number_format($total_products); ?></h3>
                        </div>
                    </div>
                </div>

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
                                    <th class="px-6 py-4">Order Date</th>
                                    <th class="px-6 py-4">Payment</th>
                                    <th class="px-6 py-4">Delivery</th>
                                    <th class="px-6 py-4">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_orders)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-16 text-center">
                                            <div class="flex flex-col items-center justify-center">
                                                <i class="fa-solid fa-clipboard-list text-5xl text-gray-300 dark:text-slate-700 mb-4"></i>
                                                <h3 class="text-xl font-bold text-gray-500 dark:text-gray-400">No orders yet</h3>
                                                <p class="text-gray-400 dark:text-gray-500 mt-2 text-sm">New orders will appear here once customers start purchasing.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <?php
                                            $shipping_name = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
                                            $customer_name = trim(($order['account_first_name'] ?? '') . ' ' . ($order['account_last_name'] ?? ''));
                                            if ($customer_name === '') {
                                                $customer_name = empty($order['user_id'])
                                                    ? ($shipping_name !== '' ? $shipping_name . ' (Guest)' : 'Guest')
                                                    : 'User #' . (int) $order['user_id'];
                                            }

                                            $order_date = !empty($order['order_date']) ? date('d M Y, h:i A', strtotime($order['order_date'])) : 'N/A';
                                            $payment_method = !empty($order['payment_method']) ? $order['payment_method'] : 'N/A';
                                            $delivery_time = !empty($order['delivery_time']) ? $order['delivery_time'] : 'N/A';
                                            $order_progress = !empty($order['order_progress']) ? $order['order_progress'] : 'Pending';
                                            $item_summary = count($order['items']) . ' item' . (count($order['items']) === 1 ? '' : 's');
                                            if (count($order['items']) === 1) {
                                                $item_summary = $order['items'][0]['product_name'] ?? $item_summary;
                                            }
                                        ?>
                                        <tr class="border-b border-gray-100 dark:border-slate-800">
                                            <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">#<?php echo (int) $order['order_id']; ?></td>
                                            <td class="px-6 py-4"><?php echo htmlspecialchars($customer_name); ?></td>
                                            <td class="px-6 py-4"><?php echo htmlspecialchars($item_summary); ?></td>
                                            <td class="px-6 py-4"><?php echo htmlspecialchars($order_date); ?></td>
                                            <td class="px-6 py-4"><?php echo htmlspecialchars($payment_method); ?></td>
                                            <td class="px-6 py-4"><?php echo htmlspecialchars($delivery_time); ?></td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?php echo getOrderProgressClass($order_progress); ?>">
                                                    <?php echo htmlspecialchars($order_progress); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

</body>
</html>