<?php

require_once __DIR__ . '/../db/database.php';

// run prepared query return result
function executeQuery($query, $params = [], $types = '') {
    global $conn;

    try {
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
    } catch (\mysqli_sql_exception $e) {
        // PHP 8.1+ makes mysqli throw on SQL errors by default instead of
        // returning false. Catch it here so every caller can keep using the
        // simple "if (!executeQuery(...))" pattern instead of a raw 500.
        error_log("Query failed: " . $e->getMessage());
        return false;
    }
}

// fetch single row
function getRow($query, $params = [], $types = '') {
    $result = executeQuery($query, $params, $types);
    
    if (!$result) {
        return null;
    }
    
    return $result->fetch_assoc();
}

// fetch all matching rows
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

// fetch single scalar value
function getValue($query, $params = [], $types = '') {
    $row = getRow($query, $params, $types);
    
    if (!$row) {
        return null;
    }
    
    $values = array_values($row);
    return $values[0] ?? null;
}

// run write statement return success
function executeStatement($query, $params = [], $types = '') {
    global $conn;

    try {
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
    } catch (\mysqli_sql_exception $e) {
        error_log("Statement failed: " . $e->getMessage());
        return false;
    }
}

// last inserted row id
function getLastInsertId() {
    global $conn;
    return $conn->insert_id;
}

// rows affected by write
function getAffectedRows() {
    global $conn;
    return $conn->affected_rows;
}

// start database transaction
function beginTransaction() {
    global $conn;
    $conn->begin_transaction();
}

// commit database transaction
function commitTransaction() {
    global $conn;
    $conn->commit();
}

// roll back database transaction
function rollbackTransaction() {
    global $conn;
    $conn->rollback();
}

// hash password for storage
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// verify password against hash
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * True when a stored password value isn't a recognised password_hash() hash
 * (i.e. it's a legacy plaintext value that should be re-hashed on next login).
 */
function passwordNeedsUpgrade($hash) {
    $info = password_get_info((string) $hash);
    return empty($info['algo']);
}

/**
 * Verifies a submitted password against the stored value, transparently
 * supporting legacy plaintext rows so logins keep working until every row has
 * been migrated. Callers should re-hash when passwordNeedsUpgrade() is true.
 */
function verifyUserPassword($input, $hash) {
    if (passwordNeedsUpgrade($hash)) {
        return hash_equals((string) $hash, (string) $input);
    }
    return password_verify((string) $input, (string) $hash);
}
