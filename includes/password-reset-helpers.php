<?php

require_once __DIR__ . '/db-helpers.php';

// create password_resets table if missing
function ensurePasswordResetSchema() {
    global $conn;

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS password_resets (
        reset_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_token (token_hash),
        KEY idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// issue a fresh single use reset token
function createPasswordResetToken($user_id) {
    // retire any outstanding tokens for this user first
    executeStatement("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL", [$user_id], 'i');

    $token = bin2hex(random_bytes(32));
    // store only the hash never the raw token
    $token_hash = hash('sha256', $token);

    executeStatement(
        "INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))",
        [$user_id, $token_hash],
        'is'
    );

    return $token;
}

// look up an unused unexpired token
function getValidResetForToken($rawToken) {
    if ($rawToken === '' || !ctype_xdigit($rawToken)) {
        return null;
    }

    $token_hash = hash('sha256', $rawToken);

    return getRow(
        "SELECT reset_id, user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1",
        [$token_hash],
        's'
    );
}

// mark a token consumed
function markResetUsed($reset_id) {
    return executeStatement("UPDATE password_resets SET used_at = NOW() WHERE reset_id = ?", [$reset_id], 'i');
}
