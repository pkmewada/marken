<?php
// Imports app/blog-seed.json (an exact export of the old static js/blog-data.js) into
// blogCategories / blogs. Safe to rerun: rows are matched by slug and existing ones are never
// touched, so admin edits survive and nothing is duplicated.
// Runs automatically once (schema v5, see schema.php); scripts/import-blog.php reruns it with a report.
require_once __DIR__ . '/html-sanitizer.php';

const BLOG_SEED_FILE = __DIR__ . '/blog-seed.json';
const BLOG_SEED_POST_KEYS = ['slug', 'title', 'category', 'image', 'date', 'featured', 'excerpt', 'relatedProductSlug', 'sections'];

/** Old static "sections" ({heading, body} with blank-line paragraphs) → the HTML the editor stores. */
function blogSectionsToHtml(array $sections): string {
    $html = '';
    foreach ($sections as $s) {
        if (($s['heading'] ?? '') !== '') $html .= '<h2>' . htmlspecialchars($s['heading'], ENT_QUOTES, 'UTF-8') . '</h2>';
        foreach (preg_split('/\n\s*\n/', (string)($s['body'] ?? '')) as $para) {
            if (trim($para) !== '') $html .= '<p>' . htmlspecialchars(trim($para), ENT_QUOTES, 'UTF-8') . '</p>';
        }
    }
    return $html;
}

/** Returns a report: inserted/skipped counts plus warnings (missing images, bad slugs, unmapped fields…). */
function importBlogSeed(PDO $pdo): array {
    $r = ['categoriesInserted' => 0, 'categoriesSkipped' => 0, 'postsInserted' => 0, 'postsSkipped' => 0, 'warnings' => []];
    $seed = json_decode((string)@file_get_contents(BLOG_SEED_FILE), true);
    if (!is_array($seed)) {
        $r['warnings'][] = 'Seed file missing or invalid: ' . BLOG_SEED_FILE;
        return $r;
    }
    $slugOk = fn($s) => is_string($s) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $s);

    $insCat = $pdo->prepare('INSERT IGNORE INTO `blogCategories` (name, slug, isActive, sortOrder) VALUES (?, ?, 1, ?)');
    foreach ($seed['categories'] ?? [] as $i => $c) {
        if (!$slugOk($c['slug'] ?? null) || ($c['name'] ?? '') === '') {
            $r['warnings'][] = 'Category skipped (invalid slug or name): ' . json_encode($c);
            continue;
        }
        $insCat->execute([$c['name'], $c['slug'], $i + 1]);
        $insCat->rowCount() ? $r['categoriesInserted']++ : $r['categoriesSkipped']++;
    }

    $catId  = $pdo->prepare('SELECT id FROM `blogCategories` WHERE slug = ?');
    $prodId = $pdo->prepare('SELECT id FROM `products` WHERE slug = ?');
    $insPost = $pdo->prepare('INSERT IGNORE INTO `blogs`
        (categoryId, title, slug, excerpt, content, featuredImage, authorName, status, featured, relatedProductId, publishedAt)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    foreach ($seed['posts'] ?? [] as $p) {
        $slug = $p['slug'] ?? '';
        if (!$slugOk($slug) || ($p['title'] ?? '') === '') {
            $r['warnings'][] = 'Post skipped (invalid slug or title): ' . ($p['title'] ?? json_encode($slug));
            continue;
        }
        if ($extra = array_diff(array_keys($p), BLOG_SEED_POST_KEYS)) {
            $r['warnings'][] = "$slug: unmapped fields not imported: " . implode(', ', $extra);
        }
        $catId->execute([$p['category'] ?? '']);
        $categoryId = $catId->fetchColumn();
        if (!$categoryId) {
            $r['warnings'][] = "$slug: skipped — unknown category \"" . ($p['category'] ?? '') . '"';
            continue;
        }
        $image = (string)($p['image'] ?? '');
        if ($image === '' || !is_file(dirname(__DIR__) . '/' . $image)) {
            $r['warnings'][] = "$slug: featured image not found on disk: " . ($image ?: '(none)');
        }
        $relatedId = null;
        if (!empty($p['relatedProductSlug'])) {
            $prodId->execute([$p['relatedProductSlug']]);
            $relatedId = $prodId->fetchColumn() ?: null;
            if (!$relatedId) $r['warnings'][] = "$slug: related product \"{$p['relatedProductSlug']}\" not found — link left empty";
        }
        $date = (string)($p['date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $r['warnings'][] = "$slug: missing/invalid date \"$date\" — imported as a draft";
        }

        $insPost->execute([
            $categoryId, $p['title'], $slug, $p['excerpt'] ?? null,
            sanitizeHtml(blogSectionsToHtml($p['sections'] ?? [])),
            $image ?: null, 'MarkEn Team',
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? 'published' : 'draft',
            !empty($p['featured']) ? 1 : 0, $relatedId,
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? "$date 00:00:00" : null,
        ]);
        $insPost->rowCount() ? $r['postsInserted']++ : $r['postsSkipped']++;
    }
    return $r;
}
