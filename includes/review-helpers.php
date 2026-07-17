<?php

require_once __DIR__ . '/db-helpers.php';
require_once __DIR__ . '/helpers.php';

/**
 * Product reviews, replies and reactions.
 *
 * This is an initial (test-on-localhost) build: the three tables are created
 * on first use via ensureReviewSchema(), the same idempotent
 * CREATE TABLE IF NOT EXISTS pattern used by ensureCartSchema() and
 * ensureOrdersSchemaV2().
 */

/**
 * The reactions a user can leave on a review. Keyed by the value stored in the
 * DB; the emoji is what gets rendered. Kept small on purpose for the first pass.
 */
function getAllowedReactions() {
    return [
        'like' => '👍',
        'love' => '❤️',
        'fire' => '🔥',
        'wow'  => '😮',
    ];
}

// create review tables if missing
function ensureReviewSchema() {
    global $conn;

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS product_reviews (
        review_id INT AUTO_INCREMENT PRIMARY KEY,
        product_id BIGINT UNSIGNED NOT NULL,
        user_id INT NOT NULL,
        rating TINYINT NOT NULL DEFAULT 5,
        title VARCHAR(150) DEFAULT NULL,
        body TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_product (user_id, product_id),
        KEY idx_product (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS review_replies (
        reply_id INT AUTO_INCREMENT PRIMARY KEY,
        review_id INT NOT NULL,
        parent_reply_id INT DEFAULT NULL,
        user_id INT NOT NULL,
        is_admin TINYINT(1) NOT NULL DEFAULT 0,
        body TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_review (review_id),
        KEY idx_parent (parent_reply_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Migration for installs created before threaded replies existed: add the
    // parent pointer that turns the flat reply list into a tree. Idempotent -
    // only runs when the column is missing. NULL parent = reply to the review.
    try {
        $col = mysqli_query($conn, "SHOW COLUMNS FROM review_replies LIKE 'parent_reply_id'");
        if ($col && mysqli_num_rows($col) === 0) {
            mysqli_query($conn, "ALTER TABLE review_replies ADD COLUMN parent_reply_id INT DEFAULT NULL AFTER review_id, ADD KEY idx_parent (parent_reply_id)");
        }
    } catch (\mysqli_sql_exception $e) {
        error_log('review_replies migration failed: ' . $e->getMessage());
    }

    // Reactions are polymorphic: a target is either a review or a reply,
    // identified by (target_type, target_id). One reaction per user per target.
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS review_reactions (
        reaction_id INT AUTO_INCREMENT PRIMARY KEY,
        target_type VARCHAR(10) NOT NULL DEFAULT 'review',
        target_id INT NOT NULL,
        user_id INT NOT NULL,
        reaction VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_target (user_id, target_type, target_id),
        KEY idx_target (target_type, target_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Migration for installs whose review_reactions predates reply reactions
    // (only had review_id). Adds the polymorphic target columns, backfills them
    // from review_id, and swaps the unique key. Idempotent.
    try {
        $has_target = mysqli_query($conn, "SHOW COLUMNS FROM review_reactions LIKE 'target_id'");
        if ($has_target && mysqli_num_rows($has_target) === 0) {
            mysqli_query($conn, "ALTER TABLE review_reactions
                ADD COLUMN target_type VARCHAR(10) NOT NULL DEFAULT 'review' AFTER reaction_id,
                ADD COLUMN target_id INT NOT NULL DEFAULT 0 AFTER target_type");

            $has_review_col = mysqli_query($conn, "SHOW COLUMNS FROM review_reactions LIKE 'review_id'");
            if ($has_review_col && mysqli_num_rows($has_review_col) > 0) {
                mysqli_query($conn, "UPDATE review_reactions SET target_id = review_id, target_type = 'review' WHERE target_id = 0");
            }

            $old_idx = mysqli_query($conn, "SHOW INDEX FROM review_reactions WHERE Key_name = 'unique_user_review'");
            if ($old_idx && mysqli_num_rows($old_idx) > 0) {
                mysqli_query($conn, "ALTER TABLE review_reactions DROP INDEX unique_user_review");
            }

            $new_idx = mysqli_query($conn, "SHOW INDEX FROM review_reactions WHERE Key_name = 'unique_user_target'");
            if ($new_idx && mysqli_num_rows($new_idx) === 0) {
                mysqli_query($conn, "ALTER TABLE review_reactions
                    ADD UNIQUE KEY unique_user_target (user_id, target_type, target_id),
                    ADD KEY idx_target (target_type, target_id)");
            }
        }

        // Drop the legacy review_id column once target_id has taken over. It's
        // NOT NULL with no default, so leaving it would make target-only inserts
        // fail under strict SQL mode. Runs independently of the block above so a
        // half-migrated table still gets cleaned up.
        $legacy = mysqli_query($conn, "SHOW COLUMNS FROM review_reactions LIKE 'review_id'");
        if ($legacy && mysqli_num_rows($legacy) > 0) {
            mysqli_query($conn, "ALTER TABLE review_reactions DROP COLUMN review_id");
        }
    } catch (\mysqli_sql_exception $e) {
        error_log('review_reactions migration failed: ' . $e->getMessage());
    }
}

/**
 * Resolves who is acting on the review widget straight from the session, so it
 * works for both customers and admins (product.php's checkCustomerOrGuest()
 * only recognises customers). Returns a normalized shape whether logged in or
 * not.
 */
function getReviewViewer() {
    if (!isset($_SESSION['user_id'])) {
        return ['logged_in' => false, 'user_id' => null, 'role' => null, 'is_admin' => false, 'name' => '', 'avatar' => ''];
    }

    $row = getRow(
        "SELECT id, first_name, last_name, profile_picture, role FROM users WHERE id = ? LIMIT 1",
        [(int) $_SESSION['user_id']],
        'i'
    );

    if (!$row) {
        return ['logged_in' => false, 'user_id' => null, 'role' => null, 'is_admin' => false, 'name' => '', 'avatar' => ''];
    }

    $is_admin = ($row['role'] === 'admin');

    return [
        'logged_in' => true,
        'user_id' => (int) $row['id'],
        'role' => $row['role'],
        'is_admin' => $is_admin,
        'name' => trim($row['first_name'] . ' ' . $row['last_name']),
        'avatar' => getAvatarUrl($row['first_name'], $row['last_name'], $row['profile_picture'], $is_admin),
    ];
}

/**
 * True when this user has an order containing this product that has reached a
 * completed state (Delivered). Drives the "Verified Purchase" badge.
 */
function hasCompletedOrderForProduct($user_id, $product_id) {
    return (int) getValue(
        "SELECT COUNT(*)
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.order_id
         WHERE o.user_id = ? AND oi.product_id = ?
           AND LOWER(o.order_progress) IN ('delivered', 'completed')",
        [$user_id, $product_id],
        'ii'
    ) > 0;
}

// count reactions per type
function getReactionSummary($target_type, $target_id) {
    $rows = getRows(
        "SELECT reaction, COUNT(*) AS cnt FROM review_reactions WHERE target_type = ? AND target_id = ? GROUP BY reaction",
        [$target_type, $target_id],
        'si'
    );

    $summary = [];
    foreach ($rows as $row) {
        $summary[$row['reaction']] = (int) $row['cnt'];
    }
    return $summary;
}

// viewers reaction on target
function getViewerReaction($target_type, $target_id, $user_id) {
    if (!$user_id) {
        return null;
    }
    return getValue(
        "SELECT reaction FROM review_reactions WHERE target_type = ? AND target_id = ? AND user_id = ?",
        [$target_type, $target_id, $user_id],
        'sii'
    );
}

// fetch threaded replies for review
function getRepliesForReview($review_id, $viewer_id = null) {
    $replies = getRows(
        "SELECT rr.reply_id, rr.review_id, rr.parent_reply_id, rr.user_id, rr.is_admin, rr.body, rr.created_at,
                u.first_name, u.last_name, u.profile_picture
         FROM review_replies rr
         JOIN users u ON u.id = rr.user_id
         WHERE rr.review_id = ?
         ORDER BY rr.created_at ASC",
        [$review_id],
        'i'
    );

    foreach ($replies as &$reply) {
        $reply['reactions'] = getReactionSummary('reply', (int) $reply['reply_id']);
        $reply['viewer_reaction'] = getViewerReaction('reply', (int) $reply['reply_id'], $viewer_id);
    }
    unset($reply);

    return $replies;
}

/**
 * All reviews for a product, newest first, each hydrated with its author info,
 * replies, reaction counts, the viewer's own reaction and a verified-purchase
 * flag. N+1 queries per review are fine for this localhost test.
 */
function getReviewsForProduct($product_id, $viewer_id = null) {
    $reviews = getRows(
        "SELECT r.review_id, r.product_id, r.user_id, r.rating, r.title, r.body, r.created_at, r.updated_at,
                u.first_name, u.last_name, u.profile_picture
         FROM product_reviews r
         JOIN users u ON u.id = r.user_id
         WHERE r.product_id = ?
         ORDER BY r.created_at DESC",
        [$product_id],
        'i'
    );

    foreach ($reviews as &$rev) {
        $rev['replies'] = getRepliesForReview((int) $rev['review_id'], $viewer_id);
        $rev['reactions'] = getReactionSummary('review', (int) $rev['review_id']);
        $rev['viewer_reaction'] = getViewerReaction('review', (int) $rev['review_id'], $viewer_id);
        $rev['verified_purchase'] = hasCompletedOrderForProduct((int) $rev['user_id'], (int) $product_id);
    }
    unset($rev);

    return $reviews;
}

// rating average and distribution
function getProductRatingSummary($product_id) {
    $row = getRow(
        "SELECT COUNT(*) AS total, COALESCE(AVG(rating), 0) AS avg_rating
         FROM product_reviews WHERE product_id = ?",
        [$product_id],
        'i'
    );

    $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    $dist_rows = getRows(
        "SELECT rating, COUNT(*) AS cnt FROM product_reviews WHERE product_id = ? GROUP BY rating",
        [$product_id],
        'i'
    );
    foreach ($dist_rows as $r) {
        $rating = (int) $r['rating'];
        if (isset($distribution[$rating])) {
            $distribution[$rating] = (int) $r['cnt'];
        }
    }

    return [
        'total' => (int) $row['total'],
        'average' => round((float) $row['avg_rating'], 1),
        'distribution' => $distribution,
    ];
}

/**
 * Batch version of getProductRatingSummary() for listing pages (e.g. the
 * catalog) so we don't run a query per card. Returns
 * [product_id => ['total' => int, 'average' => float]].
 */
function getRatingSummariesForProducts(array $product_ids) {
    if (empty($product_ids)) {
        return [];
    }

    // All ids are cast to int, so inlining them in the IN() list is safe.
    $ids = array_values(array_unique(array_map('intval', $product_ids)));
    $in = implode(',', $ids);

    $rows = getRows(
        "SELECT product_id, COUNT(*) AS total, COALESCE(AVG(rating), 0) AS avg_rating
         FROM product_reviews
         WHERE product_id IN ($in)
         GROUP BY product_id"
    );

    $map = [];
    foreach ($rows as $row) {
        $map[(int) $row['product_id']] = [
            'total' => (int) $row['total'],
            'average' => round((float) $row['avg_rating'], 1),
        ];
    }
    return $map;
}

/**
 * Renders a row of 5 stars for a given rating (whole-star). Shared between the
 * product page and the catalog cards.
 */
function renderStars($rating, $size = 'text-base') {
    $rating = (int) round($rating);
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $cls = $i <= $rating ? 'fa-solid text-amber-400' : 'fa-regular text-gray-300 dark:text-slate-600';
        $out .= '<i class="' . $cls . ' fa-star ' . $size . '"></i>';
    }
    return $out;
}

// fetch single review row
function getReviewById($review_id) {
    return getRow("SELECT * FROM product_reviews WHERE review_id = ? LIMIT 1", [$review_id], 'i');
}

/**
 * Creates a review or, if this user already reviewed this product, updates it.
 * One review per user per product is enforced by the unique key.
 */
function addOrUpdateReview($user_id, $product_id, $rating, $title, $body) {
    $rating = max(1, min(5, (int) $rating));

    return executeStatement(
        "INSERT INTO product_reviews (product_id, user_id, rating, title, body)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE rating = VALUES(rating), title = VALUES(title), body = VALUES(body)",
        [$product_id, $user_id, $rating, $title, $body],
        'iiiss'
    );
}

// delete review with dependents
function deleteReview($review_id) {
    // Remove dependent replies/reactions first (no FK cascade defined).
    executeStatement("DELETE FROM review_replies WHERE review_id = ?", [$review_id], 'i');
    executeStatement("DELETE FROM review_reactions WHERE review_id = ?", [$review_id], 'i');
    return executeStatement("DELETE FROM product_reviews WHERE review_id = ?", [$review_id], 'i');
}

/**
 * Adds a reply. $parent_reply_id NULL means a direct reply to the review;
 * a non-null value nests it under another reply (reply-to-reply).
 */
function addReply($review_id, $user_id, $is_admin, $body, $parent_reply_id = null) {
    if ($parent_reply_id) {
        return executeStatement(
            "INSERT INTO review_replies (review_id, parent_reply_id, user_id, is_admin, body) VALUES (?, ?, ?, ?, ?)",
            [$review_id, $parent_reply_id, $user_id, $is_admin ? 1 : 0, $body],
            'iiiis'
        );
    }

    return executeStatement(
        "INSERT INTO review_replies (review_id, user_id, is_admin, body) VALUES (?, ?, ?, ?)",
        [$review_id, $user_id, $is_admin ? 1 : 0, $body],
        'iiis'
    );
}

// fetch single reply row
function getReplyById($reply_id) {
    return getRow("SELECT * FROM review_replies WHERE reply_id = ? LIMIT 1", [$reply_id], 'i');
}

/**
 * Deletes a reply and all of its descendants (so a deleted reply doesn't leave
 * orphaned children in the thread).
 */
function deleteReply($reply_id) {
    $children = getRows("SELECT reply_id FROM review_replies WHERE parent_reply_id = ?", [$reply_id], 'i');
    foreach ($children as $child) {
        deleteReply((int) $child['reply_id']);
    }
    return executeStatement("DELETE FROM review_replies WHERE reply_id = ?", [$reply_id], 'i');
}

/**
 * Toggles a reaction. Clicking the reaction you already have removes it;
 * clicking a different one switches to it. Returns the viewer's resulting
 * reaction (or null if cleared).
 */
function setReaction($target_type, $target_id, $user_id, $reaction) {
    if (!array_key_exists($reaction, getAllowedReactions())) {
        return getViewerReaction($target_type, $target_id, $user_id);
    }

    $existing = getViewerReaction($target_type, $target_id, $user_id);

    if ($existing === $reaction) {
        executeStatement("DELETE FROM review_reactions WHERE target_type = ? AND target_id = ? AND user_id = ?", [$target_type, $target_id, $user_id], 'sii');
        return null;
    }

    executeStatement(
        "INSERT INTO review_reactions (target_type, target_id, user_id, reaction) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE reaction = VALUES(reaction)",
        [$target_type, $target_id, $user_id, $reaction],
        'siis'
    );
    return $reaction;
}
