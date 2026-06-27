<?php
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$customer = checkCustomerOrGuest();
$admin = checkAdminOrGuest();
$user = $admin['is_admin'] ? $admin : $customer;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>ImVidia</title>
    <?php include 'includes/head.php'; ?>
</head>

<body class="bg-fixed bg-gray-50 text-gray-800 flex flex-col min-h-screen dark:bg-slate-950 dark:text-gray-100" style="background-image: radial-gradient(circle, rgba(156, 163, 175, 0.2) 2.5px, transparent 2.5px); background-size: 40px 40px;">
   
    <?php include 'includes/navbar-customer.php'; ?>

    <header class="bg-gray-900 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 md:py-28 flex flex-col md:flex-row items-center">
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
                        <button class="bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg shadow-lg transition transform hover:-translate-y-1">Learn More</button>
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

    if ($catalog_result && mysqli_num_rows($catalog_result) > 0) {
        while ($prod = mysqli_fetch_assoc($catalog_result)) {
            $cat = strtolower($prod['category']);
            if (isset($products_by_category[$cat])) {
                $products_by_category[$cat][] = $prod;
            }
        }
    }
    ?>

    <div id="catalog" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24 w-full">
        
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
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 w-full">
                                    
                                    <?php foreach($products as $prod):
                                        $prod_id = $prod['product_id'];
                                        $prod_name = htmlspecialchars($prod['name']);
                                        $prod_price = number_format($prod['price'], 2);
                                        $prod_cat = htmlspecialchars($prod['category']);
                                        $prod_img = !empty($prod['image_url']) ? htmlspecialchars($prod['image_url']) : 'https://ui-avatars.com/api/?name=No+Image&background=f1f5f9&color=94a3b8';
                                    ?>
                                        <div class="group relative bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-4 transition hover:shadow-lg flex flex-col h-80 w-full">
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
                                                </div>
                                                <p class="text-sm font-bold text-gray-900 dark:text-white whitespace-nowrap mt-auto">RM <?php echo $prod_price; ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
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

                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const images = document.querySelectorAll('.carousel-image');
            let currentindex = 0;
            let pullingindex = -1;
            let isInitialized = false;
            let intervalId;
            let timeoutId;

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

        function toggleCategory(categoryName, btnId) {
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
        function updateCartBadge() {
            let cart = JSON.parse(localStorage.getItem('imvidia_cart')) || [];
            const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
            
            const badge = document.getElementById('cart-badge');
            if (badge) {
                badge.innerText = totalItems;
                badge.classList.add('scale-150');
                setTimeout(() => badge.classList.remove('scale-150'), 200);
            }
        }

        function addToCart(productName, price) {
            let cart = JSON.parse(localStorage.getItem('imvidia_cart')) || [];
            let existingItem = cart.find(item => item.name === productName);
            if (existingItem) {
                existingItem.quantity += 1; 
            } else {
                cart.push({ name: productName, price: price, quantity: 1 });
            }
            localStorage.setItem('imvidia_cart', JSON.stringify(cart));
            updateCartBadge();
            alert(`Added 1x ${productName} to your cart!`);
        }

        function viewCart() {
            window.location.href = 'cart.html';
        }

        document.addEventListener('DOMContentLoaded', updateCartBadge);
    </script>

</body>
</html>