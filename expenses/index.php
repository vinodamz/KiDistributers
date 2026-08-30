<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_module('expenses');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'status' && $user['role'] === 'admin') {
    csrf_check();
    $st = $_POST['status'] ?? '';
    if (in_array($st, ['submitted','approved','rejected','paid'], true)) {
        db()->prepare("UPDATE expenses SET status=:s WHERE id=:id")->execute([':s'=>$st, ':id'=>(int)$_POST['id']]);
        flash_set('ok', 'Expense updated.');
    }
    redirect('/expenses/index.php');
}

$mine = $user['role'] !== 'admin';
$sql = "SELECT e.*, u.name AS who FROM expenses e JOIN users u ON u.id = e.user_id";
if ($mine) $sql .= " WHERE e.user_id = " . (int)$user['id'];
$sql .= " ORDER BY e.expense_date DESC, e.id DESC LIMIT 200";
$rows = db()->query($sql)->fetchAll();

$pageTitle = 'Expenses';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Expenses</h1><p class="muted">Fuel, travel, and other claims.</p></div>
    <div class="head-actions"><a class="btn btn-primary" href="/expenses/edit.php">+ Claim</a></div>
</div>
<?php if (!$rows): ?>
    <div class="empty"><p>No expenses yet.</p></div>
<?php else: ?>
<div class="table-scroll card">
<table class="data-table">
    <thead><tr><th>Date</th><th>Who</th><th>Category</th><th>Amount</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= e($r['expense_date']) ?></td>
            <td><?= e($r['who']) ?></td>
            <td><?= e($r['category']) ?><?php if ($r['merchant']): ?><div class="muted small"><?= e($r['merchant']) ?></div><?php endif; ?></td>
            <td class="num"><?= e(inr((float)$r['amount'], 2)) ?></td>
            <td><span class="pill st-<?= e($r['status'] === 'approved' || $r['status'] === 'paid' ? 'done' : 'open') ?>"><?= e($r['status']) ?></span></td>
            <td>
                <?php if ($user['role'] === 'admin'): ?>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="op" value="status">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <select name="status" onchange="this.form.submit()">
                            <?php foreach (['submitted','approved','rejected','paid'] as $s): ?>
                                <option value="<?= e($s) ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
