<?php
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

function fail(int $code, string $msg): never {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

if (!isLoggedIn()) fail(401, 'Your session has expired. Please log in again.');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'Method not allowed.');
if (!hash_equals(csrfToken(), (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) fail(403, 'Invalid security token. Refresh the page.');

$file = $_FILES['file'] ?? null;
if (!$file || is_array($file['error'])) fail(400, 'No file received.');
if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
    fail(413, 'File is larger than the server allows (' . ini_get('upload_max_filesize') . ').');
}
if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) fail(400, 'Upload failed. Please try again.');
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
$ext  = UPLOAD_TYPES[$mime] ?? null;
if (!$ext) fail(415, 'File type not allowed. Use JPG, PNG, WebP, GIF, MP4, WebM, PDF or Word.');

$isVideo = str_starts_with($mime, 'video/');
if ($file['size'] > ($isVideo ? MAX_VIDEO_BYTES : MAX_UPLOAD_BYTES)) {
    fail(413, $isVideo ? 'Video is too large (max 50 MB).' : 'File is too large (max 10 MB).');
}

$base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', pathinfo($file['name'], PATHINFO_FILENAME)));
$base = trim(substr($base, 0, 50), '-') ?: 'file';
$name = date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '-' . $base . '.' . $ext;

if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) fail(500, 'Upload folder is not writable.');
if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) fail(500, 'Could not save the file.');

echo json_encode(['url' => UPLOAD_URL . $name, 'filename' => $name, 'size' => $file['size'], 'mime' => $mime]);
