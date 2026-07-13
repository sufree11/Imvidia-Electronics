<?php

require_once 'vendor/autoload.php';
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

function getAvatarUrl($firstName, $lastName, $profilePicture = '', $isAdmin = false) {
    if (!empty($profilePicture)) {
        return htmlspecialchars($profilePicture);
    }
    
    $bgColor = $isAdmin ? '1F2468' : '49C2FA';
    $name = urlencode($firstName . ' ' . $lastName);
    
    return "https://ui-avatars.com/api/?name={$name}&background={$bgColor}&color=fff&size=128";
}

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
    
    $s3 = new S3Client([
        'version' => 'latest',
        'region'  => getenv('SPACES_REGION'),
        'endpoint' => getenv('SPACES_ENDPOINT'),
        'credentials' => [
            'key'    => getenv('SPACES_KEY'),
            'secret' => getenv('SPACES_SECRET'),
        ],
    ]);
    
    $newFilename = $prefix . $fileId . '_' . time() . '.' . $fileExt;
    
    try {
        $uploadResult = $s3->putObject([
            'Bucket'      => getenv('SPACES_BUCKET'),
            'Key'         => $newFilename,
            'SourceFile'  => $file['tmp_name'],
            'ACL'         => 'public-read',
            'ContentType' => mime_content_type($file['tmp_name'])
        ]);
        
        $result['success'] = true;
        $result['url'] = $uploadResult['ObjectURL'];
        
    } catch (AwsException $e) {
        $result['error'] = 'Failed to upload to cloud storage.';
    }
    
    return $result;
}

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

function displayAvatar($avatarUrl, $fullName, $classes = 'w-10 h-10 rounded-full') {
    echo '<img src="' . htmlspecialchars($avatarUrl) . '" alt="' . htmlspecialchars($fullName) . '" class="' . $classes . ' object-cover bg-white shadow-sm">';
}

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

