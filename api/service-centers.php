<?php
// Public service center directory (published centers only). Filtering happens in the browser.
//   GET api/service-centers.php → { ok, centers, categories: [{slug,name}], serviceTypes }
require_once dirname(__DIR__) . '/app/public-api.php';
require_once dirname(__DIR__) . '/app/company.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonOut(405, ['ok' => false, 'message' => 'Method not allowed.']);
header('Cache-Control: no-cache');

try {
    $categories = dbAll('SELECT slug, name FROM `categories` ORDER BY sortOrder, name');
    $names = array_column($categories, 'name', 'slug');

    $centers = array_map(fn($r) => [
        'slug'         => $r['slug'],
        'name'         => $r['name'],
        'image'        => (string)$r['image'],
        'address'      => $r['address'],
        'locality'     => (string)$r['locality'],
        'city'         => $r['city'],
        'state'        => $r['state'],
        'phone'        => (string)$r['phone'],
        'email'        => (string)$r['email'],
        'mapUrl'       => (string)$r['mapUrl'],
        // Categories deleted since the center was saved are dropped.
        'categories'   => array_values(array_filter(array_map(
            fn($slug) => isset($names[$slug]) ? ['slug' => $slug, 'name' => $names[$slug]] : null,
            fromJson($r['productCategories'])
        ))),
        'serviceTypes' => fromJson($r['serviceTypes']),
        'serviceInfo'  => (string)$r['serviceInfo'],
    ], dbAll(
        "SELECT slug, name, image, address, locality, city, state, phone, email, mapUrl, productCategories, serviceTypes, serviceInfo
         FROM `serviceCenters` WHERE status = 'published' ORDER BY state, city, sortOrder, name"
    ));

    jsonOut(200, ['ok' => true, 'centers' => $centers, 'categories' => $categories, 'serviceTypes' => SERVICE_TYPES]);
} catch (Throwable $e) {
    error_log('service-centers.php: ' . $e->getMessage());
    jsonOut(500, ['ok' => false, 'message' => 'Service centers are unavailable right now. Please try again later.']);
}
