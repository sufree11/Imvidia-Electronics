<?php
require_once 'includes/auth.php';
require_once 'includes/db-helpers.php';
require_once 'includes/helpers.php';
require_once 'includes/review-helpers.php';

ensureReviewSchema();

$customer = checkCustomerOrGuest();
$admin = checkAdminOrGuest();
$user = $admin['is_admin'] ? $admin : $customer;

$wishlist_product_ids = [];
if ($customer['is_logged_in'] && !$admin['is_admin']) {
    $wishlist_rows = getRows("SELECT product_id FROM wishlist WHERE user_id = ?", [$customer['user_id']], 'i');
    $wishlist_product_ids = array_column($wishlist_rows, 'product_id');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>ImVidia</title>
    <?php include 'includes/head.php'; ?>
</head>

<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen dark:bg-slate-950 dark:text-gray-100">
   
    <?php include 'includes/navbar-customer.php'; ?>

    <header class="bg-gray-900 text-white animate-fade-in-up relative overflow-hidden">
        <img src="assets/logo-light.svg" alt="" aria-hidden="true" class="pointer-events-none select-none absolute -left-24 top-1/2 -translate-y-1/2 md:-left-32 w-[32rem] md:w-[48rem] max-w-none opacity-10 z-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 md:py-28 flex flex-col md:flex-row items-center relative z-10">
            <div class="md:w-1/2 mb-10 md:mb-0">
                <h1 class="text-4xl md:text-5xl font-extrabold leading-tight mb-4">
                    Powering <span class="text-imvidia-light">Lives</span>, <br> at a better <span class="text-imvidia">Price</span>.
                </h1>
                <p class="text-lg text-gray-300 mb-8">
                    Delivering innovative, affordable, and user-friendly solutions that improve your home comfort. Power your life with ImVidia today.
                </p>
                <div class="flex space-x-4">
                    <a href="#catalog" id="catalogLink2" class="bg-imvidia hover:bg-imvidia-dark text-white font-bold py-3 px-6 rounded-lg shadow-lg transition transform hover:-translate-y-1">Shop Now</a>
                    <script>
                        document.getElementById('catalogLink2').addEventListener('click', function(e) {
                            e.preventDefault();
                            window.scrollTo({ top: document.getElementById('catalog').offsetTop - 80, behavior: 'smooth' });
                        });
                    </script>
                    <a href="about.php">
                        <button class="bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg shadow-lg transition transform hover:-translate-y-1">About Us</button>
                    </a>
                </div>
            </div>
            <div class="md:w-1/2 w-full flex justify-center relative h-64 md:h-80 mt-10 md:mt-0">
                <img src="assets/hero1.jpg" alt="ImVidia Fryer" class="carousel-image w-1/2 max-w-md rounded-xl shadow-2xl transition-all duration-700 ease-in-out object-cover aspect-video">
                <img src="assets/hero2.jpg" alt="ImVidia Stove" class="carousel-image w-1/2 max-w-md rounded-xl shadow-2xl duration-700 ease-in-out object-cover aspect-video">
                <img src="assets/hero3.jpg" alt="ImVidia Mixer" class="carousel-image w-1/2 max-w-md rounded-xl shadow-2xl duration-700 ease-in-out object-cover aspect-video">
            </div>
        </div>
    </header>

    <?php
    $products_by_category = [
        'kitchen' => [],
        'audio' => [],
        'portable' => [],
        'personal' => [],
        'home' => []
    ];

    $catalog_query = "SELECT * FROM product ORDER BY product_id DESC";
    $catalog_result = mysqli_query($conn, $catalog_query);

    $catalog_product_ids = [];
    if ($catalog_result && mysqli_num_rows($catalog_result) > 0) {
        while ($prod = mysqli_fetch_assoc($catalog_result)) {
            $cat = strtolower($prod['category']);
            if (isset($products_by_category[$cat])) {
                $products_by_category[$cat][] = $prod;
                $catalog_product_ids[] = (int) $prod['product_id'];
            }
        }
    }

    // One batched query for every card's star rating (avoids a query per card).
    $rating_map = getRatingSummariesForProducts($catalog_product_ids);

    // Shared markup for a single product card (catalog grid, search results,
    // and the featured products carousel all render the same card).
    function renderProductCard($prod, $rating_map, $wishlist_product_ids) {
        $prod_id = $prod['product_id'];
        $prod_name = htmlspecialchars($prod['name']);
        $prod_price = number_format($prod['price'], 2);
        $prod_cat = htmlspecialchars($prod['category']);
        $prod_img = !empty($prod['image_url']) ? htmlspecialchars($prod['image_url']) : 'https://ui-avatars.com/api/?name=No+Image&background=f1f5f9&color=94a3b8';
        $prod_wishlisted = in_array($prod_id, $wishlist_product_ids);
        $prod_rating = $rating_map[$prod_id] ?? ['total' => 0, 'average' => 0];
        ob_start();
        ?>
        <div class="group relative bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-4 transition hover:shadow-lg flex flex-col h-80 w-full" data-product-card data-id="<?php echo (int) $prod_id; ?>" data-name="<?php echo htmlspecialchars(strtolower($prod['name'])); ?>" data-category="<?php echo htmlspecialchars(strtolower($prod['category'])); ?>" data-price="<?php echo htmlspecialchars($prod['price']); ?>">
            <button onclick="event.preventDefault(); event.stopPropagation(); toggleWishlist(<?php echo $prod_id; ?>, this.querySelector('i'))" class="absolute top-6 right-6 z-20 w-9 h-9 rounded-full bg-white/90 dark:bg-slate-900/90 shadow-md flex items-center justify-center hover:scale-110 transition" title="Toggle wishlist">
                <i class="<?php echo $prod_wishlisted ? 'fa-solid text-imvidia-light' : 'fa-regular text-gray-400'; ?> fa-heart text-lg"></i>
            </button>
            <div class="w-full h-48 bg-white dark:bg-slate-700 rounded-xl overflow-hidden group-hover:opacity-75 flex items-center justify-center p-2">
                <img src="<?php echo $prod_img; ?>" alt="<?php echo $prod_name; ?>" class="max-w-full max-h-full object-contain drop-shadow-md">
            </div>
            <div class="mt-4 flex justify-between flex-col flex-grow">
                <div>
                    <h3 class="text-sm text-gray-700 dark:text-gray-200 font-bold line-clamp-2">
                        <a href="product.php?id=<?php echo $prod_id; ?>">
                            <span aria-hidden="true" class="absolute inset-0"></span>
                            <?php echo $prod_name; ?>
                        </a>
                    </h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider"><?php echo $prod_cat; ?></p>
                    <?php if ($prod_rating['total'] > 0): ?>
                        <div class="flex items-center gap-1 mt-1.5 relative z-10">
                            <span class="space-x-0.5 leading-none"><?php echo renderStars($prod_rating['average'], 'text-xs'); ?></span>
                            <span class="text-xs text-gray-400">(<?php echo $prod_rating['total']; ?>)</span>
                        </div>
                    <?php endif; ?>
                </div>
                <p class="text-sm font-bold text-gray-900 dark:text-white whitespace-nowrap mt-auto">RM <?php echo $prod_price; ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // Featured products: a random handful of products, rotated 3 at a time.
    $all_products = array_merge(...array_values($products_by_category));
    shuffle($all_products);
    $featured_groups = array_chunk(array_slice($all_products, 0, 9), 3);
    ?>

    <?php if (!empty($featured_groups)): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 sm:pt-24 w-full">
            <div class="mb-10 text-center">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white">Featured Products</h2>
                <p class="text-gray-500 dark:text-gray-400 mt-2">Luck of the draw, these might catch your eye.</p>
            </div>

            <div id="featured-products" class="relative">
                <?php foreach ($featured_groups as $group_idx => $group): ?>
                    <div class="featured-group grid grid-cols-1 sm:grid-cols-3 gap-6 transition-opacity duration-300 ease-in-out <?php echo $group_idx === 0 ? 'opacity-100' : 'hidden opacity-0'; ?>">
                        <?php foreach ($group as $prod): ?>
                            <?php echo renderProductCard($prod, $rating_map, $wishlist_product_ids); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div id="catalog" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24 w-full">

        <div class="mb-10 text-center">
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white">Our Catalog</h2>
            <p class="text-gray-500 dark:text-gray-400 mt-2">Look for what you need.</p>
        </div>

        <form id="globalSearchForm" onsubmit="event.preventDefault(); performGlobalSearch();" class="w-full mb-8 relative z-10">
            <div class="bg-white dark:bg-slate-900/80 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-800 p-4 md:p-6 backdrop-blur-md flex flex-col md:flex-row gap-3 md:items-end">
                <div class="flex-1">
                    <label for="globalSearchInput" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Search products</label>
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" id="globalSearchInput" placeholder="Search by name or category..." class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm dark:bg-slate-800 dark:text-white transition">
                    </div>
                </div>
                <div class="w-full md:w-52">
                    <label for="globalSortSelect" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Sort by</label>
                    <select id="globalSortSelect" onchange="performGlobalSearch()" class="w-full px-3 py-2.5 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm dark:bg-slate-800 dark:text-white transition cursor-pointer">
                        <option value="newest">Newest First</option>
                        <option value="name_asc">Name (A-Z)</option>
                        <option value="name_desc">Name (Z-A)</option>
                        <option value="price_low">Price (Low to High)</option>
                        <option value="price_high">Price (High to Low)</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 md:flex-none px-5 py-2.5 bg-imvidia hover:bg-imvidia-dark text-white rounded-lg shadow-md transition font-bold text-sm flex items-center justify-center">
                        <i class="fa-solid fa-magnifying-glass mr-2"></i> Search
                    </button>
                    <button type="button" onclick="clearGlobalSearch()" class="px-4 py-2.5 bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 text-gray-600 dark:text-gray-300 rounded-lg transition text-sm font-medium">
                        Clear
                    </button>
                </div>
            </div>
            <p id="globalSearchStatus" class="hidden mt-3 text-sm text-gray-500 dark:text-gray-400"></p>
        </form>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 lg:gap-6 mt-12 relative z-10 w-full">
            <button id="cat-kitchen" onclick="toggleCategory('Kitchen Appliances', 'cat-kitchen')" class="category-btn w-full relative pt-4 px-4 pb-8 backdrop-blur-lg flex flex-col items-center justify-center border-2 border-gray-200 dark:border-slate-800 rounded-xl hover:border-imvidia hover:bg-imvidia dark:hover:bg-imvidia hover:shadow-md transition duration-300 group min-h-[140px]">
                <iconify-icon icon="material-symbols-light:kitchen-outline" class="z-10 text-5xl text-gray-500 dark:text-gray-400 transition duration-300 transform group-hover:text-white group-hover:scale-110"></iconify-icon>
                <br>
                <span class="font-medium text-lg text-gray-500 dark:text-gray-400 mb-2 transform group-hover:-translate-y-1 group-hover:text-white duration-300 text-center">Kitchen<br>Appliances</span>
                <i class="fa-solid fa-chevron-down absolute bottom-3 opacity-0 group-hover:opacity-100 group-hover:text-white transition-all duration-300 transform arrow-icon text-sm"></i>
            </button>
            
            <button id="cat-audio" onclick="toggleCategory('Audio Visual', 'cat-audio')" class="category-btn w-full relative pt-4 px-4 pb-8 backdrop-blur-lg flex flex-col items-center justify-center border-2 border-gray-200 dark:border-slate-800 rounded-xl hover:border-imvidia hover:bg-imvidia dark:hover:bg-imvidia hover:shadow-md transition duration-300 group min-h-[140px]">
                <iconify-icon icon="fluent:tv-48-regular" class="text-5xl text-gray-500 dark:text-gray-400 transition duration-300 transform group-hover:text-white group-hover:scale-110"></iconify-icon>
                <br>
                <span class="font-medium text-lg text-gray-500 dark:text-gray-400 mb-2 transform group-hover:-translate-y-1 group-hover:text-white duration-300 text-center">Audio<br>Visual</span>
                <i class="fa-solid fa-chevron-down absolute bottom-3 opacity-0 group-hover:opacity-100 group-hover:text-white transition-all duration-300 transform arrow-icon text-sm"></i>
            </button>
            
            <button id="cat-portable" onclick="toggleCategory('Portable Devices', 'cat-portable')" class="category-btn w-full relative pt-4 px-4 pb-8 backdrop-blur-lg flex flex-col items-center justify-center border-2 border-gray-200 dark:border-slate-800 rounded-xl hover:border-imvidia hover:bg-imvidia dark:hover:bg-imvidia hover:shadow-md transition duration-300 group min-h-[140px]">
                <iconify-icon icon="fluent:phone-laptop-20-regular" class="text-5xl text-gray-500 dark:text-gray-400 transition duration-300 transform group-hover:text-white group-hover:scale-110"></iconify-icon>
                <br>
                <span class="font-medium text-lg text-gray-500 dark:text-gray-400 mb-2 transform group-hover:-translate-y-1 group-hover:text-white duration-300 text-center">Portable<br>Devices</span>
                <i class="fa-solid fa-chevron-down absolute bottom-3 opacity-0 group-hover:opacity-100 group-hover:text-white transition-all duration-300 transform arrow-icon text-sm"></i>
            </button>
            
            <button id="cat-personal" onclick="toggleCategory('Personal Care', 'cat-personal')" class="category-btn w-full relative pt-4 px-4 pb-8 backdrop-blur-lg flex flex-col items-center justify-center border-2 border-gray-200 dark:border-slate-800 rounded-xl hover:border-imvidia hover:bg-imvidia dark:hover:bg-imvidia hover:shadow-md transition duration-300 group min-h-[140px]">
                <iconify-icon icon="ph:hair-dryer-light" class="text-5xl text-gray-500 dark:text-gray-400 transition duration-300 transform group-hover:text-white group-hover:scale-110"></iconify-icon>
                <br>
                <span class="font-medium text-lg text-gray-500 dark:text-gray-400 mb-2 transform group-hover:-translate-y-1 group-hover:text-white duration-300 text-center">Personal<br>Care</span>
                <i class="fa-solid fa-chevron-down absolute bottom-3 opacity-0 group-hover:opacity-100 group-hover:text-white transition-all duration-300 transform arrow-icon text-sm"></i>
            </button>
            
            <button id="cat-home" onclick="toggleCategory('Home Appliances', 'cat-home')" class="category-btn w-full relative pt-4 px-4 pb-8 backdrop-blur-lg flex flex-col items-center justify-center border-2 border-gray-200 dark:border-slate-800 rounded-xl hover:border-imvidia hover:bg-imvidia dark:hover:bg-imvidia hover:shadow-md transition duration-300 group min-h-[140px] col-span-2 md:col-span-1">
                <iconify-icon icon="material-symbols-light:dishwasher-gen-outline-rounded" class="text-5xl text-gray-500 dark:text-gray-400 transition duration-300 transform group-hover:text-white group-hover:scale-110"></iconify-icon>
                <br>
                <span class="font-medium text-lg text-gray-500 dark:text-gray-400 mb-2 transform group-hover:-translate-y-1 group-hover:text-white duration-300 text-center">Home<br>Appliances</span>
                <i class="fa-solid fa-chevron-down absolute bottom-3 opacity-0 group-hover:opacity-100 group-hover:text-white transition-all duration-300 transform arrow-icon text-sm"></i>
            </button>
        </div>

        <div id="productContainer" class="dropdown-wrapper mt-6 relative z-0 w-full">
            <div class="dropdown-inner w-full">

                <div class="w-full bg-white dark:bg-slate-900/80 rounded-[2rem] shadow-sm border border-gray-200 dark:border-slate-800 p-6 md:p-10 backdrop-blur-md">
                    
                    <?php foreach($products_by_category as $cat_key => $products): ?>
                        <div id="grid-<?php echo $cat_key; ?>" class="category-grid hidden w-full">
                            
                            <?php if (count($products) > 0): ?>
                                <?php $is_scrollable_category = count($products) > 6; ?>
                                <div class="<?php echo $is_scrollable_category ? 'max-h-[44rem] overflow-y-auto pr-2' : ''; ?> w-full">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 w-full">
                                    
                                    <?php foreach($products as $prod): ?>
                                        <?php echo renderProductCard($prod, $rating_map, $wishlist_product_ids); ?>
                                    <?php endforeach; ?>
                                    
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="col-span-1 md:col-span-3 flex flex-col items-center justify-center py-16 bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-dashed border-gray-300 dark:border-slate-700 w-full">
                                    <a href="https://www.google.com/logos/2010/pacman10-i.html" target="_blank" rel="noopener noreferrer" title="A blue ghost...">
                                        <i class="fa-solid fa-ghost text-6xl text-gray-300 dark:text-slate-600 mb-4 hover:text-imvidia duration-300 hover:scale-110 transition transform"></i>
                                    </a>
                                    <h3 class="text-2xl font-bold text-gray-500 dark:text-gray-400">Nothing here just yet...</h3>
                                    <p id="text-<?php echo $cat_key; ?>" class="text-gray-400 dark:text-gray-500 mt-2 text-sm">Products will appear here.</p>
                                </div>
                            <?php endif; ?>

                        </div>
                    <?php endforeach; ?>

                    <div id="grid-search" class="category-grid hidden w-full">
                        <div id="search-results-inner" class="grid grid-cols-1 md:grid-cols-3 gap-8 w-full"></div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        // rotate the featured products carousel 3 at a time
        document.addEventListener('DOMContentLoaded', () => {
            const featuredGroups = document.querySelectorAll('#featured-products .featured-group');
            if (featuredGroups.length < 2) return;

            let activeIndex = 0;
            setInterval(() => {
                const current = featuredGroups[activeIndex];
                current.classList.add('opacity-0');

                setTimeout(() => {
                    current.classList.add('hidden');
                    activeIndex = (activeIndex + 1) % featuredGroups.length;

                    const next = featuredGroups[activeIndex];
                    next.classList.remove('hidden');
                    requestAnimationFrame(() => next.classList.remove('opacity-0'));
                }, 300);
            }, 4000);
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const images = document.querySelectorAll('.carousel-image');
            let currentindex = 0;
            let pullingindex = -1;
            let isInitialized = false;
            let intervalId;
            let timeoutId;

            // position carousel images
            function updateCarousel() {
                images.forEach((img, index) => {
                    let baseClasses = 'carousel-image absolute top-1/2 rounded-xl shadow-2xl object-cover aspect-video';
                    if (isInitialized) {
                        baseClasses += ' transition-all duration-700 ease-in-out';
                    }
                    img.className = baseClasses;
                    
                    if (index === pullingindex) {
                        img.classList.add('z-50', 'w-[70%]', 'left-[85%]', '-translate-x-1/2', '-translate-y-[55%]', 'scale-100', 'brightness-100', 'opacity-100', 'rotate-12');
                    } else if (index === currentindex) {
                        img.classList.add('z-40', 'w-[75%]', 'left-1/2', '-translate-x-1/2', '-translate-y-[60%]', 'scale-100', 'brightness-100', 'opacity-100', 'rotate-0');
                    } else if (index === (currentindex + 1) % images.length) {
                        img.classList.add('z-10', 'w-[60%]', 'left-[80%]', '-translate-x-1/2', '-translate-y-[40%]', 'scale-90', 'brightness-50', 'opacity-70', 'rotate-3');
                    } else {
                        img.classList.add('z-20', 'w-[60%]', 'left-[20%]', '-translate-x-1/2', '-translate-y-[40%]', 'scale-90', 'brightness-50', 'opacity-70', '-rotate-3');
                    }
                });
            }

            // auto advance the carousel
            function startCarousel() {
                if (intervalId) clearInterval(intervalId);
                intervalId = setInterval(() => {
                    const nextindex = (currentindex + 1) % images.length;
                    pullingindex = nextindex;
                    currentindex = nextindex;
                    updateCarousel();
                    timeoutId = setTimeout(() => {
                        pullingindex = -1; 
                        updateCarousel();
                    }, 350); 
                }, 3000);
            }

            // pause the carousel
            function stopCarousel() {
                clearInterval(intervalId);
                clearTimeout(timeoutId);
                pullingindex = -1;
                updateCarousel(); 
            }

            updateCarousel();
            setTimeout(() => { isInitialized = true; startCarousel(); }, 15);

            document.addEventListener("visibilitychange", () => {
                if (document.hidden) stopCarousel();
                else startCarousel();
            });
        });
    </script>

    <script>
        let activeCategoryId = null;

        // expand category product grid
        function toggleCategory(categoryName, btnId) {
            clearGlobalSearch();

            const container = document.getElementById('productContainer');
            const clickedBtn = document.getElementById(btnId);
            const clickedArrow = clickedBtn.querySelector('.arrow-icon');

            const categoryKey = btnId.replace('cat-', '');

            document.querySelectorAll('.category-grid').forEach(grid => {
                grid.classList.add('hidden');
            });

            if (activeCategoryId === btnId) {
                container.classList.remove('open');
                clickedArrow.classList.remove('rotate-180', 'text-imvidia', 'opacity-100');
                activeCategoryId = null;
            } else {
                if (activeCategoryId) {
                    const prevBtn = document.getElementById(activeCategoryId);
                    if (prevBtn) {
                        const prevArrow = prevBtn.querySelector('.arrow-icon');
                        if (prevArrow) prevArrow.classList.remove('rotate-180', 'text-imvidia', 'opacity-100');
                    }
                }

                const targetGrid = document.getElementById('grid-' + categoryKey);
                if (targetGrid) {
                    targetGrid.classList.remove('hidden');
                }

                const catText = document.getElementById('text-' + categoryKey);
                if (catText) {
                    const suffix = (categoryName === 'Audio Visual' || categoryName === 'Personal Care') ? ' products will appear here.' : ' will appear here.';
                    catText.innerText = categoryName + suffix;
                }

                container.classList.add('open');
                clickedArrow.classList.add('rotate-180', 'text-imvidia', 'opacity-100');
                activeCategoryId = btnId;

                setTimeout(() => {
                    const catalogTop = document.getElementById('catalog').offsetTop;
                    window.scrollTo({
                        top: catalogTop + 50, 
                        behavior: 'smooth'
                    });
                }, 300);
            }
        }
    </script>

    <script>
        // global search + sort across all categories
        function collectAllProductCards() {
            return Array.from(document.querySelectorAll('.category-grid[id^="grid-"]:not(#grid-search) [data-product-card]'));
        }

        function sortProductCards(cards, sortValue) {
            const sorted = cards.slice();
            switch (sortValue) {
                case 'name_asc':
                    sorted.sort((a, b) => a.dataset.name.localeCompare(b.dataset.name));
                    break;
                case 'name_desc':
                    sorted.sort((a, b) => b.dataset.name.localeCompare(a.dataset.name));
                    break;
                case 'price_low':
                    sorted.sort((a, b) => parseFloat(a.dataset.price || '0') - parseFloat(b.dataset.price || '0'));
                    break;
                case 'price_high':
                    sorted.sort((a, b) => parseFloat(b.dataset.price || '0') - parseFloat(a.dataset.price || '0'));
                    break;
                default: // newest
                    sorted.sort((a, b) => parseInt(b.dataset.id || '0', 10) - parseInt(a.dataset.id || '0', 10));
            }
            return sorted;
        }

        // Reorders the cards already inside an open category grid, in place -
        // used when the sort dropdown changes but there's no search text, so
        // a manually-selected category stays open and just gets re-sorted.
        function sortCategoryInPlace(categoryKey, sortValue) {
            const grid = document.getElementById('grid-' + categoryKey);
            if (!grid) return;

            const cards = Array.from(grid.querySelectorAll('[data-product-card]'));
            if (cards.length === 0) return;

            const inner = cards[0].parentElement;
            sortProductCards(cards, sortValue).forEach(card => inner.appendChild(card));
        }

        function performGlobalSearch() {
            const query = document.getElementById('globalSearchInput').value.trim().toLowerCase();
            const sortValue = document.getElementById('globalSortSelect').value;

            // No search text but a category is open: just re-sort that
            // category's products, leave the category view as-is.
            if (!query && activeCategoryId) {
                document.getElementById('globalSearchStatus').classList.add('hidden');
                document.getElementById('grid-search').classList.add('hidden');
                sortCategoryInPlace(activeCategoryId.replace('cat-', ''), sortValue);
                return;
            }

            // No search text and no category open: sort every product.
            // Search text: filter to matches, then sort - both cases render
            // into the shared search-results grid.
            const allCards = collectAllProductCards();
            const matches = sortProductCards(
                query
                    ? allCards.filter(card => (card.dataset.name || '').includes(query) || (card.dataset.category || '').includes(query))
                    : allCards,
                sortValue
            );

            // Overrides manual category selection while active.
            document.querySelectorAll('.category-btn .arrow-icon').forEach(a => {
                a.classList.remove('rotate-180', 'text-imvidia', 'opacity-100');
            });
            activeCategoryId = null;

            document.querySelectorAll('.category-grid').forEach(grid => grid.classList.add('hidden'));

            const searchGrid = document.getElementById('grid-search');
            const searchInner = document.getElementById('search-results-inner');
            const statusEl = document.getElementById('globalSearchStatus');

            searchInner.innerHTML = '';
            statusEl.classList.remove('hidden');

            if (matches.length === 0) {
                statusEl.textContent = 'No products match your search.';
                searchInner.innerHTML = `
                    <div class="col-span-1 md:col-span-3 flex flex-col items-center justify-center py-16 bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-dashed border-gray-300 dark:border-slate-700 w-full">
                        <i class="fa-solid fa-magnifying-glass text-6xl text-gray-300 dark:text-slate-600 mb-4"></i>
                        <h3 class="text-2xl font-bold text-gray-500 dark:text-gray-400">No matches found</h3>
                        <p class="text-gray-400 dark:text-gray-500 mt-2 text-sm">Try a different search term.</p>
                    </div>`;
            } else {
                statusEl.textContent = query
                    ? `${matches.length} product${matches.length !== 1 ? 's' : ''} found.`
                    : `Showing all ${matches.length} products.`;
                matches.forEach(card => searchInner.appendChild(card.cloneNode(true)));
            }

            searchGrid.classList.remove('hidden');
            document.getElementById('productContainer').classList.add('open');

            setTimeout(() => {
                const catalogTop = document.getElementById('catalog').offsetTop;
                window.scrollTo({ top: catalogTop + 50, behavior: 'smooth' });
            }, 100);
        }

        function clearGlobalSearch() {
            document.getElementById('globalSearchInput').value = '';
            document.getElementById('globalSortSelect').value = 'newest';
            document.getElementById('globalSearchStatus').classList.add('hidden');
            document.getElementById('grid-search').classList.add('hidden');

            if (activeCategoryId) {
                sortCategoryInPlace(activeCategoryId.replace('cat-', ''), 'newest');
            } else {
                document.getElementById('productContainer').classList.remove('open');
            }
        }
    </script>

    <script>
        // refresh guest cart badge
        function updateCartBadge() {
            // Logged-in users get their count server-rendered from the DB
            // cart (includes/navbar-customer.php) - don't stomp it here.
            if (window.IMVIDIA_LOGGED_IN) return;

            let cart = JSON.parse(localStorage.getItem(window.IMVIDIA_CART_KEY)) || [];
            const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);

            const badge = document.getElementById('cart-badge');
            if (badge) {
                badge.innerText = totalItems;
                badge.classList.add('scale-150');
                setTimeout(() => badge.classList.remove('scale-150'), 200);
            }
        }

        // add item to guest cart
        function addToCart(productName, price) {
            let cart = JSON.parse(localStorage.getItem(window.IMVIDIA_CART_KEY)) || [];
            let existingItem = cart.find(item => item.name === productName);
            if (existingItem) {
                existingItem.quantity += 1; 
            } else {
                cart.push({ name: productName, price: price, quantity: 1 });
            }
            localStorage.setItem(window.IMVIDIA_CART_KEY, JSON.stringify(cart));
            updateCartBadge();
            showToast('Added to cart!', 'fa-solid fa-cart-plus');
        }

        document.addEventListener('DOMContentLoaded', updateCartBadge);
    </script>

</body>
</html>