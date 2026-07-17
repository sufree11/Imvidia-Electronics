<?php

require_once __DIR__ . '/db-helpers.php';

/**
 * Creates the cart_items table on first use. Safe to call on every request:
 * CREATE TABLE IF NOT EXISTS is a no-op once the table exists, same pattern
 * as ensureOrdersSchemaV2() in order-helpers.php and the sessions table in
 * db/session.php.
 */
function ensureCartSchema() {
    global $conn;

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS cart_items (
        cart_item_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        selected TINYINT(1) NOT NULL DEFAULT 1,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_product (user_id, product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// fetch cart items with product
function getCartItemsForUser($user_id) {
    return getRows(
        "SELECT ci.product_id, ci.quantity, ci.selected, p.name, p.price, p.image_url, p.stock_quantity
         FROM cart_items ci
         JOIN product p ON p.product_id = ci.product_id
         WHERE ci.user_id = ?
         ORDER BY ci.added_at ASC",
        [$user_id],
        'i'
    );
}

// total quantity in cart
function getCartCountForUser($user_id) {
    return (int) getValue("SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE user_id = ?", [$user_id], 'i');
}

// add or bump cart item
function addOrIncrementCartItem($user_id, $product_id, $qty = 1) {
    $existing = getRow("SELECT cart_item_id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?", [$user_id, $product_id], 'ii');
    $stock = (int) getValue("SELECT stock_quantity FROM product WHERE product_id = ?", [$product_id], 'i');

    if ($existing) {
        $new_qty = min($stock, (int) $existing['quantity'] + $qty);
        return executeStatement("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?", [$new_qty, $existing['cart_item_id']], 'ii');
    }

    $new_qty = min($stock, max(1, $qty));
    return executeStatement("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)", [$user_id, $product_id, $new_qty], 'iii');
}

// set cart item quantity
function setCartItemQuantity($user_id, $product_id, $qty) {
    if ($qty <= 0) {
        return removeCartItem($user_id, $product_id);
    }

    $stock = (int) getValue("SELECT stock_quantity FROM product WHERE product_id = ?", [$product_id], 'i');
    $qty = min($stock, $qty);

    return executeStatement("UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?", [$qty, $user_id, $product_id], 'iii');
}

// remove item from cart
function removeCartItem($user_id, $product_id) {
    return executeStatement("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?", [$user_id, $product_id], 'ii');
}

// toggle single item selection
function setCartItemSelected($user_id, $product_id, $selected) {
    return executeStatement("UPDATE cart_items SET selected = ? WHERE user_id = ? AND product_id = ?", [$selected ? 1 : 0, $user_id, $product_id], 'iii');
}

// toggle all item selections
function setAllCartItemsSelected($user_id, $selected) {
    return executeStatement("UPDATE cart_items SET selected = ? WHERE user_id = ?", [$selected ? 1 : 0, $user_id], 'ii');
}

/**
 * Folds a guest's localStorage cart (added before logging in) into the
 * account's DB cart. Items only carry a product name/quantity (localStorage
 * items have no product_id), so each is resolved by name the same way
 * checkout.php's saveOrder() already does.
 */
function mergeGuestCartItems($user_id, array $items) {
    foreach ($items as $item) {
        $name = trim((string) ($item['name'] ?? ''));
        $qty = max(1, (int) ($item['quantity'] ?? 1));

        if ($name === '') {
            continue;
        }

        $product = getRow("SELECT product_id FROM product WHERE name = ? LIMIT 1", [$name], 's');
        if (!$product) {
            continue;
        }

        addOrIncrementCartItem($user_id, (int) $product['product_id'], $qty);
    }
}
