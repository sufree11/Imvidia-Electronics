<?php
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Require admin login
requireAdminLogin();

// Get admin data for navbar
$admin_data = getAdminUserData();

$message = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = mysqli_real_escape_string($conn, $_POST['product_name'] ?? '');
    $price = floatval($_POST['product_price'] ?? 0);
    $category = mysqli_real_escape_string($conn, $_POST['product_category'] ?? 'Uncategorized');
    $stock = intval($_POST['product_stock'] ?? 0);
    $desc = mysqli_real_escape_string($conn, $_POST['product_desc'] ?? '');
    
    $image_url = '';

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
        $insert_query = "INSERT INTO product (name, description, price, category, stock_quantity, image_url, admin_id, created_at) 
                         VALUES ('$name', '$desc', '$price', '$category', '$stock', '" . mysqli_real_escape_string($conn, $image_url) . "', " . $_SESSION['user_id'] . ", NOW())";
        
        if (mysqli_query($conn, $insert_query)) {
            $message = "Product added successfully!";
            $msg_type = "success";
        } else {
            $message = "Database error: " . mysqli_error($conn);
            $msg_type = "error";
        }
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

                    <div class="flex flex-col items-center justify-center py-24 bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-dashed border-gray-300 dark:border-slate-700">
                        <a href="https://www.google.com/logos/2010/pacman10-i.html" target="_blank" rel="noopener noreferrer" title="A blue ghost...">
                            <i class="fa-solid fa-ghost text-6xl text-gray-300 dark:text-slate-600 mb-4 hover:text-imvidia duration-300 hover:scale-110 transition transform"></i>
                        </a>
                        <h3 class="text-2xl font-bold text-gray-500 dark:text-gray-400">Nothing here just yet...</h3>
                        <p class="text-gray-400 dark:text-gray-500 mt-2 text-sm">Products will appear here once added to the database.</p>
                    </div>
                </div>

                <div id="addProductView" class="hidden animate-fade-in-up">
                    <div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Add New Product</h1>
        <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Create a new product listing for the catalog.</p>
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
            // Automatically switch back to the product list view on success
            document.addEventListener('DOMContentLoaded', function() {
                showProductList();
            });
        </script>
    <?php endif; ?>
<?php endif; ?>

                    <form id="addProductForm" method="POST" enctype="multipart/form-data" onsubmit="handleFormSubmit(event)" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        
                        <div class="lg:col-span-2 space-y-6">
                            
                            <div class="bg-white dark:bg-slate-900 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 border-b border-gray-100 dark:border-slate-800 pb-3">Basic Information</h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Product Name <span class="text-red-500">*</span></label>
                                        <input type="text" name="product_name" required class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm dark:bg-slate-800 dark:text-white transition" placeholder="e.g. ImVidia Pro Hairdryer">
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category <span class="text-red-500">*</span></label>
                                            <select name="product_category" required class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm dark:bg-slate-800 dark:text-white transition cursor-pointer">
                                                <option value="" disabled selected>Select a category...</option>
                                                <option value="kitchen">Kitchen Appliances</option>
                                                <option value="audio">Audio Visual</option>
                                                <option value="portable">Portable Devices</option>
                                                <option value="personal">Personal Care</option>
                                                <option value="home">Home Appliances</option>
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
                                
                                <textarea id="product-editor" name="product_desc" placeholder="Enter product description...">
                                </textarea>
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
                                            <input name="product_price" type="number" id="selling-price" step="any" onblur="if(this.value) this.value = parseFloat(this.value).toFixed(2)" onkeydown="if(event.key==='ArrowUp'){this.value=(parseFloat(this.value||0)+1).toFixed(2);event.preventDefault();} if(event.key==='ArrowDown'){this.value=Math.max(0, parseFloat(this.value||0)-1).toFixed(2);event.preventDefault();}" required class="hide-spinner w-full pl-10 pr-20 py-2.5 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm dark:bg-slate-800 dark:text-white transition" placeholder="0.00">
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
                                            <input name="product_stock" type="number" id="stock-qty" value="10" min="0" onkeydown="if(event.key==='ArrowUp'){this.value=parseInt(this.value||0)+1;event.preventDefault();} if(event.key==='ArrowDown'){this.value=Math.max(0, parseInt(this.value||0)-1);event.preventDefault();}" class="hide-spinner w-full px-4 pr-20 py-2.5 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm dark:bg-slate-800 dark:text-white transition">
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
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 border-b border-gray-100 dark:border-slate-800 pb-3">Gallery Images</h3>
                                
                                <div id="image-dropzone" class="border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-lg p-6 flex flex-col items-center justify-center text-center cursor-pointer hover:border-imvidia dark:hover:border-imvidia bg-gray-50 dark:bg-slate-800/50 transition-colors group">
                                    <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-400 dark:text-gray-500 group-hover:text-imvidia mb-3 transition-colors"></i>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Click to upload or drag and drop</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">SVG, PNG, JPG or GIF (max. 5MB)</span>
                                </div>
                                
                                <input name="product_image" type="file" id="gallery-upload" multiple accept="image/*" class="hidden">
                                
                                <div id="image-preview-container" class="grid grid-cols-3 sm:grid-cols-4 gap-3 mt-4 empty:hidden"></div>
                            </div>

                            <button type="submit" class="w-full py-3 px-4 bg-imvidia hover:bg-imvidia-dark text-white rounded-lg shadow-md font-bold text-sm transition transform hover:-translate-y-0.5 flex items-center justify-center">
                                <i class="fa-solid fa-floppy-disk mr-2"></i> Publish Product
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
            if (galleryFiles.length > 0) {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(galleryFiles[0]); 
                document.getElementById('gallery-upload').files = dataTransfer.files;
            }
        }
    </script>

    <script>
        let galleryFiles = [];
        const dropzone = document.getElementById('image-dropzone');
        const fileInput = document.getElementById('gallery-upload');
        const previewContainer = document.getElementById('image-preview-container');

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
            Array.from(files).forEach(file => {
                if (file.size > 5 * 1024 * 1024) {
                    alert(`File "${file.name}" is too large. Maximum size is 5MB.`);
                    return;
                }
                if (!file.type.startsWith('image/')) {
                    alert(`File "${file.name}" is not a valid image format.`);
                    return;
                }
                galleryFiles.push(file);
                renderPreviews();
            });
            fileInput.value = '';
        }

        function removeGalleryImage(index) {
            galleryFiles.splice(index, 1);
            renderPreviews();
        }

        function renderPreviews() {
            previewContainer.innerHTML = '';
            galleryFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const previewDiv = document.createElement('div');
                    previewDiv.className = "relative aspect-square rounded-lg overflow-hidden border border-gray-200 dark:border-slate-700 shadow-sm group";
                    previewDiv.innerHTML = `
                        <img src="${e.target.result}" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center backdrop-blur-sm">
                            <button type="button" onclick="removeGalleryImage(${index})" class="w-8 h-8 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition transform hover:scale-110 shadow-lg" title="Remove Image">
                                <i class="fa-solid fa-trash-can text-sm"></i>
                            </button>
                        </div>
                    `;
                    previewContainer.appendChild(previewDiv);
                };
                reader.readAsDataURL(file);
            });
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
        });
    </script>

</body>
</html>