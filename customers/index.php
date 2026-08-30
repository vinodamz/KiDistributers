<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_module('customers');

$q = trim((string)($_GET['q'] ?? ''));
$where = '1=1'; $params = [];
if ($q !== '') {
    $where = '(name LIKE :q1 OR phone LIKE :q2 OR area LIKE :q3 OR route LIKE :q4)';
    $params = [':q1'=>"%$q%", ':q2'=>"%$q%", ':q3'=>"%$q%", ':q4'=>"%$q%"];
}
$st = db()->prepare("SELECT * FROM customers WHERE $where ORDER BY is_active DESC, name");
$st->execute($params);
$rows = $st->fetchAll();

$pageTitle = 'Customers';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Customers</h1><p class="muted">Outlets on the beat.</p></div>
    <div class="head-actions"><a class="btn btn-primary" href="/customers/edit.php">+ Add outlet</a></div>
</div>
<form class="filter-row" method="get">
    <div class="field"><label>Search</label><input name="q" value="<?= e($q) ?>" placeholder="Name, phone, area, route"></div>
    <div class="actions"><button class="btn">Filter</button></div>
</form>
<?php if (!$rows): ?>
    <div class="empty"><p>No customers yet.</p></div>
<?php else: ?>
<ul class="student-grid">
<?php foreach ($rows as $r): ?>
    <li class="student-card <?= $r['is_active'] ? '' : 'is-inactive' ?>" style="--card: <?= e(user_color((int)$r['id'])) ?>">
        <div class="student-head">
            <span class="student-avatar"><?= e(user_initials($r['name'])) ?></span>
            <div>
                <div class="student-name"><a href="/customers/view.php?id=<?= (int)$r['id'] ?>"><?= e($r['name']) ?></a></div>
                <div class="muted small"><?= e(customer_type_label($r['type'])) ?> · <?= e($r['route'] ?: 'No route') ?></div>
            </div>
        </div>
        <div class="student-status">
            <span><?= e($r['area'] ?: '—') ?></span>
            <?php if ($r['phone']): ?><span><?= e($r['phone']) ?></span><?php endif; ?>
        </div>
        <div class="student-actions">
            <a class="btn" href="/customers/view.php?id=<?= (int)$r['id'] ?>">View</a>
            <a class="btn" href="/customers/edit.php?id=<?= (int)$r['id'] ?>">Edit</a>
            <?php if (user_has_module($user, 'orders')): ?>
                <a class="btn btn-primary" href="/orders/edit.php?customer_id=<?= (int)$r['id'] ?>">Order</a>
            <?php endif; ?>
        </div>
    </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
