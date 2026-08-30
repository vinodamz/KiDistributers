<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_module('orders');

$id = (int)($_GET['id'] ?? 0);
$preCust = (int)($_GET['customer_id'] ?? 0);
$order = null; $lines = [];
if ($id) {
    $st = db()->prepare("SELECT * FROM orders WHERE id = :id");
    $st->execute([':id' => $id]);
    $order = $st->fetch();
    if (!$order) { flash_set('error', 'Order not found.'); redirect('/orders/index.php'); }
    if (!in_array($order['status'], ['draft','confirmed'], true)) {
        flash_set('error', 'Only draft/confirmed orders can be edited.');
        redirect('/orders/view.php?id=' . $id);
    }
    $lines = db()->prepare("SELECT * FROM order_lines WHERE order_id = :id");
    $lines->execute([':id' => $id]);
    $lines = $lines->fetchAll();
}

$customers = db()->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name")->fetchAll();
$products = db()->query("SELECT id, sku, name, trade_price, gst_pct, qty_on_hand FROM products WHERE is_active = 1 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $cid = (int)$_POST['customer_id'];
    $date = (string)$_POST['order_date'];
    $notes = trim((string)$_POST['notes']) ?: null;
    $status = $_POST['status'] ?? 'draft';
    if (!in_array($status, ['draft','confirmed'], true)) $status = 'draft';
    if ($cid <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        flash_set('error', 'Outlet and date are required.');
        redirect('/orders/edit.php' . ($id ? "?id=$id" : ''));
    }
    $pids = $_POST['product_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $prices = $_POST['unit_price'] ?? [];
    $built = [];
    foreach ($pids as $i => $pid) {
        $pid = (int)$pid;
        $qty = (float)($qtys[$i] ?? 0);
        if ($pid <= 0 || $qty <= 0) continue;
        $built[] = ['product_id' => $pid, 'qty' => $qty, 'unit_price' => (float)($prices[$i] ?? 0)];
    }
    if (!$built) {
        flash_set('error', 'Add at least one line.');
        redirect('/orders/edit.php' . ($id ? "?id=$id" : ''));
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($id) {
            $pdo->prepare("UPDATE orders SET customer_id=:c, order_date=:d, status=:s, notes=:n WHERE id=:id")
                ->execute([':c'=>$cid, ':d'=>$date, ':s'=>$status, ':n'=>$notes, ':id'=>$id]);
            $pdo->prepare("DELETE FROM order_lines WHERE order_id = :id")->execute([':id'=>$id]);
            $oid = $id;
        } else {
            $pdo->prepare("INSERT INTO orders (customer_id, taken_by, order_date, status, notes) VALUES (:c,:u,:d,:s,:n)")
                ->execute([':c'=>$cid, ':u'=>$user['id'], ':d'=>$date, ':s'=>$status, ':n'=>$notes]);
            $oid = (int)$pdo->lastInsertId();
        }
        $ins = $pdo->prepare("INSERT INTO order_lines (order_id, product_id, qty, unit_price, gst_pct) VALUES (:o,:p,:q,:pr,:g)");
        $gstLookup = [];
        foreach ($products as $p) $gstLookup[(int)$p['id']] = (float)$p['gst_pct'];
        foreach ($built as $ln) {
            $ins->execute([
                ':o'=>$oid, ':p'=>$ln['product_id'], ':q'=>$ln['qty'],
                ':pr'=>$ln['unit_price'], ':g'=>$gstLookup[$ln['product_id']] ?? 0,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash_set('error', 'Could not save: ' . $e->getMessage());
        redirect('/orders/edit.php' . ($id ? "?id=$id" : ''));
    }
    flash_set('ok', 'Order saved.');
    redirect('/orders/view.php?id=' . $oid);
}

$pageTitle = $id ? 'Edit order' : 'New order';
require __DIR__ . '/../includes/header.php';
$prodJson = json_encode($products, JSON_UNESCAPED_UNICODE);
?>
<div class="page-head"><h1><?= $id ? 'Edit order #' . $id : 'New order' ?></h1></div>
<form method="post" class="card card-form" id="orderForm">
    <?= csrf_field() ?>
    <div class="grid-form">
        <label>Outlet
            <select name="customer_id" required>
                <option value="">Choose…</option>
                <?php foreach ($customers as $c):
                    $sel = (int)($order['customer_id'] ?? $preCust) === (int)$c['id']; ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $sel ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Date <input type="date" name="order_date" required value="<?= e($order['order_date'] ?? date('Y-m-d')) ?>"></label>
        <label>Status
            <select name="status">
                <option value="draft" <?= ($order['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="confirmed" <?= ($order['status'] ?? '') === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
            </select>
        </label>
        <label class="full">Notes <input name="notes" value="<?= e($order['notes'] ?? '') ?>"></label>
    </div>
    <h3>Lines</h3>
    <div class="table-scroll">
    <table class="data-table" id="lines">
        <thead><tr><th>SKU</th><th>Qty</th><th>Trade</th><th></th></tr></thead>
        <tbody>
        <?php
        $seed = $lines ?: [['product_id'=>'','qty'=>'1','unit_price'=>'']];
        foreach ($seed as $ln): ?>
            <tr>
                <td>
                    <select name="product_id[]" class="prod">
                        <option value="">Choose…</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" data-price="<?= e((string)$p['trade_price']) ?>"
                                <?= (int)($ln['product_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                                <?= e($p['sku'] . ' — ' . $p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input class="qty-input" name="qty[]" type="number" step="0.001" min="0" value="<?= e((string)($ln['qty'] ?? '1')) ?>"></td>
                <td><input name="unit_price[]" type="number" step="0.01" value="<?= e((string)($ln['unit_price'] ?? '')) ?>"></td>
                <td><button type="button" class="btn small danger rm">×</button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p><button type="button" class="btn" id="addLine">+ Line</button></p>
    <div class="form-actions">
        <a class="btn" href="/orders/index.php">Cancel</a>
        <button class="btn btn-primary">Save order</button>
    </div>
</form>
<script>
(function(){
  const tbody = document.querySelector('#lines tbody');
  const tmpl = tbody.rows[0].cloneNode(true);
  tmpl.querySelectorAll('input').forEach(i => i.value = i.name === 'qty[]' ? '1' : '');
  tmpl.querySelector('select').selectedIndex = 0;
  document.getElementById('addLine').addEventListener('click', () => tbody.appendChild(tmpl.cloneNode(true)));
  tbody.addEventListener('click', e => {
    if (e.target.closest('.rm') && tbody.rows.length > 1) e.target.closest('tr').remove();
  });
  tbody.addEventListener('change', e => {
    const sel = e.target.closest('select.prod');
    if (!sel) return;
    const opt = sel.selectedOptions[0];
    const price = opt && opt.dataset.price;
    if (price) sel.closest('tr').querySelector('[name="unit_price[]"]').value = price;
  });
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
