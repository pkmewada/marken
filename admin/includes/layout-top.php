<?php
/**
 * Expects: $pageTitle, $pageActive (a key of $nav below, e.g. 'products' or 'blogs'),
 * optional $topbarActions (trusted HTML string).
 */
$pageTitle     ??= 'Admin';
$pageActive    ??= '';
$topbarActions ??= '';
$nav = [
    'Catalogue' => [
        'categories' => ['categories', 'bi-grid-3x3-gap', 'Categories'],
        'products'   => ['products',   'bi-box-seam',     'Products'],
    ],
    'Blog' => [
        'blogs'           => ['blogs',           'bi-journal-richtext', 'Blog Posts'],
        'blog-categories' => ['blog-categories', 'bi-tags',             'Blog Categories'],
    ],
    'Careers' => [
        'jobs'             => ['jobs',             'bi-briefcase',      'Job Openings'],
        'job-applications' => ['job-applications', 'bi-person-lines-fill', 'Job Applications'],
    ],
    'Company' => [
        'events'          => ['events',          'bi-calendar-event', 'Events'],
        'service-centers' => ['service-centers', 'bi-geo-alt',        'Service Centers'],
    ],
    'CRM' => [
        'enquiries'         => ['enquiries',         'bi-envelope',       'Contact Enquiries'],
        'document-requests' => ['document-requests', 'bi-file-earmark-arrow-down', 'Document Requests'],
        'newsletter'        => ['newsletter',        'bi-megaphone',      'Newsletter'],
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <!-- Pretty URLs like products/edit/slug are nested; resolve relative links from the admin root. -->
  <base href="<?= h(adminUrl()) ?>">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <meta name="csrf-token" content="<?= h(csrfToken()) ?>">
  <title><?= h($pageTitle) ?> — MarkEn Admin</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/admin.css?v=4">
</head>
<body>
<aside class="mk-sidebar">
  <a href="products" class="brand"><i class="bi bi-heart-pulse me-2"></i>MarkEn Admin</a>
  <nav>
    <?php foreach ($nav as $group => $items): ?>
      <div class="nav-label"><?= h($group) ?></div>
      <?php foreach ($items as $key => [$href, $icon, $label]): ?>
        <a href="<?= $href ?>" class="nav-link<?= $pageActive === $key ? ' active' : '' ?>">
          <i class="bi <?= $icon ?>"></i> <?= h($label) ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <div class="nav-label">Website</div>
    <a href="../" class="nav-link" target="_blank" rel="noopener"><i class="bi bi-globe"></i> View Website</a>
  </nav>
  <div class="sidebar-footer">
    <i class="bi bi-person-circle me-1"></i><?= h($_SESSION['admin'] ?? '') ?>
    &middot; <a href="logout">Logout</a>
  </div>
</aside>
<div class="mk-backdrop" data-sidebar-toggle></div>

<div class="mk-main">
  <header class="mk-topbar">
    <div class="d-flex align-items-center gap-2">
      <button type="button" class="btn btn-sm btn-outline-secondary d-lg-none" data-sidebar-toggle aria-label="Menu">
        <i class="bi bi-list"></i>
      </button>
      <h1><?= h($pageTitle) ?></h1>
    </div>
    <div class="d-flex align-items-center gap-2"><?= $topbarActions ?></div>
  </header>
  <main class="mk-content">
    <?= flashHtml() ?>
