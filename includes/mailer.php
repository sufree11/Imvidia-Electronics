<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// send the reset link email, or log it when mail creds are absent (dev)
function sendPasswordResetEmail($toEmail, $toName, $resetUrl) {
    $user = appConfig('MAIL_USER', '');
    $pass = appConfig('MAIL_PASSWORD', '');
    $fromName = appConfig('MAIL_FROM_NAME', 'ImVidia Electronics');

    // dev fallback: no smtp credentials yet so just log the link
    if ($user === '' || $pass === '') {
        error_log('[password-reset] dev fallback no MAIL_USER/MAIL_PASSWORD set. Reset link for ' . $toEmail . ': ' . $resetUrl);
        return true;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $user;
        $mail->Password = $pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom($user, $fromName);
        $mail->addAddress($toEmail, $toName);
        $mail->addEmbeddedImage(__DIR__ . '/../assets/logo-email.png', 'imvidialogo', 'imvidia-logo.png');

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request';
        $mail->Body = renderResetEmailHtml($toName, $resetUrl);
        $mail->AltBody = "Hi $toName,\n\nWe received a request to reset your ImVidia password.\n"
            . "Open this link to choose a new one (valid for 30 minutes):\n$resetUrl\n\n"
            . "If you didn't request this, you can ignore this email.";

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('[password-reset] mail send failed: ' . $mail->ErrorInfo);
        return false;
    }
}

// branded html body with the cid embedded logo header
function renderResetEmailHtml($toName, $resetUrl) {
    $safeName = htmlspecialchars($toName, ENT_QUOTES);
    $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES);

    return '
    <div style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
      <div style="max-width:520px;margin:0 auto;padding:24px;">
        <div style="background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;">
          <div style="background:#1F2468;padding:28px;text-align:center;">
            <img src="cid:imvidialogo" width="64" height="64" alt="ImVidia" style="display:inline-block;border-radius:12px;background:#ffffff;">
            <div style="color:#ffffff;font-size:22px;font-weight:bold;margin-top:12px;">ImVidia<span style="color:#49C2FA;">.</span></div>
          </div>
          <div style="padding:32px;">
            <h1 style="margin:0 0 12px;font-size:20px;color:#0f172a;">Reset your password</h1>
            <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#475569;">Hi ' . $safeName . ', we received a request to reset the password for your ImVidia account. Click the button below to choose a new password.</p>
            <div style="text-align:center;margin:28px 0;">
              <a href="' . $safeUrl . '" style="display:inline-block;background:#49C2FA;color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;padding:14px 32px;border-radius:10px;">Reset Password</a>
            </div>
            <p style="margin:0 0 8px;font-size:13px;color:#64748b;">This link expires in <strong>30 minutes</strong> and can only be used once.</p>
            <p style="margin:0 0 20px;font-size:13px;color:#64748b;">If you didn\'t request a password reset you can safely ignore this email.</p>
            <p style="margin:0;font-size:12px;color:#94a3b8;word-break:break-all;">Button not working? Paste this link into your browser:<br>' . $safeUrl . '</p>
          </div>
        </div>
        <p style="text-align:center;font-size:11px;color:#94a3b8;margin:16px 0 0;">&copy; 2015 ImVidia Electronics</p>
      </div>
    </div>';
}
