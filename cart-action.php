<?php
require_once 'db/session.php';
require_once 'includes/auth.php';
require_once 'includes/db-helpers.php';
require_once 'includes/cart-helpers.php';

header('Content-Type: application/json');

if (($_SESSION['user_role'] ?? '') !== 'customer' || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'require_login' => true]);
    exit();
}

ensureCartSchema();

$user_id = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add':
        $product_id = (int) ($_POST['product_id'] ?? 0);
        $qty = max(1, (int) ($_POST['quantity'] ?? 1));
        if ($product_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product.']);
            exit();
        }
        addOrIncrementCartItem($user_id, $product_id, $qty);
        break;

    case 'set_quantity':
        $product_id = (int) ($_POST['product_id'] ?? 0);
        $qty = (int) ($_POST['quantity'] ?? 0);
        if ($product_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product.']);
            exit();
        }
        setCartItemQuantity($user_id, $product_id, $qty);
        break;

    case 'remove':
        $product_id = (int) ($_POST['product_id'] ?? 0);
        if ($product_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product.']);
            exit();
        }
        removeCartItem($user_id, $product_id);
        break;

    case 'set_selected':
        $product_id = (int) ($_POST['product_id'] ?? 0);
        $selected = !empty($_POST['selected']) && $_POST['selected'] !== 'false';
        if ($product_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product.']);
            exit();
        }
        setCartItemSelected($user_id, $product_id, $selected);
        break;

    case 'set_all_selected':
        $selected = !empty($_POST['selected']) && $_POST['selected'] !== 'false';
        setAllCartItemsSelected($user_id, $selected);
        break;

    case 'merge_guest':
        $items_json = $_POST['items'] ?? '[]';
        $items = json_decode($items_json, true);
        if (is_array($items)) {
            mergeGuestCartItems($user_id, $items);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        exit();
}

echo json_encode([
    'success' => true,
    'cart' => getCartItemsForUser($user_id),
    'cart_count' => getCartCountForUser($user_id),
]);
