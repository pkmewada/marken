<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$errors = [];
$form   = ['id' => 0, 'name' => '', 'slug' => '', 'description' => '', 'image' => '', 'isActive' => 1, 'sortOrder' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = post('_action');
    $id     = (int)post('id');

    if ($action === 'delete' && $id > 0) {
        // Posts are never deleted or orphaned with their category (the FK is RESTRICT too).
        $cat   = dbOne('SELECT name FROM `blogCategories` WHERE id = ?', [$id]);
        $inUse = $cat ? (int)dbOne('SELECT COUNT(*) n FROM `blogs` WHERE categoryId = ?', [$id])['n'] : 0;
        if ($inUse) {
            setFlash('error', "Cannot delete \"{$cat['name']}\": $inUse blog post(s) still use it. Move them to another category first, or deactivate it instead.");
        } elseif ($cat) {
            dbRun('DELETE FROM `blogCategories` WHERE id = ?', [$id]);
            setFlash('success', 'Blog category deleted.');
        }
        redirect('blog-categories');
    }

    if ($action === 'save') {
        $form = [
            'id' => $id, 'name' => post('name'), 'slug' => post('slug'), 'description' => post('description'),
            'image' => post('image'), 'isActive' => isset($_POST['isActive']) ? 1 : 0, 'sortOrder' => post('sortOrder'),
        ];
        $errors = validate($form, [
            'name'        => 'required|max:200',
            'slug'        => 'required|slug|max:100',
            'description' => 'max:500',
            'image'       => 'max:500',
        ]);
        if (!isset($errors['slug']) && dbOne('SELECT id FROM `blogCategories` WHERE slug = ? AND id <> ?', [$form['slug'], $id])) {
            $errors['slug'] = 'Another blog category already uses this slug.';
        }
        if ($form['image'] !== '' && !isSafeLink($form['image'])) $errors['image'] = 'Upload an image.';
        if ($form['sortOrder'] !== '' && filter_var($form['sortOrder'], FILTER_VALIDATE_INT) === false) {
            $errors['sortOrder'] = 'Display order must be a whole number.';
        }

        if (!$errors) {
            $sort = $form['sortOrder'] !== ''
                ? (int)$form['sortOrder']
                : (int)dbOne('SELECT COALESCE(MAX(sortOrder), 0) + 1 n FROM `blogCategories`')['n'];
            $vals = [$form['name'], $form['slug'], $form['description'] ?: null, $form['image'] ?: null, $form['isActive'], $sort];
            if ($id > 0) {
                dbRun('UPDATE `blogCategories` SET name = ?, slug = ?, description = ?, image = ?, isActive = ?, sortOrder = ? WHERE id = ?', [...$vals, $id]);
                setFlash('success', 'Blog category updated.');
            } else {
                dbRun('INSERT INTO `blogCategories` (name, slug, description, image, isActive, sortOrder) VALUES (?, ?, ?, ?, ?, ?)', $vals);
                setFlash('success', 'Blog category created.');
            }
            redirect('blog-categories');
        }
    }
} elseif (is_string($_GET['edit'] ?? null)) {
    $row = dbOne('SELECT id, name, slug, description, image, isActive, sortOrder FROM `blogCategories` WHERE slug = ?', [$_GET['edit']]);
    if (!$row) {
        setFlash('error', 'Blog category not found.');
        redirect('blog-categories');
    }
    $form = array_map(fn($v) => $v ?? '', $row);
}

$categories = dbAll(
    'SELECT c.*, COUNT(b.id) AS blogCount
     FROM `blogCategories` c LEFT JOIN `blogs` b ON b.categoryId = c.id
     GROUP BY c.id ORDER BY c.sortOrder, c.name'
);
$isEdit = $form['id'] > 0;

