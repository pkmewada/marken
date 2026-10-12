<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id = (int)post('id');
    if (post('_action') === 'delete' && $id > 0) {
        $title = dbOne('SELECT title FROM `blogs` WHERE id = ?', [$id])['title'] ?? null;
        if ($title !== null) {
            dbRun('DELETE FROM `blogs` WHERE id = ?', [$id]);
            setFlash('success', "Blog post \"$title\" deleted.");
        }
    }
    redirect('blogs');
}

$blogs = dbAll(
    'SELECT b.id, b.slug, b.title, b.status, b.featured, b.featuredImage, b.authorName, b.publishedAt, b.updatedAt,
            c.name AS categoryName,
            (b.status = \'published\' AND (b.publishedAt IS NULL OR b.publishedAt > NOW())) AS isScheduled
     FROM `blogs` b JOIN `blogCategories` c ON c.id = b.categoryId
     ORDER BY b.publishedAt IS NULL DESC, b.publishedAt DESC, b.id DESC'
);
$categories = dbAll('SELECT name FROM `blogCategories` ORDER BY sortOrder, name');
$preCategory = is_string($_GET['category'] ?? null) ? $_GET['category'] : '';

$pageTitle     = 'Blog Posts';
$pageActive    = 'blogs';
$topbarActions = '<a href="blogs/new" class="btn btn-dark btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Blog Post</a>';
include __DIR__ . '/includes/layout-top.php';
?>
<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
    <span>All Blog Posts (<?= count($blogs) ?>)</span>
    <div class="d-flex flex-wrap gap-2">
      <select class="form-select form-select-sm w-auto" data-table-filter="filter" aria-label="Filter by category">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= h($c['name']) ?>"<?= $preCategory === $c['name'] ? ' selected' : '' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="form-select form-select-sm w-auto" data-table-filter="status" aria-label="Filter by status">
        <option value="">All statuses</option>
        <option value="published">Published</option>
        <option value="scheduled">Scheduled</option>
        <option value="draft">Draft</option>
      </select>
      <input type="search" class="form-control form-control-sm w-auto" placeholder="Search by title or slug…" data-table-search>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" data-table>
      <thead class="table-light">
        <tr>
          <th data-sort>Post</th>
          <th data-sort>Category</th>
          <th data-sort>Author</th>
          <th data-sort>Published</th>
          <th data-sort>Status</th>
          <th data-sort>Updated</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($blogs as $b):
          // A published post dated in the future is hidden from the site until that date (same rule as api/blog.php).
          $status = $b['isScheduled'] ? 'scheduled' : $b['status'];
          $isLive = $status === 'published';
        ?>
        <tr data-filter="<?= h($b['categoryName']) ?>" data-status="<?= $status ?>">
          <td>
            <div class="d-flex align-items-center gap-2">
              <?php if ($b['featuredImage']): ?>
                <img src="../<?= h($b['featuredImage']) ?>" alt="" class="thumb" style="object-fit:cover" loading="lazy">
              <?php else: ?>
                <span class="thumb d-inline-block"></span>
              <?php endif; ?>
              <div>
                <div class="fw-semibold"><?= h($b['title']) ?><?php if ($b['featured']): ?> <i class="bi bi-star-fill text-warning small" title="Featured on the homepage"></i><?php endif; ?></div>
                <div class="text-muted small"><?= h($b['slug']) ?></div>
              </div>
            </div>
          </td>
          <td><?= h($b['categoryName']) ?></td>
          <td class="small"><?= h($b['authorName']) ?></td>
          <td class="small text-muted text-nowrap" data-sort-value="<?= h($b['publishedAt'] ?? '') ?>"><?= $b['publishedAt'] ? h(date('d M Y', strtotime($b['publishedAt']))) : '—' ?></td>
          <td><span class="badge badge-<?= $status === 'scheduled' ? 'new' : h($b['status']) ?>"><?= h(ucfirst($status)) ?></span></td>
          <td class="small text-muted text-nowrap" data-sort-value="<?= h($b['updatedAt']) ?>"><?= h(date('d M Y', strtotime($b['updatedAt']))) ?></td>
          <td class="text-end text-nowrap">
            <a href="../blog-details?slug=<?= h(rawurlencode($b['slug'])) ?><?= $isLive ? '' : '&amp;preview=1' ?>" target="_blank" rel="noopener"
               class="btn btn-sm btn-outline-secondary" title="<?= $isLive ? 'View on site' : 'Preview (only visible to you)' ?>"><i class="bi bi-<?= $isLive ? 'box-arrow-up-right' : 'eye' ?>"></i></a>
            <a href="blogs/edit/<?= h($b['slug']) ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
            <form method="post" class="d-inline" data-confirm="Delete &quot;<?= h($b['title']) ?>&quot;? This cannot be undone.">
              <?= csrfField() ?>
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$blogs): ?>
          <tr data-empty><td colspan="7" class="text-center text-muted py-4">No blog posts yet. <a href="blogs/new">Write the first one</a>.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center small text-muted">
    <span data-table-info></span>
    <div class="d-flex gap-1" data-table-pager></div>
  </div>
</div>
<?php include __DIR__ . '/includes/layout-bottom.php'; ?>
