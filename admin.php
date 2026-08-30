<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$me = require_admin();

function pin_is_in_use(string $pin, ?int $excludeUserId = null): bool
{
    $stmt = db()->query("SELECT id, pin_hash FROM users");
    foreach ($stmt as $row) {
        if ($excludeUserId !== null && (int)$row['id'] === $excludeUserId) continue;
        if (password_verify($pin, $row['pin_hash'])) return true;
    }
    return false;
}

function modules_from_post(array $post): string
{
    $picked = $post['modules'] ?? [];
    if (!is_array($picked)) $picked = [];
    $valid = array_intersect($picked, array_keys(all_modules()));
    return implode(',', $valid);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';

    if ($op === 'create') {
        $name = trim($_POST['name'] ?? '');
        $pin  = preg_replace('/\D/', '', $_POST['pin'] ?? '');
        $role = in_array($_POST['role'] ?? '', assignable_roles(), true) ? $_POST['role'] : 'sales';
        $modules = modules_from_post($_POST);
        if ($name === '' || strlen($pin) < 4 || strlen($pin) > 6) {
            flash_set('error', 'Name and a 4–6 digit PIN are required.');
            redirect('/admin.php');
        }
        if (pin_is_in_use($pin)) {
            flash_set('error', 'That PIN is already in use.');
            redirect('/admin.php');
        }
        db()->prepare("INSERT INTO users (name, pin_hash, role, modules, active) VALUES (:n,:h,:r,:m,1)")
            ->execute([':n'=>$name, ':h'=>password_hash($pin, PASSWORD_DEFAULT), ':r'=>$role, ':m'=>$modules]);
        flash_set('ok', "User added. PIN is $pin — share it privately.");
        redirect('/admin.php');
    }

    if ($op === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $role = in_array($_POST['role'] ?? '', assignable_roles(), true) ? $_POST['role'] : 'sales';
        $active = !empty($_POST['active']) ? 1 : 0;
        $modules = modules_from_post($_POST);
        $newPin = preg_replace('/\D/', '', $_POST['pin'] ?? '');
        if ($id === $me['id'] && $role !== 'admin') {
            flash_set('error', "You can't demote yourself.");
            redirect('/admin.php');
        }
        if ($id === $me['id'] && !$active) {
            flash_set('error', "You can't deactivate yourself.");
            redirect('/admin.php');
        }
        if ($name === '' || $id <= 0) {
            flash_set('error', 'Name is required.');
            redirect('/admin.php');
        }
        db()->prepare("UPDATE users SET name=:n, role=:r, modules=:m, active=:a WHERE id=:id")
            ->execute([':n'=>$name, ':r'=>$role, ':m'=>$modules, ':a'=>$active, ':id'=>$id]);
        if (strlen($newPin) >= 4) {
            if (pin_is_in_use($newPin, $id)) {
                flash_set('error', 'That PIN is already in use.');
                redirect('/admin.php');
            }
            db()->prepare("UPDATE users SET pin_hash=:h WHERE id=:id")
                ->execute([':h'=>password_hash($newPin, PASSWORD_DEFAULT), ':id'=>$id]);
        }
        flash_set('ok', 'User saved.');
        redirect('/admin.php');
    }
}

$people = db()->query("SELECT id, name, role, modules, active FROM users ORDER BY active DESC, name")->fetchAll();
$pageTitle = 'Admin';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div><h1>Admin</h1><p class="muted">People, PINs, and module access.</p></div>
</div>

<details class="card card-form" open>
    <summary>Add user</summary>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="create">
        <div class="grid-form">
            <label>Name <input name="name" required></label>
            <label>PIN (4–6 digits) <input name="pin" inputmode="numeric" pattern="[0-9]{4,6}" required></label>
            <label>Role
                <select name="role">
                    <?php foreach (assignable_roles() as $r): ?>
                        <option value="<?= e($r) ?>"><?= e(role_label($r)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="full">
                <span class="muted small">Modules</span><br>
                <?php foreach (all_modules() as $k => $lab): ?>
                    <label class="check"><input type="checkbox" name="modules[]" value="<?= e($k) ?>" checked> <?= e($lab) ?></label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-actions"><button class="btn btn-primary">Add</button></div>
    </form>
</details>

<div class="table-scroll card">
<table class="admin-table">
    <thead><tr><th>Name</th><th>Role</th><th>Modules</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($people as $p): ?>
        <tr class="<?= $p['active'] ? '' : 'is-inactive' ?>">
            <td colspan="4">
                <form method="post" class="grid-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="op" value="update">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <label>Name <input name="name" value="<?= e($p['name']) ?>"></label>
                    <label>Role
                        <select name="role">
                            <?php foreach (assignable_roles() as $r): ?>
                                <option value="<?= e($r) ?>" <?= $p['role'] === $r ? 'selected' : '' ?>><?= e(role_label($r)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>New PIN <input name="pin" placeholder="leave blank"></label>
                    <label class="check"><input type="checkbox" name="active" value="1" <?= $p['active'] ? 'checked' : '' ?>> Active</label>
                    <div class="full">
                        <?php $have = user_modules_from_row($p); ?>
                        <?php foreach (all_modules() as $k => $lab): ?>
                            <label class="check"><input type="checkbox" name="modules[]" value="<?= e($k) ?>" <?= in_array($k, $have, true) ? 'checked' : '' ?>> <?= e($lab) ?></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-actions"><button class="btn">Save</button></div>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
