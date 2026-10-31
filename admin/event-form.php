<?php
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/app/company.php';
requireLogin();

$editSlug = is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '';
$id       = 0;
$errors   = [];

$e = [
    'title' => '', 'slug' => '', 'excerpt' => '', 'description' => '', 'bannerImage' => '', 'gallery' => [],
    'startDate' => '', 'endDate' => '', 'startTime' => '', 'endTime' => '', 'venue' => '', 'address' => '', 'city' => '',
    'mapUrl' => '', 'contactInfo' => '', 'eventStatus' => 'auto', 'status' => 'draft',
];

if ($editSlug !== '') {
    $row = dbOne('SELECT * FROM `events` WHERE slug = ?', [$editSlug]);
    if (!$row) {
        setFlash('error', 'Event not found.');
        redirect('events');
    }
    $id = (int)$row['id'];
    $row['gallery']   = fromJson($row['gallery']);
    $row['startTime'] = $row['startTime'] ? substr($row['startTime'], 0, 5) : '';
    $row['endTime']   = $row['endTime'] ? substr($row['endTime'], 0, 5) : '';
    $e = array_merge($e, array_map(fn($v) => $v ?? '', array_intersect_key($row, $e)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    foreach (array_keys($e) as $k) $e[$k] = is_array($e[$k]) ? postList($k) : post($k);
    $e['gallery'] = array_values(array_filter($e['gallery']));

    $errors = validate($e, [
        'title'       => 'required|max:200',
        'slug'        => 'required|slug|max:150',
        'excerpt'     => 'required|max:500',
        'description' => 'required|max:20000',
        'bannerImage' => 'required|max:500',
        'startDate'   => 'required',
        'venue'       => 'required|max:200',
        'address'     => 'max:500',
        'city'        => 'required|max:100',
        'mapUrl'      => 'max:1000',
        'contactInfo' => 'max:500',
    ]);
    if (!isset($errors['slug']) && dbOne('SELECT id FROM `events` WHERE slug = ? AND id <> ?', [$e['slug'], $id])) {
        $errors['slug'] = 'Another event already uses this slug.';
    }
    $isDate = fn($v) => ($d = DateTime::createFromFormat('!Y-m-d', $v)) && $d->format('Y-m-d') === $v;
    $isTime = fn($v) => (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v);
    if ($e['startDate'] !== '' && !$isDate($e['startDate'])) $errors['startDate'] = 'Enter a valid date.';
    if ($e['endDate'] !== '') {
        if (!$isDate($e['endDate'])) $errors['endDate'] = 'Enter a valid date.';
        elseif (!isset($errors['startDate']) && $e['endDate'] < $e['startDate']) $errors['endDate'] = 'End date cannot be before the start date.';
    }
    if ($e['startTime'] !== '' && !$isTime($e['startTime'])) $errors['startTime'] = 'Enter a valid time.';
    if ($e['endTime'] !== '' && !$isTime($e['endTime'])) $errors['endTime'] = 'Enter a valid time.';
    if ($e['bannerImage'] !== '' && (!isSafeLink($e['bannerImage']) || !preg_match('/\.(jpe?g|png|webp|gif)$/i', $e['bannerImage']))) {
        $errors['bannerImage'] = 'Upload a JPG, PNG, WebP or GIF image.';
    }
    foreach ($e['gallery'] as $g) if (!isSafeLink($g)) { $errors['gallery'] = 'Invalid gallery image.'; break; }
    if ($e['mapUrl'] !== '' && !preg_match('#^https://[^\s<>"\']+$#i', $e['mapUrl'])) $errors['mapUrl'] = 'Paste the https:// link from Google Maps (Share → Copy link).';
    if (!isset(EVENT_STATUSES[$e['eventStatus']])) $errors['eventStatus'] = 'Invalid event status.';
    if (!in_array($e['status'], ['published', 'draft'], true)) $errors['status'] = 'Invalid status.';

    if (!$errors) {
        $vals = [
            $e['title'], $e['slug'], $e['excerpt'], $e['description'], $e['bannerImage'], toJson($e['gallery']),
            $e['startDate'], $e['endDate'] ?: null, $e['startTime'] ?: null, $e['endTime'] ?: null,
            $e['venue'], $e['address'] ?: null, $e['city'], $e['mapUrl'] ?: null, $e['contactInfo'] ?: null,
            $e['eventStatus'], $e['status'],
        ];
        $cols = 'title = ?, slug = ?, excerpt = ?, description = ?, bannerImage = ?, gallery = ?, startDate = ?, endDate = ?,
                 startTime = ?, endTime = ?, venue = ?, address = ?, city = ?, mapUrl = ?, contactInfo = ?, eventStatus = ?, status = ?';
        if ($id > 0) {
            dbRun("UPDATE `events` SET $cols WHERE id = ?", [...$vals, $id]);
            setFlash('success', "Event \"{$e['title']}\" updated.");
        } else {
            dbRun("INSERT INTO `events` SET $cols", $vals);
            setFlash('success', "Event \"{$e['title']}\" created.");
        }
        redirect('events');
    }
}

$isEdit        = $id > 0;
$action        = $isEdit ? 'events/edit/' . rawurlencode($editSlug) : 'events/new';
$pageTitle     = $isEdit ? 'Edit Event' : 'Add Event';
$pageActive    = 'events';
$topbarActions = '<a href="events" class="btn btn-outline-secondary btn-sm">Cancel</a>'
               . '<button type="submit" form="eventForm" class="btn btn-dark btn-sm"><i class="bi bi-check-lg me-1"></i>'
               . ($isEdit ? 'Update' : 'Save') . ' Event</button>';
include __DIR__ . '/includes/layout-top.php';
?>
<?php if ($errors): ?>
  <div class="alert alert-danger small">Please fix the highlighted fields below.</div>
<?php endif; ?>

<form method="post" action="<?= h($action) ?>" id="eventForm" novalidate>
  <?= csrfField() ?>
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header">Event</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="evTitle">Event Title <span class="text-danger">*</span></label>
            <input type="text" id="evTitle" name="title" maxlength="200" class="form-control<?= invalid($errors, 'title') ?>"
                   value="<?= h($e['title']) ?>" data-slug-source="evSlug" placeholder="e.g. MarkEn at Medical Fair India 2027">
            <?= fieldError($errors, 'title') ?>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="evSlug">Slug <span class="text-danger">*</span></label>
            <input type="text" id="evSlug" name="slug" maxlength="150" class="form-control font-monospace<?= invalid($errors, 'slug') ?>"
                   value="<?= h($e['slug']) ?>" placeholder="e.g. medical-fair-india-2027 (auto-filled from the title)">
            <?= fieldError($errors, 'slug') ?: '<div class="form-text">Event page URL: <code>/events/slug</code></div>' ?>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="evExcerpt">Short Description <span class="text-danger">*</span></label>
            <textarea id="evExcerpt" name="excerpt" rows="2" maxlength="500" class="form-control<?= invalid($errors, 'excerpt') ?>"
                      placeholder="1–2 sentences shown on the event card, e.g. Visit stall H4-12 to see our latest WHO PQS cold chain range."><?= h($e['excerpt']) ?></textarea>
            <?= fieldError($errors, 'excerpt') ?>
          </div>
          <div>
            <label class="form-label small fw-semibold" for="evDesc">Full Description <span class="text-danger">*</span></label>
            <textarea id="evDesc" name="description" rows="8" maxlength="20000" class="form-control<?= invalid($errors, 'description') ?>"
                      placeholder="Everything visitors should know about the event. Separate paragraphs with a blank line."><?= h($e['description']) ?></textarea>
            <?= fieldError($errors, 'description') ?>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Date, Time &amp; Venue</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-sm-6 col-md-3">
              <label class="form-label small fw-semibold" for="evStart">Start Date <span class="text-danger">*</span></label>
              <input type="date" id="evStart" name="startDate" class="form-control<?= invalid($errors, 'startDate') ?>" value="<?= h($e['startDate']) ?>" placeholder="e.g. 2027-02-10">
              <?= fieldError($errors, 'startDate') ?>
            </div>
            <div class="col-sm-6 col-md-3">
              <label class="form-label small fw-semibold" for="evEnd">End Date</label>
              <input type="date" id="evEnd" name="endDate" class="form-control<?= invalid($errors, 'endDate') ?>" value="<?= h($e['endDate']) ?>" placeholder="e.g. 2027-02-12">
              <?= fieldError($errors, 'endDate') ?: '<div class="form-text">Blank = one-day event.</div>' ?>
            </div>
            <div class="col-sm-6 col-md-3">
              <label class="form-label small fw-semibold" for="evStartTime">Start Time</label>
              <input type="time" id="evStartTime" name="startTime" class="form-control<?= invalid($errors, 'startTime') ?>" value="<?= h($e['startTime']) ?>" placeholder="e.g. 10:00">
              <?= fieldError($errors, 'startTime') ?>
            </div>
            <div class="col-sm-6 col-md-3">
              <label class="form-label small fw-semibold" for="evEndTime">End Time</label>
              <input type="time" id="evEndTime" name="endTime" class="form-control<?= invalid($errors, 'endTime') ?>" value="<?= h($e['endTime']) ?>" placeholder="e.g. 18:00">
              <?= fieldError($errors, 'endTime') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold" for="evVenue">Venue <span class="text-danger">*</span></label>
              <input type="text" id="evVenue" name="venue" maxlength="200" class="form-control<?= invalid($errors, 'venue') ?>"
                     value="<?= h($e['venue']) ?>" placeholder="e.g. Pragati Maidan, Hall 4">
              <?= fieldError($errors, 'venue') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold" for="evCity">City / Location <span class="text-danger">*</span></label>
              <input type="text" id="evCity" name="city" maxlength="100" class="form-control<?= invalid($errors, 'city') ?>"
                     value="<?= h($e['city']) ?>" placeholder="e.g. New Delhi">
              <?= fieldError($errors, 'city') ?>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold" for="evAddress">Address</label>
              <input type="text" id="evAddress" name="address" maxlength="500" class="form-control<?= invalid($errors, 'address') ?>"
                     value="<?= h($e['address']) ?>" placeholder="e.g. Bharat Mandapam, Mathura Road, New Delhi 110001">
              <?= fieldError($errors, 'address') ?: '<div class="form-text">Used for the map on the event page (falls back to venue + city).</div>' ?>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold" for="evMap">Google Maps Link</label>
              <input type="url" id="evMap" name="mapUrl" maxlength="1000" class="form-control<?= invalid($errors, 'mapUrl') ?>"
                     value="<?= h($e['mapUrl']) ?>" placeholder="e.g. https://maps.app.goo.gl/AbCdEf123">
              <?= fieldError($errors, 'mapUrl') ?: '<div class="form-text">Used for “Get Directions”. Blank = directions search for the address.</div>' ?>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold" for="evContact">Contact / Enquiry Information</label>
              <input type="text" id="evContact" name="contactInfo" maxlength="500" class="form-control<?= invalid($errors, 'contactInfo') ?>"
                     value="<?= h($e['contactInfo']) ?>" placeholder="e.g. Meet our team at stall H4-12 or write to info@markenworld.com">
              <?= fieldError($errors, 'contactInfo') ?: '<div class="form-text">Blank = the event page shows the MarkEn head office contact.</div>' ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-4">
        <div class="card-header">Publish</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="evStatus">Visibility</label>
            <select id="evStatus" name="status" class="form-select<?= invalid($errors, 'status') ?>">
              <option value="draft"<?= $e['status'] === 'draft' ? ' selected' : '' ?>>Draft (hidden from website)</option>
              <option value="published"<?= $e['status'] === 'published' ? ' selected' : '' ?>>Published</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="evPhase">Event Status</label>
            <select id="evPhase" name="eventStatus" class="form-select<?= invalid($errors, 'eventStatus') ?>">
              <?php foreach (EVENT_STATUSES as $val => $label): ?>
                <option value="<?= $val ?>"<?= $e['eventStatus'] === $val ? ' selected' : '' ?>><?= h($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?= fieldError($errors, 'eventStatus') ?: '<div class="form-text">Automatic: upcoming before the start date, ongoing until the end date, then completed. Choose a status to override it (e.g. Cancelled).</div>' ?>
          </div>
          <button type="submit" class="btn btn-dark w-100"><i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update' : 'Save' ?> Event</button>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Banner Image <span class="text-danger">*</span></div>
        <div class="card-body">
          <div class="drop-zone<?= isset($errors['bannerImage']) ? ' border-danger' : '' ?>" data-image-upload="evBanner">
            <?php if ($e['bannerImage']): ?>
              <img src="../<?= h($e['bannerImage']) ?>" alt=""><span class="small text-muted">Click or drag to replace</span>
            <?php else: ?>
              <i class="bi bi-cloud-arrow-up fs-2 text-muted"></i>
              <span class="small text-muted">Click to upload or drag &amp; drop</span>
              <span class="small text-muted">JPG, PNG, WebP — max 10 MB</span>
            <?php endif; ?>
          </div>
          <input type="hidden" id="evBanner" name="bannerImage" value="<?= h($e['bannerImage']) ?>">
          <?= isset($errors['bannerImage']) ? '<div class="text-danger small mt-1">' . h($errors['bannerImage']) . '</div>' : '' ?>
          <div class="form-text">Shown on event cards (16:10) and as the wide banner on the event page — use a landscape image.</div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Gallery</div>
        <div class="card-body">
          <?= isset($errors['gallery']) ? '<div class="text-danger small mb-2">' . h($errors['gallery']) . '</div>' : '' ?>
          <div class="gallery-grid mb-2" id="galleryGrid">
            <?php foreach ($e['gallery'] as $g): ?>
              <div class="gallery-item" data-row>
                <img src="../<?= h($g) ?>" alt="">
                <input type="hidden" name="gallery[]" value="<?= h($g) ?>">
                <button type="button" class="btn-close" data-remove-row aria-label="Remove"></button>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="drop-zone" id="galleryDrop" style="min-height:80px">
            <i class="bi bi-images text-muted"></i>
            <span class="small text-muted">Add images (you can select several)</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>

<?php
$pageScripts = <<<'HTML'
<script>
document.addEventListener('click', (e) => {
  const remove = e.target.closest('[data-remove-row]');
  if (remove) remove.closest('[data-row]').remove();
});

// Gallery: several images at once (click or drop), uploaded one after another.
const galleryDrop = document.getElementById('galleryDrop');
const galleryGrid = document.getElementById('galleryGrid');
const picker = Object.assign(document.createElement('input'), { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', multiple: true, hidden: true });
galleryDrop.after(picker);
async function addGalleryFiles(files) {
  galleryDrop.classList.add('is-busy');
  for (const file of files) {
    try {
      const { url } = await uploadFile(file);
      galleryGrid.insertAdjacentHTML('beforeend',
        `<div class="gallery-item" data-row><img src="../${escHtml(url)}" alt="">` +
        `<input type="hidden" name="gallery[]" value="${escHtml(url)}">` +
        `<button type="button" class="btn-close" data-remove-row aria-label="Remove"></button></div>`);
    } catch (err) { showError(`${file.name}: ${err.message}`); }
  }
  galleryDrop.classList.remove('is-busy');
}
galleryDrop.addEventListener('click', () => picker.click());
picker.addEventListener('change', () => { addGalleryFiles([...picker.files]); picker.value = ''; });
galleryDrop.addEventListener('dragover', (e) => { e.preventDefault(); galleryDrop.classList.add('is-over'); });
galleryDrop.addEventListener('dragleave', () => galleryDrop.classList.remove('is-over'));
galleryDrop.addEventListener('drop', (e) => {
  e.preventDefault();
  galleryDrop.classList.remove('is-over');
  addGalleryFiles([...e.dataTransfer.files]);
});
</script>
HTML;
include __DIR__ . '/includes/layout-bottom.php';
