<?php
// Streams a job application's resume to a logged-in admin (storage/ is denied over HTTP).
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/app/company.php';
requireLogin();

$a = dbOne('SELECT resumeFile, resumeOriginalName FROM `jobApplications` WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
$path = $a ? RESUME_DIR . basename($a['resumeFile']) : null;
if (!$path || !is_file($path)) {
    setFlash('error', 'Resume file not found.');
    redirect('job-applications');
}

$ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = array_search($ext, RESUME_TYPES, true) ?: 'application/octet-stream';
$name = str_replace(['"', "\r", "\n"], '', $a['resumeOriginalName']);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . preg_replace('/[^\x20-\x7E]/', '_', $name) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
