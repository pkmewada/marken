<?php
// Homepage contact form → `enquiries` table (admin: Contact Enquiries).
require_once dirname(__DIR__) . '/app/public-api.php';

$in = readPublicForm();
$f = [
    'name'    => preg_replace('/\s+/u', ' ', $in['name'] ?? ''),
    'email'   => normalizeEmail($in['email'] ?? ''),
    'phone'   => $in['phone'] ?? '',
    'subject' => preg_replace('/\s+/u', ' ', $in['subject'] ?? ''),
    'message' => $in['message'] ?? '',
];

$errors = [];
// Real names: letters (any language), spaces and . ' - only — rejects URLs, digits and markup.
if (!preg_match("/^[\p{L}][\p{L}\p{M} .'\-]{1,99}$/u", $f['name'])) $errors['name'] = 'Please enter your full name (letters only).';
if ($err = emailError($f['email']))                                  $errors['email'] = $err;
if ($f['phone'] !== '' && (!preg_match('/^\+?[0-9 ()\-]{7,20}$/', $f['phone']) || strlen(preg_replace('/\D/', '', $f['phone'])) < 7)) {
    $errors['phone'] = 'Please enter a valid phone number.';
}
if (mb_strlen($f['subject']) > 300)                                  $errors['subject'] = 'Subject is too long (max 300 characters).';
if (mb_strlen($f['message']) < 10 || mb_strlen($f['message']) > 5000) $errors['message'] = 'Please describe your requirement (10–5000 characters).';
elseif (preg_match_all('#https?://|www\.#i', $f['message'] . ' ' . $f['subject']) > 2) $errors['message'] = 'Please remove the links from your message.';
if ($errors) jsonOut(422, ['ok' => false, 'message' => reset($errors), 'errors' => $errors]);

$thanks = ['ok' => true, 'message' => 'Thank you! Your message has been received — our team will get back to you shortly.'];

// Double-click / resubmission of the same message: keep a single record.
if (dbOne('SELECT id FROM `enquiries` WHERE email = ? AND message = ? AND createdAt > NOW() - INTERVAL 1 DAY', [$f['email'], $f['message']])) {
    jsonOut(200, $thanks);
}

rateLimit('enquiries', 5);

dbRun('INSERT INTO `enquiries` (name, email, phone, subject, message, ip) VALUES (?, ?, ?, ?, ?, ?)',
      [$f['name'], $f['email'], $f['phone'] ?: null, $f['subject'] ?: null, $f['message'], clientIp()]);

jsonOut(200, $thanks);
