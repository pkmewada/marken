<?php
// Imports/re-checks the original static blog posts (app/blog-seed.json) and prints a report.
// Usage: php scripts/import-blog.php
// The import also runs automatically on the first request after deploying schema v5; this script
// is for re-running it safely (existing slugs are skipped) and seeing what could not be mapped.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once dirname(__DIR__) . '/app/db.php';
require_once dirname(__DIR__) . '/app/blog-seed.php';

$r = importBlogSeed(db());
printf("Categories: %d inserted, %d already present\n", $r['categoriesInserted'], $r['categoriesSkipped']);
printf("Posts:      %d inserted, %d already present\n", $r['postsInserted'], $r['postsSkipped']);
printf("In database now: %d categories, %d posts (%d published)\n",
    dbOne('SELECT COUNT(*) n FROM `blogCategories`')['n'],
    dbOne('SELECT COUNT(*) n FROM `blogs`')['n'],
    dbOne("SELECT COUNT(*) n FROM `blogs` WHERE status = 'published'")['n']);
echo $r['warnings'] ? "\nWarnings:\n - " . implode("\n - ", $r['warnings']) . "\n" : "\nNo warnings.\n";
