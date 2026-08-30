<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_module('customers');

$id = (int)($_GET['id'] ?? 0);
$row = null;
if ($id) {
    $st = db()->prepare("SELECT * FROM customers WHERE id = :id");
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    if (!$row) { flash_set('error', 'Customer not found.'); redirect('/customers/index.php'); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim((string)$_POST['name']);
    $type = $_POST['type'] ?? 'retailer';
    if (!in_array($type, ['retailer','wholesaler','modern_trade','other'], true)) $type = 'retailer';
    if ($name === '') {
        flash_set('error', 'Name is required.');
        redirect('/customers/edit.php' . ($id ? "?id=$id" : ''));
    }
    $data = [
        ':name' => $name, ':type' => $type,
        ':phone' => trim((string)$_POST['phone']) ?: null,
        ':gstin' => trim((string)$_POST['gstin']) ?: null,
        ':area' => trim((string)$_POST['area']) ?: null,
        ':route' => trim((string)$_POST['route']) ?: null,
        ':address' => trim((string)$_POST['address']) ?: null,
        ':credit_limit' => (float)$_POST['credit_limit'],
        ':is_active' => empty($_POST['is_active']) ? 0 : 1,
        ':notes' => trim((string)$_POST['notes']) ?: null,
    ];
    if ($id) {
        db()->prepare("UPDATE customers SET name=:name, type=:type, phone=:phone, gstin=:gstin, area=:area, route=:route,
            address=:address, credit_limit=:credit_limit, is_active=:is_active, notes=:notes WHERE id=:id")
            ->execute($data + [':id' => $id]);
        flash_set('ok', 'Outlet saved.');
        redirect('/customers/view.php?id=' . $id);
    }
    db()->prepare("INSERT INTO customers (name,type,phone,gstin,area,route,address,credit_limit,is_active,notes)
        VALUES (:name,:type,:phone,:gstin,:area,:route,:address,:credit_limit,:is_active,:notes)")->execute($data);
    flash_set('ok', 'Outlet added.');
    redirect('/customers/index.php');
}

$pageTitle = $id ? 'Edit outlet' : 'New outlet';
require __DIR__ . '/../includes/header.php';
$r = $row ?? [];
?>
<div class="page-head"><h1><?= $id ? 'Edit outlet' : 'New outlet' ?></h1></div>
<form method="post" class="card card-form">
    <?= csrf_field() ?>
    <div class="grid-form">
        <label>Name <input name="name" required value="<?= e($r['name'] ?? '') ?>"></label>
        <label>Type
            <select name="type">
                <?php foreach (['retailer','wholesaler','modern_trade','other'] as $t): ?>
                    <option value="<?= e($t) ?>" <?= ($r['type'] ?? '') === $t ? 'selected' : '' ?>><?= e(customer_type_label($t)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Phone <input name="phone" value="<?= e($r['phone'] ?? '') ?>"></label>
        <label>GSTIN <input name="gstin" value="<?= e($r['gstin'] ?? '') ?>"></label>
        <label>Area <input name="area" value="<?= e($r['area'] ?? '') ?>"></label>
        <label>Route <input name="route" value="<?= e($r['route'] ?? '') ?>"></label>
        <label class="full">Address <input name="address" value="<?= e($r['address'] ?? '') ?>"></label>
        <label>Credit limit <input name="credit_limit" type="number" step="0.01" value="<?= e((string)($r['credit_limit'] ?? '0')) ?>"></label>
        <label class="check"><input type="checkbox" name="is_active" value="1" <?= ($r['is_active'] ?? 1) ? 'checked' : '' ?>> Active</label>
        <label class="full">Notes <textarea name="notes" rows="3"><?= e($r['notes'] ?? '') ?></textarea></label>
    </div>
    <div class="form-actions">
        <a class="btn" href="/customers/index.php">Cancel</a>
        <button class="btn btn-primary">Save</button>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
