<?php
// Template only. Copy to app/config.php (gitignored) on each server and fill in
// that server's DB / SMTP / admin values. Shared by admin/ and api/.

define('DB_HOST', 'localhost');
define('DB_NAME', 'marken_admin');
define('DB_USER', 'root');
define('DB_PASS', '');

define('ADMIN_USER', 'admin');
// Bcrypt hash of 'marken@2024'. Regenerate with: php -r "echo password_hash('newpass', PASSWORD_BCRYPT);"
define('ADMIN_PASS_HASH', '$2y$12$SUXUg9uZLYrCdMUrMLCq0u.t7XgADnebdS5ukLrLvh3QivoKTQ0Ju');

define('ROOT_DIR', dirname(__DIR__));
define('UPLOAD_DIR', ROOT_DIR . '/uploads/');
define('UPLOAD_URL', 'uploads/');               // stored in DB, relative to site root
define('MAX_UPLOAD_BYTES', 10 * 1024 * 1024);
define('MAX_VIDEO_BYTES', 50 * 1024 * 1024);   // PHP's own limit is raised to match in admin/.htaccess

// Extension is derived from the detected MIME type, never from the client's filename.
const UPLOAD_TYPES = [
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/webp'      => 'webp',
    'image/gif'       => 'gif',
    'video/mp4'       => 'mp4',
    'video/webm'      => 'webm',
    'application/pdf' => 'pdf',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
];

// Document cards always shown on the product page. A product document whose label matches
// one of these is attached to that card; any other label adds an extra card.
const STANDARD_DOCUMENTS = ['Brochure', 'Technical Datasheet', 'User Manual', 'Product Catalogue'];

// Outgoing mail (document requests). Leave SMTP_HOST empty to use PHP's mail().
// Gmail example: smtp.gmail.com / 587 / tls with an App Password.
define('MAIL_FROM',      'no-reply@markenworld.com');
define('MAIL_FROM_NAME', 'MarkEn');
define('MAIL_REPLY_TO',  'info@markenworld.com');
define('SMTP_HOST',   '');
define('SMTP_PORT',   587);
define('SMTP_SECURE', 'tls');   // 'tls' (STARTTLS, port 587) or 'ssl' (port 465)
define('SMTP_USER',   '');
define('SMTP_PASS',   '');

define('SESSION_NAME', 'mk_admin');
define('SESSION_LIFETIME', 7200);
