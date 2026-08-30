<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_module('products');

$q = trim((string)($_GET['q'] ?? ''));
$where = '1=1'; $params = [];
if ($q !== '') {
    $where = '(name LIKE :q1 OR sku LIKE :q2 OR category LIKE :q3)';
    $params = [':q1' => "%$q%", ':q2' => "%$q%", ':q3' => "%$q%"];
}
$st = db()->prepare("SELECT * FROM products WHERE $where ORDER BY is_active DESC, name");
$st->execute($params);
$rows = $st->fetchAll();

$pageTitle = 'Products';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Products</h1><p class="muted">Catalog used on orders and stock.</p></div>
    <div class="head-actions"><a class="btn btn-primary" href="/products/edit.php">+ Add SKU</a></div>
</div>
<form class="filter-row" method="get">
    <div class="field"><label>Search</label><input name="q" value="<?= e($q) ?>" placeholder="Name, SKU, category"></div>
    <div class="actions"><button class="btn">Filter</button></div>
</form>
<?php if (!$rows): ?>
    <div class="empty"><p>No products yet.</p></div>
<?php else: ?>
<div class="table-scroll card">
<table class="data-table">
    <thead><tr><th>SKU</th><th>Name</th><th>Category</th><th>Trade</th><th>GST</th><th>Stock</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr class="<?= $r['is_active'] ? '' : 'is-inactive' ?>">
            <td><?= e($r['sku']) ?></td>
            <td><?= e($r['name']) ?><?php if ((float)$r['reorder_level'] > 0 && (float)$r['qty_on_hand'] <= (float)$r['reorder_level']): ?> <span class="pill pill-warn">Low</span><?php endif; ?></td>
            <td><?= e($r['category']) ?></td>
            <td class="num"><?= e(inr((float)$r['trade_price'], 2)) ?></td>
            <td><?= e(qty_fmt($r['gst_pct'])) ?>%</td>
            <td class="num"><?= e(qty_fmt($r['qty_on_hand'])) ?> <?= e($r['unit']) ?></td>
            <td><a class="btn small" href="/products/edit.php?id=<?= (int)$r['id'] ?>">Edit</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
