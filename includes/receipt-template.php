<?php

require_once __DIR__ . '/order-helpers.php';


function renderReceiptHtml(array $order): string {
    $receipt_number = getOrderReceiptNumber($order);
    [$payment_label, $payment_detail_label] = formatPaymentMethodDisplay(
        $order['payment_method'] ?? '',
        $order['payment_detail'] ?? ''
    );

    $subtotal = 0.0;
    foreach ($order['items'] as $item) {
        $subtotal += (float) $item['unit_price'] * (int) $item['quantity'];
    }
    $tax = $subtotal * 0.06;
    $total = $subtotal + $tax;

    $order_date_ts = strtotime($order['order_date'] ?? 'now') ?: time();
    $customer_name = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));

    $logo_path = __DIR__ . '/../assets/logo-email.png';
    $logo_src = is_file($logo_path)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path))
        : '';

    ob_start();
    ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 28px 34px; }
    body {
        font-family: 'Helvetica', 'Arial', sans-serif;
        color: #1e293b;
        font-size: 12px;
        margin: 0;
    }
    table { border-collapse: collapse; width: 100%; }
    .header-table td { vertical-align: middle; }
    .brand-name { font-size: 22px; font-weight: bold; color: #0f172a; }
    .brand-accent { color: #49C2FA; }
    .doc-title { font-size: 26px; font-weight: bold; color: #1F2468; text-align: right; }
    .doc-subtitle { font-size: 11px; color: #64748b; text-align: right; }

    .divider { border-top: 2px solid #e2e8f0; margin: 14px 0; height: 0; font-size: 0; }

    .meta-table td { padding: 4px 12px 4px 0; }
    .meta-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; }
    .meta-value { font-size: 13px; font-weight: bold; color: #0f172a; }
    .meta-value.accent { color: #49C2FA; }

    .section-title {
        font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px;
        color: #475569; font-weight: bold; margin: 16px 0 6px;
    }

    .info-table td { padding: 3px 12px 3px 0; vertical-align: top; width: 50%; }
    .info-label { font-size: 9px; color: #94a3b8; }
    .info-value { font-size: 12px; font-weight: bold; color: #0f172a; }

    .items-table { margin-top: 6px; }
    .items-table th {
        background: #1F2468; color: #ffffff; font-size: 10px; text-transform: uppercase;
        padding: 8px 10px; text-align: left;
    }
    .items-table th.num, .items-table td.num { text-align: right; }
    .items-table td {
        padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 11.5px; color: #1e293b;
    }
    .item-name { font-weight: bold; color: #0f172a; }

    .totals-table { width: 260px; margin-left: auto; margin-top: 10px; }
    .totals-table td { padding: 4px 0; font-size: 12px; color: #475569; }
    .totals-table td.num { text-align: right; color: #0f172a; }
    .totals-table .grand td {
        border-top: 2px solid #1F2468; padding-top: 8px; font-size: 15px; font-weight: bold; color: #1F2468;
    }
    .totals-table .grand td.num { color: #49C2FA; }

    .thankyou {
        margin-top: 22px; padding: 12px 16px; background: #eaf7ff; border: 1px solid #bfe8fb;
        border-radius: 8px; text-align: center; font-size: 11.5px; color: #0f172a;
    }

    .footer { margin-top: 20px; text-align: center; font-size: 9px; color: #94a3b8; }
</style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <?php if ($logo_src): ?>
                    <img src="<?php echo htmlspecialchars($logo_src); ?>" style="width: 42px; height: 42px; border-radius: 8px; vertical-align: middle;">
                <?php endif; ?>
                <span class="brand-name">&nbsp;ImVidia<span class="brand-accent">.</span></span>
                <div style="font-size: 10px; color: #64748b; margin-top: 4px;">ImVidia Electronics</div>
            </td>
            <td style="width: 45%;">
                <div class="doc-title">RECEIPT</div>
                <div class="doc-subtitle">Order Confirmation</div>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <table class="meta-table">
        <tr>
            <td>
                <div class="meta-label">Receipt Number</div>
                <div class="meta-value accent"><?php echo htmlspecialchars($receipt_number); ?></div>
            </td>
            <td>
                <div class="meta-label">Date</div>
                <div class="meta-value"><?php echo htmlspecialchars(date('d M Y', $order_date_ts)); ?></div>
            </td>
            <td>
                <div class="meta-label">Time</div>
                <div class="meta-value"><?php echo htmlspecialchars(date('g:i A', $order_date_ts)); ?></div>
            </td>
            <td>
                <div class="meta-label">Status</div>
                <div class="meta-value"><?php echo htmlspecialchars($order['order_progress'] ?? 'Pending'); ?></div>
            </td>
        </tr>
    </table>

    <div class="section-title">Customer Information</div>
    <table class="info-table">
        <tr>
            <td>
                <div class="info-label">Name</div>
                <div class="info-value"><?php echo htmlspecialchars($customer_name); ?></div>
            </td>
            <td>
                <div class="info-label">Email</div>
                <div class="info-value"><?php echo htmlspecialchars($order['email'] ?? ''); ?></div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="info-label">Phone</div>
                <div class="info-value"><?php echo htmlspecialchars($order['phone'] ?? ''); ?></div>
            </td>
            <td>
                <div class="info-label">Payment Method</div>
                <div class="info-value">
                    <?php echo htmlspecialchars($payment_label); ?><?php if ($payment_detail_label !== ''): ?> &mdash; <?php echo htmlspecialchars($payment_detail_label); ?><?php endif; ?>
                </div>
            </td>
        </tr>
    </table>

    <div class="section-title">Shipping Address</div>
    <div class="info-value" style="font-weight: normal;">
        <?php echo htmlspecialchars($order['address'] ?? ''); ?><br>
        <?php echo htmlspecialchars(trim(($order['postcode'] ?? '') . ' ' . ($order['city'] ?? ''))); ?><br>
        <?php echo htmlspecialchars($order['state'] ?? ''); ?>, Malaysia
    </div>

    <div class="section-title">Order Items</div>
    <table class="items-table">
        <tr>
            <th>Item</th>
            <th class="num">Qty</th>
            <th class="num">Unit Price</th>
            <th class="num">Amount</th>
        </tr>
        <?php foreach ($order['items'] as $item): ?>
            <?php
                $qty = (int) $item['quantity'];
                $unit_price = (float) $item['unit_price'];
                $line_total = $unit_price * $qty;
                $name = !empty($item['product_name']) ? $item['product_name'] : ('Product #' . (int) $item['product_id']);
            ?>
            <tr>
                <td class="item-name"><?php echo htmlspecialchars($name); ?></td>
                <td class="num"><?php echo $qty; ?></td>
                <td class="num">RM <?php echo number_format($unit_price, 2); ?></td>
                <td class="num">RM <?php echo number_format($line_total, 2); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <table class="totals-table">
        <tr><td>Subtotal</td><td class="num">RM <?php echo number_format($subtotal, 2); ?></td></tr>
        <tr><td>Tax (6%)</td><td class="num">RM <?php echo number_format($tax, 2); ?></td></tr>
        <tr><td>Shipping</td><td class="num">Free</td></tr>
        <tr class="grand"><td>Grand Total</td><td class="num">RM <?php echo number_format($total, 2); ?></td></tr>
    </table>

    <div class="thankyou">
        Thank you for shopping with ImVidia Electronics.<br>
        We appreciate your business and hope you enjoy your purchase.
    </div>

    <div class="footer">
        Secure transaction powered by ImVidia &middot; Order #<?php echo (int) $order['order_id']; ?> &middot; Generated <?php echo htmlspecialchars(date('Y-m-d H:i:s')); ?>
    </div>

</body>
</html>
    <?php
    return ob_get_clean();
}
