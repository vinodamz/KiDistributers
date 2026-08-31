<?php
/**
 * install.php — first-run admin bootstrap.
 * Safe to leave on the server: it 403s once an admin exists.
 */

function kd_install_esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function kd_install_fail(string $title, string $body, ?string $detail = null): void
{
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . kd_install_esc($title) . '</title></head>'
       . '<body style="margin:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;'
       . 'background:#f4f7f8;color:#2b2b2b;padding:2rem;">'
       . '<div style="max-width:36rem;margin:2rem auto;background:#fff;border-radius:8px;padding:1.5rem;box-shadow:0 1px 4px rgba(0,0,0,.08);">'
       . '<h1 style="color:#115e59;font-size:1.25rem;margin:0 0 .75rem;">' . kd_install_esc($title) . '</h1>'
       . '<p style="color:#444;line-height:1.5;">' . kd_install_esc($body) . '</p>';
    if ($detail !== null && $detail !== '') {
        echo '<pre style="background:#f4f7f8;padding:1rem;overflow:auto;font-size:.85rem;">'
           . kd_install_esc($detail) . '</pre>';
    }
    echo '</div></body></html>';
    exit;
}

$configPath = __DIR__ . '/includes/config.php';
if (!is_readable($configPath)) {
    kd_install_fail(
        'config.php is missing',
        'Copy includes/config.example.php to includes/config.php in this folder (the live site, not the Git clone) and fill in the MySQL database name, user, and password from cPanel → MySQL Databases. Use the prefixed names (they look like yourcpaneluser_kd).'
    );
}

$cfg = require $configPath;
if (!is_array($cfg) || empty($cfg['db']['name']) || empty($cfg['db']['user'])) {
    kd_install_fail(
        'config.php is incomplete',
        'includes/config.php must return db host, name, user, and password.'
    );
}

$db = $cfg['db'];
if (($db['password'] ?? '') === 'CHANGE_ME') {
    kd_install_fail(
        'Database password not set',
        'Edit includes/config.php and replace CHANGE_ME with the MySQL user password from cPanel.'
    );
}

try {
    $dsn = 'mysql:host=' . $db['host'] . ';dbname=' . $db['name'] . ';charset=' . ($db['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (Throwable $e) {
    kd_install_fail(
        'Cannot connect to MySQL',
        'cPanel → MySQL Databases: the database name, username, and password in includes/config.php must match exactly (including the cPanel prefix). The user must have ALL PRIVILEGES on that database. Host should be localhost.',
        $e->getMessage()
    );
}

try {
    $pdo->query('SELECT 1 FROM users LIMIT 1');
} catch (Throwable $e) {
    kd_install_fail(
        'Schema not imported',
        'In phpMyAdmin, select database ' . ($db['name'] ?? '') . ' (left sidebar), then Import sql/schema.sql. You should see a users table after import.',
        $e->getMessage()
    );
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

try {
    start_session_once();
} catch (Throwable $e) {
    kd_install_fail(
        'PHP session failed',
        'cPanel → MultiPHP INI Editor: session.save_path must be writable (often /home/<cpaneluser>/tmp).',
        $e->getMessage()
    );
}

$adminExists = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1")
    ->fetchColumn() > 0;

if ($adminExists) {
    http_response_code(403);
    echo '<p>An admin user already exists. You can delete <code>install.php</code> from the server.</p>';
    exit;
}

$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $pin  = preg_replace('/\D/', '', $_POST['pin'] ?? '');
    if ($name === '' || strlen($pin) < 4 || strlen($pin) > 6) {
        $err = 'Name and a 4–6 digit PIN are required.';
    } else {
        $mods = implode(',', array_keys(all_modules()));
        $stmt = $pdo->prepare("
            INSERT INTO users (name, pin_hash, role, modules, active)
            VALUES (:n, :h, 'admin', :m, 1)
        ");
        $stmt->execute([
            ':n' => $name,
            ':h' => password_hash($pin, PASSWORD_DEFAULT),
            ':m' => $mods,
        ]);
        flash_set('ok', "Admin \"$name\" created.");
        redirect('/login.php');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install — <?= e(app_name()) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= e(asset_version()) ?>">
</head>
<body>
<main class="container">
    <h1>Create the first admin</h1>
    <p class="muted">This page is only available while no admin exists.</p>
    <?php if ($err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endif; ?>
    <form method="post" class="card card-form">
        <div class="field">
            <label>Your name</label>
            <input name="name" required maxlength="120" autofocus>
        </div>
        <div class="field">
            <label>Choose a PIN (4–6 digits)</label>
            <input name="pin" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" required>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary">Create admin</button>
        </div>
    </form>
</main>
</body>
</html>
