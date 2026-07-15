<?php

require_once __DIR__ . '/../db/session.php';

function requireCustomerLogin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
        header("Location: login.php");
        exit();
    }
}

function requireAdminLogin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        header("Location: login.php");
        exit();
    }
}

function getSessionUser(array $fields, ?string $requiredRole = null): ?array {
    global $conn;

    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    if ($requiredRole !== null && ($_SESSION['user_role'] ?? null) !== $requiredRole) {
        return null;
    }

    $user_id = mysqli_real_escape_string($conn, $_SESSION['user_id']);
    $fieldList = implode(', ', $fields);
    $query = "SELECT {$fieldList} FROM users WHERE id = '$user_id' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }

    return null;
}

function checkCustomerOrGuest() {
    $user = getSessionUser(['id', 'first_name', 'last_name', 'email', 'profile_picture', 'phone', 'address_street', 'address_city', 'address_state', 'address_zip'], 'customer');
    if (!$user) {
        return [
            'is_logged_in' => false,
            'user_id' => null,
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'profile_picture' => '',
            'phone' => '',
            'address_street' => '',
            'address_city' => '',
            'address_state' => '',
            'address_zip' => ''
        ];
    }

    return [
        'is_logged_in' => true,
        'user_id' => $user['id'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'],
        'profile_picture' => $user['profile_picture'],
        'phone' => $user['phone'],
        'address_street' => $user['address_street'],
        'address_city' => $user['address_city'],
        'address_state' => $user['address_state'],
        'address_zip' => $user['address_zip']
    ];
}

function getAdminUserData() {
    return getSessionUser(['id', 'first_name', 'last_name', 'profile_picture']) ?: [
        'id' => null,
        'first_name' => '',
        'last_name' => '',
        'profile_picture' => ''
    ];
}

function checkAdminOrGuest() {
    $user = getSessionUser(['id', 'first_name', 'last_name', 'profile_picture'], 'admin');
    if (!$user) {
        return [
            'is_logged_in' => false,
            'is_admin' => false,
            'user_id' => null,
            'first_name' => '',
            'last_name' => '',
            'profile_picture' => ''
        ];
    }

    return [
        'is_logged_in' => true,
        'is_admin' => true,
        'user_id' => $user['id'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'profile_picture' => $user['profile_picture']
    ];
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
