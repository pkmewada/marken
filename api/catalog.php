<?php
// Emits the CATEGORIES / PRODUCTS globals the front-end scripts expect (previously hardcoded in js/data.js).
require_once dirname(__DIR__) . '/app/db.php';

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache');

$categories = $products = [];
try {
    $categories = dbAll('SELECT slug, name, tagline, image FROM `categories` ORDER BY sortOrder, name');
    foreach (dbAll("SELECT * FROM `products` WHERE status = 'published' ORDER BY sortOrder, name") as $r) {
        $products[] = array_filter([
            'slug'            => $r['slug'],
            'name'            => $r['name'],
            'category'        => $r['categorySlug'],
            'image'           => $r['image'],
            'featured'        => (bool)$r['featured'],
            'description'     => $r['description'],
            'fullDescription' => $r['fullDescription'],
            'gallery'         => fromJson($r['gallery']),
            'heroMediaType'   => $r['heroMediaType'],
            'heroMedia'       => $r['heroMedia'],
            'features'        => fromJson($r['features']),
            'applications'    => fromJson($r['applications']),
            'specifications'  => fromJson($r['specifications']),
            'certifications'  => fromJson($r['certifications']),
            'documents'       => fromJson($r['documents']),
            'metaTitle'       => $r['metaTitle'],
            'metaDescription' => $r['metaDescription'],
        ], fn($v) => $v !== null && $v !== '' && $v !== []);
    }
} catch (Throwable $e) {
    error_log('catalog.php: ' . $e->getMessage());
    echo "console.error('Product catalogue unavailable.');\n";
}

$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;
echo 'const CATEGORIES = ' . json_encode($categories, $flags) . ";\n";
echo 'const PRODUCTS = ' . json_encode($products, $flags) . ";\n";
