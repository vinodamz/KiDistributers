<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_module('invoices');

$status = (string)($_GET['status'] ?? '');
$where = ['1=1']; $params = [];
if (in_array($status, ['open','partial','paid','cancelled'], true)) {
    $where[] = 'i.status = :st'; $params[':st'] = $status;
}
$st = db()->prepare("
    SELECT i.*, c.name AS customer_name,
           COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id = i.id),0) AS paid
    FROM invoices i JOIN customers c ON c.id = i.customer_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY i.invoice_date DESC, i.id DESC LIMIT 200
");
$st->execute($params);
$rows = $st->fetchAll();

$pageTitle = 'Invoices';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Invoices</h1><p class="muted">Bills raised on delivery. Record collections here.</p></div>
</div>
<form class="filter-row" method="get">
    <div class="field"><label>Status
        <select name="status">
            <option value="">All</option>
            <?php foreach (['open','partial','paid','cancelled'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(invoice_status_label($s)) ?></option>
            <?php endforeach; ?>
        </select>
    </label></div>
    <div class="actions"><button class="btn">Filter</button></div>
</form>
<?php if (!$rows): ?>
    <div class="empty"><p>No invoices yet. Completing a delivery raises one.</p></div>
<?php else: ?>
<div class="table-scroll card">
<table class="data-table">
    <thead><tr><th>Invoice</th><th>Outlet</th><th>Date</th><th>Due</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $due = (float)$r['amount'] + (float)$r['tax'] - (float)$r['paid']; ?>
        <tr>
            <td><a href="/invoices/view.php?id=<?= (int)$r['id'] ?>"><?= e($r['invoice_no']) ?></a></td>
            <td><?= e($r['customer_name']) ?></td>
            <td><?= e($r['invoice_date']) ?></td>
            <td class="num"><?= e(inr($due, 2)) ?></td>
            <td><span class="pill st-<?= e($r['status']) ?>"><?= e(invoice_status_label($r['status'])) ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
