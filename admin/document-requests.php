<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id = (int)post('id');
    if (post('_action') === 'delete' && $id > 0) {
        dbRun('DELETE FROM `documentRequests` WHERE id = ?', [$id]);
        setFlash('success', 'Request deleted.');
    }
    redirect('document-requests');
}

// CSV export for the sales team
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="document-requests-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM so Excel shows –/° correctly
    fputcsv($out, ['Email', 'Product', 'Document', 'Email status', 'Requested at']);
    foreach (dbAll('SELECT email, productName, documentLabel, mailStatus, createdAt FROM `documentRequests` ORDER BY createdAt DESC') as $r) {
        // Prefix formula-like cells so spreadsheet apps don't execute them.
        fputcsv($out, array_map(fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'$v" : $v, array_values($r)));
    }
    exit;
}

$requests = dbAll('SELECT * FROM `documentRequests` ORDER BY createdAt DESC');
$products = array_unique(array_column($requests, 'productName'));
sort($products);

$pageTitle     = 'Document Requests';
$pageActive    = 'document-requests';
$topbarActions = '<a href="document-requests?export=csv" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>Export CSV</a>';
include __DIR__ . '/includes/layout-top.php';
?>
<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
    <span>All Requests (<?= count($requests) ?>)</span>
    <div class="d-flex gap-2">
      <select class="form-select form-select-sm" data-table-filter>
        <option value="">All products</option>
        <?php foreach ($products as $name): ?><option value="<?= h($name) ?>"><?= h($name) ?></option><?php endforeach; ?>
      </select>
      <input type="search" class="form-control form-control-sm" placeholder="Search email, product or document…" data-table-search>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" data-table>
      <thead class="table-light">
        <tr><th data-sort>Email</th><th data-sort>Product</th><th data-sort>Document</th><th data-sort>Email Status</th><th data-sort>Requested</th><th class="text-end">Actions</th></tr>
      </thead>
      <tbody>
        <?php if (!$requests): ?>
          <tr data-empty><td colspan="6" class="text-center text-muted py-4">No document requests yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($requests as $r): ?>
        <tr data-filter="<?= h($r['productName']) ?>">
          <td><a href="mailto:<?= h($r['email']) ?>"><?= h($r['email']) ?></a></td>
          <td>
            <a href="../product-details/<?= h($r['productSlug']) ?>" target="_blank" rel="noopener"><?= h($r['productName']) ?></a>
          </td>
          <td>
            <?= h($r['documentLabel']) ?>
            <?php if (!$r['documentUrl']): ?><div class="small text-muted">No product document with this label at the time — send manually</div><?php endif; ?>
          </td>
          <td>
            <?php if ($r['mailStatus'] === 'sent'): ?>
              <span class="badge badge-replied">Sent</span>
            <?php else: ?>
              <span class="badge text-bg-danger" title="<?= h($r['mailError']) ?>">Failed</span>
            <?php endif; ?>
          </td>
          <td class="small text-muted" data-sort-value="<?= h($r['createdAt']) ?>"><?= h(date('d M Y, H:i', strtotime($r['createdAt']))) ?></td>
          <td class="text-end">
            <form method="post" class="d-inline" data-confirm="Delete this request?">
              <?= csrfField() ?>
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
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