$pageTitle  = 'Blog Categories';
$pageActive = 'blog-categories';
include __DIR__ . '/includes/layout-top.php';
?>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">All Blog Categories (<?= count($categories) ?>)</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr><th>Category</th><th>Order</th><th>Status</th><th>Posts</th><th class="text-end">Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($categories as $c): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php if ($c['image']): ?><img src="../<?= h($c['image']) ?>" alt="" class="thumb"><?php endif; ?>
                  <div>
                    <div class="fw-semibold"><?= h($c['name']) ?></div>
                    <code class="small"><?= h($c['slug']) ?></code>
                  </div>
                </div>
              </td>
              <td><?= (int)$c['sortOrder'] ?></td>
              <td><span class="badge badge-<?= $c['isActive'] ? 'published' : 'closed' ?>"><?= $c['isActive'] ? 'Active' : 'Inactive' ?></span></td>
              <td><a href="blogs?category=<?= h(rawurlencode($c['name'])) ?>"><?= (int)$c['blogCount'] ?></a></td>
              <td class="text-end text-nowrap">
                <a href="blog-categories/edit/<?= h($c['slug']) ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                <?php if ((int)$c['blogCount'] > 0): ?>
                  <span class="d-inline-block" tabindex="0" title="Move its <?= (int)$c['blogCount'] ?> post(s) to another category before deleting">
                    <button type="button" class="btn btn-sm btn-outline-danger" disabled><i class="bi bi-trash"></i></button>
                  </span>
                <?php else: ?>
                  <form method="post" class="d-inline" data-confirm="Delete blog category &quot;<?= h($c['name']) ?>&quot;?">
                    <?= csrfField() ?>
                    <input type="hidden" name="_action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$categories): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">No blog categories yet — create the first one on the right.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><?= $isEdit ? 'Edit Blog Category' : 'New Blog Category' ?></div>
      <div class="card-body">
        <form method="post" action="blog-categories" novalidate>
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="save">
          <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

          <div class="mb-3">
            <label class="form-label small fw-semibold" for="bcName">Name <span class="text-danger">*</span></label>
            <input type="text" id="bcName" name="name" class="form-control<?= invalid($errors, 'name') ?>"
                   value="<?= h($form['name']) ?>" maxlength="200" data-slug-source="bcSlug" required
                   placeholder="e.g. Medical Cold Chain">
            <?= fieldError($errors, 'name') ?>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="bcSlug">Slug <span class="text-danger">*</span></label>
            <input type="text" id="bcSlug" name="slug" class="form-control font-monospace<?= invalid($errors, 'slug') ?>"
                   value="<?= h($form['slug']) ?>" maxlength="100" required
                   placeholder="e.g. medical-cold-chain (auto-filled from the name)">
            <?= fieldError($errors, 'slug') ?: '<div class="form-text">Used in the blog filter link <code>blog?category=slug</code>.</div>' ?>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="bcDesc">Description</label>
            <textarea id="bcDesc" name="description" rows="2" maxlength="500" class="form-control<?= invalid($errors, 'description') ?>"
                      placeholder="Optional summary, e.g. Guides on vaccine and plasma cold storage"><?= h($form['description']) ?></textarea>
            <?= fieldError($errors, 'description') ?>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label small fw-semibold" for="bcSort">Display Order</label>
              <input type="number" id="bcSort" name="sortOrder" class="form-control<?= invalid($errors, 'sortOrder') ?>"
                     value="<?= h((string)$form['sortOrder']) ?>" step="1" placeholder="e.g. 1 (blank = last)">
              <?= fieldError($errors, 'sortOrder') ?>
            </div>
            <div class="col-6 d-flex align-items-end">
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="isActive" id="bcActive" value="1"<?= $form['isActive'] ? ' checked' : '' ?>>
                <label class="form-check-label small" for="bcActive">Active</label>
              </div>
            </div>
          </div>
          <div class="form-text mb-3">Inactive categories are hidden from the blog filter; their posts stay online under “All Articles”.</div>
          <div class="mb-4">
            <label class="form-label small fw-semibold">Image</label>
            <div class="drop-zone<?= isset($errors['image']) ? ' border-danger' : '' ?>" data-image-upload="bcImage">
              <?php if ($form['image']): ?><img src="../<?= h($form['image']) ?>" alt=""><?php else: ?>
                <i class="bi bi-cloud-arrow-up fs-2 text-muted"></i>
                <span class="small text-muted">Optional — click or drag an image here</span>
              <?php endif; ?>
            </div>
            <input type="hidden" id="bcImage" name="image" value="<?= h($form['image']) ?>">
            <?= isset($errors['image']) ? '<div class="text-danger small mt-1">' . h($errors['image']) . '</div>' : '' ?>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-dark"><?= $isEdit ? 'Update Category' : 'Create Category' ?></button>
            <?php if ($isEdit): ?><a href="blog-categories" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/layout-bottom.php'; ?>
