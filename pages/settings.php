<?php

declare(strict_types=1);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $storeName = trim($_POST['store_name'] ?? '');
    $storeAddress = trim($_POST['store_address'] ?? '');
    $storeContact = trim($_POST['store_contact'] ?? '');
    $taxRate = (float) ($_POST['tax_rate'] ?? 15);
    $currency = trim($_POST['currency'] ?? 'R');
    $receiptFooter = trim($_POST['receipt_footer'] ?? '');
    $dailyTarget = (float) ($_POST['daily_target'] ?? 5000);

    if (!$storeName) $errors[] = 'Store name is required.';
    if ($taxRate < 0) $errors[] = 'Tax rate cannot be negative.';
    if ($dailyTarget <= 0) $errors[] = 'Daily target must be greater than 0.';

    if (empty($errors)) {
        $stmt = $db->prepare("REPLACE INTO `settings` (`key`, `value`) VALUES (?, ?)");
        $stmt->execute(['store_name', $storeName]);
        $stmt->execute(['store_address', $storeAddress]);
        $stmt->execute(['store_contact', $storeContact]);
        $stmt->execute(['tax_rate', (string) $taxRate]);
        $stmt->execute(['currency', $currency]);
        $stmt->execute(['receipt_footer', $receiptFooter]);
        $stmt->execute(['daily_target', (string) $dailyTarget]);
        logAction($db, 'settings_update', 'settings', null, 'Settings updated: store_name=' . $storeName . ', tax_rate=' . $taxRate . ', currency=' . $currency . ', daily_target=' . $dailyTarget);
        $success = 'Settings saved successfully.';
    }
}
?>
<div class="page-header">
    <h1><i class="fas fa-cog"></i> Settings</h1>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:700px">
    <form method="post">
        <div class="form-group">
            <label for="store_name">Store Name</label>
            <input type="text" id="store_name" name="store_name" class="form-control" required value="<?= e(STORE_NAME) ?>">
        </div>
        <div class="form-group">
            <label for="store_address">Store Address</label>
            <textarea id="store_address" name="store_address" class="form-control" rows="3"><?= e(STORE_ADDRESS) ?></textarea>
        </div>
        <div class="form-group">
            <label for="store_contact">Contact Details</label>
            <input type="text" id="store_contact" name="store_contact" class="form-control" value="<?= e(STORE_CONTACT) ?>">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="tax_rate">VAT / Tax Rate (%)</label>
                <input type="number" id="tax_rate" name="tax_rate" class="form-control" step="0.1" min="0" value="<?= e((string) TAX_RATE) ?>">
            </div>
            <div class="form-group">
                <label for="currency">Currency Symbol</label>
                <input type="text" id="currency" name="currency" class="form-control" maxlength="5" value="<?= e(CURRENCY) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="daily_target">Daily Sales Target</label>
            <input type="number" id="daily_target" name="daily_target" class="form-control" step="0.01" min="1" value="<?= e((string) DAILY_TARGET) ?>">
        </div>
        <div class="form-group">
            <label for="receipt_footer">Receipt Footer Message</label>
            <textarea id="receipt_footer" name="receipt_footer" class="form-control" rows="2"><?= e(RECEIPT_FOOTER) ?></textarea>
        </div>
        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Settings</button>
    </form>
</div>
