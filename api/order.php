<?php
// Guest checkout (checkout.html) → `orders` table (admin: Orders), status pending-payment.
// The browser sends only what was picked; price and totals are always worked out here from the DB.
require_once dirname(__DIR__) . '/app/public-api.php';

$in = readPublicForm();
$f = [
    'product'  => $in['product'] ?? '',
    'variant'  => $in['variant'] ?? '',
    'color'    => $in['color'] ?? '',
    'quantity' => $in['quantity'] ?? '1',
    'fullName' => preg_replace('/\s+/u', ' ', $in['fullName'] ?? ''),
    'email'    => normalizeEmail($in['email'] ?? ''),
    'phone'    => $in['phone'] ?? '',
    'company'  => preg_replace('/\s+/u', ' ', $in['company'] ?? ''),
    'gstin'    => strtoupper($in['gstin'] ?? ''),
    'address1' => preg_replace('/\s+/u', ' ', $in['address1'] ?? ''),
    'address2' => preg_replace('/\s+/u', ' ', $in['address2'] ?? ''),
    'city'     => preg_replace('/\s+/u', ' ', $in['city'] ?? ''),
    'state'    => preg_replace('/\s+/u', ' ', $in['state'] ?? ''),
    'pincode'  => $in['pincode'] ?? '',
];

$product = preg_match('/^[a-z0-9-]{1,100}$/', $f['product'])
    ? dbOne("SELECT id, name, price, variants, colors FROM `products` WHERE slug = ? AND status = 'published' AND onSale = 1", [$f['product']])
    : null;
if (!$product) jsonOut(404, ['ok' => false, 'message' => 'This product is not available to buy online. Please contact us for a quote.']);

$errors = [];
$unitPrice = (float)$product['price'];
$variants = fromJson($product['variants']);
if ($variants) {
    $match = array_values(array_filter($variants, fn($v) => ($v['name'] ?? '') === $f['variant']));
    if (!$match) $errors['variant'] = 'Please choose a model.';
    else $unitPrice = (float)$match[0]['price'];
} else {
    $f['variant'] = '';
}
$colors = array_column(fromJson($product['colors']), 'name');
if ($colors && !in_array($f['color'], $colors, true)) $errors['color'] = 'Please choose a colour.';
if (!$colors) $f['color'] = '';
if (!ctype_digit($f['quantity']) || (int)$f['quantity'] < 1 || (int)$f['quantity'] > 99) $errors['quantity'] = 'Quantity must be between 1 and 99.';
if ($unitPrice <= 0) $errors['product'] = 'This product has no price yet. Please contact us for a quote.';

if (!preg_match("/^[\p{L}][\p{L}\p{M} .'\-]{1,99}$/u", $f['fullName'])) $errors['fullName'] = 'Please enter your full name (letters only).';
if ($err = emailError($f['email'])) $errors['email'] = $err;
if (!preg_match('/^\+?[0-9 ()\-]{7,20}$/', $f['phone']) || strlen(preg_replace('/\D/', '', $f['phone'])) < 10) {
    $errors['phone'] = 'Please enter a valid mobile number.';
}
if (mb_strlen($f['company']) > 150) $errors['company'] = 'Company name is too long.';
// GSTIN: 2-digit state code, PAN, entity number, "Z", checksum character.
if ($f['gstin'] !== '' && !preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $f['gstin'])) $errors['gstin'] = 'Please enter a valid 15-character GSTIN.';
if (mb_strlen($f['address1']) < 5 || mb_strlen($f['address1']) > 200) $errors['address1'] = 'Please enter your street address.';
if (mb_strlen($f['address2']) > 200) $errors['address2'] = 'Address line 2 is too long.';
if (mb_strlen($f['city']) < 2 || mb_strlen($f['city']) > 100) $errors['city'] = 'Please enter your city.';
if (mb_strlen($f['state']) < 2 || mb_strlen($f['state']) > 100) $errors['state'] = 'Please choose your state.';
if (!preg_match('/^[1-9]\d{5}$/', $f['pincode'])) $errors['pincode'] = 'Please enter a valid 6-digit PIN code.';
if ($errors) jsonOut(422, ['ok' => false, 'message' => reset($errors), 'errors' => $errors]);

$qty   = (int)$f['quantity'];
$total = round($unitPrice * $qty, 2);
$reply = fn(string $no) => ['ok' => true, 'orderNumber' => $no, 'total' => $total, 'productName' => $product['name']];

// Double-click / page refresh: reuse the same unpaid order instead of creating another.
$existing = dbOne(
    "SELECT orderNumber FROM `orders` WHERE email = ? AND productId = ? AND variant <=> ? AND color <=> ? AND quantity = ?
       AND status = 'pending-payment' AND createdAt > NOW() - INTERVAL 1 HOUR ORDER BY id DESC LIMIT 1",
    [$f['email'], $product['id'], $f['variant'] ?: null, $f['color'] ?: null, $qty]
);
if ($existing) jsonOut(200, $reply($existing['orderNumber']));

rateLimit('orders', 5);

$sql = 'INSERT INTO `orders` (orderNumber, productId, productName, variant, color, quantity, unitPrice, total,
        fullName, email, phone, company, gstin, address1, address2, city, state, pincode, ip)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
for ($try = 0; ; $try++) {
    $orderNumber = 'MK' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));   // e.g. MK261123A1B2C3
    try {
        dbRun($sql, [
            $orderNumber, $product['id'], $product['name'], $f['variant'] ?: null, $f['color'] ?: null, $qty, $unitPrice, $total,
            $f['fullName'], $f['email'], $f['phone'], $f['company'] ?: null, $f['gstin'] ?: null,
            $f['address1'], $f['address2'] ?: null, $f['city'], $f['state'], $f['pincode'], clientIp(),
        ]);
        break;
    } catch (PDOException $e) {
        // 23000 = duplicate orderNumber (very unlikely); try a new one.
        if ($e->getCode() !== '23000' || $try >= 2) throw $e;
    }
}

jsonOut(200, $reply($orderNumber));
