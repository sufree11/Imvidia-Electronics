<?php
require_once 'includes/auth.php';
require_once 'includes/db-helpers.php';
require_once 'includes/helpers.php';

requireCustomerLogin();

$user = checkCustomerOrGuest();
$user_id = (int) $user['user_id'];

$wishlist_products = getRows(
    "SELECT p.* FROM wishlist w JOIN product p ON w.product_id = p.product_id WHERE w.user_id = ? ORDER BY w.added_at DESC",
    [$user_id],
    'i'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Your Wishlist - ImVidia</title>
    <?php include 'includes/head.php'; ?>
</head>

<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen dark:bg-slate-950 dark:text-gray-100">

    <?php include 'includes/navbar-customer.php'; ?>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16 w-full relative z-10 animate-fade-in-up">

        <div class="mb-10">
            <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 dark:text-white tracking-tight">Your Wishlist</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-2">Products you've saved for later.</p>
        </div>

        <?php if (count($wishlist_products) === 0): ?>
            <div class="flex flex-col items-center justify-center py-24 bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-dashed border-gray-300 dark:border-slate-700">
                <i class="fa-regular fa-heart text-6xl text-gray-300 dark:text-slate-600 mb-6"></i>
                <h3 class="text-2xl font-bold text-gray-500 dark:text-gray-400">Your wishlist is empty...</h3>
                <p class="text-gray-400 dark:text-gray-500 mt-2 mb-8 text-sm">Tap the heart icon on any product to save it here.</p>
                <a href="index.php#catalog" class="px-6 py-3 bg-imvidia hover:bg-imvidia-dark text-white rounded-xl shadow-md font-bold text-sm transition transform hover:-translate-y-0.5">
                    <i class="fa-solid fa-store mr-2"></i> Browse Catalog
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8" id="wishlist-grid">
                <?php foreach ($wishlist_products as $prod):
                    $prod_id = (int) $prod['product_id'];
                    $prod_name = htmlspecialchars($prod['name']);
                    $prod_price = number_format($prod['price'], 2);
                    $prod_cat = htmlspecialchars($prod['category']);
                    $prod_stock = (int) $prod['stock_quantity'];
                    $prod_img = !empty($prod['image_url']) ? htmlspecialchars($prod['image_url']) : 'https://ui-avatars.com/api/?name=No+Image&background=f1f5f9&color=94a3b8';
                ?>
                    <div id="wishlist-card-<?php echo $prod_id; ?>" class="group relative bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-4 transition hover:shadow-lg flex flex-col">
                        <button onclick="removeFromWishlist(<?php echo $prod_id; ?>, this)" class="absolute top-6 right-6 z-20 w-9 h-9 rounded-full bg-white/90 dark:bg-slate-900/90 shadow-md flex items-center justify-center hover:scale-110 transition" title="Remove from wishlist">
                            <i class="fa-solid fa-heart text-imvidia-light text-lg"></i>
                        </button>

                        <a href="product.php?id=<?php echo $prod_id; ?>" class="w-full h-48 bg-white dark:bg-slate-700 rounded-xl overflow-hidden group-hover:opacity-75 flex items-center justify-center p-2">
                            <img src="<?php echo $prod_img; ?>" alt="<?php echo $prod_name; ?>" class="max-w-full max-h-full object-contain drop-shadow-md">
                        </a>

                        <div class="mt-4 flex justify-between flex-col flex-grow">
                            <div>
                                <h3 class="text-sm text-gray-700 dark:text-gray-200 font-bold line-clamp-2">
                                    <a href="product.php?id=<?php echo $prod_id; ?>" class="hover:text-imvidia transition"><?php echo $prod_name; ?></a>
                                </h3>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider"><?php echo $prod_cat; ?></p>
                            </div>
                            <div class="flex items-center justify-between mt-3">
                                <p class="text-sm font-bold text-gray-900 dark:text-white whitespace-nowrap">RM <?php echo $prod_price; ?></p>
                                <button onclick="addToCart(<?php echo $prod_id; ?>, '<?php echo htmlspecialchars(addslashes($prod['name'])); ?>', <?php echo (float) $prod['price']; ?>, <?php echo $prod_stock; ?>)"
                                        class="px-3 py-2 bg-imvidia hover:bg-imvidia-dark disabled:bg-gray-300 disabled:dark:bg-slate-700 text-white font-bold rounded-lg shadow-sm hover:shadow-md transition text-xs flex items-center space-x-1.5"
                                        <?php echo $prod_stock == 0 ? 'disabled' : ''; ?>>
                                    <i class="fa-solid fa-cart-plus"></i>
                                    <span><?php echo $prod_stock > 0 ? 'Add to Cart' : 'Out of Stock'; ?></span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        function removeFromWishlist(productId, btn) {
            const icon = btn.querySelector('i');
            toggleWishlist(productId, icon).then(() => {
                if (!icon.classList.contains('fa-solid')) {
                    const card = document.getElementById('wishlist-card-' + productId);
                    if (card) card.remove();

                    const grid = document.getElementById('wishlist-grid');
                    if (grid && grid.children.length === 0) {
                        setTimeout(() => window.location.reload(), 2300);
                    }
                }
            });
        }

        function updateCartBadge(count) {
            const badge = document.getElementById('cart-badge');
            if (badge && typeof count === 'number') {
                badge.innerText = count;
                badge.classList.add('scale-150');
                setTimeout(() => badge.classList.remove('scale-150'), 200);
            }
        }

        // This page requires a logged-in customer (requireCustomerLogin()),
        // so the cart is always the DB-backed one - no guest/localStorage path.
        async function addToCart(productId, productName, price, availableStock) {
            const body = new URLSearchParams({ action: 'add', product_id: productId, quantity: 1 });
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

            if (!data.success) {
                showToast(data.message || 'Something went wrong.', 'fa-solid fa-triangle-exclamation');
                return;
            }

            updateCartBadge(data.cart_count);
            showToast('Added to cart!', 'fa-solid fa-cart-plus');
        }
    </script>

</body>
</html>
