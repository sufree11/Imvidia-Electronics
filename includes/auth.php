<?php
/**
 * Authentication Middleware
 * Provides centralized auth checks to eliminate code duplication
 * 
 * Functions:
 * - requireCustomerLogin() - Redirect to login if not a customer
 * - requireAdminLogin() - Redirect to login if not an admin
 * - checkCustomerOrGuest() - Optional auth (allows guests)
 */

require_once __DIR__ . '/../db/session.php';

/**
 * Require customer login
 * Redirects to login.php if user is not authenticated as customer
 */
function requireCustomerLogin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
        header("Location: login.php");
        exit();
    }
}

/**
 * Require admin login
 * Redirects to login.php if user is not authenticated as admin
 */
function requireAdminLogin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        header("Location: login.php");
        exit();
    }
}

/**
 * Check customer or guest access
 * Sets $is_logged_in flag and loads user data if authenticated
 * Returns array with user data or empty array if guest
 */
function checkCustomerOrGuest() {
    global $conn;
    
    $user_data = [
        'is_logged_in' => false,
        'user_id' => null,
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'profile_picture' => ''
    ];
    
    if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'customer') {
        $user_id = mysqli_real_escape_string($conn, $_SESSION['user_id']);
        $query = "SELECT id, first_name, last_name, email, profile_picture FROM users WHERE id = '$user_id' LIMIT 1";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $user_data = [
                'is_logged_in' => true,
                'user_id' => $row['id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'profile_picture' => $row['profile_picture']
            ];
        }
    }
    
    return $user_data;
}

/**
 * Get current admin user data
 * Returns admin info for header/navbar display
 */
function getAdminUserData() {
    global $conn;
    
    $user_data = [
        'id' => null,
        'first_name' => '',
        'last_name' => '',
        'profile_picture' => ''
    ];
    
    if (isset($_SESSION['user_id'])) {
        $user_id = mysqli_real_escape_string($conn, $_SESSION['user_id']);
        $query = "SELECT id, first_name, last_name, profile_picture FROM users WHERE id = '$user_id' LIMIT 1";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $user_data = mysqli_fetch_assoc($result);
        }
    }
    
    return $user_data;
}

/**
 * Check if admin is logged in or return guest
 * Returns admin info if logged in as admin, otherwise returns guest array
 * Allows admin to visit customer pages without logging out
 */
function checkAdminOrGuest() {
    global $conn;
    
    $user_data = [
        'is_logged_in' => false,
        'is_admin' => false,
        'user_id' => null,
        'first_name' => '',
        'last_name' => '',
        'profile_picture' => ''
    ];
    
    if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'admin') {
        $user_id = mysqli_real_escape_string($conn, $_SESSION['user_id']);
        $query = "SELECT id, first_name, last_name, profile_picture FROM users WHERE id = '$user_id' LIMIT 1";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $user_data = [
                'is_logged_in' => true,
                'is_admin' => true,
                'user_id' => $row['id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'profile_picture' => $row['profile_picture']
            ];
        }
    }
    
    return $user_data;
}

// Set cache control headers for all protected pages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
