<?php

require_once __DIR__ . '/../db/database.php';

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

function getRow($query, $params = [], $types = '') {
    $result = executeQuery($query, $params, $types);
    
    if (!$result) {
        return null;
    }
    
    return $result->fetch_assoc();
}

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

function getValue($query, $params = [], $types = '') {
    $row = getRow($query, $params, $types);
    
    if (!$row) {
        return null;
    }
    
    $values = array_values($row);
    return $values[0] ?? null;
}

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

function getLastInsertId() {
    global $conn;
    return $conn->insert_id;
}

function getAffectedRows() {
    global $conn;
    return $conn->affected_rows;
}

function beginTransaction() {
    global $conn;
    $conn->begin_transaction();
}

function commitTransaction() {
    global $conn;
    $conn->commit();
}

function rollbackTransaction() {
    global $conn;
    $conn->rollback();
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
