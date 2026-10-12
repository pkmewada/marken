<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

const CERT_OPTIONS = ['WHO PQS', 'ISO 9001:2015', 'ISO 13485:2016', 'ISO 14001:2015', 'WHO GMP', 'CE Mark', 'BIS', 'CDSCO'];

$editSlug   = is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '';
$id         = 0;
$categories = dbAll('SELECT slug, name FROM `categories` ORDER BY sortOrder, name');
$errors     = [];

$p = [
    'name' => '', 'slug' => '', 'categorySlug' => '', 'status' => 'published', 'featured' => 0,
    'image' => '', 'heroMediaType' => '', 'heroMedia' => '',
    'description' => '', 'fullDescription' => '', 'metaTitle' => '', 'metaDescription' => '',
    'gallery' => [], 'features' => [], 'applications' => [], 'specifications' => [], 'certifications' => [], 'documents' => [],
];

if ($editSlug !== '') {
    $row = dbOne('SELECT * FROM `products` WHERE slug = ?', [$editSlug]);
    if (!$row) {
        setFlash('error', 'Product not found.');
        redirect('products');
    }
    $id = (int)$row['id'];
    foreach (['gallery', 'features', 'applications', 'specifications', 'certifications', 'documents'] as $k) {
        $row[$k] = fromJson($row[$k]);
    }
    $p = array_merge($p, array_map(fn($v) => $v ?? '', $row));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $p = array_merge($p, [
        'name'            => post('name'),
        'slug'            => post('slug'),
        'categorySlug'    => post('categorySlug'),
        'status'          => post('status'),
        'featured'        => isset($_POST['featured']) ? 1 : 0,
        'image'           => post('image'),
        'heroMediaType'   => post('heroMediaType'),
        'heroMedia'       => post('heroMedia'),
        'description'     => post('description'),
        'fullDescription' => post('fullDescription'),
        'metaTitle'       => post('metaTitle'),
        'metaDescription' => post('metaDescription'),
    ]);

    // Repeatable groups — blank rows are dropped.
    $p['gallery'] = array_values(array_filter(postList('gallery')));
    $p['applications'] = array_values(array_filter(array_map('trim', explode(',', post('applications')))));
    $p['certifications'] = array_values(array_intersect(CERT_OPTIONS, postList('certifications')));
    $p['features'] = $p['specifications'] = $p['documents'] = [];
    foreach (postList('featureTitle') as $i => $t) {
        $d = postList('featureDescription')[$i] ?? '';
        if ($t !== '' || $d !== '') $p['features'][] = ['title' => $t, 'description' => $d];
    }
    foreach (postList('specLabel') as $i => $k) {
        $v = postList('specValue')[$i] ?? '';
        if ($k !== '' || $v !== '') $p['specifications'][] = ['label' => $k, 'value' => $v];
    }
    foreach (postList('docLabel') as $i => $l) {
        $u = postList('docUrl')[$i] ?? '';
        $l = preg_replace('/\s+/', ' ', $l);
        // "brochure " → "Brochure" so it links to the matching card on the product page.
        foreach (STANDARD_DOCUMENTS as $std) {
            if (strcasecmp($l, $std) === 0) $l = $std;
        }
        if ($l !== '' || $u !== '') $p['documents'][] = ['label' => $l, 'url' => $u];
    }

    $errors = validate($p, [
        'name'            => 'required|max:200',
        'slug'            => 'required|slug|max:100',
        'categorySlug'    => 'required',
        'status'          => 'required',
        'image'           => 'required|max:500',
        'description'     => 'max:1000',
        'fullDescription' => 'max:20000',
        'metaTitle'       => 'max:200',
        'metaDescription' => 'max:300',
    ]);
    if (!isset($errors['slug']) && dbOne('SELECT id FROM `products` WHERE slug = ? AND id <> ?', [$p['slug'], $id])) {
        $errors['slug'] = 'Another product already uses this slug.';
    }
    if (!isset($errors['categorySlug']) && !in_array($p['categorySlug'], array_column($categories, 'slug'), true)) {
        $errors['categorySlug'] = 'Select a valid category.';
    }
    if (!in_array($p['status'], ['published', 'draft'], true)) $errors['status'] = 'Invalid status.';
    if ($p['image'] !== '' && !isSafeLink($p['image'])) $errors['image'] = 'Upload an image.';
    $p['heroMediaType'] = $p['heroMediaType'] === 'video' ? 'video' : 'image';
    if ($p['heroMedia'] === '') {
        $p['heroMediaType'] = '';
    } elseif (!isSafeLink($p['heroMedia']) || mb_strlen($p['heroMedia']) > 500
        || !preg_match($p['heroMediaType'] === 'video' ? '/\.(mp4|webm)$/i' : '/\.(jpe?g|png|webp|gif)$/i', $p['heroMedia'])) {
        $errors['heroMedia'] = 'Upload a JPG/PNG/WebP image or an MP4/WebM video.';
    }
    foreach ($p['gallery'] as $g) if (!isSafeLink($g)) { $errors['gallery'] = 'Invalid gallery image.'; break; }
    foreach ($p['features'] as $f) if ($f['title'] === '') { $errors['features'] = 'Every feature needs a title.'; break; }
    foreach ($p['specifications'] as $s) if ($s['label'] === '' || $s['value'] === '') { $errors['specifications'] = 'Each specification needs both a parameter and a value.'; break; }
    foreach ($p['documents'] as $d) {
        if ($d['label'] === '' || $d['url'] === '') { $errors['documents'] = 'Each document needs a label and a file or link.'; break; }
        if (!isSafeLink($d['url'])) { $errors['documents'] = 'Document links must start with https://, mailto: or be an uploaded file.'; break; }
    }

    if (!$errors) {
        $vals = [
            $p['name'], $p['slug'], $p['categorySlug'], $p['status'], $p['featured'], $p['image'],
            toJson($p['gallery']), $p['description'] ?: null, $p['fullDescription'] ?: null,
            toJson($p['features']), toJson($p['applications']), toJson($p['specifications']),
            toJson($p['certifications']), toJson($p['documents']),
            $p['metaTitle'] ?: null, $p['metaDescription'] ?: null,
            $p['heroMediaType'] ?: null, $p['heroMedia'] ?: null,
        ];
        $cols = 'name = ?, slug = ?, categorySlug = ?, status = ?, featured = ?, image = ?, gallery = ?,
                 description = ?, fullDescription = ?, features = ?, applications = ?, specifications = ?,
                 certifications = ?, documents = ?, metaTitle = ?, metaDescription = ?,
                 heroMediaType = ?, heroMedia = ?';
        if ($id > 0) {
            dbRun("UPDATE `products` SET $cols WHERE id = ?", [...$vals, $id]);
            setFlash('success', "Product \"{$p['name']}\" updated.");
        } else {
            $next = (int)dbOne('SELECT COALESCE(MAX(sortOrder), 0) + 1 n FROM `products`')['n'];
            dbRun("INSERT INTO `products` SET $cols, sortOrder = ?", [...$vals, $next]);
            setFlash('success', "Product \"{$p['name']}\" created.");
        }
        redirect('products');
    }
}

