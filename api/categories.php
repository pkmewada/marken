<?php
// Category list for the header mega menu / mobile menu (js/main.js).
require_once dirname(__DIR__) . '/app/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

try {
    echo json_encode(
        dbAll('SELECT slug, name, tagline FROM `categories` ORDER BY sortOrder, name'),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $e) {
    error_log('categories.php: ' . $e->getMessage());
    http_response_code(500);
    echo '[]';
}
