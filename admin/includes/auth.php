<?php
require_once dirname(__DIR__, 2) . '/app/db.php';

/* ── Session ─────────────────────────────────────────────────────── */
function startSession(): void {
    if (session_status() !== PHP_SESSION_NONE) return;
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name(SESSION_NAME);
    session_start();
}

function isLoggedIn(): bool {
    startSession();
    return !empty($_SESSION['admin']);
}

function requireLogin(): void {
    if (!isLoggedIn()) redirect('login');
}

/** Absolute URL of the admin folder (works under /marken/admin/ locally or /admin/ on a domain root). */
function adminUrl(string $path = ''): string {
    static $base = null;
    $base ??= rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
    return $base . ltrim($path, '/');
}

function redirect(string $to): never {
    header('Location: ' . adminUrl($to));
    exit;
}

/* ── CSRF ─────────────────────────────────────────────────────────── */
function csrfToken(): string {
    startSession();
    return $_SESSION['csrfToken'] ??= bin2hex(random_bytes(32));
}

function csrfField(): string {
    return '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
}

function verifyCsrf(?string $token = null): void {
    $token ??= $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}

/* ── Flash messages ───────────────────────────────────────────────── */
function setFlash(string $type, string $msg): void {
    startSession();
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function flashHtml(): string {
    startSession();
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    if (!$f) return '';
    $cls = $f['type'] === 'error' ? 'danger' : 'success';
    return '<div class="alert alert-' . $cls . ' alert-dismissible fade show" role="alert">' . h($f['msg'])
         . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

/* ── Helpers ──────────────────────────────────────────────────────── */
function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function post(string $key): string {
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

function postList(string $key): array {
    $v = $_POST[$key] ?? [];
    return is_array($v) ? array_map(fn($x) => is_string($x) ? trim($x) : '', $v) : [];
}

/**
 * Rules: required | slug | email | max:N
 * Returns [field => message] for every failing field.
 */
function validate(array $data, array $rules): array {
    $errors = [];
    foreach ($rules as $field => $ruleStr) {
        $value = (string)($data[$field] ?? '');
        $label = ucfirst(strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $field)));
        foreach (explode('|', $ruleStr) as $rule) {
            if ($rule === 'required' && $value === '') { $errors[$field] = "$label is required."; break; }
            if ($value === '') break;
            if ($rule === 'slug' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
                $errors[$field] = 'Use lowercase letters, numbers and single hyphens only.'; break;
            }
            if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = 'Enter a valid email address.'; break;
            }
            if (str_starts_with($rule, 'max:') && mb_strlen($value) > (int)substr($rule, 4)) {
                $errors[$field] = "$label must be at most " . substr($rule, 4) . ' characters.'; break;
            }
        }
    }
    return $errors;
}

/** Bootstrap invalid class + message for a field. */
function fieldError(array $errors, string $field): string {
    return isset($errors[$field]) ? '<div class="invalid-feedback">' . h($errors[$field]) . '</div>' : '';
}
function invalid(array $errors, string $field): string {
    return isset($errors[$field]) ? ' is-invalid' : '';
}
