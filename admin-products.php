<?php
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Require admin login
requireAdminLogin();

// Get admin data for navbar
$admin_data = getAdminUserData();

$message = '';
$msg_type = '';

// Check if we're in edit mode
$edit_mode = false;
$product_to_edit = null;
$existing_gallery_images = [];
if (isset($_GET['edit'])) {
    $product_id = intval($_GET['edit']);
    $edit_query = "SELECT * FROM product WHERE product_id = $product_id";
    $edit_result = mysqli_query($conn, $edit_query);
    if ($edit_result && mysqli_num_rows($edit_result) > 0) {
        $product_to_edit = mysqli_fetch_assoc($edit_result);
        $edit_mode = true;
        
        // Fetch existing gallery images
        $gallery_query = "SELECT * FROM product_gallery WHERE product_id = $product_id ORDER BY id ASC";
        $gallery_result = mysqli_query($conn, $gallery_query);
        if ($gallery_result && mysqli_num_rows($gallery_result) > 0) {
            while ($gallery = mysqli_fetch_assoc($gallery_result)) {
                $existing_gallery_images[] = $gallery;
            }
        }
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is a delete request
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['product_id'])) {
        $product_id = intval($_POST['product_id']);
        $delete_query = "DELETE FROM product WHERE product_id = $product_id";
        
        if (mysqli_query($conn, $delete_query)) {
            $message = "Product deleted successfully!";
            $msg_type = "success";
        } else {
            $message = "Error deleting product: " . mysqli_error($conn);
            $msg_type = "error";
        }
    } else {
        // Add or update product
        $name = mysqli_real_escape_string($conn, $_POST['product_name'] ?? '');
        $price = floatval($_POST['product_price'] ?? 0);
        $category = mysqli_real_escape_string($conn, $_POST['product_category'] ?? 'Uncategorized');
        $stock = intval($_POST['product_stock'] ?? 0);
        $desc = mysqli_real_escape_string($conn, $_POST['product_desc'] ?? '');
        
        $image_url = '';
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

        // Handle image upload
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $upload_result = uploadToS3($_FILES['product_image'], 'products/prod_', time() . '_' . rand(100,999));
            
            if ($upload_result['success']) {
                $image_url = $upload_result['url'];
            } else {
                $message = $upload_result['error'];
                $msg_type = "error";
            }
        }

        if ($msg_type !== "error") {
            if ($product_id > 0) {
                // Update existing product
                if (!empty($image_url)) {
                    $update_query = "UPDATE product SET name='$name', description='$desc', price=$price, category='$category', stock_quantity=$stock, image_url='" . mysqli_real_escape_string($conn, $image_url) . "' WHERE product_id=$product_id";
                } else {
                    $update_query = "UPDATE product SET name='$name', description='$desc', price=$price, category='$category', stock_quantity=$stock WHERE product_id=$product_id";
                }
                
                if (mysqli_query($conn, $update_query)) {
                    // Handle gallery images on update
                    if (isset($_POST['deleted_gallery_ids']) && !empty($_POST['deleted_gallery_ids'])) {
                        $deleted_ids = explode(',', $_POST['deleted_gallery_ids']);
                        foreach ($deleted_ids as $del_id) {
                            $del_id = intval($del_id);
                            mysqli_query($conn, "DELETE FROM product_gallery WHERE id = $del_id AND product_id = $product_id");
                        }
                    }
                    
                    // Upload new gallery images if any
                    if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'])) {
                        $file_count = count($_FILES['gallery_images']['name']);
                        for ($i = 0; $i < $file_count; $i++) {
                            if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_OK) {
                                $file = [
                                    'name' => $_FILES['gallery_images']['name'][$i],
                                    'type' => $_FILES['gallery_images']['type'][$i],
                                    'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i],
                                    'error' => $_FILES['gallery_images']['error'][$i],
                                    'size' => $_FILES['gallery_images']['size'][$i]
                                ];
                                
                                $upload_result = uploadToS3($file, 'products/gallery/gallery_', time() . '_' . rand(1000, 9999) . '_' . $i);
                                if ($upload_result['success']) {
                                    $gallery_image_url = mysqli_real_escape_string($conn, $upload_result['url']);
                                    mysqli_query($conn, "INSERT INTO product_gallery (product_id, image_url, created_at) VALUES ($product_id, '$gallery_image_url', NOW())");
                                }
                            }
                        }
                    }
                    
                    $message = "Product updated successfully!";
                    $msg_type = "success";
                    $edit_mode = false;
                } else {
                    $message = "Database error: " . mysqli_error($conn);
                    $msg_type = "error";
                }
            } else {
                // Insert new product
                $insert_query = "INSERT INTO product (name, description, price, category, stock_quantity, image_url, admin_id, created_at) 
                                 VALUES ('$name', '$desc', '$price', '$category', '$stock', '" . mysqli_real_escape_string($conn, $image_url) . "', " . $_SESSION['user_id'] . ", NOW())";
                
                if (mysqli_query($conn, $insert_query)) {
                    $new_product_id = mysqli_insert_id($conn);
                    
                    // Upload gallery images for new product
                    if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'])) {
                        $file_count = count($_FILES['gallery_images']['name']);
                        for ($i = 0; $i < $file_count; $i++) {
                            if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_OK) {
                                $file = [
                                    'name' => $_FILES['gallery_images']['name'][$i],
                                    'type' => $_FILES['gallery_images']['type'][$i],
                                    'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i],
                                    'error' => $_FILES['gallery_images']['error'][$i],
                                    'size' => $_FILES['gallery_images']['size'][$i]
                                ];
                                
                                $upload_result = uploadToS3($file, 'products/gallery/gallery_', time() . '_' . rand(1000, 9999) . '_' . $i);
                                if ($upload_result['success']) {
                                    $gallery_image_url = mysqli_real_escape_string($conn, $upload_result['url']);
                                    mysqli_query($conn, "INSERT INTO product_gallery (product_id, image_url, created_at) VALUES ($new_product_id, '$gallery_image_url', NOW())");
                                }
                            }
                        }
                    }
                    
                    $message = "Product added successfully!";
                    $msg_type = "success";
                } else {
                    $message = "Database error: " . mysqli_error($conn);
                    $msg_type = "error";
                }
            }
        }
    }
}

