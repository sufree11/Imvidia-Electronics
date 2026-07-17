<?php
require_once 'includes/auth.php';
require_once 'includes/db-helpers.php';
require_once 'includes/password-reset-helpers.php';

ensurePasswordResetSchema();

$token = $_POST['token'] ?? $_GET['token'] ?? '';
$error = '';
$success = false;

// re-validate token on both the initial load and the submit
$reset = getValidResetForToken($token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfOrFail();

    if (!$reset) {
        $error = 'This reset link is invalid or has expired.';
    } else {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (strlen($new_password) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            $updated = executeStatement(
                "UPDATE users SET password_hash = ? WHERE id = ?",
                [hashPassword($new_password), (int) $reset['user_id']],
                'si'
            );

            if ($updated) {
                markResetUsed((int) $reset['reset_id']);
                $success = true;
            } else {
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
}

$token_valid = (bool) $reset;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reset Password - ImVidia</title>
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

            <?php if ($success): ?>

                <div class="text-center mb-6">
                    <div class="flex items-center justify-center w-14 h-14 rounded-full bg-green-50 dark:bg-green-900/20 mx-auto mb-4">
                        <i class="fa-solid fa-circle-check text-green-600 dark:text-green-400 text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white mb-2">Password Reset Successful</h2>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Redirecting you to login&hellip;</p>
                </div>

                <a href="login.php" id="continue-login" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-md text-sm font-bold text-white bg-imvidia hover:bg-imvidia-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-imvidia transition transform hover:-translate-y-0.5">
                    Continue to Login
                </a>

                <meta http-equiv="refresh" content="3;url=login.php">
                <script>
                    setTimeout(function () {
                        window.location.href = 'login.php';
                    }, 2500);
                </script>

            <?php elseif (!$token_valid): ?>

                <div class="text-center mb-6">
                    <div class="flex items-center justify-center w-14 h-14 rounded-full bg-red-50 dark:bg-red-900/20 mx-auto mb-4">
                        <i class="fa-solid fa-triangle-exclamation text-red-500 text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white mb-2">Link Invalid or Expired</h2>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">This password reset link is no longer valid. Request a new one below.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="bg-red-50 text-red-500 p-3 rounded-lg mb-6 text-sm font-medium border border-red-100 text-center">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <a href="forgot-password.php" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-md text-sm font-bold text-white bg-imvidia hover:bg-imvidia-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-imvidia transition transform hover:-translate-y-0.5">
                    Request New Link
                </a>

            <?php else: ?>

                <div class="text-center mb-6">
                    <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white mb-2">Reset Password</h2>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Choose a new password for your account.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="bg-red-50 text-red-500 p-3 rounded-lg mb-6 text-sm font-medium border border-red-100 text-center">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form id="resetForm" method="POST" action="reset-password.php" class="space-y-6">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <div>
                        <label for="new-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            New Password
                        </label>
                        <div class="relative">
                            <input type="password" id="new-password" name="new_password" required minlength="8" maxlength="20"
                                   class="w-full pr-11 dark:bg-slate-800 dark:border-slate-600 dark:placeholder:text-slate-400 dark:text-white px-3 py-2 border border-gray-300 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm transition">
                            <?php include 'includes/password-toggle.php'; ?>
                        </div>
                    </div>

                    <div>
                        <label for="confirm-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Confirm Password
                        </label>
                        <div class="relative">
                            <input type="password" id="confirm-password" name="confirm_password" required minlength="8" maxlength="20"
                                   class="w-full pr-11 dark:bg-slate-800 dark:border-slate-600 dark:placeholder:text-slate-400 dark:text-white px-3 py-2 border border-gray-300 rounded-lg focus:ring-imvidia focus:border-imvidia sm:text-sm transition">
                            <?php include 'includes/password-toggle.php'; ?>
                        </div>
                        <p id="password-error" class="text-red-500 text-xs mt-1 font-medium hidden">Passwords do not match!</p>
                    </div>

                    <button type="submit" id="reset-submit-btn" class="w-full py-3 px-4 rounded-lg shadow-md text-sm font-bold text-white bg-imvidia hover:bg-imvidia-dark transition">
                        Reset Password
                    </button>
                </form>

                <script>
                    // reuse register.php's live confirm match pattern
                    const newPassword = document.getElementById('new-password');
                    const confirmPassword = document.getElementById('confirm-password');
                    const pwdError = document.getElementById('password-error');
                    const submitBtn = document.getElementById('reset-submit-btn');

                    function validatePasswords() {
                        if (confirmPassword.value !== '' && newPassword.value !== confirmPassword.value) {
                            pwdError.classList.remove('hidden');
                            confirmPassword.classList.add('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
                            submitBtn.disabled = true;
                            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                        } else {
                            pwdError.classList.add('hidden');
                            confirmPassword.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
                            submitBtn.disabled = false;
                            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                        }
                    }

                    if (newPassword && confirmPassword) {
                        newPassword.addEventListener('input', validatePasswords);
                        confirmPassword.addEventListener('input', validatePasswords);
                    }
                </script>

            <?php endif; ?>

        </div>
    </main>

</body>
</html>
