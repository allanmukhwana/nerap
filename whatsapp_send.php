<?php
/**
 * =========================================================================
 * whatsapp_send.php — Lightweight WhatsApp API wrapper (cURL, no SDK).
 * Talks to the WhatsAPI-compatible provider documented in
 * "whatsapp-api-docs.md" (POST /send/whatsapp with secret/account/recipient
 * /type/message form fields). Used by whatsapp_webhook.php, admin_broadcast.php
 * and alert_cron.php.
 * =========================================================================
 */
require_once __DIR__ . '/config.php';

/**
 * Sends a WhatsApp text message to a single recipient.
 *
 * @param string $recipient E.164 phone number, e.g. +254712345678
 * @param string $message   Message body (menus are plain numbered text lists
 *                           since this provider does not support Meta's
 *                           native interactive list/button message types).
 * @return bool true if the API accepted the request (HTTP 2xx)
 */
function wa_send($recipient, $message) {
    $postFields = [
        'secret'    => WA_API_SECRET,
        'account'   => WA_ACCOUNT_UNIQUE,
        'recipient' => $recipient,
        'type'      => 'text',
        'message'   => $message,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => WA_SEND_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields, // multipart/form-data (array = multipart in cURL)
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    wa_log("SEND -> $recipient | HTTP $httpCode | " . ($err ?: substr((string)$response, 0, 300)));
    return $httpCode >= 200 && $httpCode < 300 && !$err;
}

/**
 * Sends the same message to many recipients in throttled batches to respect
 * provider rate limits (README challenge #4: batches of 20, 1s delay).
 *
 * @param string[] $recipients
 * @param string   $message
 * @return array{sent:int, failed:int}
 */
function wa_broadcast(array $recipients, $message) {
    $sent = 0; $failed = 0; $batchSize = 20;
    $batches = array_chunk($recipients, $batchSize);
    foreach ($batches as $batch) {
        foreach ($batch as $recipient) {
            wa_send($recipient, $message) ? $sent++ : $failed++;
        }
        if (count($batches) > 1) sleep(1); // throttle between batches
    }
    return ['sent' => $sent, 'failed' => $failed];
}

/** Normalizes a phone number to the provider's expected format (E.164-ish, 254 prefix for KE). */
function wa_format_phone($phone) {
    $digits = preg_replace('/[^0-9]/', '', (string)$phone);
    if (substr($digits, 0, 1) === '0') {
        $digits = '254' . substr($digits, 1);
    } elseif (substr($digits, 0, 3) !== '254' && strlen($digits) <= 10) {
        $digits = '254' . ltrim($digits, '+');
    }
    return '+' . $digits;
}

/** Append-only debug log for WhatsApp send/receive activity. */
function wa_log($msg) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents(__DIR__ . '/whatsapp_log.txt', $line, FILE_APPEND);
}
