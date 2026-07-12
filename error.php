<?php
require_once 'db/session.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$user = checkCustomerOrGuest();

$error_code = $_SERVER['REDIRECT_STATUS'] ?? $_GET['code'] ?? 404;
$error_code = intval($error_code);

if ($error_code === 200 || $error_code === 0) {
    $error_code = 404;
}

http_response_code($error_code);

$error_title = "Unknown Error";
$error_message = "Something went wrong on our end. Please try again later.";
$icon = "fa-triangle-exclamation"; 

switch ($error_code) {
    case 400:
        $error_title = "Bad Request";
        $error_message = "The server could not understand your request due to invalid syntax.";
        $icon = "fa-circle-question";
        break;
    case 401:
        $error_title = "Unauthorized Access";
        $error_message = "You need to log in to access this resource or perform this action.";
        $icon = "fa-lock";
        break;
    case 403:
        $error_title = "Access Forbidden";
        $error_message = "You don't have the necessary administrative permissions to view this directory or page.";
        $icon = "fa-shield-halved";
        break;
    case 404:
        $error_title = "Page Not Found";
        $error_message = "The page or product you are looking for might have been removed, had its name changed, or is temporarily unavailable.";
        $icon = "fa-ghost";
        break;
    case 500:
        $error_title = "Internal Server Error";
        $error_message = "Our servers encountered an unexpected condition. The technical team has been notified.";
        $icon = "fa-server";
        break;
    case 502:
        $error_title = "Bad Gateway";
        $error_message = "The server received an invalid response from the upstream server.";
        $icon = "fa-network-wired";
        break;
    case 503:
        $error_title = "Service Unavailable";
        $error_message = "The ImVidia platform is currently down for routine maintenance or is overloaded. Please try again shortly.";
        $icon = "fa-tools";
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo $error_code; ?> <?php echo $error_title; ?> - ImVidia</title>
    <?php include 'includes/head.php'; ?>
</head>

<body class="bg-fixed bg-gray-50 text-gray-800 flex flex-col min-h-screen dark:bg-slate-950 dark:text-gray-100" style="background-image: radial-gradient(circle, rgba(156, 163, 175, 0.2) 2.5px, transparent 2.5px); background-size: 40px 40px;">

    <?php include 'includes/navbar-customer.php'; ?>

    <main class="flex-grow flex items-center justify-center py-20 px-4 sm:px-6 lg:px-8 w-full z-10">
        
        <div class="max-w-2xl w-full text-center bg-white dark:bg-slate-900 rounded-[2.5rem] shadow-xl border border-gray-100 dark:border-slate-800 p-10 sm:p-16 relative overflow-hidden">
            
            <div class="absolute top-0 left-0 w-full h-32 bg-imvidia/10 dark:bg-imvidia/5 rounded-b-[50%] -translate-y-10 pointer-events-none"></div>

            <div class="relative z-10 mx-auto w-24 h-24 bg-gray-50 dark:bg-slate-800 rounded-full flex items-center justify-center mb-8 border-4 border-white dark:border-slate-900 shadow-md">
                <i class="fa-solid <?php echo $icon; ?> text-4xl text-imvidia dark:text-imvidia-light animate-pulse"></i>
            </div>

            <h1 class="text-7xl sm:text-8xl font-black text-gray-900 dark:text-white tracking-tighter mb-4 relative z-10">
                <?php echo $error_code; ?>
            </h1>
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 dark:text-gray-200 mb-4 relative z-10">
                <?php echo $error_title; ?>
            </h2>

            <p class="text-gray-500 dark:text-gray-400 mb-10 max-w-md mx-auto relative z-10">
                <?php echo $error_message; ?>
            </p>

            <div class="flex flex-col sm:flex-row justify-center items-center space-y-4 sm:space-y-0 sm:space-x-4 relative z-10">
                <a href="index.php" class="w-full sm:w-auto px-8 py-3 bg-imvidia hover:bg-imvidia-dark text-white rounded-xl shadow-md font-bold transition transform hover:-translate-y-0.5">
                    <i class="fa-solid fa-house mr-2"></i> Return Home
                </a>
            </div>

        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        function updateCartBadge() {
            let cart = JSON.parse(localStorage.getItem('imvidia_cart')) || [];
            const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
            
            const badge = document.getElementById('cart-badge');
            if (badge) {
                badge.innerText = totalItems;
            }
        }

        document.addEventListener('DOMContentLoaded', updateCartBadge);
    </script>

</body>
</html>