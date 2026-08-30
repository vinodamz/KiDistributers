<?php
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null || !empty($GLOBALS['_app_setting_dirty'])) {
        $cache = [];
        unset($GLOBALS['_app_setting_dirty']);
        try {
            $rows = db()->query("SELECT setting_key, setting_value FROM app_settings")->fetchAll();
            foreach ($rows as $r) $cache[$r['setting_key']] = $r['setting_value'];
        } catch (Throwable $e) {
        }
    }
    return array_key_exists($key, $cache) && $cache[$key] !== null
        ? $cache[$key]
        : $default;
}

function app_name(): string
{
    $cfg = app_config();
    return (string)app_setting('app_name', $cfg['app']['name'] ?? 'Ki Distributers');
}

function app_short_name(): string
{
    $cfg = app_config();
    return (string)app_setting('app_short_name', $cfg['app']['short_name'] ?? 'KD');
}

function role_label(string $role): string
{
    static $labels = [
        'admin'     => 'Admin',
        'sales'     => 'Sales',
        'warehouse' => 'Warehouse',
        'accounts'  => 'Accounts',
    ];
    return $labels[$role] ?? ucwords(str_replace('_', ' ', $role));
}

function assignable_roles(): array
{
    return ['sales', 'warehouse', 'accounts', 'admin'];
}

function all_modules(): array
{
    return [
        'products'   => 'Products',
        'customers'  => 'Customers',
        'orders'     => 'Orders',
        'deliveries' => 'Deliveries',
        'stock'      => 'Stock',
        'invoices'   => 'Invoices',
        'expenses'   => 'Expenses',
        'tasks'      => 'Tasks',
    ];
}

function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

function flash_set(string $type, string $msg): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_get(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function user_color(int $id): string
{
    static $palette = ['#0F766E', '#F59E0B', '#2D6BA0', '#5BA547', '#7E57C2', '#E07A5F', '#5DA8A2', '#A05C7B'];
    return $palette[$id % count($palette)];
}

function user_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') return '?';
    $parts = preg_split('/\s+/', $name);
    if (count($parts) === 1) {
        return mb_strtoupper(mb_substr($parts[0], 0, 1));
    }
    return mb_strtoupper(
        mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1)
    );
}

function first_name(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    return $parts[0] ?? $name;
}

function asset_version(): string
{
    static $v = null;
    if ($v === null) {
        $css = __DIR__ . '/../assets/css/style.css';
        $v = is_readable($css) ? (string) filemtime($css) : '1';
    }
    return $v;
}

function inr(float $n, int $decimals = 0): string
{
    return '₹' . number_format($n, $decimals);
}

function qty_fmt($n): string
{
    $f = (float)$n;
    if (abs($f - round($f)) < 0.0001) return (string)(int)round($f);
    return rtrim(rtrim(number_format($f, 3, '.', ''), '0'), '.');
}

function order_status_label(string $s): string
{
    return [
        'draft'     => 'Draft',
        'confirmed' => 'Confirmed',
        'packed'    => 'Packed',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ][$s] ?? $s;
}

function delivery_status_label(string $s): string
{
    return [
        'pending' => 'Pending',
        'out'     => 'Out for delivery',
        'done'    => 'Delivered',
        'failed'  => 'Failed',
    ][$s] ?? $s;
}

function invoice_status_label(string $s): string
{
    return [
        'open'      => 'Open',
        'partial'   => 'Partial',
        'paid'      => 'Paid',
        'cancelled' => 'Cancelled',
    ][$s] ?? $s;
}

function customer_type_label(string $s): string
{
    return [
        'retailer'      => 'Retailer',
        'wholesaler'    => 'Wholesaler',
        'modern_trade' => 'Modern trade',
        'other'         => 'Other',
    ][$s] ?? $s;
}

