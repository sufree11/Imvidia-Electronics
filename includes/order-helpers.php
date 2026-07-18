<?php

require_once __DIR__ . '/db-helpers.php';

/**
 * mysqli throws on SQL errors by default (PHP 8.1+). Schema migration is
 * best-effort and idempotent by design, so a single failed step (e.g. a DB
 * user without ALTER privilege, or a statement that's already been applied
 * in a way this code doesn't recognize) should be logged and skipped, not
 * take down the whole page with an uncaught exception.
 */
function safeSchemaQuery($sql) {
    global $conn;

    try {
        return mysqli_query($conn, $sql);
    } catch (\mysqli_sql_exception $e) {
        error_log('Schema migration step failed: ' . $e->getMessage() . ' | SQL: ' . $sql);
        return false;
    }
}

/**
 * Evolves the orders schema from "one row per unit purchased" to a proper
 * header (orders) + line-item (order_items) model, and backfills any rows
 * left over from the old flat schema. Safe to call on every request: each
 * step is a no-op once already applied, and the backfill only ever touches
 * orders rows that don't have order_items yet.
 */
function ensureOrdersSchemaV2() {
    global $conn;

    safeSchemaQuery("CREATE TABLE IF NOT EXISTS order_items (
        order_item_id INT(11) NOT NULL AUTO_INCREMENT,
        order_id INT(11) NOT NULL,
        product_id INT(11) NOT NULL,
        quantity INT(11) NOT NULL DEFAULT 1,
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        PRIMARY KEY (order_item_id),
        KEY idx_order_id (order_id),
        KEY idx_product_id (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Some deployments have this column named `order date` (with a space)
    // instead of `order_date`. Standardize it so every query in this file
    // (and any hardcoded `order_date` reference elsewhere) can rely on it.
    // Uses an exact-match WHERE (not LIKE) because `_` is a single-character
    // wildcard in SQL LIKE patterns and would falsely match "order date".
    $legacy_col = safeSchemaQuery("SHOW COLUMNS FROM orders WHERE Field = 'order date'");
    $canonical_col = safeSchemaQuery("SHOW COLUMNS FROM orders WHERE Field = 'order_date'");
    if ($legacy_col && mysqli_num_rows($legacy_col) > 0 && (!$canonical_col || mysqli_num_rows($canonical_col) === 0)) {
        safeSchemaQuery("ALTER TABLE orders CHANGE COLUMN `order date` `order_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }

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
        // Extra context alongside the payment_method code ('card'/'fpx'/'ewallet'):
        // masked card last 4, or the chosen bank/e-wallet provider name.
        'payment_detail' => 'VARCHAR(100) NULL DEFAULT NULL',
    ];
    foreach ($new_columns as $column => $definition) {
        $safe = mysqli_real_escape_string($conn, $column);
        $check = safeSchemaQuery("SHOW COLUMNS FROM orders LIKE '$safe'");
        if ($check && mysqli_num_rows($check) === 0) {
            safeSchemaQuery("ALTER TABLE orders ADD COLUMN `$column` $definition");
        }
    }

    // Guest checkouts have no account to attach to - allow a NULL user_id.
    // Reuses the column's own reported type (rather than assuming INT) so
    // this doesn't fail against a BIGINT/FK-constrained column.
    $user_id_col = safeSchemaQuery("SHOW COLUMNS FROM orders LIKE 'user_id'");
    if ($user_id_col && mysqli_num_rows($user_id_col) > 0) {
        $col_info = mysqli_fetch_assoc($user_id_col);
        if (strtoupper($col_info['Null']) === 'NO') {
            safeSchemaQuery("ALTER TABLE orders MODIFY user_id {$col_info['Type']} NULL");
        }
    }

    // The legacy schema required product_id directly on `orders`. Orders are
    // now header-only, so relax that constraint if it's still NOT NULL.
    // Same reasoning as above: preserve the existing type exactly.
    $product_id_col = safeSchemaQuery("SHOW COLUMNS FROM orders LIKE 'product_id'");
    if ($product_id_col && mysqli_num_rows($product_id_col) > 0) {
        $col_info = mysqli_fetch_assoc($product_id_col);
        if (strtoupper($col_info['Null']) === 'NO') {
            safeSchemaQuery("ALTER TABLE orders MODIFY product_id {$col_info['Type']} NULL");
        }

        // Backfill: any orders row without order_items yet is a leftover
        // from the old "one row per unit" model - turn it into a single
        // line item so it shows up correctly under the new schema.
        $unmigrated = safeSchemaQuery("
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
                $price_result = safeSchemaQuery("SELECT price FROM product WHERE product_id = $product_id LIMIT 1");
                if ($price_result && mysqli_num_rows($price_result) > 0) {
                    $price = (float) mysqli_fetch_assoc($price_result)['price'];
                }

                safeSchemaQuery("INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES ($order_id, $product_id, 1, $price)");
            }
        }
    }
}

// resolve order date column
function getOrderDateColumnExpression() {
    global $conn;

    // Exact-match WHERE, not LIKE: `_` is a single-character wildcard in SQL
    // LIKE patterns, so `LIKE 'order_date'` would also match an `order date`
    // (space) column and report a false positive.
    $candidates = ['order_date', 'order date'];
    foreach ($candidates as $column) {
        $column_safe = mysqli_real_escape_string($conn, $column);
        $result = mysqli_query($conn, "SHOW COLUMNS FROM orders WHERE Field = '$column_safe'");
        if ($result && mysqli_num_rows($result) > 0) {
            return "o.`$column`";
        }
    }

    return 'NULL';
}

// fetch line items for order
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

// sum order line totals
function getOrderTotal(array $order) {
    $total = 0.0;
    foreach ($order['items'] as $item) {
        $total += (float) $item['unit_price'] * (int) $item['quantity'];
    }
    return $total;
}

/**
 * Fetches a single order (all columns, for receipt/PDF reconstruction) with
 * its line items attached, or null if it doesn't exist.
 */
function getOrderById($order_id) {
    $order_date_expr = getOrderDateColumnExpression();

    $order = getRow(
        "SELECT o.*, $order_date_expr AS order_date
         FROM orders o
         WHERE o.order_id = ?
         LIMIT 1",
        [$order_id],
        'i'
    );

    if (!$order) {
        return null;
    }

    $order['items'] = getOrderItemsForOrder((int) $order['order_id']);

    return $order;
}

/**
 * A stable, deterministic receipt/invoice number derived from the order's own
 * date and id - so it's identical every time it's regenerated (e.g. for a PDF
 * re-download), without needing a separate stored column.
 */
function getOrderReceiptNumber(array $order) {
    $timestamp = strtotime($order['order_date'] ?? 'now') ?: time();
    return 'INV' . date('YmdHis', $timestamp) . '-' . (int) $order['order_id'];
}

/**
 * Whether the current session is allowed to view/download this order's
 * receipt: the order's own account owner, an admin, or - for guest checkouts
 * with no account - the same session that just completed that checkout
 * (tracked in $_SESSION['receipt_access'], set at checkout success time).
 */
function canAccessOrderReceipt(?array $order, array $viewer) {
    if (!$order) {
        return false;
    }

    if (!empty($viewer['is_admin'])) {
        return true;
    }

    if (!empty($viewer['is_logged_in']) && $order['user_id'] !== null && (int) $order['user_id'] === (int) $viewer['user_id']) {
        return true;
    }

    $granted = $_SESSION['receipt_access'] ?? [];
    return in_array((int) $order['order_id'], $granted, true);
}

/**
 * Marks an order's receipt as viewable by the current session - used right
 * after checkout so a guest (no account) can still open/download their own
 * receipt without being logged in. Capped so the session array can't grow
 * unbounded over a long browsing session.
 */
function grantReceiptAccess($order_id) {
    $granted = $_SESSION['receipt_access'] ?? [];
    $granted[] = (int) $order_id;
    $_SESSION['receipt_access'] = array_slice(array_unique($granted), -20);
}

/**
 * FPX bank and e-wallet provider codes -> display labels. Kept as the single
 * source of truth on the PHP side; checkout.php's inline JS mirrors these
 * same codes/labels for the client-side loading screen.
 */
function getFpxBankOptions() {
    return [
        'maybank' => 'Maybank2U',
        'cimb' => 'CIMB Clicks',
        'public' => 'Public Bank',
        'rhb' => 'RHB Now',
        'hongleong' => 'Hong Leong Connect',
        'bankislam' => 'Bank Islam',
        'ambank' => 'AmBank',
    ];
}

function getEwalletOptions() {
    return [
        'tng' => "Touch 'n Go eWallet",
        'shopee' => 'ShopeePay',
        'boost' => 'Boost',
        'grab' => 'GrabPay',
        'alipay' => 'Alipay+',
    ];
}

/**
 * Human-readable "Payment Method" line for the receipt (page + PDF), e.g.
 * "Credit / Debit Card" / "**** 4242", or "FPX Online Banking" / "Maybank2U".
 * Returns [label, detail] - detail is '' when there's nothing to show.
 * Uses plain ASCII asterisks (not a Unicode bullet) for the card mask so it
 * renders correctly in the PDF's non-embedded base font, not just in HTML.
 */
function formatPaymentMethodDisplay($payment_method, $payment_detail) {
    switch ($payment_method) {
        case 'card':
            $detail = $payment_detail !== '' ? '**** ' . $payment_detail : '';
            return ['Credit / Debit Card', $detail];
        case 'fpx':
            $banks = getFpxBankOptions();
            return ['FPX Online Banking', $banks[$payment_detail] ?? $payment_detail];
        case 'ewallet':
            $wallets = getEwalletOptions();
            return ['E-Wallet', $wallets[$payment_detail] ?? $payment_detail];
        default:
            return [ucfirst((string) $payment_method), (string) $payment_detail];
    }
}
