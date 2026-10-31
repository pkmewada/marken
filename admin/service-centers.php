<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id = (int)post('id');
    if (post('_action') === 'delete' && $id > 0) {
        $name = dbOne('SELECT name FROM `serviceCenters` WHERE id = ?', [$id])['name'] ?? null;
        if ($name !== null) {
            dbRun('DELETE FROM `serviceCenters` WHERE id = ?', [$id]);
            setFlash('success', "Service center \"$name\" deleted.");
        }
    }
    redirect('service-centers');
}

$centers = dbAll('SELECT id, slug, name, image, city, state, phone, email, status, updatedAt FROM `serviceCenters` ORDER BY state, city, sortOrder, name');
$states  = array_values(array_unique(array_column($centers, 'state')));

$pageTitle     = 'Service Centers';
$pageActive    = 'service-centers';
$topbarActions = '<a href="service-centers/new" class="btn btn-dark btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Service Center</a>';
include __DIR__ . '/includes/layout-top.php';
?>
<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
    <span>All Service Centers (<?= count($centers) ?>)</span>
    <div class="d-flex flex-wrap gap-2">
      <select class="form-select form-select-sm w-auto" data-table-filter aria-label="Filter by state">
        <option value="">All states</option>
        <?php foreach ($states as $s): ?><option value="<?= h($s) ?>"><?= h($s) ?></option><?php endforeach; ?>
      </select>
      <select class="form-select form-select-sm w-auto" data-table-filter="status" aria-label="Filter by status">
        <option value="">All statuses</option>
        <option value="published">Published</option>
        <option value="draft">Draft</option>
      </select>
      <input type="search" class="form-control form-control-sm w-auto" placeholder="Search name or city…" data-table-search>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" data-table>
      <thead class="table-light">
        <tr><th data-sort>Service Center</th><th data-sort>City</th><th data-sort>State</th><th>Contact</th><th data-sort>Status</th><th class="text-end">Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($centers as $c): ?>
        <tr data-filter="<?= h($c['state']) ?>" data-status="<?= h($c['status']) ?>">
          <td>
            <div class="d-flex align-items-center gap-2">
              <?php if ($c['image']): ?>
                <img src="../<?= h($c['image']) ?>" alt="" class="thumb" style="object-fit:cover" loading="lazy">
              <?php else: ?>
                <span class="thumb d-inline-block"></span>
              <?php endif; ?>
              <div>
                <div class="fw-semibold"><?= h($c['name']) ?></div>
                <div class="text-muted small"><?= h($c['slug']) ?></div>
              </div>
            </div>
          </td>
          <td><?= h($c['city']) ?></td>
          <td><?= h($c['state']) ?></td>
          <td class="small"><?= h($c['phone'] ?: '—') ?><div class="text-muted"><?= h($c['email']) ?></div></td>
          <td><span class="badge badge-<?= h($c['status']) ?>"><?= h(ucfirst($c['status'])) ?></span></td>
          <td class="text-end text-nowrap">
            <a href="service-centers/edit/<?= h($c['slug']) ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
            <form method="post" class="d-inline" data-confirm="Delete &quot;<?= h($c['name']) ?>&quot;? This cannot be undone.">
              <?= csrfField() ?>
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$centers): ?>
          <tr data-empty><td colspan="6" class="text-center text-muted py-4">No service centers yet. <a href="service-centers/new">Add the first one</a>.</td></tr>
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
