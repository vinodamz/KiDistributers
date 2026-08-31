<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/hub.php';

$user = require_login();
$hubMode = true;
$pageTitle = hub_name() . ' — Apps';
$businesses = hub_businesses();
$tools = hub_tools();

require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1><?= e(hub_name()) ?></h1>
        <p class="muted">Pick a business or a tool. Distribution stays the same desk you already use.</p>
    </div>
</div>

<details class="app-group" open>
    <summary>Businesses <span class="muted small">(<?= count($businesses) ?>)</span></summary>
    <ul class="app-grid" role="list">
        <?php foreach ($businesses as $b): ?>
            <li>
                <a class="app-card" href="<?= e((string)$b['href']) ?>">
                    <div class="app-icon app-icon-<?= e((string)($b['icon'] ?? 'external')) ?>">
                        <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <?= hub_svg((string)($b['icon'] ?? 'external')) ?>
                        </svg>
                    </div>
                    <div class="app-text">
                        <div class="app-name"><?= e((string)$b['name']) ?></div>
                        <div class="app-subtitle"><?= e((string)($b['subtitle'] ?? '')) ?></div>
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</details>

<details class="app-group" open>
    <summary>Tools <span class="muted small">(<?= count($tools) ?>)</span></summary>
    <ul class="app-grid" role="list">
        <?php foreach ($tools as $t): ?>
            <li>
                <a class="app-card" href="<?= e((string)$t['href']) ?>">
                    <div class="app-icon app-icon-<?= e((string)($t['icon'] ?? 'tool')) ?>">
                        <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <?= hub_svg((string)($t['icon'] ?? 'tool')) ?>
                        </svg>
                    </div>
                    <div class="app-text">
                        <div class="app-name"><?= e((string)$t['name']) ?></div>
                        <div class="app-subtitle"><?= e((string)($t['subtitle'] ?? '')) ?></div>
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</details>

<p class="muted small">Add another business by editing <code>hub.businesses</code> in <code>includes/config.php</code> (URL to that site). Tools stay here so every business can use them.</p>

<?php require __DIR__ . '/../includes/footer.php'; ?>
