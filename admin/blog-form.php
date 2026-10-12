<?php
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/app/html-sanitizer.php';
requireLogin();

$editSlug   = is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '';
$id         = 0;
$categories = dbAll('SELECT id, name, isActive FROM `blogCategories` ORDER BY sortOrder, name');
$products   = dbAll('SELECT id, name, status FROM `products` ORDER BY name');
$errors     = [];

$b = [
    'title' => '', 'slug' => '', 'categoryId' => '', 'excerpt' => '', 'content' => '', 'featuredImage' => '',
    'authorName' => 'MarkEn Team', 'status' => 'draft', 'featured' => 0, 'relatedProductId' => '',
    'publishedAt' => '', 'metaTitle' => '', 'metaDescription' => '',
];

if ($editSlug !== '') {
    $row = dbOne('SELECT * FROM `blogs` WHERE slug = ?', [$editSlug]);
    if (!$row) {
        setFlash('error', 'Blog post not found.');
        redirect('blogs');
    }
    $id = (int)$row['id'];
    $row['publishedAt'] = $row['publishedAt'] ? substr($row['publishedAt'], 0, 10) : '';
    $b = array_merge($b, array_map(fn($v) => $v ?? '', array_intersect_key($row, $b)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $b = array_merge($b, [
        'title'            => post('title'),
        'slug'             => post('slug'),
        'categoryId'       => post('categoryId'),
        'excerpt'          => post('excerpt'),
        'content'          => sanitizeHtml(post('content')),
        'featuredImage'    => post('featuredImage'),
        'authorName'       => post('authorName'),
        'status'           => post('status'),
        'featured'         => isset($_POST['featured']) ? 1 : 0,
        'relatedProductId' => post('relatedProductId'),
        'publishedAt'      => post('publishedAt'),
        'metaTitle'        => post('metaTitle'),
        'metaDescription'  => post('metaDescription'),
    ]);

    $errors = validate($b, [
        'title'           => 'required|max:255',
        'slug'            => 'required|slug|max:150',
        'categoryId'      => 'required',
        'excerpt'         => 'required|max:500',
        'featuredImage'   => 'required|max:500',
        'authorName'      => 'required|max:100',
        'status'          => 'required',
        'metaTitle'       => 'max:200',
        'metaDescription' => 'max:300',
    ]);
    if (!isset($errors['slug']) && dbOne('SELECT id FROM `blogs` WHERE slug = ? AND id <> ?', [$b['slug'], $id])) {
        $errors['slug'] = 'Another blog post already uses this slug.';
    }
    if (!isset($errors['categoryId']) && !in_array($b['categoryId'], array_map('strval', array_column($categories, 'id')), true)) {
        $errors['categoryId'] = 'Select a valid category.';
    }
    if (!in_array($b['status'], ['published', 'draft'], true)) $errors['status'] = 'Invalid status.';
    if ($b['featuredImage'] !== '' && (!isSafeLink($b['featuredImage']) || !preg_match('/\.(jpe?g|png|webp|gif)$/i', $b['featuredImage']))) {
        $errors['featuredImage'] = 'Upload a JPG, PNG, WebP or GIF image.';
    }
    if (trim(html_entity_decode(strip_tags($b['content']))) === '' && !str_contains($b['content'], '<img')) {
        $errors['content'] = 'Content is required.';
    } elseif (strlen($b['content']) > 1000000) {
        $errors['content'] = 'Content is too long.';
    }
    if ($b['relatedProductId'] !== '' && !in_array($b['relatedProductId'], array_map('strval', array_column($products, 'id')), true)) {
        $errors['relatedProductId'] = 'Select a valid product.';
    }
    if ($b['publishedAt'] !== '') {
        $d = DateTime::createFromFormat('!Y-m-d', $b['publishedAt']);
        if (!$d || $d->format('Y-m-d') !== $b['publishedAt']) $errors['publishedAt'] = 'Enter a valid date.';
    } elseif ($b['status'] === 'published') {
        $b['publishedAt'] = dbOne('SELECT CURDATE() d')['d'];   // DB clock, same one the public API compares against
    }

    if (!$errors) {
        $vals = [
            (int)$b['categoryId'], $b['title'], $b['slug'], $b['excerpt'], $b['content'], $b['featuredImage'],
            $b['authorName'], $b['status'], $b['featured'], $b['relatedProductId'] !== '' ? (int)$b['relatedProductId'] : null,
            $b['publishedAt'] !== '' ? $b['publishedAt'] . ' 00:00:00' : null,
            $b['metaTitle'] ?: null, $b['metaDescription'] ?: null,
        ];
        $cols = 'categoryId = ?, title = ?, slug = ?, excerpt = ?, content = ?, featuredImage = ?, authorName = ?,
                 status = ?, featured = ?, relatedProductId = ?, publishedAt = ?, metaTitle = ?, metaDescription = ?';
        if ($id > 0) {
            dbRun("UPDATE `blogs` SET $cols WHERE id = ?", [...$vals, $id]);
            setFlash('success', "Blog post \"{$b['title']}\" updated.");
        } else {
            dbRun("INSERT INTO `blogs` SET $cols", $vals);
            setFlash('success', "Blog post \"{$b['title']}\" created.");
        }
        redirect('blogs');
    }
}

// Stored image paths are site-root relative (uploads/…); the editor runs under admin/, so it shows them via ../
$editorHtml = preg_replace('/(<img\b[^>]*\bsrc=")(?=(?:uploads|images)\/)/i', '$1../', (string)$b['content']);

$isEdit        = $id > 0;
$action        = $isEdit ? 'blogs/edit/' . rawurlencode($editSlug) : 'blogs/new';
$pageTitle     = $isEdit ? 'Edit Blog Post' : 'Add Blog Post';
$pageActive    = 'blogs';
$topbarActions = ($isEdit ? '<a href="../blog-details/' . h(rawurlencode($editSlug)) . '?preview=1" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="bi bi-eye me-1"></i>Preview</a>' : '')
               . '<a href="blogs" class="btn btn-outline-secondary btn-sm">Cancel</a>'
               . '<button type="submit" form="blogForm" class="btn btn-dark btn-sm"><i class="bi bi-check-lg me-1"></i>'
               . ($isEdit ? 'Update' : 'Save') . ' Post</button>';
include __DIR__ . '/includes/layout-top.php';
?>
<?php if ($errors): ?>
  <div class="alert alert-danger small">Please fix the highlighted fields below.</div>
<?php endif; ?>

<form method="post" action="<?= h($action) ?>" id="blogForm" novalidate>
  <?= csrfField() ?>
  <div class="row g-4">
    <div class="col-lg-8">

      <div class="card mb-4">
        <div class="card-header">Post</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="blogTitle">Title <span class="text-danger">*</span></label>
            <input type="text" id="blogTitle" name="title" class="form-control<?= invalid($errors, 'title') ?>"
                   value="<?= h($b['title']) ?>" maxlength="255" data-slug-source="blogSlug" required
                   placeholder="e.g. How Alarm Systems Improve Cold Chain Reliability">
            <?= fieldError($errors, 'title') ?>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="blogSlug">Slug <span class="text-danger">*</span></label>
            <input type="text" id="blogSlug" name="slug" class="form-control font-monospace<?= invalid($errors, 'slug') ?>"
                   value="<?= h($b['slug']) ?>" maxlength="150" required
                   placeholder="e.g. alarm-systems-cold-chain (auto-filled from the title)">
            <?= fieldError($errors, 'slug') ?: '<div class="form-text">Article URL: <code>/blog-details/slug</code> — changing it breaks links already shared.</div>' ?>
          </div>
          <div>
            <label class="form-label small fw-semibold" for="blogExcerpt">Excerpt <span class="text-danger">*</span></label>
            <textarea id="blogExcerpt" name="excerpt" rows="3" maxlength="500" class="form-control<?= invalid($errors, 'excerpt') ?>"
                      placeholder="1–2 sentence summary shown on blog cards, e.g. A well-designed alarm system catches the problem before the products do."><?= h($b['excerpt']) ?></textarea>
            <?= fieldError($errors, 'excerpt') ?>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Content <span class="text-danger">*</span></div>
        <div class="card-body">
          <div class="blog-editor<?= isset($errors['content']) ? ' border-danger' : '' ?>">
            <div class="blog-editor__toolbar btn-toolbar gap-1" role="toolbar" aria-label="Formatting">
              <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" data-block="h2" title="Heading">H2</button>
                <button type="button" class="btn btn-outline-secondary" data-block="h3" title="Subheading">H3</button>
                <button type="button" class="btn btn-outline-secondary" data-block="p" title="Paragraph"><i class="bi bi-paragraph"></i></button>
              </div>
              <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" data-cmd="bold" title="Bold"><i class="bi bi-type-bold"></i></button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="italic" title="Italic"><i class="bi bi-type-italic"></i></button>
              </div>
              <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" data-cmd="insertUnorderedList" title="Bulleted list"><i class="bi bi-list-ul"></i></button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="insertOrderedList" title="Numbered list"><i class="bi bi-list-ol"></i></button>
                <button type="button" class="btn btn-outline-secondary" data-block="blockquote" title="Quote"><i class="bi bi-quote"></i></button>
              </div>
              <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" data-link title="Insert link"><i class="bi bi-link-45deg"></i></button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="unlink" title="Remove link"><i class="bi bi-link"></i><i class="bi bi-x small"></i></button>
                <button type="button" class="btn btn-outline-secondary" data-image title="Insert image"><i class="bi bi-image"></i></button>
              </div>
              <div class="btn-group btn-group-sm ms-auto">
                <button type="button" class="btn btn-outline-secondary" data-source title="Edit HTML"><i class="bi bi-code-slash"></i></button>
              </div>
            </div>
            <div class="blog-editor__area" id="blogEditor" contenteditable="true"
                 data-placeholder="Write the article… Use H2 for section headings."><?= $editorHtml ?></div>
            <textarea class="blog-editor__source form-control font-monospace" id="blogSource" rows="16" hidden
                      placeholder="<h2>Section heading</h2><p>Paragraph text…</p>"></textarea>
          </div>
          <input type="hidden" name="content" id="blogContent">
          <?= isset($errors['content']) ? '<div class="text-danger small mt-1">' . h($errors['content']) . '</div>' : '' ?>
          <div class="form-text">Headings, paragraphs, bold/italic, lists, quotes, links and images. Scripts, styles and embeds are removed when saving.</div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">SEO</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="metaTitle">Meta Title</label>
            <input type="text" id="metaTitle" name="metaTitle" maxlength="200" class="form-control<?= invalid($errors, 'metaTitle') ?>"
                   value="<?= h($b['metaTitle']) ?>" placeholder="Defaults to “Post title | MarkEn Blog”">
            <?= fieldError($errors, 'metaTitle') ?>
          </div>
          <div>
            <label class="form-label small fw-semibold" for="metaDescription">Meta Description</label>
            <textarea id="metaDescription" name="metaDescription" rows="2" maxlength="300" class="form-control<?= invalid($errors, 'metaDescription') ?>"
                      placeholder="Up to 300 characters shown under the title in Google results — defaults to the excerpt"><?= h($b['metaDescription']) ?></textarea>
            <?= fieldError($errors, 'metaDescription') ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-4">
        <div class="card-header">Publish</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="blogStatus">Status</label>
            <select id="blogStatus" name="status" class="form-select<?= invalid($errors, 'status') ?>">
              <option value="draft"<?= $b['status'] === 'draft' ? ' selected' : '' ?>>Draft (hidden from website)</option>
              <option value="published"<?= $b['status'] === 'published' ? ' selected' : '' ?>>Published</option>
            </select>
            <?= fieldError($errors, 'status') ?>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="blogDate">Publication Date</label>
            <input type="date" id="blogDate" name="publishedAt" class="form-control<?= invalid($errors, 'publishedAt') ?>"
                   value="<?= h($b['publishedAt']) ?>" placeholder="e.g. 2026-10-12">
            <?= fieldError($errors, 'publishedAt') ?: '<div class="form-text">Blank = today when published. A future date keeps the post hidden until that day.</div>' ?>
          </div>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="featured" id="featured" value="1"<?= $b['featured'] ? ' checked' : '' ?>>
            <label class="form-check-label small" for="featured">Featured on the homepage</label>
          </div>
          <button type="submit" class="btn btn-dark w-100"><i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update' : 'Save' ?> Post</button>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Details</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="blogCat">Category <span class="text-danger">*</span></label>
            <select id="blogCat" name="categoryId" class="form-select<?= invalid($errors, 'categoryId') ?>" required>
              <option value="">— Select —</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>"<?= (string)$b['categoryId'] === (string)$c['id'] ? ' selected' : '' ?>><?= h($c['name']) ?><?= $c['isActive'] ? '' : ' (inactive)' ?></option>
              <?php endforeach; ?>
            </select>
            <?= fieldError($errors, 'categoryId') ?: (!$categories ? '<div class="form-text">No categories yet — <a href="blog-categories">create one</a> first.</div>' : '') ?>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="blogAuthor">Author <span class="text-danger">*</span></label>
            <input type="text" id="blogAuthor" name="authorName" maxlength="100" class="form-control<?= invalid($errors, 'authorName') ?>"
                   value="<?= h($b['authorName']) ?>" placeholder="e.g. MarkEn Team">
            <?= fieldError($errors, 'authorName') ?>
          </div>
          <div>
            <label class="form-label small fw-semibold" for="blogProduct">Related Product</label>
            <select id="blogProduct" name="relatedProductId" class="form-select<?= invalid($errors, 'relatedProductId') ?>">
              <option value="">— None (CTA shows “Make an Enquiry”) —</option>
              <?php foreach ($products as $pr): ?>
                <option value="<?= (int)$pr['id'] ?>"<?= (string)$b['relatedProductId'] === (string)$pr['id'] ? ' selected' : '' ?>><?= h($pr['name']) ?><?= $pr['status'] === 'draft' ? ' (draft)' : '' ?></option>
              <?php endforeach; ?>
            </select>
            <?= fieldError($errors, 'relatedProductId') ?: '<div class="form-text">Linked from the call-to-action under the article.</div>' ?>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Featured Image <span class="text-danger">*</span></div>
        <div class="card-body">
          <div class="drop-zone<?= isset($errors['featuredImage']) ? ' border-danger' : '' ?>" data-image-upload="blogImage">
            <?php if ($b['featuredImage']): ?>
              <img src="../<?= h($b['featuredImage']) ?>" alt=""><span class="small text-muted">Click or drag to replace</span>
            <?php else: ?>
              <i class="bi bi-cloud-arrow-up fs-2 text-muted"></i>
              <span class="small text-muted">Click to upload or drag &amp; drop</span>
              <span class="small text-muted">JPG, PNG, WebP — max 10 MB</span>
            <?php endif; ?>
          </div>
          <input type="hidden" id="blogImage" name="featuredImage" value="<?= h($b['featuredImage']) ?>">
          <?= isset($errors['featuredImage']) ? '<div class="text-danger small mt-1">' . h($errors['featuredImage']) . '</div>' : '' ?>
          <div class="form-text">Shown on blog cards (16:10) and as the article cover (21:9) — use a wide image.</div>
        </div>
      </div>
    </div>
  </div>
</form>

<?php
$pageScripts = <<<'HTML'
<script>
// Minimal rich-text editor (contenteditable + execCommand). The server sanitizes whatever is posted.
const editor = document.getElementById('blogEditor');
const source = document.getElementById('blogSource');
const sourceBtn = document.querySelector('[data-source]');
const toolbarBtns = document.querySelectorAll('.blog-editor__toolbar button:not([data-source])');
document.execCommand('defaultParagraphSeparator', false, 'p');

const exec = (cmd, val = null) => { editor.focus(); document.execCommand(cmd, false, val); };
document.querySelectorAll('[data-cmd]').forEach((b) => b.addEventListener('click', () => exec(b.dataset.cmd)));
document.querySelectorAll('[data-block]').forEach((b) => b.addEventListener('click', () => exec('formatBlock', `<${b.dataset.block}>`)));

document.querySelector('[data-link]').addEventListener('click', async () => {
  const sel = window.getSelection();
  const range = sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
  const { value: url } = await Swal.fire({
    title: 'Insert link', input: 'text', inputPlaceholder: 'https://example.com or products/medical-cold-chain',
    showCancelButton: true, confirmButtonColor: '#0a2342',
    inputValidator: (v) => (!v || /^\s*(javascript|data|vbscript):/i.test(v) ? 'Enter a web address or site path.' : undefined),
  });
  if (!url) return;
  editor.focus();
  if (range) { sel.removeAllRanges(); sel.addRange(range); }
  if (sel.isCollapsed) exec('insertHTML', `<a href="${escHtml(url.trim())}">${escHtml(url.trim())}</a>`);
  else exec('createLink', url.trim());
});

document.querySelector('[data-image]').addEventListener('click', () => {
  const sel = window.getSelection();
  const range = sel.rangeCount && editor.contains(sel.anchorNode) ? sel.getRangeAt(0).cloneRange() : null;
  const picker = Object.assign(document.createElement('input'), { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif' });
  picker.onchange = async () => {
    if (!picker.files[0]) return;
    try {
      const { url } = await uploadFile(picker.files[0]);
      editor.focus();
      if (range) { sel.removeAllRanges(); sel.addRange(range); }
      exec('insertHTML', `<img src="../${escHtml(url)}" alt=""><p><br></p>`);
    } catch (err) { showError(err.message); }
  };
  picker.click();
});

// Paste as plain text so Word/Google Docs styling doesn't come along.
editor.addEventListener('paste', (e) => {
  e.preventDefault();
  const text = (e.clipboardData || window.clipboardData).getData('text/plain');
  exec('insertHTML', text.split(/\n{2,}/).map((p) => `<p>${escHtml(p).replace(/\n/g, '<br>')}</p>`).join(''));
});

sourceBtn.addEventListener('click', () => {
  const toSource = source.hidden;
  if (toSource) source.value = editor.innerHTML; else editor.innerHTML = source.value;
  source.hidden = !toSource;
  editor.hidden = toSource;
  sourceBtn.classList.toggle('active', toSource);
  toolbarBtns.forEach((b) => (b.disabled = toSource));
});

// Images are shown via ../ inside the admin; store them site-root relative.
document.getElementById('blogForm').addEventListener('submit', () => {
  const html = source.hidden ? editor.innerHTML : source.value;
  document.getElementById('blogContent').value = html.replace(/(<img\b[^>]*\bsrc=")\.\.\//gi, '$1');
});
</script>
HTML;
include __DIR__ . '/includes/layout-bottom.php';