$isEdit        = $id > 0;
$action        = $isEdit ? 'products/edit/' . rawurlencode($editSlug) : 'products/new';
$pageTitle     = $isEdit ? 'Edit Product' : 'Add Product';
$pageActive    = 'products';
$topbarActions = '<a href="products" class="btn btn-outline-secondary btn-sm">Cancel</a>'
               . '<button type="submit" form="productForm" class="btn btn-dark btn-sm"><i class="bi bi-check-lg me-1"></i>'
               . ($isEdit ? 'Update' : 'Save') . ' Product</button>';
include __DIR__ . '/includes/layout-top.php';

$groupError = fn(string $k) => isset($errors[$k]) ? '<div class="text-danger small mb-2">' . h($errors[$k]) . '</div>' : '';
?>
<?php if ($errors): ?>
  <div class="alert alert-danger small">Please fix the highlighted fields below.</div>
<?php endif; ?>

<form method="post" action="<?= h($action) ?>" id="productForm" novalidate>
  <?= csrfField() ?>
  <div class="row g-4">
    <div class="col-lg-8">

      <div class="card mb-4">
        <div class="card-header">Basic Information</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="prodName">Product Name <span class="text-danger">*</span></label>
            <input type="text" id="prodName" name="name" class="form-control<?= invalid($errors, 'name') ?>"
                   value="<?= h($p['name']) ?>" maxlength="200" data-slug-source="prodSlug" required
                   placeholder="e.g. ULT Deep Freezer –86°C">
            <?= fieldError($errors, 'name') ?>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="prodSlug">Slug <span class="text-danger">*</span></label>
            <input type="text" id="prodSlug" name="slug" class="form-control font-monospace<?= invalid($errors, 'slug') ?>"
                   value="<?= h($p['slug']) ?>" maxlength="100" required
                   placeholder="e.g. ult-deep-freezer (auto-filled from the name)">
            <?= fieldError($errors, 'slug') ?: '<div class="form-text">Product URL: <code>/product-details/slug</code></div>' ?>
          </div>
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label small fw-semibold" for="prodCat">Category <span class="text-danger">*</span></label>
              <select id="prodCat" name="categorySlug" class="form-select<?= invalid($errors, 'categorySlug') ?>" required>
                <option value="">— Select —</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= h($c['slug']) ?>"<?= $p['categorySlug'] === $c['slug'] ? ' selected' : '' ?>><?= h($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <?= fieldError($errors, 'categorySlug') ?>
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold" for="prodStatus">Status</label>
              <select id="prodStatus" name="status" class="form-select<?= invalid($errors, 'status') ?>">
                <option value="published"<?= $p['status'] === 'published' ? ' selected' : '' ?>>Published</option>
                <option value="draft"<?= $p['status'] === 'draft' ? ' selected' : '' ?>>Draft (hidden from website)</option>
              </select>
              <?= fieldError($errors, 'status') ?>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Description</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="prodDesc">Short Description</label>
            <textarea id="prodDesc" name="description" rows="3" maxlength="1000" class="form-control<?= invalid($errors, 'description') ?>"
                      placeholder="1–2 sentence summary used in product listings and search results"><?= h($p['description']) ?></textarea>
            <?= fieldError($errors, 'description') ?>
          </div>
          <div>
            <label class="form-label small fw-semibold" for="prodFullDesc">Full Description</label>
            <textarea id="prodFullDesc" name="fullDescription" rows="6" class="form-control<?= invalid($errors, 'fullDescription') ?>"
                      placeholder="Detailed overview: what the product does, who it is for, and what sets it apart"><?= h($p['fullDescription']) ?></textarea>
            <?= fieldError($errors, 'fullDescription') ?>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          Key Features
          <button type="button" class="btn btn-sm btn-outline-primary" data-add-row="feature"><i class="bi bi-plus-lg"></i> Add</button>
        </div>
        <div class="card-body">
          <?= $groupError('features') ?>
          <div data-rows="feature">
            <?php foreach ($p['features'] as $f): ?>
              <div class="border rounded p-2 mb-2 position-relative" data-row>
                <button type="button" class="btn-close position-absolute top-0 end-0 m-2" data-remove-row aria-label="Remove"></button>
                <input type="text" name="featureTitle[]" class="form-control form-control-sm mb-1 w-75" placeholder="Feature title, e.g. Triple Seal Door Design" value="<?= h($f['title'] ?? '') ?>">
                <textarea name="featureDescription[]" class="form-control form-control-sm" rows="2" placeholder="Short explanation, e.g. Multi-point sealing for superior insulation"><?= h($f['description'] ?? '') ?></textarea>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          Specifications
          <button type="button" class="btn btn-sm btn-outline-primary" data-add-row="spec"><i class="bi bi-plus-lg"></i> Add</button>
        </div>
        <div class="card-body">
          <?= $groupError('specifications') ?>
          <div data-rows="spec">
            <?php foreach ($p['specifications'] as $s): ?>
              <div class="row g-2 mb-2" data-row>
                <div class="col"><input type="text" name="specLabel[]" class="form-control form-control-sm" placeholder="Parameter, e.g. Temperature Range" value="<?= h($s['label'] ?? $s['key'] ?? '') ?>"></div>
                <div class="col"><input type="text" name="specValue[]" class="form-control form-control-sm" placeholder="Value, e.g. –50°C to –86°C" value="<?= h($s['value'] ?? '') ?>"></div>
                <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row><i class="bi bi-trash"></i></button></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Applications</div>
        <div class="card-body">
          <input type="text" name="applications" class="form-control" value="<?= h(implode(', ', $p['applications'])) ?>"
                 placeholder="Where it is used, comma-separated, e.g. ICUs, Blood banks, Pathology labs">
          <div class="form-text">Comma-separated.</div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          Documents &amp; Downloads
          <button type="button" class="btn btn-sm btn-outline-primary" data-add-row="doc"><i class="bi bi-plus-lg"></i> Add</button>
        </div>
        <div class="card-body">
          <?= $groupError('documents') ?>
          <div data-rows="doc">
            <?php foreach ($p['documents'] as $d): ?>
              <div class="row g-2 mb-2" data-row>
                <div class="col-sm-4"><input type="text" name="docLabel[]" list="docLabelOptions" class="form-control form-control-sm" placeholder="Pick a document type or type your own" value="<?= h($d['label'] ?? '') ?>"></div>
                <div class="col">
                  <div class="input-group input-group-sm">
                    <input type="text" name="docUrl[]" class="form-control" placeholder="https://… or upload a file" value="<?= h($d['url'] ?? '') ?>">
                    <button type="button" class="btn btn-outline-secondary" data-doc-upload title="Upload file"><i class="bi bi-upload"></i></button>
                  </div>
                </div>
                <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row><i class="bi bi-trash"></i></button></div>
              </div>
            <?php endforeach; ?>
          </div>
          <datalist id="docLabelOptions">
            <?php foreach (STANDARD_DOCUMENTS as $std): ?><option value="<?= h($std) ?>"><?php endforeach; ?>
          </datalist>
          <div class="form-text">
            Use <strong><?= h(implode('</strong>, <strong>', STANDARD_DOCUMENTS)) ?></strong> exactly to attach the file to that card on the product page — any other label adds an extra card.
            PDF, Word or image, max 10 MB — or paste a link.
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">SEO</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="metaTitle">Meta Title</label>
            <input type="text" id="metaTitle" name="metaTitle" maxlength="200" class="form-control<?= invalid($errors, 'metaTitle') ?>"
                   value="<?= h($p['metaTitle']) ?>" placeholder="Defaults to the product name">
            <?= fieldError($errors, 'metaTitle') ?>
          </div>
          <div>
            <label class="form-label small fw-semibold" for="metaDescription">Meta Description</label>
            <textarea id="metaDescription" name="metaDescription" rows="2" maxlength="300" class="form-control<?= invalid($errors, 'metaDescription') ?>"
                      placeholder="Up to 300 characters shown under the title in Google results — defaults to the short description"><?= h($p['metaDescription']) ?></textarea>
            <?= fieldError($errors, 'metaDescription') ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-4">
        <div class="card-header">Publish</div>
        <div class="card-body">
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="featured" id="featured" value="1"<?= $p['featured'] ? ' checked' : '' ?>>
            <label class="form-check-label small" for="featured">Featured product</label>
          </div>
          <button type="submit" class="btn btn-dark w-100"><i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update' : 'Save' ?> Product</button>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Product Image <span class="text-danger">*</span></div>
        <div class="card-body">
          <div class="drop-zone<?= isset($errors['image']) ? ' border-danger' : '' ?>" data-image-upload="prodImage">
            <?php if ($p['image']): ?>
              <img src="../<?= h($p['image']) ?>" alt=""><span class="small text-muted">Click or drag to replace</span>
            <?php else: ?>
              <i class="bi bi-cloud-arrow-up fs-2 text-muted"></i>
              <span class="small text-muted">Click to upload or drag &amp; drop</span>
              <span class="small text-muted">JPG, PNG, WebP — max 10 MB</span>
            <?php endif; ?>
          </div>
          <input type="hidden" id="prodImage" name="image" value="<?= h($p['image']) ?>">
          <?= isset($errors['image']) ? '<div class="text-danger small mt-1">' . h($errors['image']) . '</div>' : '' ?>
          <div class="form-text">Used on product cards and listings.</div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          Hero Media
          <button type="button" class="btn btn-sm btn-outline-danger<?= $p['heroMedia'] ? '' : ' d-none' ?>" id="heroRemove">
            <i class="bi bi-x-lg"></i> Remove
          </button>
        </div>
        <div class="card-body">
          <div class="drop-zone<?= isset($errors['heroMedia']) ? ' border-danger' : '' ?>" id="heroDrop">
            <?php if ($p['heroMedia'] && $p['heroMediaType'] === 'video'): ?>
              <video src="../<?= h($p['heroMedia']) ?>" muted loop autoplay playsinline style="max-width:100%;max-height:160px"></video>
              <span class="small text-muted">Click or drag to replace</span>
            <?php elseif ($p['heroMedia']): ?>
              <img src="../<?= h($p['heroMedia']) ?>" alt=""><span class="small text-muted">Click or drag to replace</span>
            <?php else: ?>
              <i class="bi bi-film fs-2 text-muted"></i>
              <span class="small text-muted">Upload a wide image or a video</span>
              <span class="small text-muted">JPG, PNG, WebP (10 MB) · MP4, WebM (50 MB)</span>
            <?php endif; ?>
          </div>
          <input type="hidden" id="heroMediaType" name="heroMediaType" value="<?= h($p['heroMediaType']) ?>">
          <input type="hidden" id="heroMedia" name="heroMedia" value="<?= h($p['heroMedia']) ?>">
          <?= isset($errors['heroMedia']) ? '<div class="text-danger small mt-1">' . h($errors['heroMedia']) . '</div>' : '' ?>
          <div class="form-text">Optional. Fills the whole top section of the product page; videos autoplay muted on loop. Leave empty to show the product image instead.</div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Gallery</div>
        <div class="card-body">
          <?= $groupError('gallery') ?>
          <div class="gallery-grid mb-2" id="galleryGrid">
            <?php foreach ($p['gallery'] as $g): ?>
              <div class="gallery-item" data-row>
                <img src="../<?= h($g) ?>" alt="">
                <input type="hidden" name="gallery[]" value="<?= h($g) ?>">
                <button type="button" class="btn-close" data-remove-row aria-label="Remove"></button>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="drop-zone" id="galleryDrop" style="min-height:80px">
            <i class="bi bi-images text-muted"></i>
            <span class="small text-muted">Add images</span>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Certifications</div>
        <div class="card-body">
          <?php foreach (CERT_OPTIONS as $i => $cert): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="certifications[]" id="cert<?= $i ?>" value="<?= h($cert) ?>"<?= in_array($cert, $p['certifications'], true) ? ' checked' : '' ?>>
              <label class="form-check-label small" for="cert<?= $i ?>"><?= h($cert) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</form>

