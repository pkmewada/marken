<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) redirect('products');

$errors   = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = post('username');
    $password = (string)($_POST['password'] ?? '');

    $errors = validate(['username' => $username, 'password' => $password], [
        'username' => 'required|max:100',
        'password' => 'required|max:200',
    ]);

    if (!$errors) {
        if (hash_equals(ADMIN_USER, $username) && password_verify($password, ADMIN_PASS_HASH)) {
            session_regenerate_id(true);
            $_SESSION['admin'] = $username;
            redirect('products');
        }
        usleep(400000);   // slow down brute-force attempts
        $errors['_form'] = 'Invalid username or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Login — MarkEn Admin</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/admin.css?v=1">
</head>
<body class="login-body">
<div class="login-box">
  <div class="text-center text-white mb-4">
    <div class="fs-4 fw-bold"><i class="bi bi-heart-pulse"></i> MarkEn</div>
    <div class="small text-white-50">Admin Panel</div>
  </div>
  <div class="card shadow-lg">
    <div class="card-body p-4">
      <h5 class="fw-bold mb-4">Sign in</h5>
      <?php if (isset($errors['_form'])): ?>
        <div class="alert alert-danger py-2 small"><?= h($errors['_form']) ?></div>
      <?php endif; ?>
      <form method="post" action="login" novalidate>
        <?= csrfField() ?>
        <div class="mb-3">
          <label class="form-label small fw-semibold" for="username">Username</label>
          <input type="text" id="username" name="username" class="form-control<?= invalid($errors, 'username') ?>"
                 value="<?= h($username) ?>" autocomplete="username" autofocus placeholder="Enter your username">
          <?= fieldError($errors, 'username') ?>
        </div>
        <div class="mb-4">
          <label class="form-label small fw-semibold" for="password">Password</label>
          <input type="password" id="password" name="password" class="form-control<?= invalid($errors, 'password') ?>"
                 autocomplete="current-password" placeholder="Enter your password">
          <?= fieldError($errors, 'password') ?>
        </div>
        <button type="submit" class="btn btn-dark w-100 fw-semibold">Sign in</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
