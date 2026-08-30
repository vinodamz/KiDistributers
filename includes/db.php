<?php
// PDO wrapper. Single shared connection per request.

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $c = $config['db'];

    $dsn = "mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}";
    $pdo = new PDO($dsn, $c['user'], $c['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $offset = (new DateTime('now', new DateTimeZone(date_default_timezone_get())))->format('P');
    try {
        $pdo->exec("SET time_zone = '{$offset}'");
    } catch (Throwable $e) {
        // If the server rejects the offset, fall back silently.
    }
    return $pdo;
}
