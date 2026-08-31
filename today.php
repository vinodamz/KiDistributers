<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';
    if ($op === 'mark_out' && user_has_module($user, 'deliveries')) {
        db()->prepare("UPDATE deliveries SET status = 'out' WHERE id = :id AND status = 'pending'")
            ->execute([':id' => (int)$_POST['id']]);
        flash_set('ok', 'Marked out for delivery.');
        redirect('/today.php');
    }
    if ($op === 'mark_done' && user_has_module($user, 'deliveries')) {
        try {
            mark_delivery_done((int)$_POST['id'], $user);
            flash_set('ok', 'Delivered — stock and invoice updated.');
        } catch (Throwable $e) {
            flash_set('error', 'Could not complete delivery: ' . $e->getMessage());
        }
        redirect('/today.php');
    }
}

$del = db()->prepare("
    SELECT d.*, o.status AS order_status, c.name AS customer_name, c.area, c.route, c.phone
    FROM deliveries d
    JOIN orders o ON o.id = d.order_id
    JOIN customers c ON c.id = o.customer_id
    WHERE d.scheduled_date = :d AND d.status IN ('pending','out')
    ORDER BY c.route, c.area, c.name
");
$del->execute([':d' => $today]);
$drops = $del->fetchAll();

$openInv = db()->query("
    SELECT i.*, c.name AS customer_name,
           (i.amount + i.tax) AS billed,
           COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id = i.id), 0) AS paid
    FROM invoices i
    JOIN customers c ON c.id = i.customer_id
    WHERE i.status IN ('open','partial')
    ORDER BY i.invoice_date
    LIMIT 8
")->fetchAll();

$low = db()->query("
    SELECT * FROM products
    WHERE is_active = 1 AND reorder_level > 0 AND qty_on_hand <= reorder_level
    ORDER BY qty_on_hand ASC
    LIMIT 8
")->fetchAll();

$pageTitle = 'My Day — ' . app_name();
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>My Day</h1>
        <p class="muted"><?= e(date('l, j M')) ?> · Today’s route and collections</p>
    </div>
    <div class="head-actions">
        <?php if (user_has_module($user, 'orders')): ?>
            <a class="btn btn-primary" href="/orders/edit.php">+ New order</a>
        <?php endif; ?>
        <a class="btn" href="/apps/index.php">All businesses</a>
        <a class="btn" href="/index.php?all=1">Distribution</a>
    </div>
</div>

<ul class="admin-tiles">
    <li class="admin-tile tile-warn">
        <span class="tile-label">Drops left</span>
        <span class="tile-value"><?= count($drops) ?></span>
    </li>
    <li class="admin-tile">
        <span class="tile-label">Open bills</span>
        <span class="tile-value"><?= count($openInv) ?></span>
        <span class="tile-sub">showing first 8</span>
    </li>
    <li class="admin-tile <?= $low ? 'tile-warn' : 'tile-ok' ?>">
        <span class="tile-label">Reorder</span>
        <span class="tile-value"><?= count($low) ?></span>
    </li>
</ul>

<div class="card">
    <div class="card-head">
        <h2>Today’s deliveries</h2>
        <a href="/deliveries/index.php">All deliveries</a>
    </div>
    <?php if (!$drops): ?>
        <div class="empty"><p>Nothing on the van for today.</p></div>
    <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Outlet</th><th>Route</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($drops as $d): ?>
                <tr>
                    <td>
                        <strong><?= e($d['customer_name']) ?></strong>
                        <div class="muted small"><?= e($d['phone'] ?? '') ?> · Order #<?= (int)$d['order_id'] ?></div>
                    </td>
                    <td><?= e($d['route'] ?: '—') ?><div class="muted small"><?= e($d['area'] ?? '') ?></div></td>
                    <td><span class="pill st-<?= e($d['status']) ?>"><?= e(delivery_status_label($d['status'])) ?></span></td>
                    <td class="row-actions">
                        <?php if ($d['status'] === 'pending'): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="op" value="mark_out">
                                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                <button class="btn small">Out</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($d['status'] !== 'done'): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="op" value="mark_done">
                                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                <button class="btn btn-primary small">Delivered</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head">
        <h2>Collections</h2>
        <a href="/invoices/index.php">Invoices</a>
    </div>
    <?php if (!$openInv): ?>
        <div class="empty"><p>No open invoices.</p></div>
    <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Invoice</th><th>Outlet</th><th>Due</th></tr></thead>
            <tbody>
            <?php foreach ($openInv as $i):
                $due = (float)$i['billed'] - (float)$i['paid'];
            ?>
                <tr>
                    <td><a href="/invoices/view.php?id=<?= (int)$i['id'] ?>"><?= e($i['invoice_no']) ?></a></td>
                    <td><?= e($i['customer_name']) ?></td>
                    <td class="num"><?= e(inr($due, 2)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($low): ?>
<div class="card">
    <div class="card-head"><h2>Low stock</h2><a href="/stock/index.php">Stock</a></div>
    <ul class="parent-list">
        <?php foreach ($low as $p): ?>
            <li class="parent-row">
                <span class="parent-name"><?= e($p['name']) ?></span>
                <span class="muted small"><?= e($p['sku']) ?> · <?= e(qty_fmt($p['qty_on_hand'])) ?> <?= e($p['unit']) ?> left (reorder <?= e(qty_fmt($p['reorder_level'])) ?>)</span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
