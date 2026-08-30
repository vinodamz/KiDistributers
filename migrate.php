<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    require_admin();
    header('Content-Type: text/plain; charset=utf-8');
}

echo "Ki Distributers migrate\n";
echo "users table state: " . users_table_state() . "\n\n";

$dir = __DIR__ . '/sql';
$files = glob($dir . '/migrate_*.sql') ?: [];
sort($files);
if (!$files) {
    echo "No migrate_*.sql files. Fresh install uses sql/schema.sql.\n";
    exit(0);
}

$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    filename VARCHAR(120) NOT NULL PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$done = $pdo->query("SELECT filename FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
foreach ($files as $f) {
    $name = basename($f);
    if (in_array($name, $done, true)) {
        echo "skip $name\n";
        continue;
    }
    echo "apply $name … ";
    $sql = file_get_contents($f);
    $pdo->exec($sql);
    $pdo->prepare("INSERT INTO schema_migrations (filename) VALUES (:f)")->execute([':f' => $name]);
    echo "ok\n";
}
echo "done.\n";
