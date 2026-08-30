<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_module('stock');

$products = db()->query("SELECT id, sku, name, qty_on_hand, unit FROM products WHERE is_active = 1 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pid = (int)$_POST['product_id'];
    $delta = (float)$_POST['qty_delta'];
    $reason = $_POST['reason'] ?? 'adjustment';
    if (!in_array($reason, ['purchase','adjustment','return'], true)) $reason = 'adjustment';
    $note = trim((string)$_POST['note']) ?: null;
    if ($pid <= 0 || $delta == 0.0) {
        flash_set('error', 'Pick a product and a non-zero quantity.');
        redirect('/stock/adjust.php');
    }
    apply_stock($pid, $delta, $reason, $user, $note ?? '');
    flash_set('ok', 'Stock updated.');
    redirect('/stock/index.php');
}

$pageTitle = 'Stock adjustment';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Adjust stock</h1></div>
<form method="post" class="card card-form">
    <?= csrf_field() ?>
    <div class="grid-form">
        <label class="full">Product
            <select name="product_id" required>
                <option value="">Choose…</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"><?= e($p['sku'] . ' — ' . $p['name'] . ' (' . qty_fmt($p['qty_on_hand']) . ' ' . $p['unit'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Quantity change <input name="qty_delta" type="number" step="0.001" required placeholder="+10 or -2"></label>
        <label>Reason
            <select name="reason">
                <option value="purchase">Purchase / inward</option>
                <option value="adjustment">Adjustment</option>
                <option value="return">Return inward</option>
            </select>
        </label>
        <label class="full">Note <input name="note"></label>
    </div>
    <div class="form-actions">
        <a class="btn" href="/stock/index.php">Cancel</a>
        <button class="btn btn-primary">Apply</button>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
