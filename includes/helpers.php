<?php

require_once 'vendor/autoload.php';
require_once __DIR__ . '/config.php';
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// build avatar url with fallback
function getAvatarUrl($firstName, $lastName, $profilePicture = '', $isAdmin = false) {
    if (!empty($profilePicture)) {
        return htmlspecialchars($profilePicture);
    }
    
    $bgColor = $isAdmin ? '1F2468' : '49C2FA';
    $name = urlencode($firstName . ' ' . $lastName);
    
    return "https://ui-avatars.com/api/?name={$name}&background={$bgColor}&color=fff&size=128";
}

// upload image to s3 storage
function uploadToS3($file, $prefix, $fileId, $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp']) {
    $result = [
        'success' => false,
        'url' => '',
        'error' => ''
    ];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'File upload error.';
        return $result;
    }
    
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExt, $allowedExts)) {
        $result['error'] = 'Invalid file format. Allowed: ' . implode(', ', array_map('strtoupper', $allowedExts)) . '.';
        return $result;
    }
    
    $newFilename = $prefix . $fileId . '_' . time() . '.' . $fileExt;

    try {
        $s3 = new S3Client([
            'version' => 'latest',
            'region'  => appConfig('SPACES_REGION'),
            'endpoint' => appConfig('SPACES_ENDPOINT'),
            'credentials' => [
                'key'    => appConfig('SPACES_KEY'),
                'secret' => appConfig('SPACES_SECRET'),
            ],
        ]);

        $uploadResult = $s3->putObject([
            'Bucket'      => appConfig('SPACES_BUCKET'),
            'Key'         => $newFilename,
            'SourceFile'  => $file['tmp_name'],
            'ACL'         => 'public-read',
            'ContentType' => mime_content_type($file['tmp_name'])
        ]);

        $result['success'] = true;
        $result['url'] = $uploadResult['ObjectURL'];

    } catch (AwsException $e) {
        $result['error'] = 'Failed to upload to cloud storage.';
    } catch (\Throwable $e) {
        // Misconfigured/missing SPACES_* credentials (S3Client constructor
        // throws InvalidArgumentException, not AwsException) - surface this
        // as a normal upload failure instead of a fatal 500.
        error_log('uploadToS3 config error: ' . $e->getMessage());
        $result['error'] = 'Cloud storage is not configured correctly.';
    }

    return $result;
}

// fetch full user row
function getUserData($userId) {
    global $conn;
    
    $userId = mysqli_real_escape_string($conn, $userId);
    $query = "SELECT * FROM users WHERE id = '$userId' LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return [];
}

// echo avatar image tag
function displayAvatar($avatarUrl, $fullName, $classes = 'w-10 h-10 rounded-full') {
    echo '<img src="' . htmlspecialchars($avatarUrl) . '" alt="' . htmlspecialchars($fullName) . '" class="' . $classes . ' object-cover bg-white shadow-sm">';
}

/**
 * Normalizes any phone number (Malaysian local "012-3456789", already
 * international "+60123456789", with/without spaces or dashes, etc.) into the
 * site-wide display format "+60 XX XXXXXXX". Mirrors the JS live-formatter in
 * includes/head.php so what a user sees while typing matches what's stored.
 * Falls back to the trimmed original if there aren't enough digits to format.
 */
function formatMalaysianPhone($raw) {
    $digits = preg_replace('/\D/', '', (string) $raw);

    if ($digits === '') {
        return trim((string) $raw);
    }

    if (substr($digits, 0, 2) === '60') {
        $digits = substr($digits, 2);
    } elseif (substr($digits, 0, 1) === '0') {
        $digits = substr($digits, 1);
    }

    if (strlen($digits) < 3) {
        return trim((string) $raw);
    }

    $prefix = substr($digits, 0, 2);
    $rest = substr($digits, 2);

    return '+60 ' . $prefix . ' ' . $rest;
}

// pick status badge classes
function getOrderProgressClass($progress) {
    $normalized = strtolower(trim((string) $progress));
    if ($normalized === 'delivered' || $normalized === 'completed') {
        return 'bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-800';
    }
    if ($normalized === 'cancelled' || $normalized === 'failed') {
        return 'bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-800';
    }
    if ($normalized === 'processing' || $normalized === 'pending') {
        return 'bg-yellow-50 text-yellow-700 border border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-800';
    }

    return 'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800';
}

