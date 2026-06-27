<?php
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

requireAdminLogin();

$admin_data = getAdminUserData();

function getOrderDateColumnExpression() {
    global $conn;

    $candidates = ['order_date', 'order date'];
    foreach ($candidates as $column) {
        $column_safe = mysqli_real_escape_string($conn, $column);
        $result = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE '$column_safe'");
        if ($result && mysqli_num_rows($result) > 0) {
            return "o.`$column`";
        }
    }

    return 'NULL';
}

function getOrderProgressClass($progress) {
    $normalized = strtolower(trim((string) $progress));
    if ($normalized === 'delivered' || $normalized === 'completed') {
        return 'bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-800';
    }
    if ($normalized === 'cancelled' || $normalized === 'failed') {
        return 'bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-800';
    }
    if ($normalized === 'processing' || $normalized === 'pending') {
        return 'bg-yellow-50 text-yellow-700 border border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-800';
    }

    return 'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800';
}

$order_date_expr = getOrderDateColumnExpression();
$total_revenue = 0;
$total_orders = 0;
$total_products = 0;
$recent_orders = [];

$stats_query = "SELECT COUNT(*) AS total_orders, COALESCE(SUM(p.price), 0) AS total_revenue
                FROM orders o
                LEFT JOIN product p ON p.product_id = o.product_id";
$stats_result = mysqli_query($conn, $stats_query);
if ($stats_result && mysqli_num_rows($stats_result) > 0) {
    $stats = mysqli_fetch_assoc($stats_result);
    $total_orders = (int) ($stats['total_orders'] ?? 0);
    $total_revenue = (float) ($stats['total_revenue'] ?? 0);
}

$products_result = mysqli_query($conn, "SELECT COUNT(*) AS total_products FROM product");
if ($products_result && mysqli_num_rows($products_result) > 0) {
    $product_stats = mysqli_fetch_assoc($products_result);
    $total_products = (int) ($product_stats['total_products'] ?? 0);
}

$order_date_select = $order_date_expr . ' AS order_date';
$order_by_clause = $order_date_expr !== 'NULL' ? 'order_date DESC, o.order_id DESC' : 'o.order_id DESC';

$recent_orders_query = "SELECT o.order_id, o.user_id, o.product_id, o.payment_method, o.delivery_time, o.order_progress,
                        $order_date_select,
                        u.first_name, u.last_name,
                        p.name AS product_name, p.price AS product_price
                        FROM orders o
                        LEFT JOIN users u ON u.id = o.user_id
                        LEFT JOIN product p ON p.product_id = o.product_id
                        ORDER BY $order_by_clause
                        LIMIT 10";

$recent_orders_result = mysqli_query($conn, $recent_orders_query);
if ($recent_orders_result && mysqli_num_rows($recent_orders_result) > 0) {
    while ($order = mysqli_fetch_assoc($recent_orders_result)) {
        $recent_orders[] = $order;
    }
}

$admin_order_count = $total_orders;
?>

<!DOCTYPE html>
<html>
<head>
    
    <title>Dashboard - AdminPanel</title>
    <?php include 'includes/head.php'; ?>
</head>

<body class="bg-fixed bg-gray-50 text-gray-800 flex h-screen overflow-hidden dark:bg-slate-950 dark:text-gray-100" style="background-image: radial-gradient(circle, rgba(156, 163, 175, 0.2) 2.5px, transparent 2.5px); background-size: 40px 40px;">

    <?php include 'includes/navbar-admin.php'; ?>

        <main class="flex-1 overflow-y-auto p-6 lg:p-8">
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
                                            $customer_name = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
                                            if ($customer_name === '') {
                                                $customer_name = 'User #' . (int) $order['user_id'];
                                            }

                                            $order_date = !empty($order['order_date']) ? date('d M Y, h:i A', strtotime($order['order_date'])) : 'N/A';
                                            $payment_method = !empty($order['payment_method']) ? $order['payment_method'] : 'N/A';
                                            $delivery_time = !empty($order['delivery_time']) ? $order['delivery_time'] : 'N/A';
                                            $order_progress = !empty($order['order_progress']) ? $order['order_progress'] : 'Pending';
                                            $product_name = !empty($order['product_name']) ? $order['product_name'] : ('Product #' . (int) $order['product_id']);
                                        ?>
                                        <tr class="border-b border-gray-100 dark:border-slate-800">
                                            <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">#<?php echo (int) $order['order_id']; ?></td>
                                            <td class="px-6 py-4"><?php echo htmlspecialchars($customer_name); ?></td>
                                            <td class="px-6 py-4"><?php echo htmlspecialchars($product_name); ?></td>
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