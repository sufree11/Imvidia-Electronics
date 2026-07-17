<?php
require_once 'db/session.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
require_once 'includes/cart-helpers.php';

$user = checkCustomerOrGuest();

$placeholder_image = 'https://ui-avatars.com/api/?name=No+Image&background=f1f5f9&color=94a3b8';

// build product image and id maps
$product_images = [];
$product_ids = [];
$product_result = mysqli_query($conn, "SELECT product_id, name, image_url FROM product");
if ($product_result) {
    while ($row = mysqli_fetch_assoc($product_result)) {
        $product_images[$row['name']] = !empty($row['image_url']) ? $row['image_url'] : $placeholder_image;
        $product_ids[$row['name']] = (int) $row['product_id'];
    }
}

// load db cart for logged in user
$server_cart = [];
if (!empty($user['is_logged_in'])) {
    ensureCartSchema();
    foreach (getCartItemsForUser($user['user_id']) as $row) {
        $server_cart[] = [
            'product_id' => (int) $row['product_id'],
            'name' => $row['name'],
            'price' => (float) $row['price'],
            'quantity' => (int) $row['quantity'],
            'selected' => (bool) $row['selected'],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Your Cart - ImVidia</title>
    <?php include 'includes/head.php'; ?>
</head>

<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen dark:bg-slate-950 dark:text-gray-100">

    <?php include 'includes/navbar-customer.php'; ?>

    <!-- Main Cart Content -->
    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16 w-full relative z-10 animate-fade-in-up">

        <div class="mb-10">
            <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 dark:text-white tracking-tight">Your Cart</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-2">Review your items before proceeding to checkout.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">

            <!-- LEFT COLUMN: Cart Items -->
            <div class="lg:col-span-8">

                <!-- EMPTY STATE: Pacman Ghost -->
                <div id="empty-cart-state" class="hidden flex-col items-center justify-center py-24 bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-dashed border-gray-300 dark:border-slate-700">
                    <a href="https://www.google.com/logos/2010/pacman10-i.html" target="_blank" rel="noopener noreferrer" title="A blue ghost...">
                        <i class="fa-solid fa-ghost text-6xl text-gray-300 dark:text-slate-600 mb-6 hover:text-imvidia duration-300 hover:scale-110 transition transform"></i>
                    </a>
                    <h3 class="text-2xl font-bold text-gray-500 dark:text-gray-400">Your cart is feeling light...</h3>
                    <p class="text-gray-400 dark:text-gray-500 mt-2 mb-8 text-sm">Looks like you haven't added anything yet.</p>
                    <a href="index.php#catalog" class="px-6 py-3 bg-imvidia hover:bg-imvidia-dark text-white rounded-xl shadow-md font-bold text-sm transition transform hover:-translate-y-0.5">
                        <i class="fa-solid fa-store mr-2"></i> Browse Catalog
                    </a>
                </div>

                <!-- FILLED STATE: Items Container -->
                <div id="cart-select-all-row" class="hidden items-center mb-4 px-1">
                    <input type="checkbox" id="select-all-checkbox" onchange="toggleSelectAll(this.checked)" class="w-4 h-4 rounded border-gray-300 dark:border-slate-600 text-imvidia focus:ring-imvidia cursor-pointer">
                    <label for="select-all-checkbox" class="ml-2 text-sm font-medium text-gray-600 dark:text-gray-400 cursor-pointer">Select All</label>
                </div>
                <div id="cart-items-container" class="space-y-4">
                    <!-- Javascript will inject items here -->
                </div>
            </div>

            <!-- RIGHT COLUMN: Order Summary -->
            <div class="lg:col-span-4 relative">
                <div class="sticky top-24 bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Order Summary</h2>

                    <div class="space-y-4 text-sm mb-6 border-b border-gray-100 dark:border-slate-800 pb-6">
                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>Subtotal</span>
                            <span id="summary-subtotal" class="font-medium text-gray-900 dark:text-white">RM 0.00</span>
                        </div>
                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>Shipping</span>
                            <span>Calculated at checkout</span>
                        </div>
                    </div>

                    <div class="flex justify-between items-end mb-8">
                        <span class="text-base font-bold text-gray-900 dark:text-white">Total</span>
                        <div class="text-right">
                            <span class="text-xs text-gray-500 dark:text-gray-400 mr-1">MYR</span>
                            <span id="summary-total" class="text-2xl font-extrabold text-gray-900 dark:text-white">RM 0.00</span>
                        </div>
                    </div>

                    <a href="checkout.php" id="checkout-btn" class="w-full py-4 px-4 bg-imvidia hover:bg-imvidia-dark text-white rounded-xl shadow-lg shadow-imvidia/30 font-bold text-lg transition transform hover:-translate-y-0.5 flex items-center justify-center text-center">
                        Proceed to Checkout <i class="fa-solid fa-arrow-right ml-2"></i>
                    </a>
                </div>
            </div>

        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <!-- CART LOGIC: DB-backed when logged in, LocalStorage-backed for guests -->
    <script>
        // Product thumbnails and IDs keyed by product name, populated from the database.
        const productImages = <?php echo json_encode($product_images); ?>;
        const productIds = <?php echo json_encode($product_ids); ?>;
        const placeholderImage = <?php echo json_encode($placeholder_image); ?>;

        // Server-rendered DB cart for logged-in users; kept in sync with each
        // cart-action.php response so re-renders don't need another round trip.
        let serverCart = <?php echo json_encode($server_cart); ?>;

        // product image or placeholder
        function getProductImage(name) {
            return productImages[name] || placeholderImage;
        }

        // open product page
        function goToProduct(name) {
            const productId = productIds[name];
            if (productId) {
                window.location.href = 'product.php?id=' + productId;
            }
        }

        // send cart action to server
        async function cartActionRequest(action, params) {
            const body = new URLSearchParams({ action, ...params });
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

            if (data.success) {
                serverCart = data.cart.map(row => ({
                    product_id: parseInt(row.product_id, 10),
                    name: row.name,
                    price: parseFloat(row.price),
                    quantity: parseInt(row.quantity, 10),
                    selected: !!parseInt(row.selected, 10)
                }));
            }
        }

        // current cart data source
        function getCurrentCart() {
            if (window.IMVIDIA_LOGGED_IN) {
                return serverCart;
            }
            // Read the cart from localStorage. Items with no `selected` flag yet
            // (carts saved before this feature existed) default to selected.
            return JSON.parse(localStorage.getItem(window.IMVIDIA_CART_KEY)) || [];
        }

        // render cart contents
        function loadCart() {
            let cart = getCurrentCart();

            const container = document.getElementById('cart-items-container');
            const emptyState = document.getElementById('empty-cart-state');
            const checkoutBtn = document.getElementById('checkout-btn');
            const subtotalEl = document.getElementById('summary-subtotal');
            const totalEl = document.getElementById('summary-total');
            const selectAllRow = document.getElementById('cart-select-all-row');
            const selectAllCheckbox = document.getElementById('select-all-checkbox');

            // If empty, show the Ghost!
            if (cart.length === 0) {
                container.innerHTML = '';
                container.classList.add('hidden');
                selectAllRow.classList.add('hidden');

                emptyState.classList.remove('hidden');
                emptyState.classList.add('flex');

                subtotalEl.innerText = 'RM 0.00';
                totalEl.innerText = 'RM 0.00';

                // Disable checkout button
                checkoutBtn.classList.add('opacity-50', 'pointer-events-none');
                checkoutBtn.href = "#";
                updateCartBadge(0);
                return;
            }

            // If not empty, hide ghost and render items
            container.classList.remove('hidden');
            selectAllRow.classList.remove('hidden');
            selectAllRow.classList.add('flex');
            emptyState.classList.add('hidden');
            emptyState.classList.remove('flex');

            let html = '';
            let totalCost = 0;
            let totalItems = 0;
            let selectedCount = 0;

            cart.forEach((item, index) => {
                const isSelected = item.selected !== false;
                const itemQty = item.quantity || 1;
                const itemTotal = item.price * itemQty;

                // Logged-in mutations key off product_id (DB primary key);
                // guest mutations key off the item's position in the localStorage array.
                const key = window.IMVIDIA_LOGGED_IN ? item.product_id : index;

                totalItems += itemQty;
                if (isSelected) {
                    totalCost += itemTotal;
                    selectedCount++;
                }

                const thumbnail = getProductImage(item.name);
                const productNameArg = JSON.stringify(item.name).replace(/"/g, '&quot;');
                const hasProductPage = Boolean(productIds[item.name]);

                html += `
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 sm:p-6 bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl shadow-sm transition ${isSelected ? '' : 'opacity-60'}">
                        <!-- Checkbox, Thumbnail & Info -->
                        <div class="flex items-center space-x-4 mb-4 sm:mb-0">
                            <input type="checkbox" ${isSelected ? 'checked' : ''} onchange="toggleSelect(${key}, this.checked)" class="w-4 h-4 rounded border-gray-300 dark:border-slate-600 text-imvidia focus:ring-imvidia cursor-pointer flex-shrink-0">
                            <div class="flex items-center space-x-4 ${hasProductPage ? 'cursor-pointer' : ''}" ${hasProductPage ? `onclick="goToProduct(${productNameArg})"` : ''}>
                                <div class="w-20 h-20 bg-gray-50 dark:bg-slate-800 rounded-xl flex items-center justify-center flex-shrink-0 border border-gray-200 dark:border-slate-700">
                                    <img src="${thumbnail}" alt="${item.name}" class="max-w-full max-h-full object-contain rounded-md">
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-900 dark:text-white text-base sm:text-lg leading-tight mb-1 ${hasProductPage ? 'hover:text-imvidia transition' : ''}">${item.name}</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Unit Price: RM ${parseFloat(item.price).toFixed(2)}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Controls & Price -->
                        <div class="flex items-center justify-between sm:justify-end space-x-4 sm:space-x-8 w-full sm:w-auto">

                            <!-- Custom Qty Scroller -->
                            <div class="flex items-center justify-between border border-gray-300 dark:border-slate-700 rounded-full h-10 px-2 bg-gray-50 dark:bg-slate-800 w-24 flex-shrink-0">
                                <button onclick="updateQuantity(${key}, -1)" class="w-6 h-6 flex items-center justify-center text-gray-500 hover:text-imvidia transition rounded-full hover:bg-gray-200 dark:hover:bg-slate-700">
                                    <i class="fa-solid fa-minus text-xs"></i>
                                </button>
                                <span class="font-semibold text-gray-900 dark:text-white text-sm select-none">${itemQty}</span>
                                <button onclick="updateQuantity(${key}, 1)" class="w-6 h-6 flex items-center justify-center text-gray-500 hover:text-imvidia transition rounded-full hover:bg-gray-200 dark:hover:bg-slate-700">
                                    <i class="fa-solid fa-plus text-xs"></i>
                                </button>
                            </div>

                            <!-- Item Total -->
                            <div class="text-right w-24 hidden sm:block">
                                <p class="font-bold text-gray-900 dark:text-white text-sm sm:text-base">RM ${itemTotal.toFixed(2)}</p>
                            </div>

                            <!-- Trash Button -->
                            <button onclick="updateQuantity(${key}, -999)" class="w-10 h-10 flex items-center justify-center text-gray-400 hover:text-white hover:bg-red-500 rounded-full transition transform hover:scale-110 flex-shrink-0">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
            subtotalEl.innerText = 'RM ' + totalCost.toFixed(2);
            totalEl.innerText = 'RM ' + totalCost.toFixed(2);
            selectAllCheckbox.checked = selectedCount === cart.length;

            if (selectedCount === 0) {
                checkoutBtn.classList.add('opacity-50', 'pointer-events-none');
                checkoutBtn.href = "#";
            } else {
                checkoutBtn.classList.remove('opacity-50', 'pointer-events-none');
                checkoutBtn.href = "checkout.php";
            }

            updateCartBadge(totalItems);
        }

        // Toggle whether a single cart item is included in the next checkout
        // toggle one item selected
        function toggleSelect(key, isSelected) {
            if (window.IMVIDIA_LOGGED_IN) {
                cartActionRequest('set_selected', { product_id: key, selected: isSelected ? '1' : '0' }).then(loadCart);
                return;
            }

            let cart = JSON.parse(localStorage.getItem(window.IMVIDIA_CART_KEY)) || [];
            if (cart[key]) {
                cart[key].selected = isSelected;
                localStorage.setItem(window.IMVIDIA_CART_KEY, JSON.stringify(cart));
                loadCart();
            }
        }

        // Select or deselect every item in the cart at once
        // toggle all items selected
        function toggleSelectAll(isSelected) {
            if (window.IMVIDIA_LOGGED_IN) {
                cartActionRequest('set_all_selected', { selected: isSelected ? '1' : '0' }).then(loadCart);
                return;
            }

            let cart = JSON.parse(localStorage.getItem(window.IMVIDIA_CART_KEY)) || [];
            cart.forEach(item => item.selected = isSelected);
            localStorage.setItem(window.IMVIDIA_CART_KEY, JSON.stringify(cart));
            loadCart();
        }

        // Change quantity (if dropped to 0, it removes it)
        // change item quantity
        function updateQuantity(key, delta) {
            if (window.IMVIDIA_LOGGED_IN) {
                const item = serverCart.find(i => i.product_id === key);
                const currentQty = item ? item.quantity : 0;
                const newQty = delta <= -999 ? 0 : currentQty + delta;
                cartActionRequest('set_quantity', { product_id: key, quantity: newQty }).then(loadCart);
                return;
            }

            let cart = JSON.parse(localStorage.getItem(window.IMVIDIA_CART_KEY)) || [];

            if (cart[key]) {
                // Handle older cart arrays that might not have a quantity variable yet
                if(!cart[key].quantity) cart[key].quantity = 1;

                cart[key].quantity += delta;

                if (cart[key].quantity <= 0) {
                    cart.splice(key, 1);
                }

                localStorage.setItem(window.IMVIDIA_CART_KEY, JSON.stringify(cart));
                loadCart();
            }
        }

        // update cart badge count
        function updateCartBadge(count) {
            const badge = document.getElementById('cart-badge');
            if(badge) {
                badge.innerText = count;
                // Pop animation on change
                badge.classList.add('scale-150');
                setTimeout(() => badge.classList.remove('scale-150'), 200);
            }
        }

        // Initialize cart on load
        document.addEventListener('DOMContentLoaded', loadCart);
    </script>

</body>
</html>
