<?php
/**
 * Database Helper Functions
 * Provides prepared statement wrappers and secure query execution
 * 
 * This eliminates the need to use mysqli_real_escape_string() and string concatenation
 * for SQL queries, preventing SQL injection vulnerabilities.
 */

require_once __DIR__ . '/../db/database.php';

/**
 * Execute a prepared statement query
 * 
 * @param string $query SQL query with ? placeholders for parameters
 * @param array $params Array of parameter values
 * @param string $types String of parameter types: 'i'=int, 's'=string, 'd'=double, 'b'=blob
 * @return mysqli_result|bool Query result or false on error
 * 
 * Example:
 * $result = executeQuery("SELECT * FROM users WHERE email = ? AND role = ?", 
 *                        ['user@email.com', 'customer'], 'ss');
 */
function executeQuery($query, $params = [], $types = '') {
    global $conn;
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return false;
    }
    
    return $stmt->get_result();
}

/**
 * Get single row from query
 * 
 * @param string $query SQL query with ? placeholders
 * @param array $params Array of parameter values
 * @param string $types String of parameter types
 * @return array|null Associative array or null if no rows
 * 
 * Example:
 * $user = getRow("SELECT * FROM users WHERE id = ?", [123], 'i');
 */
function getRow($query, $params = [], $types = '') {
    $result = executeQuery($query, $params, $types);
    
    if (!$result) {
        return null;
    }
    
    return $result->fetch_assoc();
}

/**
 * Get all rows from query
 * 
 * @param string $query SQL query with ? placeholders
 * @param array $params Array of parameter values
 * @param string $types String of parameter types
 * @return array Array of associative arrays
 * 
 * Example:
 * $users = getRows("SELECT * FROM users WHERE role = ?", ['customer'], 's');
 */
function getRows($query, $params = [], $types = '') {
    $result = executeQuery($query, $params, $types);
    
    if (!$result) {
        return [];
    }
    
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    
    return $rows;
}

/**
 * Get single value from query
 * 
 * @param string $query SQL query with ? placeholders
 * @param array $params Array of parameter values
 * @param string $types String of parameter types
 * @return mixed Single value or null
 * 
 * Example:
 * $count = getValue("SELECT COUNT(*) FROM users WHERE role = ?", ['customer'], 's');
 */
function getValue($query, $params = [], $types = '') {
    $row = getRow($query, $params, $types);
    
    if (!$row) {
        return null;
    }
    
    $values = array_values($row);
    return $values[0] ?? null;
}

/**
 * Execute insert/update/delete query
 * 
 * @param string $query SQL query with ? placeholders
 * @param array $params Array of parameter values
 * @param string $types String of parameter types
 * @return bool True if successful, false otherwise
 * 
 * Example:
 * executeStatement("UPDATE users SET first_name = ? WHERE id = ?", ['John', 123], 'si');
 */
function executeStatement($query, $params = [], $types = '') {
    global $conn;
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return false;
    }
    
    $stmt->close();
    return true;
}

/**
 * Get last inserted ID after INSERT
 * 
 * @return int Last inserted ID
 */
function getLastInsertId() {
    global $conn;
    return $conn->insert_id;
}

/**
 * Get number of affected rows
 * 
 * @return int Number of affected rows
 */
function getAffectedRows() {
    global $conn;
    return $conn->affected_rows;
}

/**
 * Start transaction
 */
function beginTransaction() {
    global $conn;
    $conn->begin_transaction();
}

/**
 * Commit transaction
 */
function commitTransaction() {
    global $conn;
    $conn->commit();
}

/**
 * Rollback transaction
 */
function rollbackTransaction() {
    global $conn;
    $conn->rollback();
}

/**
 * Hash password using PHP's built-in function
 * 
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password against hash
 * 
 * @param string $password Plain text password
 * @param string $hash Password hash from database
 * @return bool True if password matches
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
