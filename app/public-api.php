<?php
// Shared helpers for the public JSON endpoints in api/ that accept form submissions.
// All queries go through prepared statements (app/db.php), so input never reaches SQL as code.
require_once __DIR__ . '/db.php';

// Throwaway inboxes — signups from these are never genuine leads.
const DISPOSABLE_EMAIL_DOMAINS = [
    'mailinator.com', 'guerrillamail.com', 'guerrillamail.info', 'sharklasers.com', '10minutemail.com',
    'tempmail.com', 'temp-mail.org', 'yopmail.com', 'trashmail.com', 'getnada.com', 'dispostable.com',
    'maildrop.cc', 'throwawaymail.com', 'fakeinbox.com', 'mailnesia.com', 'tempr.email', 'moakt.com',
];

function jsonOut(int $status, array $data): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * POST only, same-site only, passes the bot checks. Returns cleaned string fields.
 * Bots get a fake success so they don't learn what tripped them.
 */
function readPublicForm(): array {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonOut(405, ['ok' => false, 'message' => 'Method not allowed.']);

    // Browsers always send Origin on cross-site POSTs; reject anything not from this host.
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
        jsonOut(403, ['ok' => false, 'message' => 'Request not allowed.']);
    }

    $in = [];
    foreach ($_POST as $k => $v) {
        // Strip control characters (keep newlines/tabs for messages).
        $in[$k] = is_string($v) ? trim(preg_replace('/[^\P{C}\n\t]/u', '', $v) ?? '') : '';
    }

    $botReply = ['ok' => true, 'message' => 'Thank you.'];
    // Hidden "website" field: humans never see it, bots fill it in.
    if (($in['website'] ?? '') !== '') jsonOut(200, $botReply);
    // "_t" = ms the page was open before submitting (set by the site JS). Scripts post instantly.
    if (!ctype_digit($in['_t'] ?? '') || (int)$in['_t'] < 2000) jsonOut(200, $botReply);

    return $in;
}

function clientIp(): string {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/** Reject when this IP already created $max rows in $table within the last hour (mass entry). */
function rateLimit(string $table, int $max): void {
    $n = (int)dbOne("SELECT COUNT(*) n FROM `$table` WHERE ip = ? AND createdAt > NOW() - INTERVAL 1 HOUR", [clientIp()])['n'];
    if ($n >= $max) jsonOut(429, ['ok' => false, 'message' => 'Too many submissions from your network. Please try again in an hour.']);
}

/** Returns an error message, or null when the address looks genuine. */
function emailError(string $email): ?string {
    if ($email === '') return 'Please enter your email address.';
    if (mb_strlen($email) > 200 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || !preg_match('/^[^@\s]+@([a-z0-9-]+\.)+[a-z]{2,}$/i', $email)) {
        return 'Please enter a valid email address (e.g. name@company.com).';
    }
    $domain = strtolower(substr(strrchr($email, '@'), 1));
    if (in_array($domain, DISPOSABLE_EMAIL_DOMAINS, true)) {
        return 'Please use your work or personal email — temporary inboxes are not accepted.';
    }
    // The domain must be able to receive mail.
    if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
        return 'This email domain does not exist. Please check for typos.';
    }
    return null;
}

function normalizeEmail(string $email): string {
    return mb_strtolower(trim($email));
}