// Fetch all products
$sort = $_GET['sort'] ?? 'newest';
$products = [];

switch ($sort) {
    case 'price_low':
        $products_query = "SELECT * FROM product ORDER BY price ASC";
        break;
    case 'price_high':
        $products_query = "SELECT * FROM product ORDER BY price DESC";
        break;
    case 'name_asc':
        $products_query = "SELECT * FROM product ORDER BY name ASC";
        break;
    case 'name_desc':
        $products_query = "SELECT * FROM product ORDER BY name DESC";
        break;
    default: // 'newest'
        $products_query = "SELECT * FROM product ORDER BY created_at DESC";
}

$products_result = mysqli_query($conn, $products_query);
if ($products_result && mysqli_num_rows($products_result) > 0) {
    while ($product = mysqli_fetch_assoc($products_result)) {
        $products[] = $product;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Products - Admin Panel</title>
    <?php include 'includes/head.php'; ?>
    <script src="https://cdn.tiny.cloud/1/be8dfp6y9j7hrwecamdcd0qll0us7grftmz5xjf4sb32mcqg/tinymce/8/tinymce.min.js" referrerpolicy="origin" crossorigin="anonymous"></script>
</head>

<body class="bg-fixed bg-gray-50 text-gray-800 flex h-screen overflow-hidden dark:bg-slate-950 dark:text-gray-100" style="background-image: radial-gradient(circle, rgba(156, 163, 175, 0.2) 2.5px, transparent 2.5px); background-size: 40px 40px;">

    <?php include 'includes/navbar-admin.php'; ?>

        <main class="flex-1 overflow-y-auto p-6 lg:p-8">
            <div class="max-w-6xl mx-auto relative">
                
                <div id="productListView" class="block animate-fade-in-up">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Products</h1>
                            <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Manage your catalog and inventory.</p>
                        </div>
                        <button onclick="showAddProductForm()" class="px-4 py-2.5 bg-imvidia hover:bg-imvidia-dark text-white rounded-lg shadow-md transition transform hover:-translate-y-0.5 flex items-center text-sm font-bold">
                            <i class="fa-solid fa-plus mr-2"></i> Add Product
                        </button>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="mb-6 px-4 py-3 rounded-lg text-sm font-medium text-center <?php echo $msg_type === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Sorting Controls -->
                    <div class="mb-6 flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Sort by:</span>
                            <select id="sortSelect" onchange="updateSort()" class="px-4 py-2.5 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm dark:bg-slate-800 dark:text-white transition cursor-pointer bg-white dark:bg-slate-800">
                                <option value="newest" <?php echo ($sort === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                                <option value="name_asc" <?php echo ($sort === 'name_asc') ? 'selected' : ''; ?>>Name (A-Z)</option>
                                <option value="name_desc" <?php echo ($sort === 'name_desc') ? 'selected' : ''; ?>>Name (Z-A)</option>
                                <option value="price_low" <?php echo ($sort === 'price_low') ? 'selected' : ''; ?>>Price (Low to High)</option>
                                <option value="price_high" <?php echo ($sort === 'price_high') ? 'selected' : ''; ?>>Price (High to Low)</option>
                            </select>
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            <?php echo count($products); ?> product<?php echo count($products) !== 1 ? 's' : ''; ?>
                        </div>
                    </div>

                    <?php if (empty($products)): ?>
                        <!-- Empty State -->
                        <div class="flex flex-col items-center justify-center py-24 bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-dashed border-gray-300 dark:border-slate-700">
                            <a href="https://www.google.com/logos/2010/pacman10-i.html" target="_blank" rel="noopener noreferrer" title="A blue ghost...">
                                <i class="fa-solid fa-ghost text-6xl text-gray-300 dark:text-slate-600 mb-4 hover:text-imvidia duration-300 hover:scale-110 text-imvidia transition transform"></i>
                            </a>
                            <h3 class="text-2xl font-bold text-gray-500 dark:text-gray-400">Nothing here just yet...</h3>
                            <p class="text-gray-400 dark:text-gray-500 mt-2 text-sm">Products will appear here once you add them.</p>
                        </div>
                    <?php else: ?>
                        <!-- Products Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($products as $product): ?>
                                <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 overflow-hidden hover:shadow-md transition">
                                    <!-- Product Image -->
                                    <div class="relative h-48 bg-gray-100 dark:bg-slate-800 overflow-hidden group">
                                        <?php if (!empty($product['image_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-full object-contain group-hover:scale-105 transition duration-300">
                                        <?php else: ?>
                                            <div class="flex items-center justify-center h-full">
                                                <i class="fa-solid fa-image text-gray-300 dark:text-slate-700 text-4xl"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Product Info -->
                                    <div class="p-4 space-y-3">
                                        <div>
                                            <h3 class="font-bold text-gray-900 dark:text-white text-lg truncate"><?php echo htmlspecialchars($product['name']); ?></h3>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 capitalize"><?php echo htmlspecialchars($product['category']); ?></p>
                                        </div>

                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">Price</p>
                                                <p class="text-lg font-bold text-imvidia">RM <?php echo number_format($product['price'], 2); ?></p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">Stock</p>
                                                <p class="text-lg font-bold text-gray-900 dark:text-white"><?php echo $product['stock_quantity']; ?></p>
                                            </div>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="flex gap-3 pt-2 border-t border-gray-100 dark:border-slate-700">
                                            <button onclick="editProduct(<?php echo $product['product_id']; ?>)" class="flex-1 py-2 px-3 bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/30 transition text-sm font-medium flex items-center justify-center">
                                                <i class="fa-solid fa-pencil mr-1.5"></i> Edit
                                            </button>
                                            <button onclick="deleteProduct(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')" class="flex-1 py-2 px-3 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/30 transition text-sm font-medium flex items-center justify-center">
                                                <i class="fa-solid fa-trash mr-1.5"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="addProductView" class="hidden animate-fade-in-up">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $edit_mode ? 'Edit Product' : 'Add New Product'; ?></h1>
                            <p class="text-gray-500 dark:text-gray-400 text-sm mt-1"><?php echo $edit_mode ? 'Update product information.' : 'Create a new product listing for the catalog.'; ?></p>
                        </div>
                        <button type="button" onclick="showProductList()" class="px-4 py-2 bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-slate-700 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 shadow-sm transition flex items-center text-sm font-medium">
                            <i class="fa-solid fa-arrow-left mr-2"></i> Cancel
                        </button>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="mb-6 px-4 py-3 rounded-lg text-sm font-medium text-center <?php echo $msg_type === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
                            <?php echo $message; ?>
                        </div>
                        <?php if ($msg_type === 'success'): ?>
                            <script>
                                
                                document.addEventListener('DOMContentLoaded', function() {
                                    setTimeout(function() {
                                        showProductList();
                                    }, 1500);
                                });
                            </script>
                        <?php endif; ?>
                    <?php endif; ?>

                    <form id="addProductForm" method="POST" enctype="multipart/form-data" onsubmit="handleFormSubmit(event)" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <?php if ($edit_mode && $product_to_edit): ?>
                            <input type="hidden" name="product_id" value="<?php echo $product_to_edit['product_id']; ?>">
                            <input type="hidden" id="existing-image-url" value="<?php echo !empty($product_to_edit['image_url']) ? htmlspecialchars($product_to_edit['image_url']) : ''; ?>">
                        <?php else: ?>
                            <input type="hidden" id="existing-image-url" value="">
                        <?php endif; ?>
                        
                        <div class="lg:col-span-2 space-y-6">
                            
                            <div class="bg-white dark:bg-slate-900 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 border-b border-gray-100 dark:border-slate-800 pb-3">Basic Information</h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Product Name <span class="text-red-500">*</span></label>
                                        <input type="text" name="product_name" value="<?php echo $edit_mode && $product_to_edit ? htmlspecialchars($product_to_edit['name']) : ''; ?>" required class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm dark:bg-slate-800 dark:text-white transition" placeholder="e.g. ImVidia Pro Hairdryer">
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category <span class="text-red-500">*</span></label>
                                            <select name="product_category" required class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm dark:bg-slate-800 dark:text-white transition cursor-pointer">
                                                <option value="" disabled <?php echo !($edit_mode && $product_to_edit) ? 'selected' : ''; ?>>Select a category...</option>
                                                <option value="kitchen" <?php echo ($edit_mode && $product_to_edit && $product_to_edit['category'] === 'kitchen') ? 'selected' : ''; ?>>Kitchen Appliances</option>
                                                <option value="audio" <?php echo ($edit_mode && $product_to_edit && $product_to_edit['category'] === 'audio') ? 'selected' : ''; ?>>Audio Visual</option>
                                                <option value="portable" <?php echo ($edit_mode && $product_to_edit && $product_to_edit['category'] === 'portable') ? 'selected' : ''; ?>>Portable Devices</option>
                                                <option value="personal" <?php echo ($edit_mode && $product_to_edit && $product_to_edit['category'] === 'personal') ? 'selected' : ''; ?>>Personal Care</option>
                                                <option value="home" <?php echo ($edit_mode && $product_to_edit && $product_to_edit['category'] === 'home') ? 'selected' : ''; ?>>Home Appliances</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Brand</label>
                                            <input type="text" value="ImVidia Original" class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm dark:bg-slate-800 dark:text-gray-400 transition" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white dark:bg-slate-900 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 border-b border-gray-100 dark:border-slate-800 pb-3">Product Description</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Describe the product, add tables, insert inline images, and customise the description fully.</p>
                                
                                <textarea id="product-editor" name="product_desc" placeholder="Enter product description..."><?php echo $edit_mode && $product_to_edit ? htmlspecialchars($product_to_edit['description']) : ''; ?></textarea>
                            </div>
                        </div>

                        <div class="space-y-6">
                            
                            <div class="bg-white dark:bg-slate-900 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 border-b border-gray-100 dark:border-slate-800 pb-3">Pricing, Stock & Policies</h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Selling Price (RM) <span class="text-red-500">*</span></label>
                                        <div class="relative flex items-center">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">RM</span>
                                            </div>
                                            <input name="product_price" type="number" id="selling-price" step="any" value="<?php echo $edit_mode && $product_to_edit ? $product_to_edit['price'] : ''; ?>" onblur="if(this.value) this.value = parseFloat(this.value).toFixed(2)" onkeydown="if(event.key==='ArrowUp'){this.value=(parseFloat(this.value||0)+1).toFixed(2);event.preventDefault();} if(event.key==='ArrowDown'){this.value=Math.max(0, parseFloat(this.value||0)-1).toFixed(2);event.preventDefault();}" required class="hide-spinner w-full pl-10 pr-20 py-2.5 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm dark:bg-slate-800 dark:text-white transition" placeholder="0.00">
                                            <div class="absolute inset-y-0 right-1.5 flex items-center space-x-1">
                                                <button type="button" onclick="let el=document.getElementById('selling-price'); el.value=Math.max(0, parseFloat(el.value || 0) - 1).toFixed(2);" class="w-7 h-7 flex items-center justify-center bg-gray-100 dark:bg-slate-700 rounded hover:text-imvidia transition cursor-pointer text-gray-500 dark:text-gray-400">
                                                    <i class="fa-solid fa-minus text-xs"></i>
                                                </button>
                                                <button type="button" onclick="let el=document.getElementById('selling-price'); el.value=(parseFloat(el.value || 0) + 1).toFixed(2);" class="w-7 h-7 flex items-center justify-center bg-gray-100 dark:bg-slate-700 rounded hover:text-imvidia transition cursor-pointer text-gray-500 dark:text-gray-400">
                                                    <i class="fa-solid fa-plus text-xs"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stock Quantity</label>
                                        <div class="relative flex items-center">
                                            <input name="product_stock" type="number" id="stock-qty" value="<?php echo $edit_mode && $product_to_edit ? $product_to_edit['stock_quantity'] : '10'; ?>" min="0" onkeydown="if(event.key==='ArrowUp'){this.value=parseInt(this.value||0)+1;event.preventDefault();} if(event.key==='ArrowDown'){this.value=Math.max(0, parseInt(this.value||0)-1);event.preventDefault();}" class="hide-spinner w-full px-4 pr-20 py-2.5 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm dark:bg-slate-800 dark:text-white transition">
                                            <div class="absolute inset-y-0 right-1.5 flex items-center space-x-1">
                                                <button type="button" onclick="let el=document.getElementById('stock-qty'); el.value=Math.max(0, parseInt(el.value || 0) - 1);" class="w-7 h-7 flex items-center justify-center bg-gray-100 dark:bg-slate-700 rounded hover:text-imvidia transition cursor-pointer text-gray-500 dark:text-gray-400">
                                                    <i class="fa-solid fa-minus text-xs"></i>
                                                </button>
                                                <button type="button" onclick="let el=document.getElementById('stock-qty'); el.value=parseInt(el.value || 0) + 1;" class="w-7 h-7 flex items-center justify-center bg-gray-100 dark:bg-slate-700 rounded hover:text-imvidia transition cursor-pointer text-gray-500 dark:text-gray-400">
                                                    <i class="fa-solid fa-plus text-xs"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Delivery Time</label>
                                        <input type="text" value="1-2 Business Days" class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm dark:bg-slate-800 dark:text-white transition">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Warranty Period</label>
                                        <input type="text" value="1 Year Included" class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm dark:bg-slate-800 dark:text-white transition">
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white dark:bg-slate-900 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 border-b border-gray-100 dark:border-slate-800 pb-3">Product Thumbnail <span class="text-red-500">*</span></h3>
                                
                                <!-- Upload Badge (shown when no thumbnail) -->
                                <div id="image-dropzone" class="border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-lg p-8 flex flex-col items-center justify-center text-center cursor-pointer hover:border-imvidia dark:hover:border-imvidia bg-gray-50 dark:bg-slate-800/50 transition-colors group">
                                    <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-400 dark:text-gray-500 group-hover:text-imvidia mb-3 transition-colors"></i>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Click to upload or drag and drop</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">PNG, JPG, GIF, or WebP (max. 5MB)</span>
                                </div>
                                
                                <!-- Thumbnail Display (shown when image exists) -->
                                <div id="image-preview-container" class="hidden relative w-full aspect-square rounded-lg overflow-hidden border border-gray-200 dark:border-slate-700 shadow-sm group bg-gray-100 dark:bg-slate-800">
                                    <img id="thumbnail-img" src="" alt="Product Thumbnail" class="w-full h-full object-contain">
                                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center backdrop-blur-sm">
                                        <button type="button" id="delete-thumbnail-btn" class="w-10 h-10 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition transform hover:scale-110 shadow-lg" title="Delete Thumbnail">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <input name="product_image" type="file" id="thumbnail-upload" accept="image/*" class="hidden">
                            </div>

                            <div class="bg-white dark:bg-slate-900 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2 border-b border-gray-100 dark:border-slate-800 pb-3">Gallery Images</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Add up to 5 gallery images.</p>
                                
                                <!-- Upload Badge for Gallery -->
                                <div id="gallery-dropzone" class="border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-lg p-6 flex flex-col items-center justify-center text-center cursor-pointer hover:border-imvidia dark:hover:border-imvidia bg-gray-50 dark:bg-slate-800/50 transition-colors group">
                                    <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-400 dark:text-gray-500 group-hover:text-imvidia mb-3 transition-colors"></i>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Click to upload or drag and drop</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">PNG, JPG, GIF, or WebP (max. 5MB each)</span>
                                </div>
                                
                                <!-- Existing gallery images (edit mode) -->
                                <?php if ($edit_mode && !empty($existing_gallery_images)): ?>
                                        <div id="existing-gallery-container" class="grid grid-cols-3 sm:grid-cols-4 gap-3">
                                            <?php foreach ($existing_gallery_images as $idx => $gal_img): ?>
                                                <div class="relative aspect-square rounded-lg overflow-hidden border border-gray-200 dark:border-slate-700 shadow-sm group bg-gray-100 dark:bg-slate-800" data-gallery-id="<?php echo $gal_img['id']; ?>">
                                                    <img src="<?php echo htmlspecialchars($gal_img['image_url']); ?>" alt="Gallery Image <?php echo $idx + 1; ?>" class="w-full h-full object-cover">
                                                    <div class="absolute top-1 right-1 bg-gray-800 text-white text-xs px-2 py-1 rounded"><?php echo $idx + 1; ?>/5</div>
                                                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center backdrop-blur-sm">
                                                        <button type="button" class="delete-gallery-btn w-8 h-8 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition transform hover:scale-110 shadow-lg" data-gallery-id="<?php echo $gal_img['id']; ?>" title="Delete Image">
                                                            <i class="fa-solid fa-trash-can text-sm"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- New uploaded gallery images preview -->
                                <div id="gallery-preview-container" class="grid grid-cols-3 sm:grid-cols-4 gap-3 mt-4"></div>
                                
                                <input name="gallery_images[]" type="file" id="gallery-upload" accept="image/*" multiple class="hidden">
                                <input type="hidden" id="deleted-gallery-ids" name="deleted_gallery_ids" value="">
                            </div>

                            <button type="submit" class="w-full py-3 px-4 bg-imvidia hover:bg-imvidia-dark text-white rounded-lg shadow-md font-bold text-sm transition transform hover:-translate-y-0.5 flex items-center justify-center">
                                <i class="fa-solid fa-floppy-disk mr-2"></i> <?php echo $edit_mode ? 'Save Changes' : 'Publish Product'; ?>
                            </button>
                        </div>
                    </form>
                </div> 

            </div>
        </main>
    </div>

    <script>
        const productList = document.getElementById('productListView');
        const addProductForm = document.getElementById('addProductView');
        let isTinyMCEInitialized = false;

        function showAddProductForm() {
            productList.classList.remove('block');
            productList.classList.add('hidden');
            
            addProductForm.classList.remove('hidden');
            addProductForm.classList.add('block');

            if (!isTinyMCEInitialized) {
                setTimeout(function() {
                    initTinyMCE();
                    isTinyMCEInitialized = true;
                }, 100);
            }
        }

        function showProductList() {
            addProductForm.classList.remove('block');
            addProductForm.classList.add('hidden');
            
            productList.classList.remove('hidden');
            productList.classList.add('block');
        }

        function initTinyMCE() {
            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#cbd5e1' : '#475569';
            const bgColor = isDark ? '#0f172a' : '#ffffff';
            const tableBorderColor = isDark ? '#334155' : '#e2e8f0';

            tinymce.init({
                selector: '#product-editor',
                height: 400,
                menubar: false, 
                statusbar: false, 
                promotion: false, 

                plugins: [
                    'anchor', 'autolink', 'charmap', 'codesample', 'emoticons', 'image', 'link', 'lists', 'media', 'searchreplace', 'table', 'visualblocks', 'wordcount'
                ],
                
                toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
                
                skin: isDark ? 'oxide-dark' : 'oxide',
                content_css: isDark ? 'dark' : 'default',
                
                content_style: `
                    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
                    body { 
                        font-family: 'Inter', sans-serif; 
                        font-size: 14px; 
                        color: ${textColor}; 
                        background-color: ${bgColor};
                        line-height: 1.6;
                    }
                    p { margin-bottom: 1rem; }
                    table { border-collapse: collapse; width: 100%; border-radius: 8px; overflow: hidden; }
                    table td, table th { border: 1px solid ${tableBorderColor}; padding: 10px; }
                `,
                
                branding: false,
                setup: function (editor) {
                    editor.on('change', function () {
                        tinymce.triggerSave(); 
                    });
                }
            });
        }

        function handleFormSubmit(event) {
            tinymce.triggerSave();
            
            // Validate that at least one thumbnail exists
            if (!currentThumbnailFile) {
                event.preventDefault();
                alert('Please upload a product thumbnail before submitting.');
                return;
            }
            
            // Only add file to input if it's a new upload (not an existing image from DB)
            if (currentThumbnailFile && !currentThumbnailFile.isExisting) {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(currentThumbnailFile);
                document.getElementById('thumbnail-upload').files = dataTransfer.files;
            } else {
                // Clear the file input if using existing image
                document.getElementById('thumbnail-upload').value = '';
            }
            
            // Add gallery images to the form
            if (galleryFiles.length > 0) {
                const galleryDataTransfer = new DataTransfer();
                galleryFiles.forEach(file => {
                    galleryDataTransfer.items.add(file);
                });
                document.getElementById('gallery-upload').files = galleryDataTransfer.files;
            }
        }
    </script>

    <script>
        let currentThumbnailFile = null;
        const dropzone = document.getElementById('image-dropzone');
        const fileInput = document.getElementById('thumbnail-upload');
        const previewContainer = document.getElementById('image-preview-container');
        const thumbnailImg = document.getElementById('thumbnail-img');
        const deleteBtn = document.getElementById('delete-thumbnail-btn');

        dropzone.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', (e) => handleFiles(e.target.files));

        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.remove('bg-gray-50', 'dark:bg-slate-800/50', 'border-gray-300', 'dark:border-slate-600');
            dropzone.classList.add('bg-imvidia/10', 'border-imvidia');
        });

        dropzone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropzone.classList.add('bg-gray-50', 'dark:bg-slate-800/50', 'border-gray-300', 'dark:border-slate-600');
            dropzone.classList.remove('bg-imvidia/10', 'border-imvidia');
        });

        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.add('bg-gray-50', 'dark:bg-slate-800/50', 'border-gray-300', 'dark:border-slate-600');
            dropzone.classList.remove('bg-imvidia/10', 'border-imvidia');
            
            if (e.dataTransfer.files.length) {
                handleFiles(e.dataTransfer.files);
            }
        });

        function handleFiles(files) {
            // Only accept one file at a time
            const file = files[0];
            
            if (!file) return;
            
            if (file.size > 5 * 1024 * 1024) {
                alert(`File "${file.name}" is too large. Maximum size is 5MB.`);
                return;
            }
            
            if (!file.type.startsWith('image/')) {
                alert(`File "${file.name}" is not a valid image format.`);
                return;
            }
            
            currentThumbnailFile = file;
            displayThumbnail();
            fileInput.value = '';
        }

        function displayThumbnail() {
            if (!currentThumbnailFile) {
                previewContainer.classList.add('hidden');
                dropzone.classList.remove('hidden');
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                thumbnailImg.src = e.target.result;
                previewContainer.classList.remove('hidden');
                dropzone.classList.add('hidden');
            };
            reader.readAsDataURL(currentThumbnailFile);
        }

        deleteBtn.addEventListener('click', (e) => {
            e.preventDefault();
            currentThumbnailFile = null;
            displayThumbnail();
        });

        // Initialize with existing image if in edit mode
        function initializeEditModePreview() {
            const existingImageUrl = document.getElementById('existing-image-url');
            if (existingImageUrl && existingImageUrl.value) {
                thumbnailImg.src = existingImageUrl.value;
                previewContainer.classList.remove('hidden');
                dropzone.classList.add('hidden');
                // Mark that we have an existing image (no file object, but image exists)
                currentThumbnailFile = { isExisting: true };
            }
        }

        // Call on page load if in edit mode
        document.addEventListener('DOMContentLoaded', initializeEditModePreview);
    </script>

    <script>
        let galleryFiles = [];
        let deletedGalleryIds = [];
        const MAX_GALLERY_IMAGES = 5;
        
        const galleryDropzone = document.getElementById('gallery-dropzone');
        const galleryFileInput = document.getElementById('gallery-upload');
        const galleryPreviewContainer = document.getElementById('gallery-preview-container');
        const galleryCounter = document.getElementById('gallery-counter');
        const deletedIdsInput = document.getElementById('deleted-gallery-ids');
        const existingGalleryContainer = document.getElementById('existing-gallery-container');
        
        // Get current count of existing gallery images
        function getCurrentGalleryCount() {
            const existingCount = existingGalleryContainer ? existingGalleryContainer.querySelectorAll('[data-gallery-id]:not(.deleted)').length : 0;
            return existingCount + galleryFiles.length;
        }
        
        function updateGalleryCounter() {
            const count = getCurrentGalleryCount();
            galleryCounter.textContent = `${count} of ${MAX_GALLERY_IMAGES} images`;
            
            // Disable dropzone if max reached
            if (count >= MAX_GALLERY_IMAGES) {
                galleryDropzone.classList.add('opacity-50', 'pointer-events-none');
            } else {
                galleryDropzone.classList.remove('opacity-50', 'pointer-events-none');
            }
        }
        
        galleryDropzone.addEventListener('click', () => {
            if (getCurrentGalleryCount() < MAX_GALLERY_IMAGES) {
                galleryFileInput.click();
            } else {
                alert('Maximum 5 gallery images allowed.');
            }
        });
        
        galleryFileInput.addEventListener('change', (e) => handleGalleryFiles(e.target.files));
        
        galleryDropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            galleryDropzone.classList.remove('bg-gray-50', 'dark:bg-slate-800/50', 'border-gray-300', 'dark:border-slate-600');
            galleryDropzone.classList.add('bg-imvidia/10', 'border-imvidia');
        });
        
        galleryDropzone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            galleryDropzone.classList.add('bg-gray-50', 'dark:bg-slate-800/50', 'border-gray-300', 'dark:border-slate-600');
            galleryDropzone.classList.remove('bg-imvidia/10', 'border-imvidia');
        });
        
        galleryDropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            galleryDropzone.classList.add('bg-gray-50', 'dark:bg-slate-800/50', 'border-gray-300', 'dark:border-slate-600');
            galleryDropzone.classList.remove('bg-imvidia/10', 'border-imvidia');
            
            if (e.dataTransfer.files.length) {
                handleGalleryFiles(e.dataTransfer.files);
            }
        });
        
        function handleGalleryFiles(files) {
            const currentCount = getCurrentGalleryCount();
            const slotsAvailable = MAX_GALLERY_IMAGES - currentCount;
            
            if (slotsAvailable <= 0) {
                alert('Maximum 5 gallery images reached.');
                return;
            }
            
            let filesAdded = 0;
            Array.from(files).forEach(file => {
                if (filesAdded >= slotsAvailable) return;
                
                if (file.size > 5 * 1024 * 1024) {
                    alert(`File "${file.name}" is too large. Maximum size is 5MB.`);
                    return;
                }
                
                if (!file.type.startsWith('image/')) {
                    alert(`File "${file.name}" is not a valid image format.`);
                    return;
                }
                
                galleryFiles.push(file);
                filesAdded++;
            });
            
            renderGalleryPreviews();
            galleryFileInput.value = '';
        }
        
