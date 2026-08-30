<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_module('customers');
$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT * FROM customers WHERE id = :id");
$st->execute([':id' => $id]);
$c = $st->fetch();
if (!$c) { flash_set('error', 'Not found.'); redirect('/customers/index.php'); }

$orders = db()->prepare("SELECT * FROM orders WHERE customer_id = :id ORDER BY order_date DESC, id DESC LIMIT 20");
$orders->execute([':id' => $id]);
$orders = $orders->fetchAll();

$inv = db()->prepare("
    SELECT i.*, COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id = i.id),0) AS paid
    FROM invoices i WHERE customer_id = :id ORDER BY invoice_date DESC LIMIT 20
");
$inv->execute([':id' => $id]);
$invoices = $inv->fetchAll();

$pageTitle = $c['name'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1><?= e($c['name']) ?></h1>
        <p class="muted"><?= e(customer_type_label($c['type'])) ?> · <?= e($c['route'] ?: 'No route') ?> · <?= e($c['area'] ?: '') ?></p>
    </div>
    <div class="head-actions">
        <a class="btn" href="/customers/edit.php?id=<?= $id ?>">Edit</a>
        <?php if (user_has_module($user, 'orders')): ?>
            <a class="btn btn-primary" href="/orders/edit.php?customer_id=<?= $id ?>">+ Order</a>
        <?php endif; ?>
    </div>
</div>
<div class="card">
    <dl class="dl-grid">
        <div class="dl-row"><dt>Phone</dt><dd><?= e($c['phone'] ?: '—') ?></dd></div>
        <div class="dl-row"><dt>GSTIN</dt><dd><?= e($c['gstin'] ?: '—') ?></dd></div>
        <div class="dl-row"><dt>Address</dt><dd><?= e($c['address'] ?: '—') ?></dd></div>
        <div class="dl-row"><dt>Credit limit</dt><dd><?= e(inr((float)$c['credit_limit'], 2)) ?></dd></div>
    </dl>
</div>
<div class="card">
    <h2>Recent orders</h2>
    <?php if (!$orders): ?><p class="muted">None yet.</p>
    <?php else: ?>
    <table class="data-table">
        <thead><tr><th>#</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><a href="/orders/view.php?id=<?= (int)$o['id'] ?>">#<?= (int)$o['id'] ?></a></td>
                <td><?= e($o['order_date']) ?></td>
                <td><span class="pill st-<?= e($o['status']) ?>"><?= e(order_status_label($o['status'])) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<div class="card">
    <h2>Invoices</h2>
    <?php if (!$invoices): ?><p class="muted">None yet.</p>
    <?php else: ?>
    <table class="data-table">
        <thead><tr><th>No</th><th>Date</th><th>Due</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($invoices as $i):
            $due = (float)$i['amount'] + (float)$i['tax'] - (float)$i['paid']; ?>
            <tr>
                <td><a href="/invoices/view.php?id=<?= (int)$i['id'] ?>"><?= e($i['invoice_no']) ?></a></td>
                <td><?= e($i['invoice_date']) ?></td>
                <td class="num"><?= e(inr($due, 2)) ?></td>
                <td><span class="pill st-<?= e($i['status']) ?>"><?= e(invoice_status_label($i['status'])) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
