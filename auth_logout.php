<?php
/**
 * =========================================================================
 * auth_logout.php — Destroys the admin session and redirects to login.
 * =========================================================================
 */
require_once __DIR__ . '/config.php';
$_SESSION = [];
session_destroy();
header('Location: auth_login.php');
exit;
