<?php
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/app/company.php';
requireLogin();

$editSlug   = is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '';
$id         = 0;
$categories = dbAll('SELECT slug, name FROM `categories` ORDER BY sortOrder, name');
$errors     = [];

$c = [
    'name' => '', 'slug' => '', 'image' => '', 'address' => '', 'locality' => '', 'city' => '', 'state' => '',
    'phone' => '', 'email' => '', 'mapUrl' => '', 'serviceInfo' => '', 'status' => 'published', 'sortOrder' => '0',
    'productCategories' => [], 'serviceTypes' => [],
];

if ($editSlug !== '') {
    $row = dbOne('SELECT * FROM `serviceCenters` WHERE slug = ?', [$editSlug]);
    if (!$row) {
        setFlash('error', 'Service center not found.');
        redirect('service-centers');
    }
    $id = (int)$row['id'];
    $row['productCategories'] = fromJson($row['productCategories']);
    $row['serviceTypes']      = fromJson($row['serviceTypes']);
    $c = array_merge($c, array_map(fn($v) => $v ?? '', array_intersect_key($row, $c)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    foreach (array_keys($c) as $k) $c[$k] = is_array($c[$k]) ? postList($k) : post($k);
    // Keep only known values (checkbox lists).
    $c['productCategories'] = array_values(array_intersect($c['productCategories'], array_column($categories, 'slug')));
    $c['serviceTypes']      = array_values(array_intersect($c['serviceTypes'], SERVICE_TYPES));

    $errors = validate($c, [
        'name'        => 'required|max:200',
        'slug'        => 'required|slug|max:150',
        'image'       => 'max:500',
        'address'     => 'required|max:500',
        'locality'    => 'max:150',
        'city'        => 'required|max:100',
        'state'       => 'required|max:100',
        'phone'       => 'max:50',
        'email'       => 'email|max:200',
        'mapUrl'      => 'max:1000',
        'serviceInfo' => 'max:5000',
    ]);
    if (!isset($errors['slug']) && dbOne('SELECT id FROM `serviceCenters` WHERE slug = ? AND id <> ?', [$c['slug'], $id])) {
        $errors['slug'] = 'Another service center already uses this slug.';
    }
    if ($c['phone'] === '' && $c['email'] === '') $errors['phone'] = 'Enter a phone number or an email address.';
    elseif ($c['phone'] !== '' && !preg_match('/^\+?[0-9 ()\-]{7,20}$/', $c['phone'])) $errors['phone'] = 'Enter a valid phone number.';
    if ($c['image'] !== '' && (!isSafeLink($c['image']) || !preg_match('/\.(jpe?g|png|webp|gif)$/i', $c['image']))) {
        $errors['image'] = 'Upload a JPG, PNG, WebP or GIF image.';
    }
    if ($c['mapUrl'] !== '' && !preg_match('#^https://[^\s<>"\']+$#i', $c['mapUrl'])) $errors['mapUrl'] = 'Paste the https:// link from Google Maps (Share → Copy link).';
    if (!in_array($c['status'], ['published', 'draft'], true)) $errors['status'] = 'Invalid status.';
    if (!preg_match('/^-?\d{1,6}$/', $c['sortOrder'])) $errors['sortOrder'] = 'Enter a whole number.';

    if (!$errors) {
        $vals = [
            $c['name'], $c['slug'], $c['image'] ?: null, $c['address'], $c['locality'] ?: null, $c['city'], $c['state'],
            $c['phone'] ?: null, $c['email'] ? mb_strtolower($c['email']) : null, $c['mapUrl'] ?: null,
            toJson($c['productCategories']), toJson($c['serviceTypes']), $c['serviceInfo'] ?: null, $c['status'], (int)$c['sortOrder'],
        ];
        $cols = 'name = ?, slug = ?, image = ?, address = ?, locality = ?, city = ?, state = ?, phone = ?, email = ?, mapUrl = ?,
                 productCategories = ?, serviceTypes = ?, serviceInfo = ?, status = ?, sortOrder = ?';
        if ($id > 0) {
            dbRun("UPDATE `serviceCenters` SET $cols WHERE id = ?", [...$vals, $id]);
            setFlash('success', "Service center \"{$c['name']}\" updated.");
        } else {
            dbRun("INSERT INTO `serviceCenters` SET $cols", $vals);
            setFlash('success', "Service center \"{$c['name']}\" created.");
        }
        redirect('service-centers');
    }
}

$known         = dbAll('SELECT DISTINCT state, city FROM `serviceCenters` ORDER BY state, city');
$isEdit        = $id > 0;
$action        = $isEdit ? 'service-centers/edit/' . rawurlencode($editSlug) : 'service-centers/new';
$pageTitle     = $isEdit ? 'Edit Service Center' : 'Add Service Center';
$pageActive    = 'service-centers';
$topbarActions = '<a href="service-centers" class="btn btn-outline-secondary btn-sm">Cancel</a>'
               . '<button type="submit" form="centerForm" class="btn btn-dark btn-sm"><i class="bi bi-check-lg me-1"></i>'
               . ($isEdit ? 'Update' : 'Save') . ' Service Center</button>';
include __DIR__ . '/includes/layout-top.php';
?>
<?php if ($errors): ?>
  <div class="alert alert-danger small">Please fix the highlighted fields below.</div>
<?php endif; ?>

<form method="post" action="<?= h($action) ?>" id="centerForm" novalidate>
  <?= csrfField() ?>
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header">Service Center</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-7">
              <label class="form-label small fw-semibold" for="scName">Name <span class="text-danger">*</span></label>
              <input type="text" id="scName" name="name" maxlength="200" class="form-control<?= invalid($errors, 'name') ?>"
                     value="<?= h($c['name']) ?>" data-slug-source="scSlug" placeholder="e.g. MarkEn Service Center – Pune">
              <?= fieldError($errors, 'name') ?>
            </div>
            <div class="col-md-5">
              <label class="form-label small fw-semibold" for="scSlug">Slug <span class="text-danger">*</span></label>
              <input type="text" id="scSlug" name="slug" maxlength="150" class="form-control font-monospace<?= invalid($errors, 'slug') ?>"
                     value="<?= h($c['slug']) ?>" placeholder="e.g. marken-service-center-pune">
              <?= fieldError($errors, 'slug') ?>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold" for="scAddress">Address <span class="text-danger">*</span></label>
              <textarea id="scAddress" name="address" rows="2" maxlength="500" class="form-control<?= invalid($errors, 'address') ?>"
                        placeholder="e.g. Shop 4, Sai Plaza, Baner Road, Pune 411045"><?= h($c['address']) ?></textarea>
              <?= fieldError($errors, 'address') ?>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold" for="scLocality">Location / Area</label>
              <input type="text" id="scLocality" name="locality" maxlength="150" class="form-control<?= invalid($errors, 'locality') ?>"
                     value="<?= h($c['locality']) ?>" placeholder="e.g. Baner">
              <?= fieldError($errors, 'locality') ?>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold" for="scCity">City <span class="text-danger">*</span></label>
              <input type="text" id="scCity" name="city" list="cityOptions" maxlength="100" class="form-control<?= invalid($errors, 'city') ?>"
                     value="<?= h($c['city']) ?>" placeholder="e.g. Pune">
              <datalist id="cityOptions"><?php foreach (array_unique(array_column($known, 'city')) as $v): ?><option value="<?= h($v) ?>"><?php endforeach; ?></datalist>
              <?= fieldError($errors, 'city') ?>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold" for="scState">State <span class="text-danger">*</span></label>
              <input type="text" id="scState" name="state" list="stateOptions" maxlength="100" class="form-control<?= invalid($errors, 'state') ?>"
                     value="<?= h($c['state']) ?>" placeholder="e.g. Maharashtra">
              <datalist id="stateOptions"><?php foreach (array_unique(array_column($known, 'state')) as $v): ?><option value="<?= h($v) ?>"><?php endforeach; ?></datalist>
              <?= fieldError($errors, 'state') ?: '<div class="form-text">Pick an existing spelling so the filter groups centers correctly.</div>' ?>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold" for="scPhone">Contact Number</label>
              <input type="tel" id="scPhone" name="phone" maxlength="50" class="form-control<?= invalid($errors, 'phone') ?>"
                     value="<?= h($c['phone']) ?>" placeholder="e.g. +91 98765 43210">
              <?= fieldError($errors, 'phone') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold" for="scEmail">Email Address</label>
              <input type="email" id="scEmail" name="email" maxlength="200" class="form-control<?= invalid($errors, 'email') ?>"
                     value="<?= h($c['email']) ?>" placeholder="e.g. pune.service@markenworld.com">
              <?= fieldError($errors, 'email') ?>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold" for="scMap">Google Maps Link</label>
              <input type="url" id="scMap" name="mapUrl" maxlength="1000" class="form-control<?= invalid($errors, 'mapUrl') ?>"
                     value="<?= h($c['mapUrl']) ?>" placeholder="e.g. https://maps.app.goo.gl/AbCdEf123">
              <?= fieldError($errors, 'mapUrl') ?: '<div class="form-text">Used for “Get Directions”. Blank = directions search for the address. The map preview always uses the address.</div>' ?>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold" for="scInfo">Service Information</label>
              <textarea id="scInfo" name="serviceInfo" rows="4" maxlength="5000" class="form-control<?= invalid($errors, 'serviceInfo') ?>"
                        placeholder="e.g. Working hours Mon–Sat 9:30 am – 6:30 pm. On-site support within 48 hours across Pune district."><?= h($c['serviceInfo']) ?></textarea>
              <?= fieldError($errors, 'serviceInfo') ?>
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
            <label class="form-label small fw-semibold" for="scStatus">Status</label>
            <select id="scStatus" name="status" class="form-select<?= invalid($errors, 'status') ?>">
              <option value="published"<?= $c['status'] === 'published' ? ' selected' : '' ?>>Published</option>
              <option value="draft"<?= $c['status'] === 'draft' ? ' selected' : '' ?>>Draft (hidden from website)</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="scSort">Display Order</label>
            <input type="number" id="scSort" name="sortOrder" class="form-control<?= invalid($errors, 'sortOrder') ?>"
                   value="<?= h((string)$c['sortOrder']) ?>" placeholder="e.g. 0 (lower shows first within a city)">
            <?= fieldError($errors, 'sortOrder') ?>
          </div>
          <button type="submit" class="btn btn-dark w-100"><i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update' : 'Save' ?> Service Center</button>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Banner Image</div>
        <div class="card-body">
          <div class="drop-zone<?= isset($errors['image']) ? ' border-danger' : '' ?>" data-image-upload="scImage">
            <?php if ($c['image']): ?>
              <img src="../<?= h($c['image']) ?>" alt=""><span class="small text-muted">Click or drag to replace</span>
            <?php else: ?>
              <i class="bi bi-cloud-arrow-up fs-2 text-muted"></i>
              <span class="small text-muted">Click to upload or drag &amp; drop</span>
              <span class="small text-muted">JPG, PNG, WebP — max 10 MB</span>
            <?php endif; ?>
          </div>
          <input type="hidden" id="scImage" name="image" value="<?= h($c['image']) ?>">
          <?= isset($errors['image']) ? '<div class="text-danger small mt-1">' . h($errors['image']) . '</div>' : '' ?>
          <div class="form-text">Optional. Shown on the listing card (16:9). Without one the card shows a MarkEn placeholder.</div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Supported Products</div>
        <div class="card-body">
          <?php foreach ($categories as $i => $cat): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="productCategories[]" id="pc<?= $i ?>" value="<?= h($cat['slug']) ?>"<?= in_array($cat['slug'], $c['productCategories'], true) ? ' checked' : '' ?>>
              <label class="form-check-label small" for="pc<?= $i ?>"><?= h($cat['name']) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Service Types</div>
        <div class="card-body">
          <?php foreach (SERVICE_TYPES as $i => $type): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="serviceTypes[]" id="st<?= $i ?>" value="<?= h($type) ?>"<?= in_array($type, $c['serviceTypes'], true) ? ' checked' : '' ?>>
              <label class="form-check-label small" for="st<?= $i ?>"><?= h($type) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</form>
<?php include __DIR__ . '/includes/layout-bottom.php'; ?>
