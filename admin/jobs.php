<?php
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/app/company.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id = (int)post('id');
    if (post('_action') === 'delete' && $id > 0) {
        $title = dbOne('SELECT title FROM `jobs` WHERE id = ?', [$id])['title'] ?? null;
        if ($title !== null) {
            // Applications keep the job title and stay in Job Applications (FK is ON DELETE SET NULL).
            dbRun('DELETE FROM `jobs` WHERE id = ?', [$id]);
            setFlash('success', "Job opening \"$title\" deleted. Its applications are kept.");
        }
    }
    redirect('jobs');
}

$jobs = dbAll(
    'SELECT j.id, j.slug, j.title, j.department, j.location, j.employmentType, j.deadline, j.status, j.updatedAt,
            (j.status = \'published\' AND j.deadline IS NOT NULL AND j.deadline < CURDATE()) AS isExpired,
            (SELECT COUNT(*) FROM `jobApplications` a WHERE a.jobId = j.id) AS applications
     FROM `jobs` j ORDER BY j.createdAt DESC, j.id DESC'
);

$pageTitle     = 'Job Openings';
$pageActive    = 'jobs';
$topbarActions = '<a href="jobs/new" class="btn btn-dark btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Job Opening</a>';
include __DIR__ . '/includes/layout-top.php';
?>
<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
    <span>All Job Openings (<?= count($jobs) ?>)</span>
    <div class="d-flex flex-wrap gap-2">
      <select class="form-select form-select-sm w-auto" data-table-filter="status" aria-label="Filter by status">
        <option value="">All statuses</option>
        <option value="published">Published</option>
        <option value="expired">Expired</option>
        <option value="draft">Draft</option>
      </select>
      <input type="search" class="form-control form-control-sm w-auto" placeholder="Search title, department or location…" data-table-search>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" data-table>
      <thead class="table-light">
        <tr>
          <th data-sort>Job</th>
          <th data-sort>Department</th>
          <th data-sort>Location</th>
          <th data-sort>Deadline</th>
          <th data-sort>Applications</th>
          <th data-sort>Status</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($jobs as $j):
          // Published but past its deadline: hidden from the careers page, applications closed.
          $status = $j['isExpired'] ? 'expired' : $j['status'];
        ?>
        <tr data-status="<?= $status ?>">
          <td>
            <div class="fw-semibold"><?= h($j['title']) ?></div>
            <div class="text-muted small"><?= h(EMPLOYMENT_TYPES[$j['employmentType']] ?? '') ?> · <?= h($j['slug']) ?></div>
          </td>
          <td><?= h($j['department']) ?></td>
          <td class="small"><?= h($j['location']) ?></td>
          <td class="small text-muted text-nowrap" data-sort-value="<?= h($j['deadline'] ?? '') ?>"><?= $j['deadline'] ? h(date('d M Y', strtotime($j['deadline']))) : '—' ?></td>
          <td data-sort-value="<?= (int)$j['applications'] ?>">
            <?php if ($j['applications']): ?>
              <a href="job-applications?job=<?= (int)$j['id'] ?>"><?= (int)$j['applications'] ?></a>
            <?php else: ?>0<?php endif; ?>
          </td>
          <td><span class="badge badge-<?= $status ?>"><?= h(ucfirst($status)) ?></span></td>
          <td class="text-end text-nowrap">
            <?php if ($status === 'published'): ?>
              <a href="../careers/<?= h(rawurlencode($j['slug'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="View on site"><i class="bi bi-box-arrow-up-right"></i></a>
            <?php endif; ?>
            <a href="jobs/edit/<?= h($j['slug']) ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
            <form method="post" class="d-inline" data-confirm="Delete &quot;<?= h($j['title']) ?>&quot;? Its applications are kept. This cannot be undone.">
              <?= csrfField() ?>
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$jobs): ?>
          <tr data-empty><td colspan="7" class="text-center text-muted py-4">No job openings yet. <a href="jobs/new">Add the first one</a>.</td></tr>
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
