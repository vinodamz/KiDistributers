<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_module('invoices');
$id = (int)($_GET['id'] ?? 0);

$st = db()->prepare("SELECT i.*, c.name AS customer_name FROM invoices i JOIN customers c ON c.id = i.customer_id WHERE i.id = :id");
$st->execute([':id' => $id]);
$inv = $st->fetch();
if (!$inv) { flash_set('error', 'Not found.'); redirect('/invoices/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';
    if ($op === 'pay' && $inv['status'] !== 'cancelled') {
        $amt = (float)$_POST['amount'];
        $mode = $_POST['mode'] ?? 'cash';
        if (!in_array($mode, ['cash','upi','neft','cheque','other'], true)) $mode = 'cash';
        $on = $_POST['paid_on'] ?? date('Y-m-d');
        if ($amt <= 0) {
            flash_set('error', 'Amount must be positive.');
            redirect('/invoices/view.php?id=' . $id);
        }
        db()->prepare("INSERT INTO payments (invoice_id, paid_on, amount, mode, note, received_by)
            VALUES (:i,:d,:a,:m,:n,:u)")->execute([
            ':i'=>$id, ':d'=>$on, ':a'=>$amt, ':m'=>$mode,
            ':n'=>trim((string)$_POST['note']) ?: null, ':u'=>$user['id'],
        ]);
        refresh_invoice_status($id);
        flash_set('ok', 'Collection recorded.');
        redirect('/invoices/view.php?id=' . $id);
    }
}

$pays = db()->prepare("SELECT p.*, u.name AS who FROM payments p LEFT JOIN users u ON u.id = p.received_by WHERE invoice_id = :id ORDER BY p.id");
$pays->execute([':id' => $id]);
$pays = $pays->fetchAll();
$paid = invoice_paid($id);
$billed = (float)$inv['amount'] + (float)$inv['tax'];
$due = $billed - $paid;

$pageTitle = $inv['invoice_no'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1><?= e($inv['invoice_no']) ?></h1>
        <p class="muted"><a href="/customers/view.php?id=<?= (int)$inv['customer_id'] ?>"><?= e($inv['customer_name']) ?></a>
            · <?= e($inv['invoice_date']) ?>
            <?php if ($inv['order_id']): ?> · <a href="/orders/view.php?id=<?= (int)$inv['order_id'] ?>">Order #<?= (int)$inv['order_id'] ?></a><?php endif; ?>
        </p>
    </div>
    <span class="pill st-<?= e($inv['status']) ?>"><?= e(invoice_status_label($inv['status'])) ?></span>
</div>
<ul class="admin-tiles">
    <li class="admin-tile"><span class="tile-label">Billed</span><span class="tile-value"><?= e(inr($billed, 2)) ?></span></li>
    <li class="admin-tile tile-ok"><span class="tile-label">Collected</span><span class="tile-value"><?= e(inr($paid, 2)) ?></span></li>
    <li class="admin-tile <?= $due > 0.009 ? 'tile-warn' : '' ?>"><span class="tile-label">Due</span><span class="tile-value"><?= e(inr($due, 2)) ?></span></li>
</ul>
<div class="card">
    <h2>Payments</h2>
    <?php if (!$pays): ?><p class="muted">No collections yet.</p>
    <?php else: ?>
    <table class="data-table">
        <thead><tr><th>Date</th><th>Amount</th><th>Mode</th><th>Who</th></tr></thead>
        <tbody>
        <?php foreach ($pays as $p): ?>
            <tr>
                <td><?= e($p['paid_on']) ?></td>
                <td class="num"><?= e(inr((float)$p['amount'], 2)) ?></td>
                <td><?= e(strtoupper($p['mode'])) ?></td>
                <td><?= e($p['who'] ?? '') ?><?php if ($p['note']): ?> · <?= e($p['note']) ?><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php if ($inv['status'] !== 'cancelled' && $due > 0.009): ?>
<form method="post" class="card card-form">
    <?= csrf_field() ?>
    <input type="hidden" name="op" value="pay">
    <h2>Record collection</h2>
    <div class="grid-form">
        <label>Amount <input name="amount" type="number" step="0.01" required value="<?= e(number_format($due, 2, '.', '')) ?>"></label>
        <label>Date <input type="date" name="paid_on" value="<?= e(date('Y-m-d')) ?>"></label>
        <label>Mode
            <select name="mode">
                <?php foreach (['cash','upi','neft','cheque','other'] as $m): ?>
                    <option value="<?= e($m) ?>"><?= e(strtoupper($m)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Note <input name="note"></label>
    </div>
    <div class="form-actions"><button class="btn btn-primary">Record</button></div>
</form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
