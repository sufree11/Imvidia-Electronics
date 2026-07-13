<?php

require_once __DIR__ . '/db-helpers.php';

/**
 * Evolves the orders schema from "one row per unit purchased" to a proper
 * header (orders) + line-item (order_items) model, and backfills any rows
 * left over from the old flat schema. Safe to call on every request: each
 * step is a no-op once applied, and the backfill only ever touches orders
 * rows that don't have order_items yet.
 */
function ensureOrdersSchemaV2() {
    global $conn;

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS order_items (
        order_item_id INT(11) NOT NULL AUTO_INCREMENT,
        order_id INT(11) NOT NULL,
        product_id INT(11) NOT NULL,
        quantity INT(11) NOT NULL DEFAULT 1,
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        PRIMARY KEY (order_item_id),
        KEY idx_order_id (order_id),
        KEY idx_product_id (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $new_columns = [
        'cancel_reason' => 'VARCHAR(255) NULL DEFAULT NULL',
        'email' => 'VARCHAR(100) NULL DEFAULT NULL',
        'first_name' => 'VARCHAR(50) NULL DEFAULT NULL',
        'last_name' => 'VARCHAR(50) NULL DEFAULT NULL',
        'phone' => 'VARCHAR(20) NULL DEFAULT NULL',
        'address' => 'VARCHAR(255) NULL DEFAULT NULL',
        'city' => 'VARCHAR(100) NULL DEFAULT NULL',
        'state' => 'VARCHAR(50) NULL DEFAULT NULL',
        'postcode' => 'VARCHAR(20) NULL DEFAULT NULL',
    ];
    foreach ($new_columns as $column => $definition) {
        $safe = mysqli_real_escape_string($conn, $column);
        $check = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE '$safe'");
        if ($check && mysqli_num_rows($check) === 0) {
            mysqli_query($conn, "ALTER TABLE orders ADD COLUMN `$column` $definition");
        }
    }

    // Guest checkouts have no account to attach to - allow a NULL user_id.
    $user_id_col = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'user_id'");
    if ($user_id_col && mysqli_num_rows($user_id_col) > 0) {
        $col_info = mysqli_fetch_assoc($user_id_col);
        if (strtoupper($col_info['Null']) === 'NO') {
            mysqli_query($conn, "ALTER TABLE orders MODIFY user_id INT(11) NULL");
        }
    }

    // The legacy schema required product_id directly on `orders`. Orders are
    // now header-only, so relax that constraint if it's still NOT NULL.
    $product_id_col = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'product_id'");
    if ($product_id_col && mysqli_num_rows($product_id_col) > 0) {
        $col_info = mysqli_fetch_assoc($product_id_col);
        if (strtoupper($col_info['Null']) === 'NO') {
            mysqli_query($conn, "ALTER TABLE orders MODIFY product_id INT(11) NULL");
        }

        // Backfill: any orders row without order_items yet is a leftover
        // from the old "one row per unit" model - turn it into a single
        // line item so it shows up correctly under the new schema.
        $unmigrated = mysqli_query($conn, "
            SELECT o.order_id, o.product_id
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.order_id
            WHERE oi.order_item_id IS NULL AND o.product_id IS NOT NULL
        ");
        if ($unmigrated && mysqli_num_rows($unmigrated) > 0) {
            while ($row = mysqli_fetch_assoc($unmigrated)) {
                $order_id = (int) $row['order_id'];
                $product_id = (int) $row['product_id'];

                $price = 0.0;
                $price_result = mysqli_query($conn, "SELECT price FROM product WHERE product_id = $product_id LIMIT 1");
                if ($price_result && mysqli_num_rows($price_result) > 0) {
                    $price = (float) mysqli_fetch_assoc($price_result)['price'];
                }

                mysqli_query($conn, "INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES ($order_id, $product_id, 1, $price)");
            }
        }
    }
}

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

function getOrderItemsForOrder($order_id) {
    return getRows(
        "SELECT oi.quantity, oi.unit_price, p.product_id, p.name AS product_name, p.image_url
         FROM order_items oi
         LEFT JOIN product p ON p.product_id = oi.product_id
         WHERE oi.order_id = ?
         ORDER BY oi.order_item_id ASC",
        [$order_id],
        'i'
    );
}

/**
 * Fetches a customer's orders (newest first), each with its line items attached.
 */
function getOrdersForUser($user_id) {
    $order_date_expr = getOrderDateColumnExpression();
    $order_by_clause = $order_date_expr !== 'NULL' ? 'order_date DESC, order_id DESC' : 'order_id DESC';

    $orders = getRows(
        "SELECT order_id, user_id, $order_date_expr AS order_date, payment_method, delivery_time, order_progress, cancel_reason
         FROM orders o
         WHERE o.user_id = ?
         ORDER BY $order_by_clause",
        [$user_id],
        'i'
    );

    foreach ($orders as &$order) {
        $order['items'] = getOrderItemsForOrder((int) $order['order_id']);
    }
    unset($order);

    return $orders;
}

/**
 * Fetches orders for the admin panel, each with its line items attached,
 * with optional status/search filtering applied at the header + item level.
 */
function getOrdersForAdmin($status_filter = 'all', $search = '') {
    $order_date_expr = getOrderDateColumnExpression();
    $order_by_clause = $order_date_expr !== 'NULL' ? 'order_date DESC, order_id DESC' : 'order_id DESC';

    $where_clauses = [];
    $params = [];
    $types = '';

    if ($status_filter !== 'all') {
        $where_clauses[] = 'LOWER(o.order_progress) = ?';
        $params[] = $status_filter;
        $types .= 's';
    }

    if ($search !== '') {
        $where_clauses[] = "(o.order_id = ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR o.first_name LIKE ? OR o.last_name LIKE ? OR EXISTS (
            SELECT 1 FROM order_items oi2 LEFT JOIN product p2 ON p2.product_id = oi2.product_id
            WHERE oi2.order_id = o.order_id AND p2.name LIKE ?
        ))";
        $params[] = ctype_digit($search) ? (int) $search : 0;
        $types .= 'i';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= 'sssss';
    }

    $where_sql = !empty($where_clauses) ? ' WHERE ' . implode(' AND ', $where_clauses) : '';

    $orders = getRows(
        "SELECT o.order_id, o.user_id, $order_date_expr AS order_date, o.payment_method, o.delivery_time,
                o.order_progress, o.cancel_reason, o.email, o.first_name, o.last_name, o.phone,
                o.address, o.city, o.state, o.postcode,
                u.first_name AS account_first_name, u.last_name AS account_last_name
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         $where_sql
         ORDER BY $order_by_clause",
        $params,
        $types
    );

    foreach ($orders as &$order) {
        $order['items'] = getOrderItemsForOrder((int) $order['order_id']);
    }
    unset($order);

    return $orders;
}

function getOrderTotal(array $order) {
    $total = 0.0;
    foreach ($order['items'] as $item) {
        $total += (float) $item['unit_price'] * (int) $item['quantity'];
    }
    return $total;
}
