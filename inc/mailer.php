<?php
declare(strict_types=1);

/**
 * inc/mailer.php
 * Email wrapper using PHP's mail() with optional SMTP via PHPMailer.
 *
 * To enable SMTP (recommended for production):
 *   1. composer require phpmailer/phpmailer
 *   2. Set smtp_* keys in the `settings` table
 *   3. Change MAIL_DRIVER setting to 'smtp'
 *
 * Without PHPMailer, falls back to PHP mail() automatically.
 */

/**
 * Send an email.
 *
 * @param string $to       Recipient email
 * @param string $subject  Email subject
 * @param string $body     HTML body
 * @param string $altBody  Plain text fallback
 * @return bool
 */
function mail_send(string $to, string $subject, string $body, string $altBody = ''): bool
{
    $driver   = setting('mail_driver', 'mail');
    $fromName = setting('mail_from_name',  setting('site_name', 'VideoSaaS'));
    $fromAddr = setting('mail_from_email', 'noreply@' . parse_url(BASE_URL, PHP_URL_HOST));

    // ── PHPMailer SMTP path ───────────────────────────────────────────────────
    if ($driver === 'smtp' && class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        return mail_send_smtp($to, $subject, $body, $altBody, $fromAddr, $fromName);
    }

    // ── Fallback: PHP mail() ──────────────────────────────────────────────────
    $headers  = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromAddr . '>',
        'Reply-To: ' . $fromAddr,
        'X-Mailer: PHP/' . PHP_VERSION,
    ]);

    $sent = @mail($to, $subject, $body, $headers);

    if (!$sent) {
        error_log("[mailer] mail() failed to $to — subject: $subject");
    }

    return $sent;
}

/**
 * PHPMailer SMTP sender (only called when PHPMailer is installed).
 */
function mail_send_smtp(
    string $to, string $subject, string $body, string $altBody,
    string $fromAddr, string $fromName
): bool {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = setting('smtp_host', 'smtp.mailtrap.io');
        $mail->Port       = (int)setting('smtp_port', 587);
        $mail->SMTPAuth   = true;
        $mail->Username   = setting('smtp_user', '');
        $mail->Password   = setting('smtp_pass', '');
        $mail->SMTPSecure = setting('smtp_secure', 'tls'); // 'tls' or 'ssl'
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($fromAddr, $fromName);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('[mailer/smtp] ' . $e->getMessage());
        return false;
    }
}

// ── Email template renderer ───────────────────────────────────────────────────

/**
 * Wrap content in a branded HTML email shell.
 */
