<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

const ENQUIRY_STATUSES = ['new', 'replied', 'closed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id     = (int)post('id');
    $action = post('_action');
    if ($action === 'status' && $id > 0 && in_array(post('status'), ENQUIRY_STATUSES, true)) {
        dbRun('UPDATE `enquiries` SET status = ? WHERE id = ?', [post('status'), $id]);
        setFlash('success', 'Enquiry status updated.');
    } elseif ($action === 'delete' && $id > 0) {
        dbRun('DELETE FROM `enquiries` WHERE id = ?', [$id]);
        setFlash('success', 'Enquiry deleted.');
    }
    redirect('enquiries');
}

$enquiries = dbAll('SELECT * FROM `enquiries` ORDER BY createdAt DESC');

$pageTitle  = 'Contact Enquiries';
$pageActive = 'enquiries';
include __DIR__ . '/includes/layout-top.php';
?>
<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
    <span>All Enquiries (<?= count($enquiries) ?>)</span>
    <div class="d-flex gap-2">
      <select class="form-select form-select-sm" data-table-filter>
        <option value="">All statuses</option>
        <?php foreach (ENQUIRY_STATUSES as $s): ?><option value="<?= $s ?>"><?= ucfirst($s) ?></option><?php endforeach; ?>
      </select>
      <input type="search" class="form-control form-control-sm" placeholder="Search name, email or subject…" data-table-search>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" data-table>
      <thead class="table-light">
        <tr><th data-sort>Name</th><th data-sort>Email</th><th data-sort>Subject</th><th data-sort>Status</th><th data-sort>Received</th><th class="text-end">Actions</th></tr>
      </thead>
      <tbody>
        <?php if (!$enquiries): ?>
          <tr data-empty><td colspan="6" class="text-center text-muted py-4">No enquiries yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($enquiries as $e): ?>
        <tr data-filter="<?= h($e['status']) ?>">
          <td>
            <div class="fw-semibold"><?= h($e['name']) ?></div>
            <div class="small text-muted"><?= h($e['phone']) ?></div>
          </td>
          <td class="small"><?= $e['email'] ? '<a href="mailto:' . h($e['email']) . '">' . h($e['email']) . '</a>' : '—' ?></td>
          <td class="small"><?= h($e['subject'] ?: '—') ?></td>
          <td data-sort-value="<?= h($e['status']) ?>">
            <form method="post" class="d-inline">
              <?= csrfField() ?>
              <input type="hidden" name="_action" value="status">
              <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
              <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php foreach (ENQUIRY_STATUSES as $s): ?>
                  <option value="<?= $s ?>"<?= $e['status'] === $s ? ' selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td class="small text-muted" data-sort-value="<?= h($e['createdAt']) ?>"><?= h(date('d M Y, H:i', strtotime($e['createdAt']))) ?></td>
          <td class="text-end text-nowrap">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#msg<?= (int)$e['id'] ?>" title="View message"><i class="bi bi-eye"></i></button>
            <form method="post" class="d-inline" data-confirm="Delete this enquiry?">
              <?= csrfField() ?>
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
            <div class="collapse text-start mt-2" id="msg<?= (int)$e['id'] ?>">
              <div class="bg-light rounded p-2 small" style="white-space:pre-wrap;max-width:420px"><?= h($e['message'] ?: '(no message)') ?></div>
            </div>
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
