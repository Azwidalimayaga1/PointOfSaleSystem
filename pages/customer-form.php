<?php declare(strict_types=1);
$id = (int) ($_GET['id'] ?? 0);
$customer = ['id'=>0,'name'=>'','phone'=>'','email'=>'','address'=>'','notes'=>''];
if ($id) { $stmt = $db->prepare("SELECT * FROM customers WHERE id = ? AND store_id = ?"); $stmt->execute([$id, activeStoreId()]); $customer = $stmt->fetch() ?: $customer; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['_csrf'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    if (!$name) { $error = 'Name is required.'; }
    else {
        try {
            if ($id) {
                $db->prepare("UPDATE customers SET name=?, phone=?, email=?, address=?, notes=? WHERE id=? AND store_id=?")->execute([$name, $phone, $email, $address, $notes, $id, activeStoreId()]);
            } else {
                $db->prepare("INSERT INTO customers (name, phone, email, address, notes, store_id) VALUES (?,?,?,?,?,?)")->execute([$name, $phone, $email, $address, $notes, activeStoreId()]);
            }
            $_SESSION['pos_flash'] = ['type' => 'success', 'message' => 'Customer saved.'];
            redirect('index.php?page=customers');
        } catch (Exception $e) { $error = $e->getMessage(); }
    }
    }
}
?>
<div class="page-header"><a href="index.php?page=customers" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a></div>
<div class="card" style="max-width:600px">
<?php if (isset($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post">
<?= csrf_field() ?>
<div class="form-group"><label for="cust-name">Name *</label><input type="text" id="cust-name" name="name" class="form-control" value="<?= e($customer['name']) ?>" required></div>
<div class="form-group"><label for="cust-phone">Phone</label><input type="text" id="cust-phone" name="phone" class="form-control" value="<?= e($customer['phone'] ?? '') ?>"></div>
<div class="form-group"><label for="cust-email">Email</label><input type="email" id="cust-email" name="email" class="form-control" value="<?= e($customer['email'] ?? '') ?>"></div>
<div class="form-group"><label for="cust-address">Address</label><textarea id="cust-address" name="address" class="form-control" rows="2"><?= e($customer['address'] ?? '') ?></textarea></div>
<div class="form-group"><label for="cust-notes">Notes</label><textarea id="cust-notes" name="notes" class="form-control" rows="2"><?= e($customer['notes'] ?? '') ?></textarea></div>
<button class="btn btn-primary"><i class="fas fa-save"></i> Save Customer</button>
</form>
</div>
