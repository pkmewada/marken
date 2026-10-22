<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id = (int)post('id');
    // Star toggle in the list (fetch, no reload): updates only the featured flag.
    if (post('_action') === 'feature') {
        $featured = post('featured') === '1' ? 1 : 0;
        $found = $id > 0 && dbOne('SELECT id FROM `products` WHERE id = ?', [$id]);
        if ($found) dbRun('UPDATE `products` SET featured = ? WHERE id = ?', [$featured, $id]);
        header('Content-Type: application/json');
        http_response_code($found ? 200 : 404);
        echo json_encode($found ? ['ok' => true, 'featured' => $featured] : ['error' => 'Product not found. Reload the page.']);
        exit;
    }
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
            <button type="button" class="btn btn-link p-0 fs-5 lh-1" data-feature="<?= (int)$p['id'] ?>"
                    data-featured="<?= (int)$p['featured'] ?>" title="Click to <?= $p['featured'] ? 'remove from featured' : 'mark as featured' ?>">
              <i class="bi <?= $p['featured'] ? 'bi-star-fill text-warning' : 'bi-star text-muted' ?>"></i>
            </button>
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
<?php
$pageScripts = <<<'HTML'
<script>
// Featured star: toggles in place; the icon only changes once the server has saved it.
document.querySelectorAll('[data-feature]').forEach((btn) => btn.addEventListener('click', async () => {
  const next = btn.dataset.featured === '1' ? '0' : '1';
  const body = new URLSearchParams({ _csrf: CSRF, _action: 'feature', id: btn.dataset.feature, featured: next });
  btn.disabled = true;
  try {
    const res = await fetch('products', { method: 'POST', body });
    const data = await res.json().catch(() => ({ error: res.status === 403 ? 'Your session expired. Refresh the page.' : `Server error (${res.status})` }));
    if (!res.ok || data.error) throw new Error(data.error);
    btn.dataset.featured = next;
    btn.closest('td').dataset.sortValue = next;
    btn.title = next === '1' ? 'Click to remove from featured' : 'Click to mark as featured';
    btn.querySelector('i').className = next === '1' ? 'bi bi-star-fill text-warning' : 'bi bi-star text-muted';
  } catch (err) {
    showError(err.message);
  } finally {
    btn.disabled = false;
  }
}));
</script>
HTML;
include __DIR__ . '/includes/layout-bottom.php';
?>