<template id="tpl-feature">
  <div class="border rounded p-2 mb-2 position-relative" data-row>
    <button type="button" class="btn-close position-absolute top-0 end-0 m-2" data-remove-row aria-label="Remove"></button>
    <input type="text" name="featureTitle[]" class="form-control form-control-sm mb-1 w-75" placeholder="Feature title, e.g. Triple Seal Door Design">
    <textarea name="featureDescription[]" class="form-control form-control-sm" rows="2" placeholder="Short explanation, e.g. Multi-point sealing for superior insulation"></textarea>
  </div>
</template>
<template id="tpl-spec">
  <div class="row g-2 mb-2" data-row>
    <div class="col"><input type="text" name="specLabel[]" class="form-control form-control-sm" placeholder="Parameter, e.g. Temperature Range"></div>
    <div class="col"><input type="text" name="specValue[]" class="form-control form-control-sm" placeholder="Value, e.g. –50°C to –86°C"></div>
    <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row><i class="bi bi-trash"></i></button></div>
  </div>
</template>
<template id="tpl-doc">
  <div class="row g-2 mb-2" data-row>
    <div class="col-sm-4"><input type="text" name="docLabel[]" list="docLabelOptions" class="form-control form-control-sm" placeholder="Pick a document type or type your own"></div>
    <div class="col">
      <div class="input-group input-group-sm">
        <input type="text" name="docUrl[]" class="form-control" placeholder="https://… or upload a file">
        <button type="button" class="btn btn-outline-secondary" data-doc-upload title="Upload file"><i class="bi bi-upload"></i></button>
      </div>
    </div>
    <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row><i class="bi bi-trash"></i></button></div>
  </div>
