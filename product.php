<?php
require_once 'db/session.php';
require_once 'includes/auth.php';
require_once 'includes/db-helpers.php';
require_once 'includes/helpers.php';
require_once 'includes/cart-helpers.php';
require_once 'includes/review-helpers.php';

$user = checkCustomerOrGuest();

// send 404 and stop
function renderNotFound() {
    http_response_code(404);
    include 'error.php';
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    renderNotFound();
}

$product_id = intval($_GET['id']);

// load product or 404
$product_query = "SELECT * FROM product WHERE product_id = $product_id LIMIT 1";
$product_result = mysqli_query($conn, $product_query);

if (!$product_result || mysqli_num_rows($product_result) === 0) {
    renderNotFound();
}

$product = mysqli_fetch_assoc($product_result);
$main_image = !empty($product['image_url']) ? htmlspecialchars($product['image_url']) : 'https://ui-avatars.com/api/?name=No+Image&background=f1f5f9&color=94a3b8';

// load gallery images
$gallery_images = [];
$gal_query = "SELECT image_url FROM product_gallery WHERE product_id = $product_id ORDER BY id ASC";
$gal_result = mysqli_query($conn, $gal_query);

if ($gal_result && mysqli_num_rows($gal_result) > 0) {
    while ($row = mysqli_fetch_assoc($gal_result)) {
        $gallery_images[] = htmlspecialchars($row['image_url']);
    }
}

// load viewer wishlist and cart state
$is_wishlisted = false;
$cart_qty = 0;
if ($user['is_logged_in']) {
    $is_wishlisted = (bool) getRow("SELECT wishlist_id FROM wishlist WHERE user_id = ? AND product_id = ?", [$user['user_id'], $product_id], 'ii');
    ensureCartSchema();
    $cart_qty = (int) getValue("SELECT quantity FROM cart_items WHERE user_id = ? AND product_id = ?", [$user['user_id'], $product_id], 'ii');
}

ensureReviewSchema();
$review_viewer = getReviewViewer();
$rating_summary = getProductRatingSummary($product_id);
$reviews = getReviewsForProduct($product_id, $review_viewer['user_id']);
$allowed_reactions = getAllowedReactions();

// The current customer's own review, if any (so the form pre-fills for editing
// and we don't render their review a second time in the list).
$viewer_review = null;
if ($review_viewer['logged_in'] && $review_viewer['role'] === 'customer') {
    foreach ($reviews as $rev) {
        if ((int) $rev['user_id'] === $review_viewer['user_id']) {
            $viewer_review = $rev;
            break;
        }
    }
}

// renderStars() lives in includes/review-helpers.php (shared with the catalog).

// avatar url for review author
function reviewAuthorAvatar($first, $last, $picture, $is_admin = false) {
    return getAvatarUrl($first, $last, $picture, $is_admin);
}

/**
 * Total number of replies below a given reply (all descendants, any depth) -
 * this is what the "View N replies" toggle advertises.
 */
function countReplyDescendants($by_parent, $reply_id) {
    $children = $by_parent[$reply_id] ?? [];
    $count = count($children);
    foreach ($children as $child) {
        $count += countReplyDescendants($by_parent, (int) $child['reply_id']);
    }
    return $count;
}

/**
 * Renders the reaction bar for a review or a reply. $size is 'sm' (replies) or
 * 'base' (reviews). The whole bar is a .reaction-group so the JS updates only
 * this target's buttons.
 */
function renderReactionGroup($target_type, $target_id, $reactions, $viewer_reaction) {
    $pad = $target_type === 'reply' ? 'px-2 py-1 text-xs' : 'px-3 py-1.5 text-sm';
    echo '<div class="reaction-group inline-flex items-center flex-wrap gap-1.5">';
    foreach (getAllowedReactions() as $key => $emoji) {
        $count = $reactions[$key] ?? 0;
        $active = ($viewer_reaction === $key);
        $state = $active
            ? 'border-imvidia bg-imvidia/10 text-imvidia font-semibold'
            : 'border-gray-200 dark:border-slate-700 text-gray-500 dark:text-gray-400 hover:border-gray-300 dark:hover:border-slate-600';
        echo '<button type="button" data-reaction="' . $key . '" onclick="reactTo(\'' . $target_type . '\', ' . (int) $target_id . ', \'' . $key . '\', this)" '
            . 'class="reaction-btn inline-flex items-center gap-1 rounded-full border transition ' . $pad . ' ' . $state . '">'
            . '<span>' . $emoji . '</span><span class="reaction-count">' . $count . '</span></button>';
    }
    echo '</div>';
}

/**
 * Renders one reply node and, recursively, its nested children. Children are
 * collapsed behind a "View N replies" toggle (TikTok style, count = all
 * descendants); anyone logged in gets a reply box and can react to any reply.
 */
