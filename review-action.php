<?php
define('AJAX_ENDPOINT', true);
require_once 'db/session.php';
require_once 'includes/auth.php';
require_once 'includes/db-helpers.php';
require_once 'includes/review-helpers.php';

header('Content-Type: application/json');

$viewer = getReviewViewer();

if (!$viewer['logged_in']) {
    echo json_encode(['success' => false, 'require_login' => true, 'message' => 'Please log in to continue.']);
    exit();
}

requireCsrfOrFail();

ensureReviewSchema();

$action = $_POST['action'] ?? '';
$user_id = $viewer['user_id'];
$is_admin = $viewer['is_admin'];

// dispatch requested review action
switch ($action) {

    case 'submit_review': {
        // Only customers leave star reviews; admins reply, not review.
        if ($viewer['role'] !== 'customer') {
            echo json_encode(['success' => false, 'message' => 'Only customer accounts can post reviews.']);
            exit();
        }

        $product_id = (int) ($_POST['product_id'] ?? 0);
        $rating = (int) ($_POST['rating'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));

        if ($product_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product.']);
            exit();
        }
        if ($rating < 1 || $rating > 5) {
            echo json_encode(['success' => false, 'message' => 'Please choose a rating between 1 and 5 stars.']);
            exit();
        }
        if ($body === '') {
            echo json_encode(['success' => false, 'message' => 'Please write something in your review.']);
            exit();
        }

        $title = mb_substr($title, 0, 150);

        if (addOrUpdateReview($user_id, $product_id, $rating, $title, $body)) {
            echo json_encode(['success' => true, 'message' => 'Your review has been saved.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not save your review.']);
        }
        exit();
    }

    case 'delete_review': {
        $review_id = (int) ($_POST['review_id'] ?? 0);
        $review = getReviewById($review_id);

        if (!$review) {
            echo json_encode(['success' => false, 'message' => 'Review not found.']);
            exit();
        }
        // Owner or admin may delete.
        if ((int) $review['user_id'] !== $user_id && !$is_admin) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete this review.']);
            exit();
        }

        if (deleteReview($review_id)) {
            echo json_encode(['success' => true, 'message' => 'Review deleted.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not delete review.']);
        }
        exit();
    }

    case 'add_reply': {
        $review_id = (int) ($_POST['review_id'] ?? 0);
        $parent_reply_id = (int) ($_POST['parent_reply_id'] ?? 0);
        $body = trim((string) ($_POST['body'] ?? ''));

        if (!getReviewById($review_id)) {
            echo json_encode(['success' => false, 'message' => 'Review not found.']);
            exit();
        }
        if ($body === '') {
            echo json_encode(['success' => false, 'message' => 'Reply cannot be empty.']);
            exit();
        }

        // A nested reply must point at a reply belonging to the same review.
        if ($parent_reply_id > 0) {
            $parent = getReplyById($parent_reply_id);
            if (!$parent || (int) $parent['review_id'] !== $review_id) {
                echo json_encode(['success' => false, 'message' => 'Invalid reply target.']);
                exit();
            }
        }

        if (addReply($review_id, $user_id, $is_admin, $body, $parent_reply_id ?: null)) {
            echo json_encode(['success' => true, 'message' => 'Reply posted.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not post reply.']);
        }
        exit();
    }

    case 'delete_reply': {
        $reply_id = (int) ($_POST['reply_id'] ?? 0);
        $reply = getReplyById($reply_id);

        if (!$reply) {
            echo json_encode(['success' => false, 'message' => 'Reply not found.']);
            exit();
        }
        if ((int) $reply['user_id'] !== $user_id && !$is_admin) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete this reply.']);
            exit();
        }

        if (deleteReply($reply_id)) {
            echo json_encode(['success' => true, 'message' => 'Reply deleted.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not delete reply.']);
        }
        exit();
    }

    case 'react': {
        $target_type = ($_POST['target_type'] ?? 'review') === 'reply' ? 'reply' : 'review';
        $target_id = (int) ($_POST['target_id'] ?? 0);
        $reaction = (string) ($_POST['reaction'] ?? '');

        $target_exists = $target_type === 'reply' ? (bool) getReplyById($target_id) : (bool) getReviewById($target_id);
        if (!$target_exists) {
            echo json_encode(['success' => false, 'message' => ucfirst($target_type) . ' not found.']);
            exit();
        }

        $result = setReaction($target_type, $target_id, $user_id, $reaction);
        echo json_encode([
            'success' => true,
            'reactions' => getReactionSummary($target_type, $target_id),
            'viewer_reaction' => $result,
        ]);
        exit();
    }

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        exit();
}
