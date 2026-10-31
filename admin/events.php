<?php
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/app/company.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id = (int)post('id');
    if (post('_action') === 'delete' && $id > 0) {
        $title = dbOne('SELECT title FROM `events` WHERE id = ?', [$id])['title'] ?? null;
        if ($title !== null) {
            dbRun('DELETE FROM `events` WHERE id = ?', [$id]);
            setFlash('success', "Event \"$title\" deleted.");
        }
    }
    redirect('events');
}

$events = dbAll(
    'SELECT e.id, e.slug, e.title, e.bannerImage, e.startDate, e.endDate, e.venue, e.city, e.eventStatus, e.status, e.updatedAt,
            (' . EVENT_PHASE_SQL . ') AS phase
     FROM `events` e ORDER BY e.startDate DESC, e.id DESC'
);

$pageTitle     = 'Events';
$pageActive    = 'events';
$topbarActions = '<a href="events/new" class="btn btn-dark btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Event</a>';
include __DIR__ . '/includes/layout-top.php';
?>
<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
    <span>All Events (<?= count($events) ?>)</span>
    <div class="d-flex flex-wrap gap-2">
      <select class="form-select form-select-sm w-auto" data-table-filter aria-label="Filter by event status">
        <option value="">All event statuses</option>
        <?php foreach (array_slice(EVENT_STATUSES, 1) as $val => $label): ?><option value="<?= $val ?>"><?= h($label) ?></option><?php endforeach; ?>
      </select>
      <select class="form-select form-select-sm w-auto" data-table-filter="status" aria-label="Filter by visibility">
        <option value="">Published &amp; drafts</option>
        <option value="published">Published</option>
        <option value="draft">Draft</option>
      </select>
      <input type="search" class="form-control form-control-sm w-auto" placeholder="Search title, venue or city…" data-table-search>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" data-table>
      <thead class="table-light">
        <tr><th data-sort>Event</th><th data-sort>Date</th><th data-sort>Venue</th><th data-sort>Event Status</th><th data-sort>Visibility</th><th class="text-end">Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($events as $e): ?>
        <tr data-filter="<?= h($e['phase']) ?>" data-status="<?= h($e['status']) ?>">
          <td>
            <div class="d-flex align-items-center gap-2">
              <img src="../<?= h($e['bannerImage']) ?>" alt="" class="thumb" style="object-fit:cover" loading="lazy">
              <div>
                <div class="fw-semibold"><?= h($e['title']) ?></div>
                <div class="text-muted small"><?= h($e['slug']) ?></div>
              </div>
            </div>
          </td>
          <td class="small text-nowrap" data-sort-value="<?= h($e['startDate']) ?>">
            <?= h(date('d M Y', strtotime($e['startDate']))) ?><?= $e['endDate'] && $e['endDate'] !== $e['startDate'] ? ' – ' . h(date('d M Y', strtotime($e['endDate']))) : '' ?>
          </td>
          <td class="small"><?= h($e['venue']) ?><div class="text-muted"><?= h($e['city']) ?></div></td>
          <td>
            <span class="badge badge-<?= h($e['phase']) ?>"><?= h(EVENT_STATUSES[$e['phase']]) ?></span>
            <?php if ($e['eventStatus'] !== 'auto'): ?><i class="bi bi-pin-angle small text-muted" title="Set manually (overrides the dates)"></i><?php endif; ?>
          </td>
          <td><span class="badge badge-<?= h($e['status']) ?>"><?= h(ucfirst($e['status'])) ?></span></td>
          <td class="text-end text-nowrap">
            <?php if ($e['status'] === 'published'): ?>
              <a href="../events/<?= h(rawurlencode($e['slug'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="View on site"><i class="bi bi-box-arrow-up-right"></i></a>
            <?php endif; ?>
            <a href="events/edit/<?= h($e['slug']) ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
            <form method="post" class="d-inline" data-confirm="Delete &quot;<?= h($e['title']) ?>&quot;? This cannot be undone.">
              <?= csrfField() ?>
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$events): ?>
          <tr data-empty><td colspan="6" class="text-center text-muted py-4">No events yet. <a href="events/new">Add the first one</a>.</td></tr>
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
