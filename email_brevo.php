<?php
/**
 * =========================================================================
 * email_brevo.php — Brevo (Sendinblue) transactional email wrapper.
 * Uses Brevo's REST API (v3/smtp/email) via cURL — NOT SMTP, per spec.
 * Docs: https://developers.brevo.com/reference/sendtransacemail
 * =========================================================================
 */
require_once __DIR__ . '/config.php';

/**
 * Sends a transactional HTML email via the Brevo API.
 *
 * @param string $toEmail    Recipient email address
 * @param string $toName     Recipient display name
 * @param string $subject    Email subject
 * @param string $htmlBody   HTML email body
 * @return bool true on success (HTTP 2xx), false on failure
 */
function brevo_send_email($toEmail, $toName, $subject, $htmlBody) {
    $payload = [
        'sender'      => ['name' => BREVO_SENDER_NAME, 'email' => BREVO_SENDER_EMAIL],
        'to'          => [['email' => $toEmail, 'name' => $toName]],
        'subject'     => $subject,
        'htmlContent' => brevo_wrap_template($subject, $htmlBody),
    ];

    $ch = curl_init(BREVO_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . BREVO_API_KEY,
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    brevo_log("To: $toEmail | Subject: $subject | HTTP $httpCode | " . ($err ?: substr((string)$response, 0, 300)));

    return $httpCode >= 200 && $httpCode < 300;
}

/**
 * Wraps raw HTML content in a minimal branded email shell (logo + colors)
 * so every outgoing email looks consistent with the platform.
 */
function brevo_wrap_template($subject, $innerHtml) {
    return '
    <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;">
      <div style="background:' . COLOR_PRIMARY . ';padding:18px;text-align:center;border-radius:12px 12px 0 0;">
        <img src="' . SITE_LOGO . '" alt="' . SITE_NAME . '" style="height:32px;">
      </div>
      <div style="background:#fff;border:1px solid #e9edf1;border-top:none;padding:24px;border-radius:0 0 12px 12px;color:' . COLOR_PRIMARY . ';">
        ' . $innerHtml . '
      </div>
      <p style="text-align:center;color:#6c757d;font-size:12px;margin-top:12px;">&copy; ' . date('Y') . ' ' . SITE_NAME . '</p>
    </div>';
}

/** Simple file logger for email delivery attempts (debugging aid). */
function brevo_log($msg) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents(__DIR__ . '/email_log.txt', $line, FILE_APPEND);
}
