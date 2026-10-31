<?php
// Job details page "Apply Now" form → `jobApplications` table (admin: Job Applications).
// Multipart POST: text fields + `resume` (PDF/DOC/DOCX, stored privately in storage/resumes/).
require_once dirname(__DIR__) . '/app/public-api.php';
require_once dirname(__DIR__) . '/app/company.php';

// A file over post_max_size empties $_POST entirely, which would otherwise look like a bot.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    jsonOut(413, ['ok' => false, 'message' => 'Your resume is too large. Please upload a file under 5 MB.']);
}

$in = readPublicForm();
$f = [
    'job'             => $in['job'] ?? '',
    'fullName'        => preg_replace('/\s+/u', ' ', $in['fullName'] ?? ''),
    'email'           => normalizeEmail($in['email'] ?? ''),
    'phone'           => $in['phone'] ?? '',
    'currentLocation' => preg_replace('/\s+/u', ' ', $in['currentLocation'] ?? ''),
    'coverLetter'     => $in['coverLetter'] ?? '',
];

$job = preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $f['job'])
    ? dbOne('SELECT j.id, j.title FROM `jobs` j WHERE j.slug = ? AND ' . JOB_OPEN_SQL, [$f['job']])
    : null;
if (!$job) jsonOut(404, ['ok' => false, 'message' => 'This position is closed or no longer available.']);

$errors = [];
if (!preg_match("/^[\p{L}][\p{L}\p{M} .'\-]{1,149}$/u", $f['fullName'])) $errors['fullName'] = 'Please enter your full name (letters only).';
if ($err = emailError($f['email']))                                      $errors['email'] = $err;
if (!preg_match('/^\+?[0-9 ()\-]{7,20}$/', $f['phone']) || strlen(preg_replace('/\D/', '', $f['phone'])) < 7) {
    $errors['phone'] = 'Please enter a valid contact number.';
}
if (mb_strlen($f['currentLocation']) < 2 || mb_strlen($f['currentLocation']) > 150) $errors['currentLocation'] = 'Please enter your current location.';
if (mb_strlen($f['coverLetter']) > 5000) $errors['coverLetter'] = 'Cover letter is too long (max 5000 characters).';
elseif (preg_match_all('#https?://|www\.#i', $f['coverLetter']) > 3) $errors['coverLetter'] = 'Please remove some of the links from your cover letter.';

$file = $_FILES['resume'] ?? null;
$mime = null;
if (!$file || is_array($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
    $errors['resume'] = 'Please attach your resume (PDF, DOC or DOCX).';
} elseif (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file['size'] > MAX_RESUME_BYTES) {
    $errors['resume'] = 'Your resume is too large. Please upload a file under 5 MB.';
} elseif ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    $errors['resume'] = 'The resume upload failed. Please try again.';
} else {
    // Type from the file's content, never from the client's name or MIME header.
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(RESUME_TYPES[$mime])) $errors['resume'] = 'Please upload your resume as a PDF, DOC or DOCX file.';
}
if ($errors) jsonOut(422, ['ok' => false, 'message' => reset($errors), 'errors' => $errors]);

$thanks = ['ok' => true, 'message' => 'Thank you for applying! Our HR team will review your application and contact you if your profile matches the role.'];

// Resubmission (double click / back button): keep a single application per job and email per day.
if (dbOne('SELECT id FROM `jobApplications` WHERE jobId = ? AND email = ? AND createdAt > NOW() - INTERVAL 1 DAY', [$job['id'], $f['email']])) {
    jsonOut(200, $thanks);
}

rateLimit('jobApplications', 5);

$stored = date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.' . RESUME_TYPES[$mime];
if (!is_dir(RESUME_DIR) && !mkdir(RESUME_DIR, 0750, true)) {
    error_log('job-application.php: resume folder is not writable');
    jsonOut(500, ['ok' => false, 'message' => 'We could not save your application. Please try again later.']);
}
if (!move_uploaded_file($file['tmp_name'], RESUME_DIR . $stored)) {
    error_log('job-application.php: could not move uploaded resume');
    jsonOut(500, ['ok' => false, 'message' => 'We could not save your application. Please try again later.']);
}

$original = mb_substr(preg_replace('/[^\p{L}\p{N} ._()\-]/u', '_', basename((string)$file['name'])), 0, 200) ?: 'resume.' . RESUME_TYPES[$mime];
dbRun(
    'INSERT INTO `jobApplications` (jobId, jobTitle, fullName, email, phone, currentLocation, resumeFile, resumeOriginalName, coverLetter, ip)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [$job['id'], $job['title'], $f['fullName'], $f['email'], $f['phone'], $f['currentLocation'],
     $stored, $original, $f['coverLetter'] ?: null, clientIp()]
);

jsonOut(200, $thanks);