</template>

<?php
$pageScripts = <<<'HTML'
<script>
document.querySelectorAll('[data-add-row]').forEach((btn) => btn.addEventListener('click', () => {
  const key = btn.dataset.addRow;
  document.querySelector(`[data-rows="${key}"]`).append(document.getElementById(`tpl-${key}`).content.cloneNode(true));
}));

document.addEventListener('click', async (e) => {
  const remove = e.target.closest('[data-remove-row]');
  if (remove) { remove.closest('[data-row]').remove(); return; }

  const docBtn = e.target.closest('[data-doc-upload]');
  if (!docBtn) return;
  const urlInput = docBtn.parentElement.querySelector('input');
  const picker = Object.assign(document.createElement('input'), {
    type: 'file', accept: '.pdf,.doc,.docx,image/*',
  });
  picker.onchange = async () => {
    if (!picker.files[0]) return;
    docBtn.disabled = true;
    try { urlInput.value = (await uploadFile(picker.files[0])).url; }
    catch (err) { showError(err.message); }
    finally { docBtn.disabled = false; }
  };
  picker.click();
});

const galleryDrop = document.getElementById('galleryDrop');
const galleryGrid = document.getElementById('galleryGrid');
bindFilePicker(galleryDrop, 'image/jpeg,image/png,image/webp,image/gif', async (file) => {
  galleryDrop.classList.add('is-busy');
  try {
    const { url } = await uploadFile(file);
    galleryGrid.insertAdjacentHTML('beforeend',
      `<div class="gallery-item" data-row><img src="../${escHtml(url)}" alt="">` +
      `<input type="hidden" name="gallery[]" value="${escHtml(url)}">` +
      `<button type="button" class="btn-close" data-remove-row aria-label="Remove"></button></div>`);
  } catch (err) { showError(err.message); }
  finally { galleryDrop.classList.remove('is-busy'); }
});

