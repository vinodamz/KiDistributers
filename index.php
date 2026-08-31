<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/notify.php';

$user = require_login();

if ($user['role'] === 'sales' && !isset($_GET['all'])) {
    redirect('/today.php');
}

$stats = [
    'products'   => 0, 'low' => 0,
    'customers'  => 0,
    'open_orders'=> 0,
    'today_del'  => 0,
    'open_inv'   => 0.0,
    'tasks'      => 0,
];
try {
    $stats['products']    = (int)db()->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();
    $stats['low']         = (int)db()->query("SELECT COUNT(*) FROM products WHERE is_active = 1 AND reorder_level > 0 AND qty_on_hand <= reorder_level")->fetchColumn();
    $stats['customers']   = (int)db()->query("SELECT COUNT(*) FROM customers WHERE is_active = 1")->fetchColumn();
    $stats['open_orders'] = (int)db()->query("SELECT COUNT(*) FROM orders WHERE status IN ('draft','confirmed','packed')")->fetchColumn();
    $st = db()->prepare("SELECT COUNT(*) FROM deliveries WHERE scheduled_date = :d AND status IN ('pending','out')");
    $st->execute([':d' => date('Y-m-d')]);
    $stats['today_del'] = (int)$st->fetchColumn();
    $stats['open_inv']   = (float)db()->query("
        SELECT COALESCE(SUM(i.amount + i.tax) - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id = i.id), 0), 0)
        FROM invoices i WHERE i.status IN ('open','partial')
    ")->fetchColumn();
    $stats['tasks'] = (int)db()->query("SELECT COUNT(*) FROM task_items WHERE status <> 'done'")->fetchColumn();
} catch (Throwable $e) { /* pre-install */ }

$apps = [];
$catalog = [
    ['key' => 'hub',       'mod' => null,          'name' => 'All businesses', 'subtitle' => 'Tools · other companies', 'href' => '/apps/index.php'],
    ['key' => 'today',      'mod' => null,          'name' => 'My Day',     'subtitle' => 'Route · Drops · Collections', 'href' => '/today.php'],
    ['key' => 'products',   'mod' => 'products',     'name' => 'Products',    'subtitle' => 'SKU · Price · GST',          'href' => '/products/index.php'],
    ['key' => 'customers',   'mod' => 'customers',    'name' => 'Customers',   'subtitle' => 'Outlets · Routes · Credit',   'href' => '/customers/index.php'],
    ['key' => 'orders',     'mod' => 'orders',       'name' => 'Orders',     'subtitle' => 'Book · Pack · Track',        'href' => '/orders/index.php'],
    ['key' => 'deliveries', 'mod' => 'deliveries',   'name' => 'Deliveries', 'subtitle' => 'Van · Route · Proof',        'href' => '/deliveries/index.php'],
    ['key' => 'stock',      'mod' => 'stock',        'name' => 'Stock',      'subtitle' => 'Godown · Adjust · Reorder',  'href' => '/stock/index.php'],
    ['key' => 'invoices',   'mod' => 'invoices',     'name' => 'Invoices',   'subtitle' => 'Bills · Collections',         'href' => '/invoices/index.php'],
    ['key' => 'expenses',   'mod' => 'expenses',     'name' => 'Expenses',   'subtitle' => 'Travel · Fuel · Claims',     'href' => '/expenses/index.php'],
    ['key' => 'tasks',      'mod' => 'tasks',        'name' => 'Tasks',      'subtitle' => 'Team follow-ups',           'href' => '/tasks/index.php'],
];
foreach ($catalog as $c) {
    if ($c['mod'] !== null && !user_has_module($user, $c['mod'])) continue;
    $statsPills = [];
    if ($c['key'] === 'products') {
        $statsPills[] = ['label' => $stats['products'] . ' SKUs', 'tone' => ''];
        if ($stats['low'] > 0) $statsPills[] = ['label' => $stats['low'] . ' low', 'tone' => 'warn'];
    } elseif ($c['key'] === 'customers') {
        $statsPills[] = ['label' => $stats['customers'] . ' outlets', 'tone' => ''];
    } elseif ($c['key'] === 'orders') {
        $statsPills[] = ['label' => $stats['open_orders'] . ' open', 'tone' => ''];
    } elseif ($c['key'] === 'deliveries') {
        $statsPills[] = ['label' => $stats['today_del'] . ' today', 'tone' => $stats['today_del'] ? 'warn' : ''];
    } elseif ($c['key'] === 'invoices') {
        $statsPills[] = ['label' => inr((float)$stats['open_inv']), 'tone' => ''];
    } elseif ($c['key'] === 'stock') {
        if ($stats['low'] > 0) $statsPills[] = ['label' => $stats['low'] . ' to reorder', 'tone' => 'warn'];
    } elseif ($c['key'] === 'tasks') {
        $statsPills[] = ['label' => $stats['tasks'] . ' open', 'tone' => ''];
    }
    $apps[] = $c + ['stats' => $statsPills];
}

