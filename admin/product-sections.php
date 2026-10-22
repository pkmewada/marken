<?php
// Saves only a product's section order (the "Section Order" card in product-form.php),
// so reordering never touches the rest of the product row.
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/app/product-sections.php';

header('Content-Type: application/json');

function fail(int $code, string $msg): never {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

if (!isLoggedIn()) fail(401, 'Your session has expired. Please log in again.');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'Method not allowed.');
if (!hash_equals(csrfToken(), (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) fail(403, 'Invalid security token. Refresh the page.');

$body = json_decode((string)file_get_contents('php://input'), true);
$slug = is_array($body) && is_string($body['product'] ?? null) ? $body['product'] : '';
if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) fail(400, 'Invalid product.');

$product = dbOne('SELECT id FROM `products` WHERE slug = ?', [$slug]);
if (!$product) fail(404, 'Product not found. It may have been renamed or deleted — reload the page.');

$order = parseSectionOrder($body['order'] ?? null);
if ($order === null) fail(422, 'Invalid section order. The Hero must stay first and every section must appear exactly once.');

dbRun('UPDATE `products` SET sectionOrder = ? WHERE id = ?', [sectionOrderForDb($order), $product['id']]);

echo json_encode(['ok' => true, 'order' => $order, 'message' => 'Section order saved.']);
