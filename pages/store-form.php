<?php

declare(strict_types=1);

$editStore = null;
$errors = [];
$success = '';
$userRole = $_SESSION['user']['role'] ?? '';
$isSystemAdmin = $userRole === 'admin';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    // Store admin can only edit their own store
    if (!$isSystemAdmin && $id !== activeStoreId()) {
        redirect('index.php?page=dashboard');
    }
    $editStore = getStore($db, $id);
    if (!$editStore) redirect('index.php?page=stores');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $currency = trim($_POST['currency'] ?? 'R');
    $taxRate = (float) ($_POST['tax_rate'] ?? 15);
    $receiptFooter = trim($_POST['receipt_footer'] ?? '');
    $dailyTarget = (float) ($_POST['daily_target'] ?? 5000);
    $selfCheckout = isset($_POST['self_checkout']) ? 1 : 0;
    $status = $_POST['status'] ?? 'active';

    if (!$name) $errors[] = 'Store name is required.';
    if ($taxRate < 0) $errors[] = 'Tax rate cannot be negative.';
    if ($dailyTarget <= 0) $errors[] = 'Daily target must be greater than 0.';

    if (empty($errors)) {
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE stores SET name=?, address=?, contact=?, email=?, currency=?, tax_rate=?, receipt_footer=?, daily_target=?, self_checkout_enabled=?, status=? WHERE id=?");
            $stmt->execute([$name, $address, $contact, $email, $currency, $taxRate, $receiptFooter, $dailyTarget, $selfCheckout, $status, $id]);
            $success = 'Store updated successfully.';
            if ((int) $id === ACTIVE_STORE_ID) {
                $_SESSION['store_id'] = $id;
            }
        } else {
            $stmt = $db->prepare("INSERT INTO stores (name, address, contact, email, currency, tax_rate, receipt_footer, daily_target, self_checkout_enabled, status) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$name, $address, $contact, $email, $currency, $taxRate, $receiptFooter, $dailyTarget, $selfCheckout, $status]);
            $id = (int) $db->lastInsertId();
            $success = 'Store added successfully.';
        }
        $editStore = getStore($db, $id);
    }
}

$s = $editStore;
?>
<div class="page-header">
    <h1><i class="fas fa-<?= $s ? 'edit' : 'plus' ?>"></i> <?= $s ? 'Edit Store' : 'Add Store' ?></h1>
    <a href="index.php?page=<?= $isSystemAdmin ? 'stores' : 'dashboard' ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:700px">
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="name">Store Name</label>
            <input type="text" id="name" name="name" class="form-control" required value="<?= e($s['name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="address">Address</label>
            <textarea id="address" name="address" class="form-control" rows="3"><?= e($s['address'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label for="contact">Contact Details</label>
            <input type="text" id="contact" name="contact" class="form-control" value="<?= e($s['contact'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control" value="<?= e($s['email'] ?? '') ?>">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="tax_rate">VAT / Tax Rate (%)</label>
                <input type="number" id="tax_rate" name="tax_rate" class="form-control" step="0.1" min="0" value="<?= e((string) ($s['tax_rate'] ?? 15)) ?>">
            </div>
            <div class="form-group">
                <label for="currency">Currency Symbol</label>
                <input type="text" id="currency" name="currency" class="form-control" maxlength="5" value="<?= e($s['currency'] ?? 'R') ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="daily_target">Daily Sales Target</label>
            <input type="number" id="daily_target" name="daily_target" class="form-control" step="0.01" min="1" value="<?= e((string) ($s['daily_target'] ?? 5000)) ?>">
        </div>
        <div class="form-group">
            <label for="receipt_footer">Receipt Footer Message</label>
            <textarea id="receipt_footer" name="receipt_footer" class="form-control" rows="2"><?= e($s['receipt_footer'] ?? '') ?></textarea>
        </div>
        <div class="form-group" style="display:flex;align-items:center;gap:12px;padding:12px 0">
            <label style="margin:0;cursor:pointer;display:flex;align-items:center;gap:10px;font-size:15px">
                <input type="checkbox" name="self_checkout" value="1" <?= (isset($s['self_checkout_enabled']) ? (int) $s['self_checkout_enabled'] : 1) ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:var(--primary)">
                <strong>Enable Self Checkout</strong>
            </label>
            <span style="font-size:13px;color:var(--gray-400)">Allows customers to scan and pay for items themselves</span>
        </div>
        <?php if ($s): ?>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-control">
                <option value="active" <?= ($s['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($s['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> <?= $s ? 'Save Changes' : 'Add Store' ?></button>
    </form>
</div>
