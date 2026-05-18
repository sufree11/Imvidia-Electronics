<?php
/**
 * Helper Functions
 * Contains reusable functions for common operations
 * - Avatar generation
 * - S3 file uploads
 * - User data fetching
 */

require_once 'vendor/autoload.php';
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

/**
 * Generate avatar URL
 * Returns either the uploaded profile picture or a generated avatar
 * 
 * @param string $firstName User's first name
 * @param string $lastName User's last name
 * @param string $profilePicture Existing profile picture URL (optional)
 * @param bool $isAdmin Flag to use admin color scheme
 * @return string Avatar URL
 */
function getAvatarUrl($firstName, $lastName, $profilePicture = '', $isAdmin = false) {
    if (!empty($profilePicture)) {
        return htmlspecialchars($profilePicture);
    }
    
    // Use different background color for admin vs customer
    $bgColor = $isAdmin ? '1F2468' : '49C2FA'; // Admin: dark blue, Customer: cyan
    $name = urlencode($firstName . ' ' . $lastName);
    
    return "https://ui-avatars.com/api/?name={$name}&background={$bgColor}&color=fff&size=128";
}

/**
 * Handle S3/DigitalOcean Spaces file upload
 * Validates and uploads file to cloud storage
 * 
 * @param array $file $_FILES['fieldname'] array
 * @param string $prefix Directory prefix (e.g., 'avatars/user_', 'products/prod_')
 * @param string $fileId ID to include in filename (user_id or product_id)
 * @param array $allowedExts Allowed file extensions (default: common image formats)
 * @return array ['success' => bool, 'url' => string, 'error' => string]
 */
function uploadToS3($file, $prefix, $fileId, $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp']) {
    $result = [
        'success' => false,
        'url' => '',
        'error' => ''
    ];
    
    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'File upload error.';
        return $result;
    }
    
    // Validate file extension
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExt, $allowedExts)) {
        $result['error'] = 'Invalid file format. Allowed: ' . implode(', ', array_map('strtoupper', $allowedExts)) . '.';
        return $result;
    }
    
    // Create S3 client
    $s3 = new S3Client([
        'version' => 'latest',
        'region'  => getenv('SPACES_REGION'),
        'endpoint' => getenv('SPACES_ENDPOINT'),
        'credentials' => [
            'key'    => getenv('SPACES_KEY'),
            'secret' => getenv('SPACES_SECRET'),
        ],
    ]);
    
    // Generate unique filename
    $newFilename = $prefix . $fileId . '_' . time() . '.' . $fileExt;
    
    try {
        // Upload file to S3
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
        // Uncomment for debugging:
        // $result['error'] = 'Cloud Error: ' . $e->getMessage();
    }
    
    return $result;
}

/**
 * Fetch user profile data
 * Retrieves user information from database
 * 
 * @param int $userId User ID
 * @return array User data or empty array if not found
 */
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

/**
 * Display avatar image with fallback
 * Outputs HTML img tag with proper fallback
 * 
 * @param string $avatarUrl Avatar URL
 * @param string $fullName User's full name
 * @param string $classes Additional CSS classes
 * @return void Echoes HTML
 */
function displayAvatar($avatarUrl, $fullName, $classes = 'w-10 h-10 rounded-full') {
    echo '<img src="' . htmlspecialchars($avatarUrl) . '" alt="' . htmlspecialchars($fullName) . '" class="' . $classes . ' object-cover bg-white shadow-sm">';
}
