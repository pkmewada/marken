<?php
// Footer "Stay Updated" form → `newsletterSubscribers` (admin: Newsletter).
require_once dirname(__DIR__) . '/app/public-api.php';

$in    = readPublicForm();
$email = normalizeEmail($in['email'] ?? '');
if ($err = emailError($email)) jsonOut(422, ['ok' => false, 'message' => $err]);

if (dbOne('SELECT id FROM `newsletterSubscribers` WHERE email = ?', [$email])) {
    jsonOut(200, ['ok' => true, 'message' => "You're already subscribed — thank you!"]);
}

rateLimit('newsletterSubscribers', 5);

// INSERT IGNORE: the unique key on email also covers two requests racing each other.
dbRun('INSERT IGNORE INTO `newsletterSubscribers` (email, sourcePage, ip) VALUES (?, ?, ?)',
      [$email, mb_substr($in['page'] ?? '', 0, 200) ?: null, clientIp()]);

jsonOut(200, ['ok' => true, 'message' => "Thank you — you're subscribed."]);