// Hero media: one image or video; the type comes from the uploaded file.
const heroDrop = document.getElementById('heroDrop');
const heroMedia = document.getElementById('heroMedia');
const heroType = document.getElementById('heroMediaType');
const heroRemove = document.getElementById('heroRemove');
const heroEmpty = '<i class="bi bi-film fs-2 text-muted"></i>'
  + '<span class="small text-muted">Upload a wide image or a video</span>'
  + '<span class="small text-muted">JPG, PNG, WebP (10 MB) · MP4, WebM (50 MB)</span>';
bindFilePicker(heroDrop, 'image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm', async (file) => {
  heroDrop.classList.add('is-busy');
  try {
    const { url, mime } = await uploadFile(file);
    const isVideo = mime.startsWith('video/');
    heroMedia.value = url;
    heroType.value = isVideo ? 'video' : 'image';
    heroDrop.innerHTML = (isVideo
      ? `<video src="../${escHtml(url)}" muted loop autoplay playsinline style="max-width:100%;max-height:160px"></video>`
      : `<img src="../${escHtml(url)}" alt="">`) + '<span class="small text-muted">Click or drag to replace</span>';
    heroRemove.classList.remove('d-none');
  } catch (err) { showError(err.message); }
  finally { heroDrop.classList.remove('is-busy'); }
});
heroRemove.addEventListener('click', () => {
  heroMedia.value = heroType.value = '';
  heroDrop.innerHTML = heroEmpty;
  heroRemove.classList.add('d-none');
});
</script>
HTML;
include __DIR__ . '/includes/layout-bottom.php';
