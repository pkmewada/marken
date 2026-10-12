<?php
// Public blog JSON (read-only, published posts only).
//   GET api/blog.php                      → { ok, categories: [active], posts: [cards, newest first] }
//   GET api/blog.php?featured=1&limit=3   → featured posts first (homepage), then newest
//   GET api/blog.php?slug=x               → { ok, post, related: [3 cards] } or 404
//   GET api/blog.php?slug=x&preview=1     → same, but drafts/scheduled posts too for a logged-in admin
// "Published" = status 'published' AND publishedAt not in the future.
require_once dirname(__DIR__) . '/app/public-api.php';
require_once dirname(__DIR__) . '/app/html-sanitizer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonOut(405, ['ok' => false, 'message' => 'Method not allowed.']);
header('Cache-Control: no-cache');

const BLOG_PUBLIC = "b.status = 'published' AND b.publishedAt IS NOT NULL AND b.publishedAt <= NOW()";
const BLOG_CARD_COLS = 'b.slug, b.title, b.excerpt, b.featuredImage, b.publishedAt, b.featured, c.slug AS categorySlug, c.name AS categoryName';

function blogCard(array $r): array {
    return [
        'slug'         => $r['slug'],
        'title'        => $r['title'],
        'excerpt'      => (string)$r['excerpt'],
        'image'        => (string)$r['featuredImage'],
        'date'         => $r['publishedAt'] ? substr($r['publishedAt'], 0, 10) : null,
        'featured'     => (bool)$r['featured'],
        'category'     => $r['categorySlug'],
        'categoryName' => $r['categoryName'],
    ];
}

/** Drafts are only visible to a logged-in admin previewing them (session read without locking or creating one). */
function isAdminPreview(): bool {
    if (($_GET['preview'] ?? '') !== '1' || empty($_COOKIE[SESSION_NAME])) return false;
    session_name(SESSION_NAME);
    session_start(['read_and_close' => true]);
    return !empty($_SESSION['admin']);
}

try {
    $slug = $_GET['slug'] ?? null;

    if ($slug !== null) {
        if (!is_string($slug) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || strlen($slug) > 150) {
            jsonOut(404, ['ok' => false, 'message' => 'Article not found.']);
        }
        $preview = isAdminPreview();
        $row = dbOne(
            'SELECT b.*, (' . BLOG_PUBLIC . ') AS isLive, c.slug AS categorySlug, c.name AS categoryName, c.isActive AS categoryActive,
                    p.slug AS productSlug, p.name AS productName
             FROM `blogs` b
             JOIN `blogCategories` c ON c.id = b.categoryId
             LEFT JOIN `products` p ON p.id = b.relatedProductId AND p.status = \'published\'
             WHERE b.slug = ?' . ($preview ? '' : ' AND ' . BLOG_PUBLIC),
            [$slug]
        );
        if (!$row) jsonOut(404, ['ok' => false, 'message' => 'Article not found.']);
        if ($preview) header('Cache-Control: no-store');

        $post = blogCard($row) + [
            'content'         => sanitizeHtml((string)$row['content']),
            'authorName'      => $row['authorName'],
            'categoryActive'  => (bool)$row['categoryActive'],
            'updatedAt'       => $row['updatedAt'],
            'metaTitle'       => $row['metaTitle'],
            'metaDescription' => $row['metaDescription'],
            'relatedProduct'  => $row['productSlug'] ? ['slug' => $row['productSlug'], 'name' => $row['productName']] : null,
            'isPreview'       => !$row['isLive'],
        ];
        // Same category first, then the newest of the rest.
        $related = dbAll(
            'SELECT ' . BLOG_CARD_COLS . ' FROM `blogs` b JOIN `blogCategories` c ON c.id = b.categoryId
             WHERE ' . BLOG_PUBLIC . ' AND b.id <> ?
             ORDER BY (b.categoryId = ?) DESC, b.publishedAt DESC LIMIT 3',
            [$row['id'], $row['categoryId']]
        );
        jsonOut(200, ['ok' => true, 'post' => $post, 'related' => array_map('blogCard', $related)]);
    }

    $limit = filter_var($_GET['limit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
    $order = ($_GET['featured'] ?? '') === '1' ? 'b.featured DESC, b.publishedAt DESC' : 'b.publishedAt DESC';
    $posts = dbAll(
        'SELECT ' . BLOG_CARD_COLS . ' FROM `blogs` b JOIN `blogCategories` c ON c.id = b.categoryId
         WHERE ' . BLOG_PUBLIC . " ORDER BY $order, b.id DESC" . ($limit ? " LIMIT $limit" : '')
    );
    $categories = dbAll('SELECT slug, name, description FROM `blogCategories` WHERE isActive = 1 ORDER BY sortOrder, name');
    jsonOut(200, ['ok' => true, 'categories' => $categories, 'posts' => array_map('blogCard', $posts)]);
} catch (Throwable $e) {
    error_log('blog.php: ' . $e->getMessage());
    jsonOut(500, ['ok' => false, 'message' => 'Articles are unavailable right now. Please try again later.']);
}
