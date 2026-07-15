<?php
require_once 'db/session.php';
require_once 'includes/auth.php';
require_once 'includes/db-helpers.php';
require_once 'includes/helpers.php';

$user = checkCustomerOrGuest();

function renderNotFound() {
    http_response_code(404);
    include 'error.php';
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    renderNotFound();
}

$product_id = intval($_GET['id']);

$product_query = "SELECT * FROM product WHERE product_id = $product_id LIMIT 1";
$product_result = mysqli_query($conn, $product_query);

if (!$product_result || mysqli_num_rows($product_result) === 0) {
    renderNotFound();
}

$product = mysqli_fetch_assoc($product_result);
$main_image = !empty($product['image_url']) ? htmlspecialchars($product['image_url']) : 'https://ui-avatars.com/api/?name=No+Image&background=f1f5f9&color=94a3b8';

$gallery_images = [];
$gal_query = "SELECT image_url FROM product_gallery WHERE product_id = $product_id ORDER BY id ASC";
$gal_result = mysqli_query($conn, $gal_query);

if ($gal_result && mysqli_num_rows($gal_result) > 0) {
    while ($row = mysqli_fetch_assoc($gal_result)) {
        $gallery_images[] = htmlspecialchars($row['image_url']);
    }
}

$is_wishlisted = false;
if ($user['is_logged_in']) {
    $is_wishlisted = (bool) getRow("SELECT wishlist_id FROM wishlist WHERE user_id = ? AND product_id = ?", [$user['user_id'], $product_id], 'ii');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo htmlspecialchars($product['name']); ?> - ImVidia</title>
    <?php include 'includes/head.php'; ?>
</head>

<body class="bg-fixed bg-gray-50 text-gray-800 flex flex-col min-h-screen dark:bg-slate-950 dark:text-gray-100" style="background-image: radial-gradient(circle, rgba(156, 163, 175, 0.2) 2.5px, transparent 2.5px); background-size: 40px 40px;">

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

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-24 w-full flex-grow">
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
                        
                        <button id="add-to-cart-btn" onclick="addToCart('<?php echo htmlspecialchars(addslashes($product['name'])); ?>', <?php echo $product['price']; ?>, <?php echo $product['stock_quantity']; ?>)"
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
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        function changeMainImage(src) {
            const mainImg = document.getElementById('main-product-image');
            mainImg.style.opacity = '0.5';
            setTimeout(() => {
                mainImg.src = src;
                mainImg.style.opacity = '1';
            }, 150);
        }

        function incrementQty(max) {
            const input = document.getElementById('qty');
            if (parseInt(input.value) < max) {
                input.value = parseInt(input.value) + 1;
            }
        }
        function decrementQty() {
            const input = document.getElementById('qty');
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
            }
        }

        function updateCartBadge() {
            let cart = JSON.parse(localStorage.getItem(window.IMVIDIA_CART_KEY)) || [];
            const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
            
            const badge = document.getElementById('cart-badge');
            if (badge) {
                badge.innerText = totalItems;
                badge.classList.add('scale-150');
                setTimeout(() => badge.classList.remove('scale-150'), 200);
            }
        }

        const productStock = <?php echo (int) $product['stock_quantity']; ?>;

        function refreshAddToCartButton(productName) {
            const btn = document.getElementById('add-to-cart-btn');
            const label = document.getElementById('add-to-cart-label');
            const icon = document.getElementById('add-to-cart-icon');
            if (!btn || !label || productStock === 0) return;

            let cart = JSON.parse(localStorage.getItem(window.IMVIDIA_CART_KEY)) || [];
            let existingItem = cart.find(item => item.name === productName);

            if (existingItem && existingItem.quantity > 0) {
                label.innerText = `In Cart: ${existingItem.quantity}`;
                if (icon) icon.className = 'fa-solid fa-check text-lg';
            } else {
                label.innerText = 'Add to Cart';
                if (icon) icon.className = 'fa-solid fa-cart-plus text-lg';
            }
        }

        function addToCart(productName, price, availableStock) {
            const qtyInput = document.getElementById('qty');
            const qty = parseInt(qtyInput.value) || 1;

            if (qty > availableStock) {
                alert(`Only ${availableStock} item(s) available in stock. Please reduce quantity.`);
                qtyInput.value = availableStock;
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
</body>
</html>