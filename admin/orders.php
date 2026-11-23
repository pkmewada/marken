<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// Matches the `orders.status` ENUM (app/schema.php). Orders are never deleted — cancel instead.
const ORDER_STATUSES = ['pending-payment' => 'Pending Payment', 'paid' => 'Paid', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id = (int)post('id');
    if (post('_action') === 'status' && $id > 0 && isset(ORDER_STATUSES[post('status')])) {
        dbRun('UPDATE `orders` SET status = ? WHERE id = ?', [post('status'), $id]);
        setFlash('success', 'Order status updated.');
    }
    redirect('orders');
}

$orders = dbAll('SELECT * FROM `orders` ORDER BY createdAt DESC');
$inr = fn($n) => '₹' . number_format((float)$n, 2);

$pageTitle  = 'Orders';
$pageActive = 'orders';
include __DIR__ . '/includes/layout-top.php';
?>
<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
    <span>All Orders (<?= count($orders) ?>)</span>
    <div class="d-flex gap-2">
      <select class="form-select form-select-sm" data-table-filter>
        <option value="">All statuses</option>
        <?php foreach (ORDER_STATUSES as $s => $label): ?><option value="<?= $s ?>"><?= $label ?></option><?php endforeach; ?>
      </select>
      <input type="search" class="form-control form-control-sm" placeholder="Search order no., name, email or product…" data-table-search>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" data-table>
      <thead class="table-light">
        <tr><th data-sort>Order</th><th data-sort>Customer</th><th data-sort>Product</th><th data-sort class="text-end">Total</th><th data-sort>Status</th><th data-sort>Placed</th><th class="text-end">Details</th></tr>
      </thead>
      <tbody>
        <?php if (!$orders): ?>
          <tr data-empty><td colspan="7" class="text-center text-muted py-4">No orders yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($orders as $o): $oid = (int)$o['id']; ?>
        <tr data-filter="<?= h($o['status']) ?>">
          <td class="fw-semibold text-nowrap"><?= h($o['orderNumber']) ?></td>
          <td>
            <div class="fw-semibold"><?= h($o['fullName']) ?></div>
            <div class="small"><a href="mailto:<?= h($o['email']) ?>"><?= h($o['email']) ?></a></div>
            <div class="small text-muted"><?= h($o['phone']) ?></div>
          </td>
          <td class="small">
            <div class="fw-semibold"><?= h($o['productName']) ?></div>
            <div class="text-muted"><?= h(implode(' · ', array_filter([$o['variant'], $o['color'], 'Qty ' . $o['quantity']]))) ?></div>
          </td>
          <td class="text-end text-nowrap" data-sort-value="<?= h($o['total']) ?>"><?= h($inr($o['total'])) ?></td>
          <td data-sort-value="<?= h($o['status']) ?>">
            <form method="post" class="d-inline">
              <?= csrfField() ?>
              <input type="hidden" name="_action" value="status">
              <input type="hidden" name="id" value="<?= $oid ?>">
              <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php foreach (ORDER_STATUSES as $s => $label): ?>
                  <option value="<?= $s ?>"<?= $o['status'] === $s ? ' selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td class="small text-muted" data-sort-value="<?= h($o['createdAt']) ?>"><?= h(date('d M Y, H:i', strtotime($o['createdAt']))) ?></td>
          <td class="text-end text-nowrap">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#order<?= $oid ?>" title="View delivery details"><i class="bi bi-eye"></i></button>
            <div class="collapse text-start mt-2" id="order<?= $oid ?>">
              <div class="bg-light rounded p-2 small" style="max-width:420px">
                <?php if ($o['company']): ?><div><strong>Company:</strong> <?= h($o['company']) ?></div><?php endif; ?>
                <?php if ($o['gstin']): ?><div><strong>GSTIN:</strong> <?= h($o['gstin']) ?></div><?php endif; ?>
                <div class="mt-1"><strong>Deliver to:</strong><br>
                  <?= h($o['address1']) ?><br>
                  <?php if ($o['address2']): ?><?= h($o['address2']) ?><br><?php endif; ?>
                  <?= h($o['city']) ?>, <?= h($o['state']) ?> – <?= h($o['pincode']) ?>
                </div>
                <div class="mt-1"><strong>Unit price:</strong> <?= h($inr($o['unitPrice'])) ?> × <?= (int)$o['quantity'] ?></div>
              </div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center small text-muted">
    <span data-table-info></span>
    <div class="d-flex gap-1" data-table-pager></div>
  </div>
</div>
<?php include __DIR__ . '/includes/layout-bottom.php'; ?>
