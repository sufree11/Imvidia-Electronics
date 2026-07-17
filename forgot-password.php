<?php
require_once 'includes/auth.php';
require_once 'includes/db-helpers.php';
require_once 'includes/config.php';
require_once 'includes/password-reset-helpers.php';
require_once 'includes/mailer.php';

ensurePasswordResetSchema();

$error = '';
$sent = false;
$email_value = '';

// handle reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfOrFail();

    $email_value = trim($_POST['email'] ?? '');

    if ($email_value === '' || !filter_var($email_value, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // only customers recover by email admins log in by admin id
        $user = getRow(
            "SELECT id, email, first_name, last_name FROM users WHERE email = ? AND role = 'customer' LIMIT 1",
            [$email_value],
            's'
        );

        if ($user) {
            $token = createPasswordResetToken((int) $user['id']);
            $resetUrl = rtrim(appConfig('APP_URL', ''), '/') . '/reset-password.php?token=' . $token;
            $name = trim($user['first_name'] . ' ' . $user['last_name']);
            sendPasswordResetEmail($user['email'], $name !== '' ? $name : 'there', $resetUrl);
        }

        // uniform response whether or not the email matched (no enumeration)
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Forgot Password - ImVidia</title>
    <?php include 'includes/head.php'; ?>
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen dark:bg-slate-950 dark:text-gray-100">

    <nav class="bg-white shadow-sm sticky top-0 z-50 dark:bg-slate-950">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-6">
            <div class="flex justify-between md:justify-start h-16 items-center w-full">
                <a href="index.php" class="flex-shrink-0 flex items-center cursor-pointer hover:scale-105 transition transform duration-300">
                    <img class="navbar-logo h-10 w-auto mr-2" src="assets/logo.svg" alt="ImVidia Logo">
                    <span class="font-bold text-2xl tracking-tight text-gray-900 dark:text-white">ImVidia<span class="text-imvidia">.</span></span>
                </a>
                <button id="dark-mode-toggle" type="button" class="p-2 rounded-full text-gray-600 hover:text-imvidia transition dark:text-gray-300" aria-label="Toggle dark mode" onclick="toggleDarkMode()">
                    <i id="dark-mode-icon" class="fa-solid fa-moon"></i>
                </button>
            </div>
        </div>
    </nav>

    <main class="flex-grow flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 animate-fade-in-up">

        <div class="max-w-md w-full bg-white dark:bg-slate-900 px-8 pb-8 pt-14 rounded-2xl shadow-xl border border-gray-100 dark:border-slate-700 relative mt-8 z-10">

            <div class="absolute -top-12 left-1/2 transform -translate-x-1/2 w-24 h-24 bg-white dark:bg-slate-800 rounded-full shadow-lg overflow-hidden flex items-center justify-center">
                <img id="bigLogo" src="assets/logo.svg" alt="Logo" class="w-full h-full object-cover">
            </div>

            <?php if ($sent): ?>

                <div class="text-center mb-6">
                    <div class="flex items-center justify-center w-14 h-14 rounded-full bg-green-50 dark:bg-green-900/20 mx-auto mb-4">
                        <i class="fa-solid fa-paper-plane text-green-600 dark:text-green-400 text-xl"></i>
                    </div>
                    <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white mb-2">Check your inbox</h2>
                </div>

                <div class="bg-green-50 text-green-700 p-4 rounded-lg mb-6 text-sm font-medium border border-green-100 text-center dark:bg-green-900/20 dark:text-green-300 dark:border-green-800">
                    If an account exists for that email, we've sent a password reset link. It expires in 30 minutes.
                </div>

                <a href="login.php" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-md text-sm font-bold text-white bg-imvidia hover:bg-imvidia-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-imvidia transition transform hover:-translate-y-0.5">
                    Back to Login
                </a>

            <?php else: ?>

                <div class="text-center mb-6">
                    <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white mb-2">Forgot Password?</h2>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Enter your email and we'll send you a link to reset it.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="bg-red-50 text-red-500 p-3 rounded-lg mb-6 text-sm font-medium border border-red-100 text-center">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="forgot-password.php" class="space-y-6">
                    <?php echo csrfField(); ?>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Email Address
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-envelope text-gray-400"></i>
                            </div>
                            <input type="email" id="email" name="email" autocomplete="email" required
                                   value="<?php echo htmlspecialchars($email_value); ?>"
                                   placeholder="e.g. six@seven.com"
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-imvidia focus:border-imvidia outline-none transition bg-white dark:bg-slate-800 text-gray-900 dark:text-white">
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-md text-sm font-bold text-white bg-imvidia hover:bg-imvidia-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-imvidia transition transform hover:-translate-y-0.5">
                            Send Reset Link
                        </button>
                    </div>
                </form>

                <div class="mt-8 text-center text-sm text-gray-600 dark:text-gray-400 border-t border-gray-100 dark:border-slate-700 pt-6">
                    Remember your password?
                    <a href="login.php" class="font-bold text-imvidia hover:text-imvidia-dark transition">
                        Back to login
                    </a>
                </div>

            <?php endif; ?>

        </div>
    </main>

</body>
</html>
