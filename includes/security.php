<?php

/**
 * Site-wide security primitives: CSRF protection and routing of every
 * uncaught/fatal error to error.php. Included from includes/auth.php, so it
 * loads on essentially every request (the session is already started there).
 */

/**
 * Returns this session's CSRF token, generating one on first use. The same
 * token is reused for the life of the session and embedded in every form
 * (csrfField) and exposed to JS for AJAX (see includes/head.php).
 */
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden <input> to drop inside any POST form. */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES) . '">';
}

/**
 * True when the request carries a valid CSRF token (from the form field or the
 * X-CSRF-Token header). Uses hash_equals to avoid timing leaks.
 */
function verifyCsrf() {
    $sent = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $real = $_SESSION['csrf_token'] ?? '';
    return $real !== '' && is_string($sent) && hash_equals($real, $sent);
}

/**
 * Guards a state-changing POST. On a missing/invalid token it routes to
 * error.php (403) - or returns a JSON 403 for AJAX endpoints - and stops.
 * No-op for non-POST requests.
 */
function requireCsrfOrFail() {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !verifyCsrf()) {
        renderErrorAndExit(403);
    }
}

/**
 * Whether the current request expects JSON rather than an HTML error page.
 * AJAX endpoints declare themselves by defining AJAX_ENDPOINT before including
 * auth.php.
 */
function isAjaxRequest() {
    return (defined('AJAX_ENDPOINT') && AJAX_ENDPOINT)
        || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

/**
 * Central exit point for every error: sends the status code and either the
 * shared error.php page or a JSON error for AJAX. Guarded so an error while
 * rendering the error page can't recurse.
 */
function renderErrorAndExit($code = 500) {
    if (!empty($GLOBALS['__imvidia_rendering_error'])) {
        exit();
    }
    $GLOBALS['__imvidia_rendering_error'] = true;

    if (!headers_sent()) {
        http_response_code($code);
    }

    if (isAjaxRequest()) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'message' => 'Request failed (' . (int) $code . ').']);
        exit();
    }

    // error.php reads the code from $_GET['code'] (fallback path).
    $_GET['code'] = (int) $code;
    require __DIR__ . '/../error.php';
    exit();
}

/**
 * Installs handlers so uncaught exceptions and fatal errors render error.php
 * instead of a blank page or a stack trace. Non-fatal warnings/notices are
 * logged, not surfaced. Runs once per request.
 */
function initSecurity() {
    if (defined('IMVIDIA_SECURITY_INIT')) {
        return;
    }
    define('IMVIDIA_SECURITY_INIT', true);

    // All timestamps (orders, receipts, etc.) should read in Malaysian local
    // time regardless of the server's configured php.ini timezone.
    date_default_timezone_set('Asia/Kuala_Lumpur');

    // Errors go to the log and to error.php, never rendered raw to the user.
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);

    set_exception_handler(function ($e) {
        error_log('Uncaught exception: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        renderErrorAndExit(500);
    });

    register_shutdown_function(function () {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            error_log('Fatal error: ' . $err['message'] . ' @ ' . $err['file'] . ':' . $err['line']);
            renderErrorAndExit(500);
        }
    });
}