function mail_template(string $heading, string $content, string $cta_url = '', string $cta_label = ''): string
{
    $siteName = setting('site_name', 'VideoSaaS');
    $siteUrl  = setting('site_url',  BASE_URL);
    $year     = date('Y');
    $btn      = $cta_url
        ? "<p style='text-align:center;margin:28px 0'>
               <a href='$cta_url' style='background:#6c47ff;color:#fff;padding:13px 28px;
               border-radius:8px;text-decoration:none;font-weight:700;font-size:15px;
               display:inline-block'>$cta_label</a>
           </p>"
        : '';

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#0f0f1a;font-family:system-ui,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f0f1a;padding:32px 16px">
    <tr><td align="center">
      <table width="100%" style="max-width:560px;background:#1a1a2e;border-radius:16px;
             border:1px solid #2d2d4e;overflow:hidden">

        <!-- Header -->
        <tr><td style="background:linear-gradient(135deg,rgba(108,71,255,.4),rgba(0,212,170,.2));
                       padding:28px;text-align:center">
          <a href="$siteUrl" style="font-size:22px;font-weight:900;color:#fff;text-decoration:none">
            Video<span style="color:#6c47ff">SaaS</span>
          </a>
        </td></tr>

        <!-- Body -->
        <tr><td style="padding:32px 36px;color:#e2e8f0;line-height:1.7">
          <h2 style="margin:0 0 20px;font-size:20px;font-weight:700;color:#fff">$heading</h2>
          $content
          $btn
        </td></tr>

        <!-- Footer -->
        <tr><td style="padding:20px 36px;border-top:1px solid #2d2d4e;
                       text-align:center;font-size:12px;color:#64748b">
          &copy; $year $siteName. All rights reserved.<br>
          <a href="$siteUrl" style="color:#6c47ff;text-decoration:none">$siteUrl</a>
        </td></tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

// ── Pre-built transactional emails ────────────────────────────────────────────

/**
 * Send password reset email.
 */
function mail_password_reset(string $to, string $name, string $resetUrl): bool
{
    $content = "
        <p>Hi <strong>" . htmlspecialchars($name) . "</strong>,</p>
        <p>We received a request to reset your password. Click the button below to set a new one.
           This link expires in <strong>1 hour</strong>.</p>
        <p>If you didn't request this, you can safely ignore this email.</p>
    ";
    $body = mail_template('Password Reset Request', $content, $resetUrl, 'Reset My Password');
    return mail_send($to, 'Password Reset — ' . setting('site_name','VideoSaaS'), $body);
}

/**
 * Send payment received confirmation.
 */
function mail_payment_received(string $to, string $name, float $amount, float $credits, int $orderId): bool
{
    $currency = setting('currency', 'MYR');
    $content  = "
        <p>Hi <strong>" . htmlspecialchars($name) . "</strong>,</p>
        <p>We have received your payment and are reviewing it now.</p>
        <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:14px'>
          <tr style='border-bottom:1px solid #2d2d4e'>
            <td style='padding:8px 0;color:#94a3b8'>Order #</td>
            <td style='padding:8px 0;font-weight:700'>{$orderId}</td>
          </tr>
          <tr style='border-bottom:1px solid #2d2d4e'>
            <td style='padding:8px 0;color:#94a3b8'>Amount</td>
            <td style='padding:8px 0;font-weight:700'>{$currency} " . number_format($amount, 2) . "</td>
          </tr>
          <tr>
            <td style='padding:8px 0;color:#94a3b8'>Credits</td>
            <td style='padding:8px 0;font-weight:700;color:#00d4aa'>" . number_format($credits, 2) . " credits</td>
          </tr>
        </table>
        <p>Credits will be added to your wallet once our team approves your payment (usually within 1 business day).</p>
    ";
    $body = mail_template('Payment Received', $content, BASE_URL . '/client/wallet.php', 'View Wallet');
    return mail_send($to, 'Payment Received #' . $orderId . ' — ' . setting('site_name','VideoSaaS'), $body);
}

/**
 * Send payment approved notification.
 */
function mail_payment_approved(string $to, string $name, float $credits, int $orderId): bool
{
    $content = "
        <p>Hi <strong>" . htmlspecialchars($name) . "</strong>,</p>
        <p>Great news! Your payment has been approved and <strong style='color:#00d4aa'>"
             . number_format($credits, 2) . " credits</strong> have been added to your wallet.</p>
        <p>You can now generate AI marketing videos. Head to the dashboard to get started!</p>
    ";
    $body = mail_template('Payment Approved!', $content, BASE_URL . '/client/generate.php', 'Generate Now');
    return mail_send($to, 'Credits Added — ' . setting('site_name','VideoSaaS'), $body);
}

/**
 * Send payment rejected notification.
 */
function mail_payment_rejected(string $to, string $name, string $reason, int $orderId): bool
{
    $content = "
        <p>Hi <strong>" . htmlspecialchars($name) . "</strong>,</p>
        <p>Unfortunately your payment order <strong>#$orderId</strong> was not approved.</p>
        <p style='background:#1e1e3a;border-left:3px solid #e53e3e;padding:12px 16px;border-radius:4px'>
            <strong>Reason:</strong> " . htmlspecialchars($reason) . "
        </p>
        <p>Please resubmit with the correct receipt or contact support if you believe this is an error.</p>
    ";
    $body = mail_template('Payment Not Approved', $content, BASE_URL . '/client/buy-credits.php', 'Resubmit Payment');
    return mail_send($to, 'Payment Update — ' . setting('site_name','VideoSaaS'), $body);
}

/**
 * Send referral reward notification.
 */
function mail_referral_reward(string $to, string $name, float $credits, string $refereeName): bool
{
    $content = "
        <p>Hi <strong>" . htmlspecialchars($name) . "</strong>,</p>
        <p>You just earned a referral reward! <strong>" . htmlspecialchars($refereeName) . "</strong>
           made their first purchase using your referral link.</p>
        <p style='font-size:32px;font-weight:900;text-align:center;color:#00d4aa;margin:20px 0'>
            +" . number_format($credits, 2) . " credits
        </p>
        <p>Your credits have been added to your wallet automatically. Keep sharing to earn more!</p>
    ";
    $body = mail_template('You Earned a Referral Reward!', $content, BASE_URL . '/client/referral.php', 'View Referrals');
    return mail_send($to, 'Referral Reward Earned — ' . setting('site_name','VideoSaaS'), $body);
}

/**
 * Send video completed notification.
 */
function mail_video_completed(string $to, string $name, int $jobId): bool
{
    $content = "
        <p>Hi <strong>" . htmlspecialchars($name) . "</strong>,</p>
        <p>Your AI marketing video <strong>#$jobId</strong> is ready! Head to your history to preview, download, and share it.</p>
    ";
    $body = mail_template('Your Video is Ready!', $content, BASE_URL . '/client/history.php', 'View Video');
    return mail_send($to, 'Video Ready — ' . setting('site_name','VideoSaaS'), $body);
}
