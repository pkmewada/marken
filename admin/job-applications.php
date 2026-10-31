<?php
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/app/company.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id     = (int)post('id');
    $action = post('_action');
    if ($action === 'status' && $id > 0 && isset(APPLICATION_STATUSES[post('status')])) {
        dbRun('UPDATE `jobApplications` SET status = ? WHERE id = ?', [post('status'), $id]);
        setFlash('success', 'Application status updated.');
    } elseif ($action === 'delete' && $id > 0) {
        $file = dbOne('SELECT resumeFile FROM `jobApplications` WHERE id = ?', [$id])['resumeFile'] ?? null;
        if ($file !== null) {
            dbRun('DELETE FROM `jobApplications` WHERE id = ?', [$id]);
            @unlink(RESUME_DIR . basename($file));   // personal data: don't keep the resume of a deleted application
            setFlash('success', 'Application and resume deleted.');
        }
    }
    $back = (int)post('job');
    redirect('job-applications' . ($back > 0 ? "?job=$back" : ''));
}

$apps = dbAll(
    'SELECT a.id, a.jobId, a.jobTitle, a.fullName, a.email, a.phone, a.currentLocation, a.resumeOriginalName,
            a.coverLetter, a.status, a.createdAt, j.slug AS jobSlug
     FROM `jobApplications` a LEFT JOIN `jobs` j ON j.id = a.jobId
     ORDER BY a.createdAt DESC, a.id DESC'
);
// Filter by job title (rows of deleted jobs keep their title snapshot).
$jobTitles = array_values(array_unique(array_column($apps, 'jobTitle')));
sort($jobTitles);
$preJob = (int)($_GET['job'] ?? 0);
$preJobTitle = $preJob > 0 ? (dbOne('SELECT title FROM `jobs` WHERE id = ?', [$preJob])['title'] ?? '') : '';

$pageTitle  = 'Job Applications';
$pageActive = 'job-applications';
include __DIR__ . '/includes/layout-top.php';
?>
<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
    <span>All Applications (<?= count($apps) ?>)</span>
    <div class="d-flex flex-wrap gap-2">
      <select class="form-select form-select-sm w-auto" data-table-filter aria-label="Filter by job">
        <option value="">All jobs</option>
        <?php foreach ($jobTitles as $t): ?>
          <option value="<?= h($t) ?>"<?= $preJobTitle === $t ? ' selected' : '' ?>><?= h($t) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="form-select form-select-sm w-auto" data-table-filter="status" aria-label="Filter by status">
        <option value="">All statuses</option>
        <?php foreach (APPLICATION_STATUSES as $val => $label): ?><option value="<?= $val ?>"><?= h($label) ?></option><?php endforeach; ?>
      </select>
      <input type="search" class="form-control form-control-sm w-auto" placeholder="Search name, email or location…" data-table-search>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" data-table>
      <thead class="table-light">
        <tr><th data-sort>Applicant</th><th data-sort>Job</th><th data-sort>Location</th><th data-sort>Status</th><th data-sort>Received</th><th class="text-end">Actions</th></tr>
      </thead>
      <tbody>
        <?php if (!$apps): ?>
          <tr data-empty><td colspan="6" class="text-center text-muted py-4">No applications yet. They appear here when candidates apply from a job page.</td></tr>
        <?php endif; ?>
        <?php foreach ($apps as $a): ?>
        <tr data-filter="<?= h($a['jobTitle']) ?>" data-status="<?= h($a['status']) ?>">
          <td>
            <div class="fw-semibold"><?= h($a['fullName']) ?></div>
            <div class="small"><a href="mailto:<?= h($a['email']) ?>"><?= h($a['email']) ?></a> · <a href="tel:<?= h(preg_replace('/[^\d+]/', '', $a['phone'])) ?>"><?= h($a['phone']) ?></a></div>
          </td>
          <td class="small">
            <?= h($a['jobTitle']) ?>
            <?php if (!$a['jobSlug']): ?><div class="text-muted">(job deleted)</div><?php endif; ?>
          </td>
          <td class="small"><?= h($a['currentLocation']) ?></td>
          <td data-sort-value="<?= h($a['status']) ?>">
            <form method="post" class="d-inline">
              <?= csrfField() ?>
              <input type="hidden" name="_action" value="status">
              <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <input type="hidden" name="job" value="<?= $preJob ?>">
              <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Application status">
                <?php foreach (APPLICATION_STATUSES as $val => $label): ?>
                  <option value="<?= $val ?>"<?= $a['status'] === $val ? ' selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td class="small text-muted text-nowrap" data-sort-value="<?= h($a['createdAt']) ?>"><?= h(date('d M Y, H:i', strtotime($a['createdAt']))) ?></td>
          <td class="text-end text-nowrap">
            <a href="resume?id=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-primary" title="Download resume (<?= h($a['resumeOriginalName']) ?>)"><i class="bi bi-download"></i></a>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#cover<?= (int)$a['id'] ?>" title="View cover letter"><i class="bi bi-eye"></i></button>
            <form method="post" class="d-inline" data-confirm="Delete the application from <?= h($a['fullName']) ?> and their resume? This cannot be undone.">
              <?= csrfField() ?>
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <input type="hidden" name="job" value="<?= $preJob ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
            </form>
            <div class="collapse text-start mt-2" id="cover<?= (int)$a['id'] ?>">
              <div class="bg-light rounded p-2 small" style="white-space:pre-wrap;max-width:420px"><?= h($a['coverLetter'] ?: '(no cover letter)') ?></div>
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
