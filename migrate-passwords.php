<?php
/**
 * One-time migration: widen users.password_hash to hold a bcrypt hash and
 * convert every legacy plaintext password into a proper password_hash() hash.
 *
 * Idempotent - safe to run more than once. Run from the CLI:
 *     php migrate-passwords.php
 *
 * (CLI-only: refuses to run over HTTP so it can't be triggered by a visitor.)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This migration can only be run from the command line.\n");
}

require_once __DIR__ . '/includes/db-helpers.php';

global $conn;

// 1) Widen the column if it's still too small for a bcrypt hash (60 chars).
$col = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'password_hash'");
if ($col && ($info = mysqli_fetch_assoc($col))) {
    if (stripos($info['Type'], 'varchar(255)') === false) {
        mysqli_query($conn, "ALTER TABLE users MODIFY password_hash VARCHAR(255) NOT NULL");
        echo "Widened password_hash to VARCHAR(255).\n";
    } else {
        echo "password_hash already VARCHAR(255).\n";
    }
}

// 2) Hash every row that isn't already a recognised hash.
$rows = getRows("SELECT id, password_hash FROM users");
$migrated = 0;
$skipped = 0;
foreach ($rows as $row) {
    if (passwordNeedsUpgrade($row['password_hash'])) {
        $hash = password_hash($row['password_hash'], PASSWORD_DEFAULT);
        if (executeStatement("UPDATE users SET password_hash = ? WHERE id = ?", [$hash, (int) $row['id']], 'si')) {
            $migrated++;
        } else {
            echo "  ! Failed to update user id {$row['id']}\n";
        }
    } else {
        $skipped++;
    }
}

echo "Done. Hashed {$migrated} plaintext password(s); {$skipped} already hashed.\n";
