<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id = (int)post('id');
    if (post('_action') === 'delete' && $id > 0) {
        dbRun('DELETE FROM `newsletterSubscribers` WHERE id = ?', [$id]);
        setFlash('success', 'Subscriber removed.');
    }
    redirect('newsletter');
}

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="newsletter-subscribers-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Email', 'Signed up on page', 'Subscribed at']);
    foreach (dbAll('SELECT email, sourcePage, createdAt FROM `newsletterSubscribers` ORDER BY createdAt DESC') as $r) {
        fputcsv($out, array_map(fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'$v" : $v, array_values($r)));
    }
    exit;
}

$subscribers = dbAll('SELECT * FROM `newsletterSubscribers` ORDER BY createdAt DESC');

$pageTitle     = 'Newsletter Subscribers';
$pageActive    = 'newsletter';
$topbarActions = '<a href="newsletter?export=csv" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>Export CSV</a>';
include __DIR__ . '/includes/layout-top.php';
?>
<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
    <span>All Subscribers (<?= count($subscribers) ?>)</span>
    <input type="search" class="form-control form-control-sm w-auto" placeholder="Search by email…" data-table-search>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" data-table>
      <thead class="table-light">
        <tr><th data-sort>Email</th><th data-sort>Signed Up On</th><th data-sort>Subscribed</th><th class="text-end">Actions</th></tr>
      </thead>
      <tbody>
        <?php if (!$subscribers): ?>
          <tr data-empty><td colspan="4" class="text-center text-muted py-4">No subscribers yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($subscribers as $s): ?>
        <tr>
          <td><a href="mailto:<?= h($s['email']) ?>"><?= h($s['email']) ?></a></td>
          <td class="small text-muted"><?= h($s['sourcePage'] ?: '—') ?></td>
          <td class="small text-muted" data-sort-value="<?= h($s['createdAt']) ?>"><?= h(date('d M Y, H:i', strtotime($s['createdAt']))) ?></td>
          <td class="text-end">
            <form method="post" class="d-inline" data-confirm="Remove <?= h($s['email']) ?> from the newsletter list?">
              <?= csrfField() ?>
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove"><i class="bi bi-trash"></i></button>
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
