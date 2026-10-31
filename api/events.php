<?php
// Public events JSON (published events only).
//   GET api/events.php          → { ok, upcoming: [cards, soonest first], past: [cards, newest first] }
//   GET api/events.php?slug=x   → { ok, event } or 404
// Upcoming = upcoming/ongoing phase; past = completed/cancelled (EVENT_PHASE_SQL, app/company.php).
require_once dirname(__DIR__) . '/app/public-api.php';
require_once dirname(__DIR__) . '/app/company.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonOut(405, ['ok' => false, 'message' => 'Method not allowed.']);
header('Cache-Control: no-cache');

const EVENT_CARD_COLS = 'e.slug, e.title, e.excerpt, e.bannerImage, e.startDate, e.endDate, e.startTime, e.endTime, e.venue, e.city';

function eventCard(array $r): array {
    return [
        'slug'      => $r['slug'],
        'title'     => $r['title'],
        'excerpt'   => $r['excerpt'],
        'image'     => $r['bannerImage'],
        'startDate' => $r['startDate'],
        'endDate'   => $r['endDate'],
        'startTime' => $r['startTime'] ? substr($r['startTime'], 0, 5) : null,
        'endTime'   => $r['endTime'] ? substr($r['endTime'], 0, 5) : null,
        'venue'     => $r['venue'],
        'city'      => $r['city'],
        'phase'     => $r['phase'],
    ];
}

try {
    $slug = $_GET['slug'] ?? null;
    if ($slug !== null) {
        if (!is_string($slug) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || strlen($slug) > 150) {
            jsonOut(404, ['ok' => false, 'message' => 'Event not found.']);
        }
        $row = dbOne('SELECT ' . EVENT_CARD_COLS . ', e.description, e.gallery, e.address, e.mapUrl, e.contactInfo, ('
                   . EVENT_PHASE_SQL . ") AS phase FROM `events` e WHERE e.slug = ? AND e.status = 'published'", [$slug]);
        if (!$row) jsonOut(404, ['ok' => false, 'message' => 'Event not found.']);
        jsonOut(200, ['ok' => true, 'event' => eventCard($row) + [
            'description' => (string)$row['description'],
            'gallery'     => fromJson($row['gallery']),
            'address'     => (string)$row['address'],
            'mapUrl'      => (string)$row['mapUrl'],
            'contactInfo' => (string)$row['contactInfo'],
        ]]);
    }

    $rows = dbAll('SELECT ' . EVENT_CARD_COLS . ', (' . EVENT_PHASE_SQL . ") AS phase
                   FROM `events` e WHERE e.status = 'published' ORDER BY e.startDate, e.startTime");
    $upcoming = array_values(array_filter($rows, fn($r) => in_array($r['phase'], ['upcoming', 'ongoing'], true)));
    $past     = array_reverse(array_values(array_filter($rows, fn($r) => in_array($r['phase'], ['completed', 'cancelled'], true))));
    jsonOut(200, ['ok' => true, 'upcoming' => array_map('eventCard', $upcoming), 'past' => array_map('eventCard', $past)]);
} catch (Throwable $e) {
    error_log('events.php: ' . $e->getMessage());
    jsonOut(500, ['ok' => false, 'message' => 'Events are unavailable right now. Please try again later.']);
}
