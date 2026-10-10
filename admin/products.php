<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id = (int)post('id');
    if (post('_action') === 'delete' && $id > 0) {
        dbRun('DELETE FROM `products` WHERE id = ?', [$id]);
        setFlash('success', 'Product deleted.');
    }
    redirect('products');
}

$products = dbAll(
    'SELECT p.id, p.slug, p.name, p.status, p.featured, p.image, p.updatedAt, c.name AS categoryName
     FROM `products` p LEFT JOIN `categories` c ON c.slug = p.categorySlug
     ORDER BY p.sortOrder, p.name'
);
$categories = dbAll('SELECT name FROM `categories` ORDER BY sortOrder, name');

$pageTitle     = 'Products';
$pageActive    = 'products';
$topbarActions = '<a href="products/new" class="btn btn-dark btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Product</a>';
include __DIR__ . '/includes/layout-top.php';
?>
<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
    <span>All Products (<?= count($products) ?>)</span>
    <div class="d-flex gap-2">
      <select class="form-select form-select-sm" data-table-filter>
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= h($c['name']) ?>"><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="search" class="form-control form-control-sm" placeholder="Search by name or slug…" data-table-search>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" data-table>
      <thead class="table-light">
        <tr>
          <th data-sort>Product</th>
          <th data-sort>Category</th>
          <th data-sort>Status</th>
          <th data-sort>Featured</th>
          <th data-sort>Updated</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): ?>
        <tr data-filter="<?= h($p['categoryName']) ?>">
          <td>
            <div class="d-flex align-items-center gap-2">
              <?php if ($p['image']): ?>
                <img src="../<?= h($p['image']) ?>" alt="" class="thumb" loading="lazy">
              <?php else: ?>
                <span class="thumb d-inline-block"></span>
              <?php endif; ?>
              <div>
                <div class="fw-semibold"><?= h($p['name']) ?></div>
                <div class="text-muted small"><?= h($p['slug']) ?></div>
              </div>
            </div>
          </td>
          <td><?= h($p['categoryName'] ?? '—') ?></td>
          <td><span class="badge badge-<?= h($p['status']) ?>"><?= h(ucfirst($p['status'])) ?></span></td>
          <td data-sort-value="<?= (int)$p['featured'] ?>">
            <i class="bi <?= $p['featured'] ? 'bi-star-fill text-warning' : 'bi-star text-muted' ?>"></i>
          </td>
          <td class="small text-muted" data-sort-value="<?= h($p['updatedAt']) ?>"><?= h(date('d M Y', strtotime($p['updatedAt']))) ?></td>
          <td class="text-end text-nowrap">
            <a href="../product-details/<?= h($p['slug']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="View on site"><i class="bi bi-box-arrow-up-right"></i></a>
            <a href="products/edit/<?= h($p['slug']) ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
            <form method="post" class="d-inline" data-confirm="Delete &quot;<?= h($p['name']) ?>&quot;? This cannot be undone.">
              <?= csrfField() ?>
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center small text-muted">
    <span data-table-info></span>
    <div class="d-flex gap-1" data-table-pager></div>
  </div>
</div>
<?php include __DIR__ . '/includes/layout-bottom.php'; ?>
