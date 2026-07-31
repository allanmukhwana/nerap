<?php
/**
 * =========================================================================
 * whatsapp_session.php — Conversation State Manager.
 * Manages multi-step WhatsApp conversations in the stateless webhook by
 * persisting state to the `wa_sessions` MySQL table, keyed by phone number.
 * Uses SELECT ... FOR UPDATE to avoid race conditions when messages from
 * the same user arrive in rapid succession (README challenge #1).
 * =========================================================================
 */
require_once __DIR__ . '/config.php';

/**
 * Fetches (and row-locks) the session for a phone number, creating an
 * 'idle' session if none exists yet. MUST be called inside a transaction
 * that is later committed via wa_set_session()/wa_clear_session() or
 * explicitly rolled back.
 */
function wa_get_session($phone) {
    $conn = db();
    $conn->begin_transaction();
    $stmt = $conn->prepare("SELECT phone, state, data FROM wa_sessions WHERE phone = ? FOR UPDATE");
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        $ins = $conn->prepare("INSERT INTO wa_sessions (phone, state, data) VALUES (?, 'idle', '{}')");
        $ins->bind_param('s', $phone);
        $ins->execute();
        return ['phone' => $phone, 'state' => 'idle', 'data' => []];
    }

    return [
        'phone' => $row['phone'],
        'state' => $row['state'],
        'data'  => json_decode($row['data'] ?: '{}', true) ?: [],
    ];
}

/** Persists new state/data for a phone number and commits the transaction. */
function wa_set_session($phone, $state, array $data = []) {
    $conn = db();
    $stmt = $conn->prepare("INSERT INTO wa_sessions (phone, state, data) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE state = VALUES(state), data = VALUES(data)");
    $json = json_encode($data);
    $stmt->bind_param('sss', $phone, $state, $json);
    $stmt->execute();
    $conn->commit();
}

/** Resets a user's conversation back to idle (used by RESET/MENU commands). */
function wa_clear_session($phone) {
    wa_set_session($phone, 'idle', []);
}
