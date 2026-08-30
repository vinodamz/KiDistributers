<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_module('stock');

$view = (string)($_GET['view'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$where = ['1=1']; $params = [];
if ($q !== '') {
    $where[] = '(name LIKE :q1 OR sku LIKE :q2)';
    $params[':q1'] = "%$q%"; $params[':q2'] = "%$q%";
}
if ($view === 'low') {
    $where[] = 'is_active = 1 AND reorder_level > 0 AND qty_on_hand <= reorder_level';
}
$st = db()->prepare("SELECT * FROM products WHERE " . implode(' AND ', $where) . " ORDER BY name");
$st->execute($params);
$rows = $st->fetchAll();

$moves = db()->query("
    SELECT m.*, p.sku, p.name AS product_name, u.name AS who
    FROM stock_moves m
    JOIN products p ON p.id = m.product_id
    LEFT JOIN users u ON u.id = m.created_by
    ORDER BY m.id DESC LIMIT 25
")->fetchAll();

$pageTitle = 'Stock';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Stock</h1><p class="muted">Godown on-hand vs reorder.</p></div>
    <div class="head-actions">
        <a class="btn <?= $view === 'low' ? 'btn-primary' : '' ?>" href="/stock/index.php?view=low">Low only</a>
        <a class="btn btn-primary" href="/stock/adjust.php">Adjust</a>
    </div>
</div>
<form class="filter-row" method="get">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <div class="field"><label>Search</label><input name="q" value="<?= e($q) ?>"></div>
    <div class="actions"><button class="btn">Filter</button></div>
</form>
<div class="table-scroll card">
<table class="data-table">
    <thead><tr><th>SKU</th><th>Name</th><th>On hand</th><th>Reorder</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $low = (float)$r['reorder_level'] > 0 && (float)$r['qty_on_hand'] <= (float)$r['reorder_level']; ?>
        <tr>
            <td><?= e($r['sku']) ?></td>
            <td><?= e($r['name']) ?><?php if ($low): ?> <span class="pill pill-warn">Low</span><?php endif; ?></td>
            <td class="num"><?= e(qty_fmt($r['qty_on_hand'])) ?> <?= e($r['unit']) ?></td>
            <td class="num"><?= e(qty_fmt($r['reorder_level'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<div class="card">
    <h2>Recent moves</h2>
    <?php if (!$moves): ?><p class="muted">No movements yet.</p>
    <?php else: ?>
    <table class="data-table">
        <thead><tr><th>When</th><th>SKU</th><th>Δ</th><th>Why</th><th>Who</th></tr></thead>
        <tbody>
        <?php foreach ($moves as $m): ?>
            <tr>
                <td><?= e($m['created_at']) ?></td>
                <td><?= e($m['sku']) ?></td>
                <td class="num"><?= (float)$m['qty_delta'] > 0 ? '+' : '' ?><?= e(qty_fmt($m['qty_delta'])) ?></td>
                <td><?= e($m['reason']) ?><?php if ($m['note']): ?> · <?= e($m['note']) ?><?php endif; ?></td>
                <td><?= e($m['who'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
