<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/hub.php';
$cfg  = app_config();
$user = current_user();
$v    = asset_version();
$unreadCount = $user ? unread_count((int)$user['id']) : 0;
$hubMode = !empty($hubMode);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#F4F7F8">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle ?? app_name()) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Quicksand:wght@500;600;700&display=swap">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= e($v) ?>">
</head>
<body<?= isset($bodyClass) ? ' class="' . e($bodyClass) . '"' : '' ?>>
<header class="topbar">
    <a href="<?= $hubMode ? '/apps/index.php' : '/index.php' ?>" class="brand">
        <img src="/assets/img/logo.png" alt="" class="brand-logo">
        <span class="brand-mark"><?= e($hubMode ? hub_name() : app_name()) ?></span>
    </a>
    <?php if ($user): ?>
        <nav>
            <?php if ($hubMode): ?>
                <a href="/apps/index.php">Apps</a>
                <a href="/index.php">Distribution</a>
            <?php elseif ($user['role'] === 'sales'): ?>
                <a href="/apps/index.php">Apps</a>
                <a href="/today.php">My Day</a>
                <?php if (user_has_module($user, 'orders')): ?>
                    <a href="/orders/index.php">Orders</a>
                <?php endif; ?>
                <?php if (user_has_module($user, 'deliveries')): ?>
                    <a href="/deliveries/index.php">Deliveries</a>
                <?php endif; ?>
                <details class="more-menu">
                    <summary>More ▾</summary>
                    <div class="more-menu-list">
                        <?php foreach (all_modules() as $mk => $mLabel): ?>
                            <?php if (user_has_module($user, $mk)): ?>
                                <a href="/<?= e($mk) ?>/index.php"><?= e($mLabel) ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <a href="/index.php?all=1">Distribution home</a>
                        <a href="/apps/index.php">All businesses</a>
                    </div>
                </details>
            <?php else: ?>
                <a href="/apps/index.php">Apps</a>
                <a href="/index.php">Home</a>
                <a href="/today.php">Today</a>
                <?php foreach (['orders' => 'Orders', 'deliveries' => 'Deliveries', 'stock' => 'Stock', 'invoices' => 'Invoices'] as $mk => $lab): ?>
                    <?php if (user_has_module($user, $mk)): ?>
                        <a href="/<?= e($mk) ?>/index.php"><?= e($lab) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <details class="more-menu">
                    <summary>More ▾</summary>
                    <div class="more-menu-list">
                        <?php foreach (['products' => 'Products', 'customers' => 'Customers', 'expenses' => 'Expenses', 'tasks' => 'Tasks'] as $mk => $lab): ?>
                            <?php if (user_has_module($user, $mk)): ?>
                                <a href="/<?= e($mk) ?>/index.php"><?= e($lab) ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($user['role'] === 'admin'): ?>
                            <a href="/admin.php">Admin</a>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>
            <a href="/notifications.php" class="bell" title="Notifications" aria-label="Notifications<?= $unreadCount > 0 ? ' (' . $unreadCount . ' unread)' : '' ?>">
                <span class="bell-icon" aria-hidden="true">🔔</span>
                <?php if ($unreadCount > 0): ?>
                    <span class="bell-badge"><?= $unreadCount > 99 ? '99+' : (int)$unreadCount ?></span>
                <?php endif; ?>
            </a>
            <span class="who" style="--card: <?= e(user_color((int)$user['id'])) ?>;">
                <span class="who-avatar"><?= e(user_initials($user['name'])) ?></span>
                <span>
                    <span class="who-name"><?= e(first_name($user['name'])) ?></span><br>
                    <span class="who-role"><?= e(role_label($user['role'])) ?></span>
                </span>
            </span>
            <a href="/logout.php" class="who-out">Log out</a>
        </nav>
    <?php endif; ?>
</header>
<main class="container">
<?php foreach (flash_get() as $fl): ?>
    <div class="flash flash-<?= $fl['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($fl['msg']) ?></div>
<?php endforeach; ?>