$icons = [
    'hub'        => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    'today'      => '<path d="M12 8v5l3 2"/><circle cx="12" cy="12" r="9"/>',
    'products'   => '<path d="M3 7l9-4 9 4-9 4-9-4Z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/>',
    'customers'  => '<path d="M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    'orders'     => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/>',
    'deliveries' => '<rect x="3" y="10" width="13" height="8" rx="1"/><path d="M16 14h4l3 4v0H16"/><circle cx="7" cy="20" r="2"/><circle cx="18" cy="20" r="2"/>',
    'stock'      => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 10h8M8 14h5"/>',
    'invoices'   => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
    'expenses'   => '<rect x="4" y="5" width="16" height="14" rx="2"/><path d="M8 9h8M8 13h5"/>',
    'tasks'      => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 10l2 2 4-4"/>',
];

$GROUPS = [
    'ki'    => ['label' => 'Ki',     'keys' => ['hub']],
    'route' => ['label' => 'Route',  'keys' => ['today', 'orders', 'deliveries', 'customers']],
    'godown'=> ['label' => 'Godown', 'keys' => ['products', 'stock']],
    'money' => ['label' => 'Money',  'keys' => ['invoices', 'expenses']],
    'ops'   => ['label' => 'Ops',    'keys' => ['tasks']],
];
$grouped = array_fill_keys(array_keys($GROUPS), []);
foreach ($apps as $app) {
    foreach ($GROUPS as $gk => $g) {
        if (in_array($app['key'], $g['keys'], true)) { $grouped[$gk][] = $app; continue 2; }
    }
    $grouped['ops'][] = $app;
}

$pageTitle = 'Home — ' . app_name();
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Welcome, <?= e(first_name($user['name'])) ?> 👋</h1>
        <p class="muted"><?= e(date('l, j M')) ?> · Distribution desk · <a href="/apps/index.php">All businesses &amp; tools</a></p>
    </div>
</div>

<ul class="admin-tiles">
    <li class="admin-tile">
        <span class="tile-label">Open orders</span>
        <span class="tile-value"><?= (int)$stats['open_orders'] ?></span>
        <span class="tile-sub">Not yet delivered</span>
    </li>
    <li class="admin-tile <?= $stats['today_del'] ? 'tile-warn' : 'tile-ok' ?>">
        <span class="tile-label">Drops today</span>
        <span class="tile-value"><?= (int)$stats['today_del'] ?></span>
        <span class="tile-sub">Pending or out</span>
    </li>
    <li class="admin-tile">
        <span class="tile-label">Outstanding</span>
        <span class="tile-value"><?= e(inr((float)$stats['open_inv'])) ?></span>
        <span class="tile-sub">Open invoices</span>
    </li>
    <li class="admin-tile <?= $stats['low'] ? 'tile-warn' : '' ?>">
        <span class="tile-label">Low stock</span>
        <span class="tile-value"><?= (int)$stats['low'] ?></span>
        <span class="tile-sub">SKUs at reorder</span>
    </li>
</ul>

<?php foreach ($GROUPS as $gk => $g): if (!$grouped[$gk]) continue; ?>
<details class="app-group" open>
    <summary><?= e($g['label']) ?> <span class="muted small">(<?= count($grouped[$gk]) ?>)</span></summary>
    <ul class="app-grid" role="list">
        <?php foreach ($grouped[$gk] as $app): ?>
            <li>
                <a class="app-card" href="<?= e($app['href']) ?>">
                    <div class="app-icon app-icon-<?= e($app['key']) ?>">
                        <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <?= $icons[$app['key']] ?? '' ?>
                        </svg>
                    </div>
                    <div class="app-text">
                        <div class="app-name"><?= e($app['name']) ?></div>
                        <div class="app-subtitle"><?= e($app['subtitle']) ?></div>
                        <?php if (!empty($app['stats'])): ?>
                            <div class="app-stats">
                                <?php foreach ($app['stats'] as $s): ?>
                                    <span class="pill <?= $s['tone'] === 'warn' ? 'pill-warn' : '' ?>"><?= e($s['label']) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</details>
<?php endforeach; ?>

<?php if ($user['role'] === 'admin'): ?>
    <p class="muted"><a href="/admin.php">Admin → Users</a></p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
