<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_module('expenses');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $amt = (float)$_POST['amount'];
    $date = $_POST['expense_date'] ?? '';
    $cat = trim((string)$_POST['category']) ?: 'Travel';
    if ($amt <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        flash_set('error', 'Date and a positive amount are required.');
        redirect('/expenses/edit.php');
    }
    db()->prepare("INSERT INTO expenses (user_id, expense_date, category, amount, merchant, notes, status)
        VALUES (:u,:d,:c,:a,:m,:n,'submitted')")->execute([
        ':u'=>$user['id'], ':d'=>$date, ':c'=>$cat, ':a'=>$amt,
        ':m'=>trim((string)$_POST['merchant']) ?: null,
        ':n'=>trim((string)$_POST['notes']) ?: null,
    ]);
    flash_set('ok', 'Expense submitted.');
    redirect('/expenses/index.php');
}

$pageTitle = 'New expense';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>New expense</h1></div>
<form method="post" class="card card-form">
    <?= csrf_field() ?>
    <div class="grid-form">
        <label>Date <input type="date" name="expense_date" required value="<?= e(date('Y-m-d')) ?>"></label>
        <label>Amount <input name="amount" type="number" step="0.01" required></label>
        <label>Category
            <select name="category">
                <?php foreach (['Travel','Fuel','Meals','Lodging','Toll','Other'] as $c): ?>
                    <option><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Merchant <input name="merchant"></label>
        <label class="full">Notes <textarea name="notes" rows="3"></textarea></label>
    </div>
    <div class="form-actions">
        <a class="btn" href="/expenses/index.php">Cancel</a>
        <button class="btn btn-primary">Submit</button>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
