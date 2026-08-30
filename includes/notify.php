<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function unread_count(int $userId): int
{
    try {
        $st = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :u AND read_at IS NULL");
        $st->execute([':u' => $userId]);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function notify(int $userId, string $category, string $eventType, string $title, string $body = '', ?string $link = null): void
{
    try {
        db()->prepare("
            INSERT INTO notifications (user_id, category, event_type, title, body, link, email_status)
            VALUES (:u, :c, :e, :t, :b, :l, 'skipped')
        ")->execute([
            ':u' => $userId, ':c' => $category, ':e' => $eventType,
            ':t' => $title, ':b' => $body, ':l' => $link,
        ]);
    } catch (Throwable $e) {
        // Never break the calling page over a bell.
    }
}
