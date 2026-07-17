<?php
require_once 'includes/auth.php';
require_once 'includes/db-helpers.php';
require_once 'includes/helpers.php';
require_once 'includes/order-helpers.php';

requireAdminLogin();

$admin_data = getAdminUserData();

ensureOrdersSchemaV2();

$message = '';
$msg_type = '';

$allowed_statuses = ['Pending', 'Processing', 'Shipped', 'Delivered'];

// handle order cancel and status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfOrFail();

    $action = $_POST['action'] ?? '';
    $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;

    if ($order_id <= 0) {
        $message = 'Invalid order.';
        $msg_type = 'error';
    } elseif ($action === 'cancel') {
        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') {
            $message = 'A cancellation reason is required.';
            $msg_type = 'error';
        } elseif (executeStatement(
            "UPDATE orders SET order_progress = 'Cancelled', cancel_reason = ? WHERE order_id = ?",
            [$reason, $order_id],
            'si'
        )) {
            $message = "Order #$order_id has been cancelled.";
            $msg_type = 'success';
        } else {
            $message = 'Failed to cancel order.';
            $msg_type = 'error';
        }
    } elseif ($action === 'update_status') {
        $new_status = $_POST['order_progress'] ?? '';
        if (!in_array($new_status, $allowed_statuses, true)) {
            $message = 'Invalid status.';
            $msg_type = 'error';
        } elseif (executeStatement(
            "UPDATE orders SET order_progress = ?, cancel_reason = NULL WHERE order_id = ?",
            [$new_status, $order_id],
            'si'
        )) {
            $message = "Order #$order_id updated to \"$new_status\".";
            $msg_type = 'success';
        } else {
            $message = 'Failed to update order status.';
            $msg_type = 'error';
        }
    }
}

// load filtered order list
$status_filter = $_GET['status'] ?? 'all';
$allowed_filters = ['all', 'pending', 'processing', 'shipped', 'delivered', 'cancelled'];
if (!in_array($status_filter, $allowed_filters, true)) {
    $status_filter = 'all';
}

$search = trim($_GET['search'] ?? '');

$orders = getOrdersForAdmin($status_filter, $search);

$admin_order_count = (int) getValue("SELECT COUNT(*) FROM orders");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Orders - AdminPanel</title>
    <?php include 'includes/head.php'; ?>