function renderReplyNode($reply, $by_parent, $viewer, $review_id, $depth) {
    $reply_id = (int) $reply['reply_id'];
    $name = trim($reply['first_name'] . ' ' . $reply['last_name']);
    $is_admin_reply = (int) $reply['is_admin'] === 1;
    $avatar = getAvatarUrl($reply['first_name'], $reply['last_name'], $reply['profile_picture'], $is_admin_reply);
    $can_delete = ($viewer['logged_in'] && (int) $reply['user_id'] === $viewer['user_id']) || $viewer['is_admin'];
    $children = $by_parent[$reply_id] ?? [];
    $descendant_count = countReplyDescendants($by_parent, $reply_id);
    ?>
    <div class="reply-node" data-reply-id="<?php echo $reply_id; ?>">
        <div class="flex items-start gap-3">
            <img src="<?php echo htmlspecialchars($avatar); ?>" alt="<?php echo htmlspecialchars($name); ?>" class="w-8 h-8 rounded-full object-cover bg-white shadow-sm flex-shrink-0">
            <div class="flex-1 min-w-0">
                <div class="bg-gray-50 dark:bg-slate-800/50 rounded-xl px-4 py-2.5">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($name); ?></span>
                        <?php if ($is_admin_reply): ?>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-imvidia text-white font-bold flex items-center gap-1"><i class="fa-solid fa-shield-halved"></i> ImVidia</span>
                        <?php endif; ?>
                        <span class="text-xs text-gray-400"><?php echo date('d M Y', strtotime($reply['created_at'])); ?></span>
                        <?php if ($can_delete): ?>
                            <button type="button" onclick="deleteReply(<?php echo $reply_id; ?>)" class="ml-auto text-xs text-gray-400 hover:text-red-500 transition"><i class="fa-solid fa-trash-can"></i></button>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm text-gray-700 dark:text-gray-300 mt-1 whitespace-pre-line"><?php echo htmlspecialchars($reply['body']); ?></p>
                </div>

                <div class="flex items-center gap-3 mt-1.5 pl-1 flex-wrap">
                    <?php renderReactionGroup('reply', $reply_id, $reply['reactions'] ?? [], $reply['viewer_reaction'] ?? null); ?>
                    <?php if ($viewer['logged_in']): ?>
                        <button type="button" onclick="toggleReplyForm('reply-form-reply-<?php echo $reply_id; ?>')" class="text-xs font-semibold text-gray-500 dark:text-gray-400 hover:text-imvidia transition"><i class="fa-solid fa-reply mr-1"></i> Reply</button>
                    <?php endif; ?>
                    <?php if ($descendant_count > 0): ?>
                        <button type="button" data-count="<?php echo $descendant_count; ?>" onclick="toggleNested('nested-<?php echo $reply_id; ?>', this)" class="text-xs font-semibold text-imvidia hover:text-imvidia-dark transition">
                            <i class="fa-solid fa-chevron-down mr-1"></i> View <?php echo $descendant_count; ?> <?php echo $descendant_count === 1 ? 'reply' : 'replies'; ?>
                        </button>
                    <?php endif; ?>
                </div>

                <?php if ($viewer['logged_in']) renderReplyForm('reply-form-reply-' . $reply_id, $review_id, $reply_id, $viewer); ?>

                <?php if (!empty($children)): ?>
                    <div id="nested-<?php echo $reply_id; ?>" class="hidden mt-3 space-y-3 <?php echo $depth < 3 ? 'border-l-2 border-gray-100 dark:border-slate-800 pl-4' : ''; ?>">
                        <?php renderReplyList($children, $by_parent, $viewer, $review_id, 'r' . $reply_id, $depth + 1); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Renders a sibling list of replies. Shows the first 3; any beyond that are
 * hidden behind a "View N more replies" button so long threads stay compact.
 */
function renderReplyList($replies, $by_parent, $viewer, $review_id, $group_key, $depth) {
    $limit = 3;
    $visible = array_slice($replies, 0, $limit);
    $hidden = array_slice($replies, $limit);

    foreach ($visible as $r) {
        renderReplyNode($r, $by_parent, $viewer, $review_id, $depth);
    }

    if (!empty($hidden)) {
        $more_id = 'more-' . $group_key;
        $n = count($hidden);
        echo '<div id="' . $more_id . '" class="hidden space-y-3">';
        foreach ($hidden as $r) {
            renderReplyNode($r, $by_parent, $viewer, $review_id, $depth);
        }
        echo '</div>';
        echo '<button type="button" onclick="showMore(\'' . $more_id . '\', this)" class="text-xs font-semibold text-imvidia hover:text-imvidia-dark transition">'
            . '<i class="fa-solid fa-chevron-down mr-1"></i> View ' . $n . ' more ' . ($n === 1 ? 'reply' : 'replies') . '</button>';
    }
}

/**
 * A hidden inline reply box. $parent_reply_id 0 = reply to the review itself.
 */
function renderReplyForm($form_id, $review_id, $parent_reply_id, $viewer) {
    $placeholder = $viewer['is_admin'] ? 'Write an official response...' : 'Write a reply...';
    ?>
    <form id="<?php echo $form_id; ?>" class="hidden mt-2 flex items-start gap-2" onsubmit="submitReply(event, <?php echo (int) $review_id; ?>, <?php echo (int) $parent_reply_id; ?>)">
        <input type="text" name="body" required placeholder="<?php echo htmlspecialchars($placeholder); ?>" class="flex-1 px-4 py-2 border border-gray-300 dark:border-slate-700 rounded-full focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition text-sm">
        <button type="submit" class="px-4 py-2 bg-imvidia hover:bg-imvidia-dark text-white font-semibold rounded-full text-sm transition"><i class="fa-solid fa-paper-plane"></i></button>
    </form>
    <?php
}

/**
 * Renders the whole reply thread for a review: a review-level reply box plus
 * the tree of replies built from the flat list.
 */
function renderReplyThread($replies, $viewer, $review_id) {
    $by_parent = [];
    foreach ($replies as $r) {
        $pid = $r['parent_reply_id'] !== null ? (int) $r['parent_reply_id'] : 0;
        $by_parent[$pid][] = $r;
    }

    if ($viewer['logged_in']) {
        renderReplyForm('reply-form-review-' . $review_id, $review_id, 0, $viewer);
    }

    $top_level = $by_parent[0] ?? [];
    if (!empty($top_level)) {
        echo '<div class="mt-4 space-y-3 border-l-2 border-gray-100 dark:border-slate-800 pl-4">';
        renderReplyList($top_level, $by_parent, $viewer, $review_id, 'review-' . $review_id, 1);
        echo '</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo htmlspecialchars($product['name']); ?> - ImVidia</title>
    <?php include 'includes/head.php'; ?>
</head>

<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen dark:bg-slate-950 dark:text-gray-100">

    <?php include 'includes/navbar-customer.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 w-full">
        <nav class="flex text-sm text-gray-500 dark:text-gray-400 font-medium">
            <a href="index.php" class="hover:text-imvidia transition">Home</a>
            <span class="mx-3 text-gray-400">/</span>
            <a href="index.php#catalog" class="hover:text-imvidia transition">Catalog</a>
            <span class="mx-3 text-gray-400">/</span>
            <span class="text-gray-900 dark:text-gray-200"><?php echo htmlspecialchars($product['category']); ?></span>
        </nav>
    </div>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-24 w-full flex-grow animate-fade-in-up">
        <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-gray-100 dark:border-slate-800 overflow-hidden flex flex-col md:flex-row">

            <div class="w-full md:w-1/2 p-6 md:p-10 border-b md:border-b-0 md:border-r border-gray-100 dark:border-slate-800 flex flex-col">
                <div class="flex-grow flex items-center justify-center bg-gray-50 dark:bg-slate-800/50 rounded-2xl mb-6 p-4 md:p-8 aspect-square relative group">
                    <img id="main-product-image" src="<?php echo $main_image; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="max-w-full max-h-full object-contain drop-shadow-lg transition-transform duration-500 group-hover:scale-105">
                </div>

                <?php if (count($gallery_images) > 0): ?>
                    <div class="grid grid-cols-4 sm:grid-cols-5 gap-3">
                        <?php foreach($gallery_images as $gal_img): ?>
                            <button onclick="changeMainImage('<?php echo $gal_img; ?>')" class="border-2 border-gray-200 dark:border-slate-700 hover:border-imvidia dark:hover:border-imvidia rounded-lg overflow-hidden focus:outline-none transition aspect-square bg-gray-50 dark:bg-slate-800/50 flex items-center justify-center p-1">
                                <img src="<?php echo $gal_img; ?>" class="w-full h-full object-contain mix-blend-multiply dark:mix-blend-normal">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="w-full md:w-1/2 p-6 md:p-12 flex flex-col justify-center">
                <div class="flex items-center space-x-3 mb-4">
                    <span class="px-3 py-1 bg-imvidia/10 text-imvidia dark:bg-imvidia/20 dark:text-imvidia-light rounded-full text-xs font-bold uppercase tracking-wider">
                        <?php echo htmlspecialchars($product['category']); ?>
                    </span>
                    
                    <?php if ($product['stock_quantity'] > 10): ?>
                        <span class="px-3 py-1 bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-400 rounded-full text-xs font-bold flex items-center"><i class="fa-solid fa-check mr-1.5"></i> In Stock</span>
                    <?php elseif ($product['stock_quantity'] > 0): ?>
                        <span class="px-3 py-1 bg-orange-50 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400 rounded-full text-xs font-bold flex items-center"><i class="fa-solid fa-fire mr-1.5"></i> Low Stock</span>
                    <?php else: ?>
                        <span class="px-3 py-1 bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400 rounded-full text-xs font-bold flex items-center"><i class="fa-solid fa-xmark mr-1.5"></i> Out of Stock</span>
                    <?php endif; ?>
                </div>

                <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 dark:text-white mb-2 leading-tight">
                    <?php echo htmlspecialchars($product['name']); ?>
                </h1>

                <a href="#reviews" class="inline-flex items-center gap-2 mb-2 group w-max">
                    <span class="space-x-0.5"><?php echo renderStars($rating_summary['average'], 'text-sm'); ?></span>
                    <?php if ($rating_summary['total'] > 0): ?>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300"><?php echo number_format($rating_summary['average'], 1); ?></span>
                        <span class="text-sm text-gray-400 group-hover:text-imvidia transition"><?php echo (int) $rating_summary['total']; ?> review<?php echo $rating_summary['total'] === 1 ? '' : 's'; ?></span>
                    <?php else: ?>
                        <span class="text-sm text-gray-400 group-hover:text-imvidia transition">No reviews yet</span>
                    <?php endif; ?>
                </a>


                <p class="text-4xl font-black text-gray-900 dark:text-white mb-8 border-b border-gray-100 dark:border-slate-800 pb-8">
                    RM <?php echo number_format($product['price'], 2); ?>
                </p>

                <div class="prose dark:prose-invert mb-10 max-w-none desc-scroll-container">
                    <?php 
                    echo $product['description']; 
                    ?>
                </div>

                <div class="mt-auto pt-4">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Quantity</label>
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center border border-gray-200 dark:border-slate-700 rounded-lg overflow-hidden bg-white dark:bg-slate-800 w-32 h-14 shrink-0">
                            <button onclick="decrementQty()" class="w-1/3 h-full text-gray-500 hover:bg-gray-50 dark:hover:bg-slate-700 transition flex justify-center items-center font-bold text-lg focus:outline-none" <?php echo $product['stock_quantity'] == 0 ? 'disabled' : ''; ?>>-</button>
                            
                            <input type="number" id="qty" value="<?php echo $product['stock_quantity'] > 0 ? '1' : '0'; ?>" min="1" max="<?php echo $product['stock_quantity']; ?>" readonly class="w-1/3 h-full text-center border-x border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white font-bold bg-transparent outline-none">
                            
                            <button onclick="incrementQty(<?php echo $product['stock_quantity']; ?>)" class="w-1/3 h-full text-gray-500 hover:bg-gray-50 dark:hover:bg-slate-700 transition flex justify-center items-center font-bold text-lg focus:outline-none" <?php echo $product['stock_quantity'] == 0 ? 'disabled' : ''; ?>>+</button>
                        </div>
                        
                        <button id="add-to-cart-btn" onclick="addToCart(<?php echo (int) $product['product_id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name'])); ?>', <?php echo $product['price']; ?>, <?php echo $product['stock_quantity']; ?>)"
                                class="flex-1 h-14 bg-imvidia hover:bg-imvidia-dark disabled:bg-gray-300 disabled:dark:bg-slate-700 text-white font-bold rounded-lg shadow-md hover:shadow-lg transition transform hover:-translate-y-0.5 flex items-center justify-center space-x-2"
                                <?php echo $product['stock_quantity'] == 0 ? 'disabled' : ''; ?>>
                            <i id="add-to-cart-icon" class="fa-solid fa-cart-plus text-lg"></i>
                            <span id="add-to-cart-label"><?php echo $product['stock_quantity'] > 0 ? 'Add to Cart' : 'Out of Stock'; ?></span>
                        </button>

                        <button onclick="toggleWishlist(<?php echo $product_id; ?>, this.querySelector('i'))" class="w-14 h-14 flex-shrink-0 flex items-center justify-center rounded-lg border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:shadow-md transition transform hover:-translate-y-0.5" title="Toggle wishlist">
                            <i class="<?php echo $is_wishlisted ? 'fa-solid text-imvidia-light' : 'fa-regular text-gray-400'; ?> fa-heart text-xl"></i>
                        </button>
                    </div>
                </div>

            </div>
        </div>
        <!-- ============================ REVIEWS ============================ -->
        <section id="reviews" class="mt-10 bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-gray-100 dark:border-slate-800 overflow-hidden">
            <div class="p-6 md:p-10">
                <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white mb-6 flex items-center gap-3">
                    <i class="fa-solid fa-star text-amber-400"></i> Ratings &amp; Reviews
                </h2>

                <!-- Summary -->
                <div class="flex flex-col md:flex-row gap-8 md:gap-12 pb-8 border-b border-gray-100 dark:border-slate-800">
                    <div class="text-center md:text-left md:w-56 flex-shrink-0">
                        <div class="text-5xl font-black text-gray-900 dark:text-white leading-none">
                            <?php echo $rating_summary['total'] > 0 ? number_format($rating_summary['average'], 1) : '—'; ?>
                        </div>
                        <div class="mt-2 space-x-0.5"><?php echo renderStars($rating_summary['average'], 'text-lg'); ?></div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                            <?php echo (int) $rating_summary['total']; ?> review<?php echo $rating_summary['total'] === 1 ? '' : 's'; ?>
                        </p>
                    </div>
                    <div class="flex-1 space-y-1.5 max-w-md">
                        <?php for ($star = 5; $star >= 1; $star--):
                            $count = $rating_summary['distribution'][$star];
                            $pct = $rating_summary['total'] > 0 ? ($count / $rating_summary['total']) * 100 : 0; ?>
                            <div class="flex items-center gap-3 text-sm">
                                <span class="w-10 text-gray-500 dark:text-gray-400 font-medium"><?php echo $star; ?> <i class="fa-solid fa-star text-xs text-amber-400"></i></span>
                                <div class="flex-1 h-2.5 bg-gray-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-amber-400 rounded-full" style="width: <?php echo $pct; ?>%"></div>
                                </div>
                                <span class="w-8 text-right text-gray-400 dark:text-gray-500"><?php echo $count; ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Write / edit review -->
                <div class="py-8 border-b border-gray-100 dark:border-slate-800">
                    <?php if (!$review_viewer['logged_in']): ?>
                        <div class="text-center py-6">
                            <p class="text-gray-600 dark:text-gray-300 mb-4">Sign in to your account to leave a review.</p>
                            <a href="login.php" class="inline-flex items-center px-6 py-3 bg-imvidia hover:bg-imvidia-dark text-white font-bold rounded-lg shadow-md transition transform hover:-translate-y-0.5">
                                <i class="fa-solid fa-right-to-bracket mr-2"></i> Log in to review
                            </a>
                        </div>
                    <?php elseif ($review_viewer['is_admin']): ?>
                        <div class="flex items-center gap-3 px-4 py-3 bg-imvidia/5 dark:bg-imvidia/10 border border-imvidia/20 rounded-xl text-sm text-gray-600 dark:text-gray-300">
                            <i class="fa-solid fa-circle-info text-imvidia"></i>
                            You're signed in as an admin. You can reply to customer reviews below with an official response.
                        </div>
                    <?php else: ?>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                                <?php echo $viewer_review ? 'Your review' : 'Write a review'; ?>
                            </h3>
                            <?php if ($viewer_review): ?>
                                <button type="button" onclick="toggleReviewForm()" id="edit-review-btn" class="text-sm font-semibold text-imvidia hover:text-imvidia-dark transition">
                                    <i class="fa-solid fa-pen mr-1"></i> Edit
                                </button>
                            <?php endif; ?>
                        </div>

                        <form id="review-form" class="space-y-4 <?php echo $viewer_review ? 'hidden' : ''; ?>" onsubmit="submitReview(event)">
                            <input type="hidden" name="product_id" value="<?php echo (int) $product_id; ?>">

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Your rating</label>
                                <div id="star-input" class="flex items-center gap-1 text-2xl">
                                    <?php $current_rating = $viewer_review ? (int) $viewer_review['rating'] : 0; ?>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <button type="button" data-value="<?php echo $i; ?>" onclick="setRating(<?php echo $i; ?>)" class="star-btn focus:outline-none transition transform hover:scale-110">
                                            <i class="<?php echo $i <= $current_rating ? 'fa-solid text-amber-400' : 'fa-regular text-gray-300 dark:text-slate-600'; ?> fa-star"></i>
                                        </button>
                                    <?php endfor; ?>
                                    <input type="hidden" name="rating" id="rating-value" value="<?php echo $current_rating; ?>">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Title <span class="text-gray-400 font-normal">(optional)</span></label>
                                <input type="text" name="title" maxlength="150" value="<?php echo $viewer_review ? htmlspecialchars($viewer_review['title']) : ''; ?>" placeholder="Sum it up in a few words" class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition text-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Your review</label>
                                <textarea name="body" rows="4" required placeholder="What did you like or dislike? How did it perform?" class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-imvidia/50 focus:border-imvidia dark:bg-slate-800 dark:text-white transition text-sm resize-y"><?php echo $viewer_review ? htmlspecialchars($viewer_review['body']) : ''; ?></textarea>
                            </div>

                            <div class="flex items-center gap-3">
                                <button type="submit" class="px-6 py-2.5 bg-imvidia hover:bg-imvidia-dark text-white font-bold rounded-lg shadow-md transition transform hover:-translate-y-0.5">
                                    <i class="fa-solid fa-paper-plane mr-2"></i> <?php echo $viewer_review ? 'Update review' : 'Submit review'; ?>
                                </button>
                                <?php if ($viewer_review): ?>
                                    <button type="button" onclick="toggleReviewForm()" class="px-5 py-2.5 text-gray-600 dark:text-gray-300 font-semibold rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition">Cancel</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Reviews list -->
                <div class="pt-8 space-y-8" id="reviews-list">
                    <?php if (empty($reviews)): ?>
                        <div class="text-center py-12">
                            <i class="fa-regular fa-comment-dots text-5xl text-gray-300 dark:text-slate-600 mb-4"></i>
                            <p class="text-gray-500 dark:text-gray-400 font-medium">No reviews yet. Be the first to review this product!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($reviews as $rev):
                            $rev_id = (int) $rev['review_id'];
                            $is_own_review = $review_viewer['logged_in'] && (int) $rev['user_id'] === $review_viewer['user_id'];
                            $author_name = trim($rev['first_name'] . ' ' . $rev['last_name']);
                            $author_avatar = reviewAuthorAvatar($rev['first_name'], $rev['last_name'], $rev['profile_picture']);
                            $can_delete_review = $is_own_review || $review_viewer['is_admin'];
                        ?>
                            <div class="review-card" data-review-id="<?php echo $rev_id; ?>">
                                <div class="flex items-start gap-4">
                                    <img src="<?php echo htmlspecialchars($author_avatar); ?>" alt="<?php echo htmlspecialchars($author_name); ?>" class="w-11 h-11 rounded-full object-cover bg-white shadow-sm flex-shrink-0">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($author_name); ?></span>
                                            <?php if ($is_own_review): ?>
                                                <span class="text-xs px-2 py-0.5 rounded-full bg-imvidia/10 text-imvidia font-semibold">You</span>
                                            <?php endif; ?>
                                            <?php if (!empty($rev['verified_purchase'])): ?>
                                                <span class="text-xs px-2 py-0.5 rounded-full bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-400 font-semibold flex items-center gap-1"><i class="fa-solid fa-circle-check"></i> Verified Purchase</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="space-x-0.5"><?php echo renderStars((int) $rev['rating'], 'text-sm'); ?></span>
                                            <span class="text-xs text-gray-400 dark:text-gray-500"><?php echo date('d M Y', strtotime($rev['created_at'])); ?></span>
                                        </div>

                                        <?php if (!empty($rev['title'])): ?>
                                            <h4 class="font-bold text-gray-900 dark:text-white mt-3"><?php echo htmlspecialchars($rev['title']); ?></h4>
                                        <?php endif; ?>
                                        <p class="text-gray-700 dark:text-gray-300 mt-1 whitespace-pre-line leading-relaxed"><?php echo htmlspecialchars($rev['body']); ?></p>

                                        <!-- Reactions -->
                                        <div class="flex items-center flex-wrap gap-2 mt-4">
                                            <?php renderReactionGroup('review', $rev_id, $rev['reactions'] ?? [], $rev['viewer_reaction'] ?? null); ?>

                                            <?php if ($review_viewer['logged_in']): ?>
                                                <button type="button" onclick="toggleReplyForm('reply-form-review-<?php echo $rev_id; ?>')" class="ml-1 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm text-gray-500 dark:text-gray-400 hover:text-imvidia transition">
                                                    <i class="fa-solid fa-reply"></i> Reply
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($can_delete_review): ?>
                                                <button type="button" onclick="deleteReview(<?php echo $rev_id; ?>)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm text-gray-400 hover:text-red-500 transition">
                                                    <i class="fa-solid fa-trash-can"></i> Delete
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Threaded replies -->
                                        <?php renderReplyThread($rev['replies'], $review_viewer, $rev_id); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        // swap main product image
        function changeMainImage(src) {
            const mainImg = document.getElementById('main-product-image');
            mainImg.style.opacity = '0.5';
            setTimeout(() => {
                mainImg.src = src;
                mainImg.style.opacity = '1';
            }, 150);
        }

        // increase quantity input
        function incrementQty(max) {
            const input = document.getElementById('qty');
            if (parseInt(input.value) < max) {
                input.value = parseInt(input.value) + 1;
            }
        }
        // decrease quantity input
        function decrementQty() {
            const input = document.getElementById('qty');
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
            }
        }

        // Server-rendered quantity already in this account's DB cart for this
        // product; kept in sync locally after each successful add.
        let currentCartQty = <?php echo (int) $cart_qty; ?>;

        // update cart badge count
        function updateCartBadge(count) {
            const badge = document.getElementById('cart-badge');
            if (!badge) return;

            if (window.IMVIDIA_LOGGED_IN) {
                if (typeof count === 'number') {
                    badge.innerText = count;
                }
            } else {
                let cart = JSON.parse(localStorage.getItem(window.IMVIDIA_CART_KEY)) || [];
                badge.innerText = cart.reduce((sum, item) => sum + item.quantity, 0);
            }

            badge.classList.add('scale-150');
            setTimeout(() => badge.classList.remove('scale-150'), 200);
        }

        const productStock = <?php echo (int) $product['stock_quantity']; ?>;

        // refresh add to cart button
        function refreshAddToCartButton(productName) {
            const btn = document.getElementById('add-to-cart-btn');
            const label = document.getElementById('add-to-cart-label');
            const icon = document.getElementById('add-to-cart-icon');
            if (!btn || !label || productStock === 0) return;

            let qtyInCart = 0;
            if (window.IMVIDIA_LOGGED_IN) {
                qtyInCart = currentCartQty;
            } else {
                let cart = JSON.parse(localStorage.getItem(window.IMVIDIA_CART_KEY)) || [];
                let existingItem = cart.find(item => item.name === productName);
                qtyInCart = existingItem ? existingItem.quantity : 0;
            }

            if (qtyInCart > 0) {
                label.innerText = `In Cart: ${qtyInCart}`;
                if (icon) icon.className = 'fa-solid fa-check text-lg';
            } else {
                label.innerText = 'Add to Cart';
                if (icon) icon.className = 'fa-solid fa-cart-plus text-lg';
            }
        }

        // add product to cart
        async function addToCart(productId, productName, price, availableStock) {
            const qtyInput = document.getElementById('qty');
            const qty = parseInt(qtyInput.value) || 1;

            if (qty > availableStock) {
                alert(`Only ${availableStock} item(s) available in stock. Please reduce quantity.`);
                qtyInput.value = availableStock;
                return;
            }

            if (window.IMVIDIA_LOGGED_IN) {
                if (currentCartQty + qty > availableStock) {
                    alert(`You already have ${currentCartQty} of this item in cart. Adding ${qty} more would exceed available stock of ${availableStock}.`);
                    return;
                }

                const body = new URLSearchParams({ action: 'add', product_id: productId, quantity: qty });
                const response = await fetch('cart-action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': window.IMVIDIA_CSRF },
                    body
                });
                const data = await response.json();

                if (data.require_login) {
                    window.location.href = 'login.php';
                    return;
                }

                const updatedItem = (data.cart || []).find(item => parseInt(item.product_id, 10) === productId);
                currentCartQty = updatedItem ? parseInt(updatedItem.quantity, 10) : currentCartQty + qty;
                updateCartBadge(data.cart_count);
                refreshAddToCartButton(productName);
                showToast('Added to cart!', 'fa-solid fa-cart-plus');
                return;
            }

            let cart = JSON.parse(localStorage.getItem(window.IMVIDIA_CART_KEY)) || [];
            let existingItem = cart.find(item => item.name === productName);

            if (existingItem) {
                const newTotal = existingItem.quantity + qty;
                if (newTotal > availableStock) {
                    alert(`You already have ${existingItem.quantity} of this item in cart. Adding ${qty} more would exceed available stock of ${availableStock}.`);
                    return;
                }
                existingItem.quantity += qty;
            } else {
                cart.push({ name: productName, price: price, quantity: qty });
            }

            localStorage.setItem(window.IMVIDIA_CART_KEY, JSON.stringify(cart));
            updateCartBadge();
            refreshAddToCartButton(productName);
            showToast('Added to cart!', 'fa-solid fa-cart-plus');
        }

        document.addEventListener('DOMContentLoaded', () => {
            updateCartBadge();
            refreshAddToCartButton(<?php echo json_encode($product['name']); ?>);
        });
    </script>

    <!-- ===================== REVIEWS SCRIPT ===================== -->
    <script>
        // POSTs to review-action.php and returns the parsed JSON. Redirects to
        // login if the server says the session isn't authenticated.
        // post review action request
        async function postReviewAction(params) {
            const body = new URLSearchParams(params);
            const res = await fetch('review-action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': window.IMVIDIA_CSRF },
                body
            });
            const data = await res.json();
            if (data.require_login) {
                window.location.href = 'login.php';
                return null;
            }
            return data;
        }

        // --- Write-review form ---
        // set star rating input
        function setRating(value) {
            document.getElementById('rating-value').value = value;
            document.querySelectorAll('#star-input .star-btn').forEach(btn => {
                const icon = btn.querySelector('i');
                const on = parseInt(btn.dataset.value, 10) <= value;
                icon.className = (on ? 'fa-solid text-amber-400' : 'fa-regular text-gray-300 dark:text-slate-600') + ' fa-star';
            });
        }

        // toggle review form
        function toggleReviewForm() {
            const form = document.getElementById('review-form');
            if (form) form.classList.toggle('hidden');
        }

        // submit review form
        async function submitReview(event) {
            event.preventDefault();
            const form = event.target;
            const rating = parseInt(form.querySelector('#rating-value').value, 10) || 0;
            if (rating < 1) {
                showToast('Please pick a star rating.', 'fa-solid fa-triangle-exclamation');
                return;
            }
            const data = await postReviewAction({
                action: 'submit_review',
                product_id: form.product_id.value,
                rating: rating,
                title: form.title.value,
                body: form.body.value
            });
            if (!data) return;
            if (data.success) {
                showToast(data.message || 'Review saved!', 'fa-solid fa-star');
                setTimeout(() => window.location.reload(), 400);
            } else {
                showToast(data.message || 'Something went wrong.', 'fa-solid fa-triangle-exclamation');
            }
        }

        // delete a review
        async function deleteReview(reviewId) {
            if (!confirm('Delete this review? This also removes its replies and reactions.')) return;
            const data = await postReviewAction({ action: 'delete_review', review_id: reviewId });
            if (!data) return;
            if (data.success) {
                showToast(data.message || 'Review deleted.', 'fa-solid fa-trash-can');
                setTimeout(() => window.location.reload(), 400);
            } else {
                showToast(data.message || 'Something went wrong.', 'fa-solid fa-triangle-exclamation');
            }
        }

        // --- Replies ---
        // toggle reply box
        function toggleReplyForm(formId) {
            const form = document.getElementById(formId);
            if (!form) return;
            form.classList.toggle('hidden');
            if (!form.classList.contains('hidden')) form.querySelector('input[name="body"]').focus();
        }

        // Reveals a hidden "N more replies" overflow group and drops the button.
        // reveal hidden replies
        function showMore(id, btn) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('hidden');
            if (btn) btn.remove();
        }

        // Expands/collapses a reply's nested children (TikTok-style).
        // toggle nested replies
        function toggleNested(id, btn) {
            const el = document.getElementById(id);
            if (!el) return;
            const nowHidden = el.classList.toggle('hidden');
            const n = btn.dataset.count;
            const word = n === '1' ? 'reply' : 'replies';
            btn.innerHTML = nowHidden
                ? `<i class="fa-solid fa-chevron-down mr-1"></i> View ${n} ${word}`
                : `<i class="fa-solid fa-chevron-up mr-1"></i> Hide ${word}`;
        }

        // submit a reply
        async function submitReply(event, reviewId, parentReplyId) {
            event.preventDefault();
            const form = event.target;
            const data = await postReviewAction({
                action: 'add_reply',
                review_id: reviewId,
                parent_reply_id: parentReplyId || 0,
                body: form.body.value
            });
            if (!data) return;
            if (data.success) {
                showToast(data.message || 'Reply posted.', 'fa-solid fa-reply');
                setTimeout(() => window.location.reload(), 400);
            } else {
                showToast(data.message || 'Something went wrong.', 'fa-solid fa-triangle-exclamation');
            }
        }

        // delete a reply
        async function deleteReply(replyId) {
            if (!confirm('Delete this reply?')) return;
            const data = await postReviewAction({ action: 'delete_reply', reply_id: replyId });
            if (!data) return;
            if (data.success) {
                showToast(data.message || 'Reply deleted.', 'fa-solid fa-trash-can');
                setTimeout(() => window.location.reload(), 400);
            } else {
                showToast(data.message || 'Something went wrong.', 'fa-solid fa-triangle-exclamation');
            }
        }

        // --- Reactions (works for reviews and replies, updated in place) ---
        const REACT_ACTIVE = ['border-imvidia', 'bg-imvidia/10', 'text-imvidia', 'font-semibold'];
        const REACT_INACTIVE = ['border-gray-200', 'dark:border-slate-700', 'text-gray-500', 'dark:text-gray-400', 'hover:border-gray-300', 'dark:hover:border-slate-600'];

        // toggle a reaction
        async function reactTo(targetType, targetId, reaction, btn) {
            const data = await postReviewAction({ action: 'react', target_type: targetType, target_id: targetId, reaction: reaction });
            if (!data) return;
            if (!data.success) {
                showToast(data.message || 'Something went wrong.', 'fa-solid fa-triangle-exclamation');
                return;
            }
            // Only repaint the buttons for this exact target (its .reaction-group),
            // so a reply's reactions don't clobber the review's, and vice versa.
            const group = btn.closest('.reaction-group');
            group.querySelectorAll('.reaction-btn').forEach(b => {
                const key = b.dataset.reaction;
                b.querySelector('.reaction-count').innerText = data.reactions[key] || 0;
                const active = data.viewer_reaction === key;
                // Toggle only state classes; keep each button's own size classes.
                REACT_ACTIVE.forEach(c => b.classList.toggle(c, active));
                REACT_INACTIVE.forEach(c => b.classList.toggle(c, !active));
            });
        }

        // If arriving from "Write a review" (order history), open the form and
        // scroll to it.
        document.addEventListener('DOMContentLoaded', () => {
            if (window.location.hash === '#write-review') {
                const form = document.getElementById('review-form');
                const editBtn = document.getElementById('edit-review-btn');
                if (form && form.classList.contains('hidden')) form.classList.remove('hidden');
                const target = document.getElementById('reviews');
                if (target) target.scrollIntoView({ behavior: 'smooth' });
                const bodyField = form ? form.querySelector('textarea[name="body"]') : null;
                if (bodyField) setTimeout(() => bodyField.focus(), 500);
            }
        });
    </script>
</body>
</html>