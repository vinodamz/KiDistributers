<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_module('orders');

$status = (string)($_GET['status'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$where = ['1=1']; $params = [];
if (in_array($status, ['draft','confirmed','packed','delivered','cancelled'], true)) {
    $where[] = 'o.status = :st'; $params[':st'] = $status;
}
if ($q !== '') {
    $where[] = '(c.name LIKE :q1 OR o.id = :qid)';
    $params[':q1'] = "%$q%";
    $params[':qid'] = (int)$q;
}
$sql = "SELECT o.*, c.name AS customer_name FROM orders o JOIN customers c ON c.id = o.customer_id
        WHERE " . implode(' AND ', $where) . " ORDER BY o.order_date DESC, o.id DESC LIMIT 200";
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$pageTitle = 'Orders';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Orders</h1><p class="muted">Book from the beat, pack at the godown.</p></div>
    <div class="head-actions"><a class="btn btn-primary" href="/orders/edit.php">+ New order</a></div>
</div>
<form class="filter-row" method="get">
    <div class="field"><label>Search</label><input name="q" value="<?= e($q) ?>" placeholder="Outlet or order #"></div>
    <div class="field"><label>Status
        <select name="status">
            <option value="">All</option>
            <?php foreach (['draft','confirmed','packed','delivered','cancelled'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(order_status_label($s)) ?></option>
            <?php endforeach; ?>
        </select>
    </label></div>
    <div class="actions"><button class="btn">Filter</button></div>
</form>
<?php if (!$rows): ?>
    <div class="empty"><p>No orders match.</p></div>
<?php else: ?>
<div class="table-scroll card">
<table class="data-table">
    <thead><tr><th>#</th><th>Date</th><th>Outlet</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><a href="/orders/view.php?id=<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></a></td>
            <td><?= e($r['order_date']) ?></td>
            <td><?= e($r['customer_name']) ?></td>
            <td><span class="pill st-<?= e($r['status']) ?>"><?= e(order_status_label($r['status'])) ?></span></td>
            <td><a class="btn small" href="/orders/view.php?id=<?= (int)$r['id'] ?>">Open</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
