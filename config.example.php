<?php
/**
 * =========================================================================
 * config.php — NERAP Cloud global configuration
 * -------------------------------------------------------------------------
 * Loaded by EVERY page (public + admin + WhatsApp webhook + cron).
 * Holds: database connection, site constants, brand colors, and 3rd-party
 * API credentials (WhatsApp / Brevo / Google Maps).
 *
 * IMPORTANT: Replace every "CHANGE_ME" placeholder below with your real
 * credentials before going live.
 * =========================================================================
 */

// Show errors while developing. Set to 0 on production.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for admin/moderator authentication.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Africa/Nairobi');

/* -------------------------------------------------------------------------
 * SITE / BRAND CONSTANTS
 * ---------------------------------------------------------------------- */
define('SITE_NAME', 'NERAP Cloud');
define('SITE_TAGLINE', 'Emergency Resource Intelligence, Everywhere.');
define('SITE_URL', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
define('SITE_LOGO', 'https://nerap.cloud/logo.png');

// Brand colors (used inline + in assets/style.css via CSS variables)
define('COLOR_PRIMARY', '#042238');   // Deep navy — headers, nav, buttons
define('COLOR_SECONDARY', '#1b693f'); // Green — success/confirmed status, accents
define('COLOR_BACKGROUND', '#ffffff'); // White background

/* -------------------------------------------------------------------------
 * DATABASE (MySQL via mysqli)
 * ---------------------------------------------------------------------- */
define('DB_HOST', 'localhost');
define('DB_NAME', 'nerap_cloud');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * Returns a shared mysqli connection (singleton pattern).
 * Using mysqli (not PDO) to match the "vanilla PHP, zero framework" spirit.
 */
function db() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            http_response_code(500);
            die('Database connection failed: ' . $conn->connect_error);
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

/* -------------------------------------------------------------------------
 * WHATSAPP API (WhatsAPI provider — see whatsapp_send.php / whatsapp_webhook.php)
 * Docs reference: whatsapp.dryven.dev REST API (send + webhook style)
 * ---------------------------------------------------------------------- */
define('WA_API_BASE', 'https://whatsapp.dryven.dev/api');
define('WA_SEND_URL', WA_API_BASE . '/send/whatsapp');
define('WA_ACCOUNT_UNIQUE', 'CHANGE_ME_WHATSAPP_UNIQUE_ID'); // "account" / unique ID of the connected WhatsApp number
define('WA_API_SECRET', 'CHANGE_ME_WHATSAPP_API_SECRET');     // secret used to SEND messages
define('WA_WEBHOOK_SECRET', 'CHANGE_ME_WHATSAPP_WEBHOOK_SECRET'); // secret Meta/WhatsAPI sends back on inbound webhook
define('WA_NUMBER', '+254700000000'); // the NERAP WhatsApp number shown to users

/* -------------------------------------------------------------------------
 * BREVO (formerly Sendinblue) — Transactional email via REST API (no SMTP)
 * ---------------------------------------------------------------------- */
define('BREVO_API_KEY', 'CHANGE_ME_BREVO_API_KEY');
define('BREVO_API_URL', 'https://api.brevo.com/v3/smtp/email');
define('BREVO_SENDER_EMAIL', 'alerts@nerap.cloud');
define('BREVO_SENDER_NAME', SITE_NAME);

/* -------------------------------------------------------------------------
 * GOOGLE MAPS
 * ---------------------------------------------------------------------- */
define('GOOGLE_MAPS_API_KEY', 'CHANGE_ME_GOOGLE_MAPS_API_KEY');

/* -------------------------------------------------------------------------
 * STOCK STATUS CONSTANTS (shared across WhatsApp bot + dashboard + admin)
 * ---------------------------------------------------------------------- */
define('STATUS_CONFIRMED', 'confirmed');     // 🟢 green
define('STATUS_LOW', 'low');                 // 🟡 amber
define('STATUS_OUT', 'out');                 // 🔴 red
define('STATUS_UNVERIFIED', 'unverified');   // ⚫ grey

/**
 * Small helper: returns a hex color for a given stock status (used by map
 * pins, badges, and charts so the whole app stays visually consistent).
 */
function status_color($status) {
    switch ($status) {
        case STATUS_CONFIRMED: return '#1b693f'; // secondary brand green
        case STATUS_LOW:       return '#e0a800'; // amber
        case STATUS_OUT:       return '#c0392b'; // red
        default:                return '#6c757d'; // grey (unverified)
    }
}

/** Human readable label for a status code. */
function status_label($status) {
    $map = [
        STATUS_CONFIRMED => 'Confirmed Stock',
        STATUS_LOW       => 'Low Stock',
        STATUS_OUT       => 'Out of Stock',
        STATUS_UNVERIFIED => 'Unverified',
    ];
    return $map[$status] ?? 'Unknown';
}

/**
 * e() — shorthand for htmlspecialchars() to safely echo values in views.
 */
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Require an authenticated admin/moderator session. Redirects to login
 * if not authenticated. Call at the top of every admin_*.php page.
 */
function require_admin() {
    if (empty($_SESSION['admin_id'])) {
        header('Location: auth_login.php');
        exit;
    }
}
