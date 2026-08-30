<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/notify.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['op'] ?? '') === 'read_all') {
        db()->prepare("UPDATE notifications SET read_at = NOW() WHERE user_id = :u AND read_at IS NULL")
            ->execute([':u' => $user['id']]);
        redirect('/notifications.php');
    }
    if (($_POST['op'] ?? '') === 'read') {
        db()->prepare("UPDATE notifications SET read_at = NOW() WHERE id = :id AND user_id = :u")
            ->execute([':id' => (int)$_POST['id'], ':u' => $user['id']]);
        redirect('/notifications.php');
    }
}

$rows = db()->prepare("SELECT * FROM notifications WHERE user_id = :u ORDER BY id DESC LIMIT 50");
$rows->execute([':u' => $user['id']]);
$rows = $rows->fetchAll();

$pageTitle = 'Notifications';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div><h1>Notifications</h1></div>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="read_all">
        <button class="btn">Mark all read</button></form>
</div>
<?php if (!$rows): ?>
    <div class="empty"><p>No notifications yet.</p></div>
<?php else: ?>
<ul class="notif-list">
<?php foreach ($rows as $n): ?>
    <li class="notif-row <?= $n['read_at'] ? '' : 'is-unread' ?>">
        <div class="notif-body">
            <div class="notif-title">
                <?php if ($n['link']): ?><a href="<?= e($n['link']) ?>"><?= e($n['title']) ?></a>
                <?php else: ?><?= e($n['title']) ?><?php endif; ?>
            </div>
            <?php if ($n['body']): ?><div class="notif-text"><?= e($n['body']) ?></div><?php endif; ?>
            <div class="notif-meta muted small"><?= e($n['created_at']) ?></div>
        </div>
        <?php if (!$n['read_at']): ?>
            <form method="post"><?= csrf_field() ?>
                <input type="hidden" name="op" value="read">
                <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                <button class="btn small">Read</button>
            </form>
        <?php endif; ?>
    </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
