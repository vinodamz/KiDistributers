<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_module('orders');
$id = (int)($_GET['id'] ?? 0);

$st = db()->prepare("SELECT o.*, c.name AS customer_name, c.phone, c.area, u.name AS taker
    FROM orders o JOIN customers c ON c.id = o.customer_id
    LEFT JOIN users u ON u.id = o.taken_by WHERE o.id = :id");
$st->execute([':id' => $id]);
$o = $st->fetch();
if (!$o) { flash_set('error', 'Not found.'); redirect('/orders/index.php'); }

$lines = db()->prepare("SELECT ol.*, p.sku, p.name AS product_name, p.unit
    FROM order_lines ol JOIN products p ON p.id = ol.product_id WHERE ol.order_id = :id");
$lines->execute([':id' => $id]);
$lines = $lines->fetchAll();
$totals = order_totals($id);

$del = db()->prepare("SELECT * FROM deliveries WHERE order_id = :id ORDER BY id DESC");
$del->execute([':id' => $id]);
$dels = $del->fetchAll();

$staff = db()->query("SELECT id, name FROM users WHERE active = 1 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';
    if ($op === 'status') {
        $s = $_POST['status'] ?? '';
        if (in_array($s, ['draft','confirmed','packed','cancelled'], true)) {
            db()->prepare("UPDATE orders SET status = :s WHERE id = :id")->execute([':s'=>$s, ':id'=>$id]);
            flash_set('ok', 'Status updated.');
        }
        redirect('/orders/view.php?id=' . $id);
    }
    if ($op === 'schedule' && user_has_module($user, 'deliveries')) {
        $date = $_POST['scheduled_date'] ?? '';
        $who = (int)($_POST['assigned_to'] ?? 0) ?: null;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            flash_set('error', 'Pick a delivery date.');
            redirect('/orders/view.php?id=' . $id);
        }
        db()->prepare("INSERT INTO deliveries (order_id, assigned_to, scheduled_date, status) VALUES (:o,:u,:d,'pending')")
            ->execute([':o'=>$id, ':u'=>$who, ':d'=>$date]);
        if ($o['status'] === 'draft') {
            db()->prepare("UPDATE orders SET status = 'confirmed' WHERE id = :id")->execute([':id'=>$id]);
        }
        flash_set('ok', 'Delivery scheduled.');
        redirect('/orders/view.php?id=' . $id);
    }
}

$pageTitle = 'Order #' . $id;
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Order #<?= $id ?></h1>
        <p class="muted"><a href="/customers/view.php?id=<?= (int)$o['customer_id'] ?>"><?= e($o['customer_name']) ?></a>
            · <?= e($o['order_date']) ?> · <?= e($o['taker'] ?? '') ?></p>
    </div>
    <div class="head-actions">
        <?php if (in_array($o['status'], ['draft','confirmed'], true)): ?>
            <a class="btn" href="/orders/edit.php?id=<?= $id ?>">Edit lines</a>
        <?php endif; ?>
        <span class="pill st-<?= e($o['status']) ?>"><?= e(order_status_label($o['status'])) ?></span>
    </div>
</div>

<div class="card">
    <table class="data-table">
        <thead><tr><th>SKU</th><th>Product</th><th>Qty</th><th>Price</th><th>GST</th><th>Amount</th></tr></thead>
        <tbody>
        <?php foreach ($lines as $ln):
            $amt = (float)$ln['qty'] * (float)$ln['unit_price']; ?>
            <tr>
                <td><?= e($ln['sku']) ?></td>
                <td><?= e($ln['product_name']) ?></td>
                <td class="num"><?= e(qty_fmt($ln['qty'])) ?> <?= e($ln['unit']) ?></td>
                <td class="num"><?= e(inr((float)$ln['unit_price'], 2)) ?></td>
                <td><?= e(qty_fmt($ln['gst_pct'])) ?>%</td>
                <td class="num"><?= e(inr($amt, 2)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="muted">Subtotal <?= e(inr($totals['subtotal'], 2)) ?> · GST <?= e(inr($totals['tax'], 2)) ?> ·
        <strong>Total <?= e(inr($totals['total'], 2)) ?></strong></p>
</div>

<?php if ($o['status'] !== 'delivered'): ?>
<div class="card">
    <h2>Status</h2>
    <form method="post" class="filter-row">
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="status">
        <div class="field"><label>Move to
            <select name="status">
                <?php foreach (['draft','confirmed','packed','cancelled'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= e(order_status_label($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </label></div>
        <div class="actions"><button class="btn">Update</button></div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h2>Deliveries</h2>
    <?php if ($dels): ?>
        <ul class="parent-list">
            <?php foreach ($dels as $d): ?>
                <li class="parent-row">
                    <span class="pill st-<?= e($d['status']) ?>"><?= e(delivery_status_label($d['status'])) ?></span>
                    <?= e($d['scheduled_date']) ?>
                    <?php if ($d['delivered_at']): ?> · done <?= e($d['delivered_at']) ?><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if (user_has_module($user, 'deliveries') && $o['status'] !== 'cancelled' && $o['status'] !== 'delivered'): ?>
        <form method="post" class="filter-row" style="margin-top:1rem">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="schedule">
            <div class="field"><label>Date <input type="date" name="scheduled_date" value="<?= e(date('Y-m-d')) ?>"></label></div>
            <div class="field"><label>Assign
                <select name="assigned_to">
                    <option value="">Anyone</option>
                    <?php foreach ($staff as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label></div>
            <div class="actions"><button class="btn btn-primary">Schedule delivery</button></div>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
