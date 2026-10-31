<?php
// Public careers JSON (open jobs only: published and deadline not passed).
//   GET api/careers.php          → { ok, jobs: [cards] }
//   GET api/careers.php?slug=x   → { ok, job } or 404
require_once dirname(__DIR__) . '/app/public-api.php';
require_once dirname(__DIR__) . '/app/company.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonOut(405, ['ok' => false, 'message' => 'Method not allowed.']);
header('Cache-Control: no-cache');

function jobCard(array $r): array {
    return [
        'slug'           => $r['slug'],
        'title'          => $r['title'],
        'department'     => $r['department'],
        'location'       => $r['location'],
        'employmentType' => EMPLOYMENT_TYPES[$r['employmentType']] ?? $r['employmentType'],
        'experience'     => (string)$r['experience'],
        'deadline'       => $r['deadline'],
    ];
}

try {
    $slug = $_GET['slug'] ?? null;
    if ($slug !== null) {
        if (!is_string($slug) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || strlen($slug) > 150) {
            jsonOut(404, ['ok' => false, 'message' => 'This position was not found.']);
        }
        $row = dbOne('SELECT j.* FROM `jobs` j WHERE j.slug = ? AND ' . JOB_OPEN_SQL, [$slug]);
        if (!$row) jsonOut(404, ['ok' => false, 'message' => 'This position is closed or no longer available.']);
        jsonOut(200, ['ok' => true, 'job' => jobCard($row) + [
            'description'             => (string)$row['description'],
            'responsibilities'        => fromJson($row['responsibilities']),
            'qualifications'          => fromJson($row['qualifications']),
            'applicationInstructions' => (string)$row['applicationInstructions'],
            'datePosted'              => substr($row['createdAt'], 0, 10),
        ]]);
    }

    $jobs = dbAll(
        'SELECT j.slug, j.title, j.department, j.location, j.employmentType, j.experience, j.deadline
         FROM `jobs` j WHERE ' . JOB_OPEN_SQL . ' ORDER BY j.createdAt DESC, j.id DESC'
    );
    jsonOut(200, ['ok' => true, 'jobs' => array_map('jobCard', $jobs)]);
} catch (Throwable $e) {
    error_log('careers.php: ' . $e->getMessage());
    jsonOut(500, ['ok' => false, 'message' => 'Open positions are unavailable right now. Please try again later.']);
}
