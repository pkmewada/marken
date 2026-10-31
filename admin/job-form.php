<?php
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/app/company.php';
requireLogin();

$editSlug = is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '';
$id       = 0;
$errors   = [];

$j = [
    'title' => '', 'slug' => '', 'department' => '', 'location' => '', 'employmentType' => 'full-time',
    'experience' => '', 'description' => '', 'responsibilities' => '', 'qualifications' => '',
    'applicationInstructions' => '', 'deadline' => '', 'status' => 'draft',
];

// Lists are edited one item per line.
$lines = fn(string $text) => array_values(array_filter(array_map('trim', preg_split('/\R/', $text)), 'strlen'));

if ($editSlug !== '') {
    $row = dbOne('SELECT * FROM `jobs` WHERE slug = ?', [$editSlug]);
    if (!$row) {
        setFlash('error', 'Job opening not found.');
        redirect('jobs');
    }
    $id = (int)$row['id'];
    $row['responsibilities'] = implode("\n", fromJson($row['responsibilities']));
    $row['qualifications']   = implode("\n", fromJson($row['qualifications']));
    $j = array_merge($j, array_map(fn($v) => $v ?? '', array_intersect_key($row, $j)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    foreach (array_keys($j) as $k) $j[$k] = post($k);

    $errors = validate($j, [
        'title'                   => 'required|max:200',
        'slug'                    => 'required|slug|max:150',
        'department'              => 'required|max:100',
        'location'                => 'required|max:150',
        'experience'              => 'max:100',
        'description'             => 'required|max:10000',
        'responsibilities'        => 'required|max:10000',
        'qualifications'          => 'required|max:10000',
        'applicationInstructions' => 'max:3000',
    ]);
    if (!isset($errors['slug']) && dbOne('SELECT id FROM `jobs` WHERE slug = ? AND id <> ?', [$j['slug'], $id])) {
        $errors['slug'] = 'Another job opening already uses this slug.';
    }
    if (!isset(EMPLOYMENT_TYPES[$j['employmentType']])) $errors['employmentType'] = 'Select an employment type.';
    if (!in_array($j['status'], ['published', 'draft'], true)) $errors['status'] = 'Invalid status.';
    if ($j['deadline'] !== '') {
        $d = DateTime::createFromFormat('!Y-m-d', $j['deadline']);
        if (!$d || $d->format('Y-m-d') !== $j['deadline']) $errors['deadline'] = 'Enter a valid date.';
    }

    if (!$errors) {
        $vals = [
            $j['title'], $j['slug'], $j['department'], $j['location'], $j['employmentType'], $j['experience'] ?: null,
            $j['description'], toJson($lines($j['responsibilities'])), toJson($lines($j['qualifications'])),
            $j['applicationInstructions'] ?: null, $j['deadline'] ?: null, $j['status'],
        ];
        $cols = 'title = ?, slug = ?, department = ?, location = ?, employmentType = ?, experience = ?, description = ?,
                 responsibilities = ?, qualifications = ?, applicationInstructions = ?, deadline = ?, status = ?';
        if ($id > 0) {
            dbRun("UPDATE `jobs` SET $cols WHERE id = ?", [...$vals, $id]);
            setFlash('success', "Job opening \"{$j['title']}\" updated.");
        } else {
            dbRun("INSERT INTO `jobs` SET $cols", $vals);
            setFlash('success', "Job opening \"{$j['title']}\" created.");
        }
        redirect('jobs');
    }
}

$departments   = array_column(dbAll('SELECT DISTINCT department FROM `jobs` ORDER BY department'), 'department');
$isEdit        = $id > 0;
$action        = $isEdit ? 'jobs/edit/' . rawurlencode($editSlug) : 'jobs/new';
$pageTitle     = $isEdit ? 'Edit Job Opening' : 'Add Job Opening';
$pageActive    = 'jobs';
$topbarActions = '<a href="jobs" class="btn btn-outline-secondary btn-sm">Cancel</a>'
               . '<button type="submit" form="jobForm" class="btn btn-dark btn-sm"><i class="bi bi-check-lg me-1"></i>'
               . ($isEdit ? 'Update' : 'Save') . ' Job</button>';
include __DIR__ . '/includes/layout-top.php';
?>
<?php if ($errors): ?>
  <div class="alert alert-danger small">Please fix the highlighted fields below.</div>
<?php endif; ?>

<form method="post" action="<?= h($action) ?>" id="jobForm" novalidate>
  <?= csrfField() ?>
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header">Job</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="jobTitle">Job Title <span class="text-danger">*</span></label>
            <input type="text" id="jobTitle" name="title" class="form-control<?= invalid($errors, 'title') ?>" maxlength="200"
                   value="<?= h($j['title']) ?>" data-slug-source="jobSlug" placeholder="e.g. Service Engineer – Cold Chain">
            <?= fieldError($errors, 'title') ?>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="jobSlug">Slug <span class="text-danger">*</span></label>
            <input type="text" id="jobSlug" name="slug" class="form-control font-monospace<?= invalid($errors, 'slug') ?>" maxlength="150"
                   value="<?= h($j['slug']) ?>" placeholder="e.g. service-engineer-cold-chain (auto-filled from the title)">
            <?= fieldError($errors, 'slug') ?: '<div class="form-text">Job page URL: <code>/careers/slug</code></div>' ?>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="jobDesc">Job Description <span class="text-danger">*</span></label>
            <textarea id="jobDesc" name="description" rows="6" maxlength="10000" class="form-control<?= invalid($errors, 'description') ?>"
                      placeholder="About the role, the team and what success looks like. Separate paragraphs with a blank line."><?= h($j['description']) ?></textarea>
            <?= fieldError($errors, 'description') ?>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="jobResp">Responsibilities <span class="text-danger">*</span></label>
            <textarea id="jobResp" name="responsibilities" rows="6" maxlength="10000" class="form-control<?= invalid($errors, 'responsibilities') ?>"
                      placeholder="One per line, e.g.&#10;Install and commission cold chain equipment at customer sites&#10;Carry out preventive maintenance visits"><?= h($j['responsibilities']) ?></textarea>
            <?= fieldError($errors, 'responsibilities') ?: '<div class="form-text">One responsibility per line — shown as a bulleted list.</div>' ?>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="jobQual">Required Skills &amp; Qualifications <span class="text-danger">*</span></label>
            <textarea id="jobQual" name="qualifications" rows="6" maxlength="10000" class="form-control<?= invalid($errors, 'qualifications') ?>"
                      placeholder="One per line, e.g.&#10;Diploma / B.E. in Mechanical or Electrical Engineering&#10;Working knowledge of refrigeration systems"><?= h($j['qualifications']) ?></textarea>
            <?= fieldError($errors, 'qualifications') ?: '<div class="form-text">One item per line — shown as a bulleted list.</div>' ?>
          </div>
          <div>
            <label class="form-label small fw-semibold" for="jobInstr">Application Instructions</label>
            <textarea id="jobInstr" name="applicationInstructions" rows="3" maxlength="3000" class="form-control<?= invalid($errors, 'applicationInstructions') ?>"
                      placeholder="e.g. Attach your latest resume and mention your notice period in the cover letter."><?= h($j['applicationInstructions']) ?></textarea>
            <?= fieldError($errors, 'applicationInstructions') ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-4">
        <div class="card-header">Publish</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="jobStatus">Status</label>
            <select id="jobStatus" name="status" class="form-select<?= invalid($errors, 'status') ?>">
              <option value="draft"<?= $j['status'] === 'draft' ? ' selected' : '' ?>>Draft (hidden from website)</option>
              <option value="published"<?= $j['status'] === 'published' ? ' selected' : '' ?>>Published</option>
            </select>
            <?= fieldError($errors, 'status') ?>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="jobDeadline">Application Deadline</label>
            <input type="date" id="jobDeadline" name="deadline" class="form-control<?= invalid($errors, 'deadline') ?>"
                   value="<?= h($j['deadline']) ?>" placeholder="e.g. 2026-12-31">
            <?= fieldError($errors, 'deadline') ?: '<div class="form-text">Blank = open until unpublished. After this date the job is hidden and applications close.</div>' ?>
          </div>
          <button type="submit" class="btn btn-dark w-100"><i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update' : 'Save' ?> Job</button>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Details</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="jobDept">Department <span class="text-danger">*</span></label>
            <input type="text" id="jobDept" name="department" list="deptOptions" maxlength="100" class="form-control<?= invalid($errors, 'department') ?>"
                   value="<?= h($j['department']) ?>" placeholder="e.g. Service & Support">
            <datalist id="deptOptions"><?php foreach ($departments as $d): ?><option value="<?= h($d) ?>"><?php endforeach; ?></datalist>
            <?= fieldError($errors, 'department') ?>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="jobLocation">Location <span class="text-danger">*</span></label>
            <input type="text" id="jobLocation" name="location" maxlength="150" class="form-control<?= invalid($errors, 'location') ?>"
                   value="<?= h($j['location']) ?>" placeholder="e.g. Nashik, Maharashtra">
            <?= fieldError($errors, 'location') ?>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="jobType">Employment Type <span class="text-danger">*</span></label>
            <select id="jobType" name="employmentType" class="form-select<?= invalid($errors, 'employmentType') ?>">
              <?php foreach (EMPLOYMENT_TYPES as $val => $label): ?>
                <option value="<?= $val ?>"<?= $j['employmentType'] === $val ? ' selected' : '' ?>><?= h($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?= fieldError($errors, 'employmentType') ?>
          </div>
          <div>
            <label class="form-label small fw-semibold" for="jobExp">Experience</label>
            <input type="text" id="jobExp" name="experience" maxlength="100" class="form-control<?= invalid($errors, 'experience') ?>"
                   value="<?= h($j['experience']) ?>" placeholder="e.g. 2–5 years">
            <?= fieldError($errors, 'experience') ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>
<?php include __DIR__ . '/includes/layout-bottom.php'; ?>
