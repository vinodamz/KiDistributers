<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_module('deliveries');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $op = $_POST['op'] ?? '';
    if ($op === 'out') {
        db()->prepare("UPDATE deliveries SET status='out' WHERE id=:id AND status='pending'")->execute([':id'=>$id]);
        flash_set('ok', 'Out for delivery.');
    } elseif ($op === 'done') {
        try {
            mark_delivery_done($id, $user);
            flash_set('ok', 'Delivered. Stock reduced and invoice raised.');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }
    } elseif ($op === 'fail') {
        db()->prepare("UPDATE deliveries SET status='failed' WHERE id=:id AND status IN ('pending','out')")->execute([':id'=>$id]);
        flash_set('ok', 'Marked failed.');
    }
    redirect('/deliveries/index.php?date=' . urlencode((string)($_POST['date'] ?? $_GET['date'] ?? date('Y-m-d'))));
}

$date = (string)($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

$st = db()->prepare("
    SELECT d.*, c.name AS customer_name, c.route, c.area, c.phone, o.status AS order_status
    FROM deliveries d
    JOIN orders o ON o.id = d.order_id
    JOIN customers c ON c.id = o.customer_id
    WHERE d.scheduled_date = :d
    ORDER BY FIELD(d.status,'out','pending','done','failed'), c.route, c.name
");
$st->execute([':d' => $date]);
$rows = $st->fetchAll();

$pageTitle = 'Deliveries';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Deliveries</h1><p class="muted">Van sheet for a day.</p></div>
</div>
<form class="filter-row" method="get">
    <div class="field"><label>Date <input type="date" name="date" value="<?= e($date) ?>"></label></div>
    <div class="actions"><button class="btn">Go</button></div>
</form>
<?php if (!$rows): ?>
    <div class="empty"><p>No drops on this date. Schedule from an order.</p></div>
<?php else: ?>
<div class="table-scroll card">
<table class="data-table">
    <thead><tr><th>Outlet</th><th>Route</th><th>Order</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td>
                <strong><?= e($r['customer_name']) ?></strong>
                <div class="muted small"><?= e($r['phone'] ?? '') ?></div>
            </td>
            <td><?= e($r['route'] ?: '—') ?><div class="muted small"><?= e($r['area'] ?? '') ?></div></td>
            <td><a href="/orders/view.php?id=<?= (int)$r['order_id'] ?>">#<?= (int)$r['order_id'] ?></a></td>
            <td><span class="pill st-<?= e($r['status']) ?>"><?= e(delivery_status_label($r['status'])) ?></span></td>
            <td class="row-actions">
                <?php if (in_array($r['status'], ['pending','out'], true)): ?>
                    <?php if ($r['status'] === 'pending'): ?>
                    <form method="post"><?= csrf_field() ?>
                        <input type="hidden" name="date" value="<?= e($date) ?>">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="op" value="out"><button class="btn small">Out</button></form>
                    <?php endif; ?>
                    <form method="post"><?= csrf_field() ?>
                        <input type="hidden" name="date" value="<?= e($date) ?>">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="op" value="done"><button class="btn btn-primary small">Delivered</button></form>
                    <form method="post"><?= csrf_field() ?>
                        <input type="hidden" name="date" value="<?= e($date) ?>">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="op" value="fail"><button class="btn small danger">Fail</button></form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