function renderGalleryPreviews() {
    galleryPreviewContainer.innerHTML = '';
    const existingCount = existingGalleryContainer ? existingGalleryContainer.querySelectorAll('[data-gallery-id]:not(.deleted)').length : 0;
    
    galleryFiles.forEach((file, index) => {
        // Generate a synchronous, temporary URL for the image
        const imgUrl = URL.createObjectURL(file);
        
        const previewDiv = document.createElement('div');
        previewDiv.className = "relative aspect-square rounded-lg overflow-hidden border border-gray-200 dark:border-slate-700 shadow-sm group bg-gray-100 dark:bg-slate-800";
        previewDiv.innerHTML = `
            <img src="${imgUrl}" class="w-full h-full object-cover">
            <div class="absolute top-1 right-1 bg-gray-800 text-white text-xs px-2 py-1 rounded">${existingCount + index + 1}/5</div>
            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center backdrop-blur-sm">
                <button type="button" onclick="removeGalleryImage(${index})" class="w-8 h-8 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition transform hover:scale-110 shadow-lg" title="Remove Image">
                    <i class="fa-solid fa-trash-can text-sm"></i>
                </button>
            </div>
        `;
        galleryPreviewContainer.appendChild(previewDiv);
    });
    
    updateGalleryCounter();
}
        
        function removeGalleryImage(index) {
            galleryFiles.splice(index, 1);
            renderGalleryPreviews();
        }
        
        // Handle delete button for existing gallery images
        document.querySelectorAll('.delete-gallery-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const galleryId = btn.getAttribute('data-gallery-id');
                const tile = btn.closest('[data-gallery-id]');
                
                // Add to deleted list
                if (!deletedGalleryIds.includes(galleryId)) {
                    deletedGalleryIds.push(galleryId);
                }
                
                // Mark as deleted visually
                tile.classList.add('deleted', 'opacity-50');
                btn.disabled = true;
                
                // Update hidden input
                deletedIdsInput.value = deletedGalleryIds.join(',');
                
                updateGalleryCounter();
            });
        });
        
        // Initialize counter on page load
        document.addEventListener('DOMContentLoaded', () => {
            updateGalleryCounter();
        });
    </script>

    <!-- Product Management Scripts -->
    <script>
        function updateSort() {
            const sortValue = document.getElementById('sortSelect').value;
            window.location.href = `?sort=${sortValue}`;
        }

        function deleteProduct(productId, productName) {
            showDeleteModal(productId, productName);
        }

        function editProduct(productId) {
            window.location.href = `?edit=${productId}`;
        }

        function showDeleteModal(productId, productName) {
            const modal = document.createElement('div');
            modal.id = 'deleteModal';
            modal.className = 'fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 animate-fade-in';
            modal.innerHTML = `
                <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-gray-100 dark:border-slate-800 max-w-sm mx-4 animate-slide-up">
                    <div class="p-6 border-b border-gray-100 dark:border-slate-800">
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-red-50 dark:bg-red-900/20 mx-auto mb-4">
                            <i class="fa-solid fa-trash text-red-600 dark:text-red-400 text-lg"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white text-center">Delete Product</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center mt-2">Are you sure you want to delete "${productName}"?</p>
                    </div>
                    
                    <div class="p-6 space-y-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400 text-center">This action cannot be undone. The product will be permanently removed from your catalog.</p>
                    </div>

                    <div class="p-6 border-t border-gray-100 dark:border-slate-800 flex gap-3">
                        <button onclick="closeDeleteModal()" class="flex-1 py-2.5 px-4 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition font-medium text-sm">
                            Cancel
                        </button>
                        <button onclick="confirmDelete(${productId})" class="flex-1 py-2.5 px-4 bg-red-500 hover:bg-red-600 text-white rounded-lg transition font-medium text-sm flex items-center justify-center">
                            <i class="fa-solid fa-trash mr-2"></i> Delete
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Close modal when clicking outside
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeDeleteModal();
                }
            });
        }

        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            if (modal) {
                modal.classList.add('animate-fade-out');
                setTimeout(() => modal.remove(), 200);
            }
        }

        function confirmDelete(productId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="product_id" value="${productId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    </script>

    <script>
        function updateLogoForMode() {
            const logo = document.getElementById('navbarLogo');
            if (!logo) return;
            logo.src = document.documentElement.classList.contains('dark') ? 'assets/logo-light.svg' : 'assets/logo.svg';
        }

        function updateDarkToggleIcon() {
            const icon = document.getElementById('dark-mode-icon');
            if (!icon) return;
            icon.className = document.documentElement.classList.contains('dark') ? 'fa-solid fa-sun text-lg' : 'fa-solid fa-moon text-lg';
        }

        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            const isDark = document.documentElement.classList.contains('dark');
            localStorage.setItem('imvidiaDarkMode', isDark ? 'true' : 'false');
            
            updateLogoForMode();
            updateDarkToggleIcon();

            if (isTinyMCEInitialized && tinymce.get('product-editor')) {
                tinymce.remove('#product-editor');
                initTinyMCE();
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const stored = localStorage.getItem('imvidiaDarkMode');
            if (stored === 'true') {
                document.documentElement.classList.add('dark');
            }
            updateLogoForMode();
            updateDarkToggleIcon();
            
            // Auto-open edit form if in edit mode
            <?php if ($edit_mode): ?>
                showAddProductForm();
            <?php endif; ?>
        });
    </script>



</body>
</html>