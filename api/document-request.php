<?php
// Product page "Documentation" cards: email the requested document (attached when
// the admin uploaded a file for it) and log the request for sales follow-up.
require_once dirname(__DIR__) . '/app/public-api.php';
require_once dirname(__DIR__) . '/app/mailer.php';

$in    = readPublicForm();
$email = normalizeEmail($in['email'] ?? '');
$slug  = $in['product'] ?? '';
$label = $in['document'] ?? '';

if ($err = emailError($email)) jsonOut(422, ['ok' => false, 'message' => $err]);

$product = dbOne("SELECT slug, name, documents FROM `products` WHERE slug = ? AND status = 'published'", [$slug]);
if (!$product) jsonOut(404, ['ok' => false, 'message' => 'Product not found.']);

// Resolve the document server-side; never trust a URL sent by the browser.
$doc = null;
foreach (fromJson($product['documents']) as $d) {
    if (strcasecmp(trim($d['label'] ?? ''), $label) === 0) { $doc = $d; break; }
}
if (!$doc && !in_array($label, STANDARD_DOCUMENTS, true)) {
    jsonOut(422, ['ok' => false, 'message' => 'Unknown document.']);
}
$label = $doc['label'] ?? $label;
$url   = $doc['url'] ?? '';

// Same document already emailed to this address today: don't send or log it again.
if (dbOne("SELECT id FROM `documentRequests` WHERE email = ? AND productSlug = ? AND documentLabel = ?
           AND mailStatus = 'sent' AND createdAt > NOW() - INTERVAL 1 DAY", [$email, $product['slug'], $label])) {
    jsonOut(200, ['ok' => true, 'message' => "We already sent this to $email today — please check your inbox and spam folder."]);
}

rateLimit('documentRequests', 10);

// Local uploads are attached; external links go in the email body.
$attachments = [];
$link = '';
if ($url !== '' && preg_match('#^https?://#i', $url)) {
    $link = $url;
} elseif ($url !== '') {
    $path = realpath(ROOT_DIR . '/' . $url);
    $root = realpath(ROOT_DIR);
    if ($path && is_file($path) && str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $attachments[] = ['path' => $path, 'name' => preg_replace('/[^A-Za-z0-9 ._-]+/', '', "{$product['name']} - $label") . ".$ext"];
    }
}

$e = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
if ($attachments) {
    $intro = "Please find the <strong>{$e($label)}</strong> for <strong>{$e($product['name'])}</strong> attached to this email.";
} elseif ($link) {
    $intro = "You can download the <strong>{$e($label)}</strong> for <strong>{$e($product['name'])}</strong> here:<br><br>"
           . "<a href=\"{$e($link)}\" style=\"display:inline-block;background:#00176d;color:#fff;padding:12px 22px;text-decoration:none;font-weight:600\">Download {$e($label)}</a>";
} else {
    $intro = "Thank you for requesting the <strong>{$e($label)}</strong> for <strong>{$e($product['name'])}</strong>. "
           . 'Our product team is preparing the latest version and will send it to you shortly.';
}
$html = <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;color:#0e0e0e;max-width:600px;margin:0 auto">
  <div style="background:#00176d;color:#fff;padding:20px 28px;font-size:20px;font-weight:bold">MarkEn</div>
  <div style="padding:28px;border:1px solid #e3e5ea;border-top:0;line-height:1.6;font-size:15px">
    <p>Hello,</p>
    <p>$intro</p>
    <p>If you have any questions or would like a quotation, simply reply to this email or call us on +91 91303 05959.</p>
    <p style="margin-top:28px">Best regards,<br><strong>Team MarkEn</strong><br>
      <a href="mailto:info@markenworld.com" style="color:#00176d">info@markenworld.com</a></p>
  </div>
  <p style="font-size:12px;color:#626979;padding:12px 28px">You received this email because this address requested product documentation on the MarkEn website.</p>
</div>
HTML;

$status = 'sent';
$error  = null;
try {
    sendMail($email, "{$label} – {$product['name']} | MarkEn", $html, $attachments);
} catch (Throwable $ex) {
    $status = 'failed';
    $error  = mb_substr($ex->getMessage(), 0, 500);
    error_log('document-request mail: ' . $ex->getMessage());
}

dbRun('INSERT INTO `documentRequests` (email, productSlug, productName, documentLabel, documentUrl, mailStatus, mailError, ip)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
      [$email, $product['slug'], $product['name'], $label, $url ?: null, $status, $error, clientIp()]);

jsonOut(200, $status === 'sent'
    ? ['ok' => true, 'message' => "Sent! Check your inbox at $email."]
    : ['ok' => true, 'message' => 'Request received — our team will email it to you shortly.']);