</head>
<body class="bg-gray-50 text-gray-800 flex h-screen overflow-hidden dark:bg-slate-950 dark:text-gray-100">
    <?php include 'includes/navbar-admin.php'; ?>

        <main class="flex-1 overflow-y-auto p-6 lg:p-8 animate-fade-in-up">
            <div class="max-w-7xl mx-auto">

                <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Orders Management</h1>
                        <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">View, update, and cancel customer orders.</p>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="mb-6 px-4 py-3 rounded-lg text-sm font-medium text-center <?php echo $msg_type === 'success' ? 'bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : 'bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <form method="GET" class="bg-white dark:bg-slate-900 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 mb-6 flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by order ID, customer, or product..." class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm text-sm">
                    </div>
                    <div>
                        <select name="status" class="w-full sm:w-48 px-4 py-2.5 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm text-sm appearance-none cursor-pointer">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <button type="submit" class="px-6 py-2.5 bg-imvidia hover:bg-imvidia-dark text-white rounded-xl font-semibold text-sm transition shadow-sm">
                        <i class="fa-solid fa-filter mr-2"></i>Filter
                    </button>
                    <?php if ($status_filter !== 'all' || $search !== ''): ?>
                        <a href="admin-orders.php" class="px-6 py-2.5 bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-gray-300 rounded-xl font-semibold text-sm transition shadow-sm text-center hover:bg-gray-200 dark:hover:bg-slate-700">
                            Reset
                        </a>
                    <?php endif; ?>
                </form>

                <?php if (empty($orders)): ?>
                    <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 py-16 text-center">
                        <i class="fa-solid fa-clipboard-list text-5xl text-gray-300 dark:text-slate-700 mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-500 dark:text-gray-400">No orders found</h3>
                        <p class="text-gray-400 dark:text-gray-500 mt-2 text-sm">Try adjusting your search or filters.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($orders as $order): ?>
                            <?php
                                $shipping_name = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
                                $customer_name = trim(($order['account_first_name'] ?? '') . ' ' . ($order['account_last_name'] ?? ''));
                                if ($customer_name === '') {
                                    $customer_name = empty($order['user_id'])
                                        ? ($shipping_name !== '' ? $shipping_name . ' (Guest)' : 'Guest')
                                        : 'User #' . (int) $order['user_id'];
                                }

                                $order_date = !empty($order['order_date']) ? date('d M Y, h:i A', strtotime($order['order_date'])) : 'N/A';
                                $delivery_time = !empty($order['delivery_time']) ? date('d M Y', strtotime($order['delivery_time'])) : 'N/A';
                                $payment_method = !empty($order['payment_method']) ? ucfirst($order['payment_method']) : 'N/A';
                                $order_progress = !empty($order['order_progress']) ? $order['order_progress'] : 'Pending';
                                $is_cancelled = strtolower($order_progress) === 'cancelled';
                                $order_total = getOrderTotal($order);
                            ?>
                            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 overflow-hidden">
                                <div class="px-6 py-4 bg-gray-50 dark:bg-slate-800/50 border-b border-gray-100 dark:border-slate-800 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                                    <div class="flex items-center gap-6 flex-wrap">
                                        <div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-semibold">Order</p>
                                            <p class="text-sm font-bold text-gray-900 dark:text-white">#<?php echo (int) $order['order_id']; ?></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-semibold">Customer</p>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($customer_name); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-semibold">Placed</p>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($order_date); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-semibold">Payment</p>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($payment_method); ?></p>
                                        </div>
                                        <?php if ($shipping_name !== '' || !empty($order['address'])): ?>
                                        <div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-semibold">Ship To</p>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($shipping_name); ?></p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars(trim(($order['address'] ?? '') . ', ' . ($order['city'] ?? ''), ', ')); ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?php echo getOrderProgressClass($order_progress); ?>">
                                            <?php echo htmlspecialchars($order_progress); ?>
                                        </span>
                                        <?php if (!$is_cancelled): ?>
                                            <form method="POST" class="flex items-center gap-1">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="order_id" value="<?php echo (int) $order['order_id']; ?>">
                                                <select name="order_progress" class="px-2 py-1.5 border border-gray-300 dark:border-slate-700 rounded-lg text-xs dark:bg-slate-800 dark:text-white focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia">
                                                    <?php foreach ($allowed_statuses as $status_option): ?>
                                                        <option value="<?php echo $status_option; ?>" <?php echo strcasecmp($order_progress, $status_option) === 0 ? 'selected' : ''; ?>><?php echo $status_option; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="px-2.5 py-1.5 bg-imvidia hover:bg-imvidia-dark text-white rounded-lg text-xs font-semibold transition">
                                                    Update
                                                </button>
                                            </form>
                                            <button type="button" onclick="showCancelModal(<?php echo (int) $order['order_id']; ?>)" class="px-2.5 py-1.5 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 rounded-lg text-xs font-semibold transition">
                                                Cancel
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($is_cancelled && !empty($order['cancel_reason'])): ?>
                                    <div class="px-6 py-3 bg-red-50 dark:bg-red-900/10 border-b border-red-100 dark:border-red-900/30 text-sm text-red-600 dark:text-red-400">
                                        <i class="fa-solid fa-circle-info mr-1"></i> Cancellation reason: <?php echo htmlspecialchars($order['cancel_reason']); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="divide-y divide-gray-100 dark:divide-slate-800">
                                    <?php foreach ($order['items'] as $item): ?>
                                        <?php $item_name = !empty($item['product_name']) ? $item['product_name'] : ('Product #' . (int) $item['product_id']); ?>
                                        <div class="px-6 py-3 flex items-center justify-between text-sm">
                                            <span class="text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($item_name); ?> <span class="text-gray-400 dark:text-gray-500">&times; <?php echo (int) $item['quantity']; ?></span></span>
                                            <span class="font-medium text-gray-900 dark:text-white">RM <?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="px-6 py-3 bg-gray-50 dark:bg-slate-800/50 border-t border-gray-100 dark:border-slate-800 flex justify-end items-center">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400 mr-3">Order Total</span>
                                    <span class="text-base font-extrabold text-gray-900 dark:text-white">RM <?php echo number_format($order_total, 2); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <!-- Cancel Order Modal -->
    <div id="cancel-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Cancel Order</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Please provide a reason for cancelling this order. This will be visible in the customer's order history.</p>
            <form method="POST" id="cancel-form">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="order_id" id="cancel-order-id" value="">
                <textarea name="reason" required rows="3" placeholder="e.g. Out of stock, customer requested cancellation..." class="w-full px-4 py-3 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition shadow-sm text-sm"></textarea>
                <div class="flex justify-end gap-3 mt-5">
                    <button type="button" onclick="hideCancelModal()" class="px-5 py-2.5 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-gray-300 rounded-xl font-medium text-sm hover:bg-gray-200 dark:hover:bg-slate-700 transition">
                        Back
                    </button>
                    <button type="submit" class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-semibold text-sm transition">
                        Confirm Cancellation
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showCancelModal(orderId) {
            document.getElementById('cancel-order-id').value = orderId;
            document.getElementById('cancel-modal').classList.remove('hidden');
        }

        function hideCancelModal() {
            document.getElementById('cancel-modal').classList.add('hidden');
            document.getElementById('cancel-form').reset();
        }
    </script>

</body>
</html>
