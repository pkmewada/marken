<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$errors = [];
$form   = ['id' => 0, 'name' => '', 'slug' => '', 'tagline' => '', 'image' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = post('_action');
    $id     = (int)post('id');

    if ($action === 'delete' && $id > 0) {
        $cat   = dbOne('SELECT slug, name FROM `categories` WHERE id = ?', [$id]);
        $inUse = $cat ? (int)dbOne('SELECT COUNT(*) n FROM `products` WHERE categorySlug = ?', [$cat['slug']])['n'] : 0;
        if ($inUse) {
            setFlash('error', "Cannot delete \"{$cat['name']}\": $inUse product(s) still use it. Move them first.");
        } elseif ($cat) {
            dbRun('DELETE FROM `categories` WHERE id = ?', [$id]);
            setFlash('success', 'Category deleted.');
        }
        redirect('categories');
    }

    if ($action === 'save') {
        $form = ['id' => $id, 'name' => post('name'), 'slug' => post('slug'), 'tagline' => post('tagline'), 'image' => post('image')];
        $errors = validate($form, [
            'name'    => 'required|max:200',
            'slug'    => 'required|slug|max:100',
            'tagline' => 'max:500',
            'image'   => 'max:500',
        ]);
        if (!isset($errors['slug']) && dbOne('SELECT id FROM `categories` WHERE slug = ? AND id <> ?', [$form['slug'], $id])) {
            $errors['slug'] = 'Another category already uses this slug.';
        }

        if (!$errors) {
            $vals = [$form['name'], $form['slug'], $form['tagline'] ?: null, $form['image'] ?: null];
            if ($id > 0) {
                $old = dbOne('SELECT slug FROM `categories` WHERE id = ?', [$id]);
                db()->beginTransaction();
                dbRun('UPDATE `categories` SET name = ?, slug = ?, tagline = ?, image = ? WHERE id = ?', [...$vals, $id]);
                if ($old && $old['slug'] !== $form['slug']) {
                    dbRun('UPDATE `products` SET categorySlug = ? WHERE categorySlug = ?', [$form['slug'], $old['slug']]);
                }
                db()->commit();
                setFlash('success', 'Category updated.');
            } else {
                $next = (int)dbOne('SELECT COALESCE(MAX(sortOrder), 0) + 1 n FROM `categories`')['n'];
                dbRun('INSERT INTO `categories` (name, slug, tagline, image, sortOrder) VALUES (?, ?, ?, ?, ?)', [...$vals, $next]);
                setFlash('success', 'Category created.');
            }
            redirect('categories');
        }
    }
} elseif (is_string($_GET['edit'] ?? null)) {
    $row = dbOne('SELECT id, name, slug, tagline, image FROM `categories` WHERE slug = ?', [$_GET['edit']]);
    if (!$row) {
        setFlash('error', 'Category not found.');
        redirect('categories');
    }
    $form = array_map(fn($v) => $v ?? '', $row);
}

$categories = dbAll(
    'SELECT c.*, COUNT(p.id) AS productCount
     FROM `categories` c LEFT JOIN `products` p ON p.categorySlug = c.slug
     GROUP BY c.id ORDER BY c.sortOrder, c.name'
);
$isEdit = $form['id'] > 0;

$pageTitle  = 'Categories';
$pageActive = 'categories';
include __DIR__ . '/includes/layout-top.php';
?>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">All Categories (<?= count($categories) ?>)</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr><th>Category</th><th>Products</th><th class="text-end">Actions</th></tr>
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
              <td><?= (int)$c['productCount'] ?></td>
              <td class="text-end text-nowrap">
                <a href="categories/edit/<?= h($c['slug']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <form method="post" class="d-inline" data-confirm="Delete category &quot;<?= h($c['name']) ?>&quot;?">
                  <?= csrfField() ?>
                  <input type="hidden" name="_action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><?= $isEdit ? 'Edit Category' : 'New Category' ?></div>
      <div class="card-body">
        <form method="post" action="categories" novalidate>
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="save">
          <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

          <div class="mb-3">
            <label class="form-label small fw-semibold" for="catName">Name <span class="text-danger">*</span></label>
            <input type="text" id="catName" name="name" class="form-control<?= invalid($errors, 'name') ?>"
                   value="<?= h($form['name']) ?>" maxlength="200" data-slug-source="catSlug" required
                   placeholder="e.g. Medical Cold Chain">
            <?= fieldError($errors, 'name') ?>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="catSlug">Slug <span class="text-danger">*</span></label>
            <input type="text" id="catSlug" name="slug" class="form-control font-monospace<?= invalid($errors, 'slug') ?>"
                   value="<?= h($form['slug']) ?>" maxlength="100" required
                   placeholder="e.g. medical-cold-chain (auto-filled from the name)">
            <?= fieldError($errors, 'slug') ?: '<div class="form-text">Used in <code>products?category=slug</code>.</div>' ?>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="catTagline">Tagline</label>
            <input type="text" id="catTagline" name="tagline" class="form-control<?= invalid($errors, 'tagline') ?>"
                   value="<?= h($form['tagline']) ?>" maxlength="500"
                   placeholder="One-line summary, e.g. Precision cold storage for vaccines and plasma">
            <?= fieldError($errors, 'tagline') ?>
          </div>
          <div class="mb-4">
            <label class="form-label small fw-semibold">Banner Image</label>
            <div class="drop-zone" data-image-upload="catImage">
              <?php if ($form['image']): ?><img src="../<?= h($form['image']) ?>" alt=""><?php else: ?>
                <i class="bi bi-cloud-arrow-up fs-2 text-muted"></i>
                <span class="small text-muted">Click or drag an image here</span>
              <?php endif; ?>
            </div>
            <input type="hidden" id="catImage" name="image" value="<?= h($form['image']) ?>">
            <?= fieldError($errors, 'image') ?>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-dark"><?= $isEdit ? 'Update Category' : 'Create Category' ?></button>
            <?php if ($isEdit): ?><a href="categories" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/layout-bottom.php'; ?>
