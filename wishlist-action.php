<?php
define('AJAX_ENDPOINT', true);
require_once 'db/session.php';
require_once 'includes/auth.php';
require_once 'includes/db-helpers.php';
require_once 'includes/helpers.php';

header('Content-Type: application/json');

if (($_SESSION['user_role'] ?? '') !== 'customer' || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'require_login' => true]);
    exit();
}

requireCsrfOrFail();

$user_id = (int) $_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit();
}

// toggle product in wishlist
$existing = getRow("SELECT wishlist_id FROM wishlist WHERE user_id = ? AND product_id = ?", [$user_id, $product_id], 'ii');

if ($existing) {
    $ok = executeStatement("DELETE FROM wishlist WHERE wishlist_id = ?", [$existing['wishlist_id']], 'i');
    $in_wishlist = false;
} else {
    $ok = executeStatement("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)", [$user_id, $product_id], 'ii');
    $in_wishlist = true;
}

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
    exit();
}

// return updated wishlist state
$wishlist_count = (int) getValue("SELECT COUNT(*) FROM wishlist WHERE user_id = ?", [$user_id], 'i');

echo json_encode([
    'success' => true,
    'in_wishlist' => $in_wishlist,
    'wishlist_count' => $wishlist_count,
]);
