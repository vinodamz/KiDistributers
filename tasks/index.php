<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_module('tasks');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';
    if ($op === 'add') {
        $title = trim((string)$_POST['title']);
        if ($title === '') {
            flash_set('error', 'Title required.');
            redirect('/tasks/index.php');
        }
        db()->prepare("INSERT INTO task_items (title, due_date, assigned_to, notes, created_by)
            VALUES (:t,:d,:a,:n,:u)")->execute([
            ':t'=>$title,
            ':d'=>($_POST['due_date'] ?? '') ?: null,
            ':a'=>(int)($_POST['assigned_to'] ?? 0) ?: null,
            ':n'=>trim((string)$_POST['notes']) ?: null,
            ':u'=>$user['id'],
        ]);
        flash_set('ok', 'Task added.');
    } elseif ($op === 'status') {
        $st = $_POST['status'] ?? 'todo';
        if (in_array($st, ['todo','in_progress','done'], true)) {
            db()->prepare("UPDATE task_items SET status=:s WHERE id=:id")->execute([':s'=>$st, ':id'=>(int)$_POST['id']]);
        }
    }
    redirect('/tasks/index.php');
}

$rows = db()->query("
    SELECT t.*, u.name AS who FROM task_items t
    LEFT JOIN users u ON u.id = t.assigned_to
    ORDER BY t.status='done', t.due_date IS NULL, t.due_date, t.id DESC
")->fetchAll();
$staff = db()->query("SELECT id, name FROM users WHERE active = 1 ORDER BY name")->fetchAll();

$pageTitle = 'Tasks';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Tasks</h1><p class="muted">Follow-ups that are not an order.</p></div>
</div>
<form method="post" class="card card-form">
    <?= csrf_field() ?>
    <input type="hidden" name="op" value="add">
    <div class="grid-form">
        <label class="full">Title <input name="title" required placeholder="Call City Super Mart about overdue bill"></label>
        <label>Due <input type="date" name="due_date"></label>
        <label>Assign
            <select name="assigned_to">
                <option value="">Unassigned</option>
                <?php foreach ($staff as $s): ?>
                    <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="form-actions"><button class="btn btn-primary">Add task</button></div>
</form>
<?php if (!$rows): ?>
    <div class="empty"><p>No tasks.</p></div>
<?php else: ?>
<ul class="parent-list">
<?php foreach ($rows as $r): ?>
    <li class="parent-row">
        <div class="parent-name">
            <?= e($r['title']) ?>
            <span class="pill st-<?= $r['status'] === 'done' ? 'done' : 'open' ?>"><?= e(str_replace('_',' ', $r['status'])) ?></span>
        </div>
        <div class="parent-meta muted small">
            <?= e($r['who'] ?: 'Unassigned') ?>
            <?php if ($r['due_date']): ?> · due <?= e($r['due_date']) ?><?php endif; ?>
        </div>
        <form method="post" class="inline-form" style="margin-top:.4rem">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="status">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <select name="status" onchange="this.form.submit()">
                <?php foreach (['todo'=>'To do','in_progress'=>'In progress','done'=>'Done'] as $k=>$lab): ?>
                    <option value="<?= e($k) ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