function order_totals(int $orderId): array
{
    $st = db()->prepare("
        SELECT
          COALESCE(SUM(qty * unit_price), 0) AS subtotal,
          COALESCE(SUM(qty * unit_price * gst_pct / 100), 0) AS tax
        FROM order_lines WHERE order_id = :id
    ");
    $st->execute([':id' => $orderId]);
    $r = $st->fetch() ?: ['subtotal' => 0, 'tax' => 0];
    $sub = (float)$r['subtotal'];
    $tax = (float)$r['tax'];
    return ['subtotal' => $sub, 'tax' => $tax, 'total' => $sub + $tax];
}

function invoice_paid(int $invoiceId): float
{
    $st = db()->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = :id");
    $st->execute([':id' => $invoiceId]);
    return (float)$st->fetchColumn();
}

function refresh_invoice_status(int $invoiceId): void
{
    $st = db()->prepare("SELECT amount, tax, status FROM invoices WHERE id = :id");
    $st->execute([':id' => $invoiceId]);
    $inv = $st->fetch();
    if (!$inv || $inv['status'] === 'cancelled') return;
    $due = (float)$inv['amount'] + (float)$inv['tax'];
    $paid = invoice_paid($invoiceId);
    $status = 'open';
    if ($paid >= $due - 0.009) $status = 'paid';
    elseif ($paid > 0) $status = 'partial';
    db()->prepare("UPDATE invoices SET status = :s WHERE id = :id")->execute([':s' => $status, ':id' => $invoiceId]);
}

function next_invoice_no(): string
{
    $year = date('Y');
    $st = db()->prepare("SELECT invoice_no FROM invoices WHERE invoice_no LIKE :p ORDER BY id DESC LIMIT 1");
    $st->execute([':p' => 'KD-' . $year . '-%']);
    $last = (string)$st->fetchColumn();
    $n = 1;
    if (preg_match('/-(\d+)$/', $last, $m)) $n = (int)$m[1] + 1;
    return sprintf('KD-%s-%04d', $year, $n);
}

function apply_stock(int $productId, float $delta, string $reason, array $user, string $note = '', ?string $refType = null, ?int $refId = null): void
{
    db()->prepare("UPDATE products SET qty_on_hand = qty_on_hand + :d WHERE id = :id")
        ->execute([':d' => $delta, ':id' => $productId]);
    db()->prepare("
        INSERT INTO stock_moves (product_id, qty_delta, reason, ref_type, ref_id, note, created_by)
        VALUES (:p, :d, :r, :rt, :rid, :n, :u)
    ")->execute([
        ':p' => $productId, ':d' => $delta, ':r' => $reason,
        ':rt' => $refType, ':rid' => $refId, ':n' => $note ?: null,
        ':u' => $user['id'] ?? null,
    ]);
}

function create_invoice_from_order(int $orderId): int
{
    $ord = db()->prepare("SELECT * FROM orders WHERE id = :id");
    $ord->execute([':id' => $orderId]);
    $o = $ord->fetch();
    if (!$o) throw new RuntimeException('Order not found');

    $existing = db()->prepare("SELECT id FROM invoices WHERE order_id = :id AND status <> 'cancelled'");
    $existing->execute([':id' => $orderId]);
    $eid = $existing->fetchColumn();
    if ($eid) return (int)$eid;

    $t = order_totals($orderId);
    db()->prepare("
        INSERT INTO invoices (invoice_no, order_id, customer_id, invoice_date, amount, tax, status)
        VALUES (:n, :o, :c, :d, :a, :t, 'open')
    ")->execute([
        ':n' => next_invoice_no(),
        ':o' => $orderId,
        ':c' => $o['customer_id'],
        ':d' => date('Y-m-d'),
        ':a' => $t['subtotal'],
        ':t' => $t['tax'],
    ]);
    return (int)db()->lastInsertId();
}

function mark_delivery_done(int $deliveryId, array $user): void
{
    $st = db()->prepare("SELECT * FROM deliveries WHERE id = :id");
    $st->execute([':id' => $deliveryId]);
    $d = $st->fetch();
    if (!$d) throw new RuntimeException('Delivery not found');
    if ($d['status'] === 'done') return;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $lines = $pdo->prepare("SELECT * FROM order_lines WHERE order_id = :id");
        $lines->execute([':id' => $d['order_id']]);
        foreach ($lines->fetchAll() as $ln) {
            apply_stock((int)$ln['product_id'], -1 * (float)$ln['qty'], 'sale', $user,
                'Delivery #' . $deliveryId, 'delivery', $deliveryId);
        }
        $pdo->prepare("UPDATE deliveries SET status = 'done', delivered_at = NOW() WHERE id = :id")
            ->execute([':id' => $deliveryId]);
        $pdo->prepare("UPDATE orders SET status = 'delivered' WHERE id = :id")
            ->execute([':id' => $d['order_id']]);
        create_invoice_from_order((int)$d['order_id']);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}
