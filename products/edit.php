<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_module('products');

$id = (int)($_GET['id'] ?? 0);
$row = null;
if ($id) {
    $st = db()->prepare("SELECT * FROM products WHERE id = :id");
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    if (!$row) { flash_set('error', 'Product not found.'); redirect('/products/index.php'); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $sku = trim((string)$_POST['sku']);
    $name = trim((string)$_POST['name']);
    if ($sku === '' || $name === '') {
        flash_set('error', 'SKU and name are required.');
        redirect('/products/edit.php' . ($id ? "?id=$id" : ''));
    }
    $fields = [
        'sku' => $sku, 'name' => $name,
        'category' => trim((string)$_POST['category']) ?: 'General',
        'unit' => trim((string)$_POST['unit']) ?: 'pcs',
        'pack_size' => trim((string)$_POST['pack_size']) ?: null,
        'mrp' => (float)$_POST['mrp'],
        'trade_price' => (float)$_POST['trade_price'],
        'gst_pct' => (float)$_POST['gst_pct'],
        'reorder_level' => (float)$_POST['reorder_level'],
        'is_active' => empty($_POST['is_active']) ? 0 : 1,
        'notes' => trim((string)$_POST['notes']) ?: null,
    ];
    if ($id) {
        db()->prepare("UPDATE products SET sku=:sku, name=:name, category=:category, unit=:unit, pack_size=:pack_size,
            mrp=:mrp, trade_price=:trade_price, gst_pct=:gst_pct, reorder_level=:reorder_level, is_active=:is_active, notes=:notes
            WHERE id=:id")->execute($fields + [':id' => $id]);
        flash_set('ok', 'Product saved.');
        redirect('/products/index.php');
    }
    $opening = (float)($_POST['qty_on_hand'] ?? 0);
    db()->prepare("INSERT INTO products (sku,name,category,unit,pack_size,mrp,trade_price,gst_pct,qty_on_hand,reorder_level,is_active,notes)
        VALUES (:sku,:name,:category,:unit,:pack_size,:mrp,:trade_price,:gst_pct,0,:reorder_level,:is_active,:notes)")
        ->execute($fields);
    $newId = (int)db()->lastInsertId();
    if ($opening != 0.0) {
        apply_stock($newId, $opening, 'opening', $user, 'Opening stock');
    }
    flash_set('ok', 'Product added.');
    redirect('/products/index.php');
}

$pageTitle = ($id ? 'Edit product' : 'New product');
require __DIR__ . '/../includes/header.php';
$r = $row ?? [];
?>
<div class="page-head"><h1><?= $id ? 'Edit product' : 'New product' ?></h1></div>
<form method="post" class="card card-form">
    <?= csrf_field() ?>
    <div class="grid-form">
        <label>SKU <input name="sku" required value="<?= e($r['sku'] ?? '') ?>"></label>
        <label>Name <input name="name" required value="<?= e($r['name'] ?? '') ?>"></label>
        <label>Category <input name="category" value="<?= e($r['category'] ?? '') ?>"></label>
        <label>Unit <input name="unit" value="<?= e($r['unit'] ?? 'case') ?>"></label>
        <label>Pack size <input name="pack_size" value="<?= e($r['pack_size'] ?? '') ?>"></label>
        <label>MRP <input name="mrp" type="number" step="0.01" value="<?= e((string)($r['mrp'] ?? '0')) ?>"></label>
        <label>Trade price <input name="trade_price" type="number" step="0.01" value="<?= e((string)($r['trade_price'] ?? '0')) ?>"></label>
        <label>GST % <input name="gst_pct" type="number" step="0.01" value="<?= e((string)($r['gst_pct'] ?? '0')) ?>"></label>
        <label>Reorder level <input name="reorder_level" type="number" step="0.001" value="<?= e((string)($r['reorder_level'] ?? '0')) ?>"></label>
        <?php if (!$id): ?>
            <label>Opening stock <input name="qty_on_hand" type="number" step="0.001" value="0"></label>
        <?php endif; ?>
        <label class="check"><input type="checkbox" name="is_active" value="1" <?= ($r['is_active'] ?? 1) ? 'checked' : '' ?>> Active</label>
        <label class="full">Notes <textarea name="notes" rows="3"><?= e($r['notes'] ?? '') ?></textarea></label>
    </div>
    <div class="form-actions">
        <a class="btn" href="/products/index.php">Cancel</a>
        <button class="btn btn-primary">Save</button>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
