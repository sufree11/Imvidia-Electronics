<?php
require_once 'includes/auth.php';
require_once 'includes/db-helpers.php';
require_once 'includes/order-helpers.php';
require_once 'includes/receipt-template.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
$order = $order_id > 0 ? getOrderById($order_id) : null;

// Admins can view any receipt; a logged-in customer can view their own past
// orders; a guest (or a customer mid-session) can view an order that was just
// granted to this session at checkout time (see grantReceiptAccess()).
$customer = checkCustomerOrGuest();
$admin = checkAdminOrGuest();
$viewer = [
    'is_admin' => $admin['is_admin'] ?? false,
    'is_logged_in' => $customer['is_logged_in'] ?? false,
    'user_id' => $customer['user_id'] ?? null,
];

if (!canAccessOrderReceipt($order, $viewer)) {
    renderErrorAndExit(403);
}

$html = renderReceiptHtml($order);

// DigitalOcean App Platform serves the app from a read-only filesystem at
// runtime (only /tmp is writable), so Dompdf's default font cache location
// inside vendor/ isn't usable there - it has to be redirected to /tmp or
// every render fatals trying to write font metrics.
$dompdfTempDir = sys_get_temp_dir() . '/dompdf';
if (!is_dir($dompdfTempDir)) {
    mkdir($dompdfTempDir, 0700, true);
}

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'Helvetica');
$options->set('tempDir', $dompdfTempDir);
$options->set('fontDir', $dompdfTempDir);
$options->set('fontCache', $dompdfTempDir);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$receipt_number = getOrderReceiptNumber($order);
$dompdf->stream("ImVidia-Receipt-{$receipt_number}.pdf", ['Attachment' => false]);
