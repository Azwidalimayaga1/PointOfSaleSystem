<?php

declare(strict_types=1);


$productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$product = getProduct($db, $productId);
if (!$product) redirect('index.php?page=inventory');

// Store admin can only adjust their own store's products
if (isStoreAdmin() && isset($product['store_id']) && (int) $product['store_id'] !== currentUserStoreId()) {
    accessDenied();
}

$success = '';
$errors = [];
$history = getStockHistory($db, $productId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['_csrf'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    $type = $_POST['type'] ?? 'adjustment';
    $quantity = (int) ($_POST['quantity'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    if ($quantity <= 0) {
        $errors[] = 'Quantity must be greater than 0.';
    }

    if (empty($errors)) {
        $prevStock = (int) $product['stock_quantity'];

        if (in_array($type, ['sale', 'damage', 'return'], true)) {
            $newStock = $prevStock - $quantity;
        } else {
            $newStock = $prevStock + $quantity;
        }

        if ($newStock < 0) $newStock = 0;

        $stmt = $db->prepare("UPDATE products SET stock_quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$newStock, $productId]);

        recordStockAdjustment($db, $productId, $_SESSION['user_id'], userName(), $type, $quantity, $prevStock, $newStock, $reason);
        logAction($db, 'stock_adjustment', 'product', $productId, 'Stock adjusted for ' . $product['name'] . ': ' . $type . ' qty=' . $quantity . ' (prev: ' . $prevStock . ', new: ' . $newStock . ')' . ($reason ? ' reason: ' . $reason : ''));

        $success = 'Stock adjusted successfully.';
        $product = getProduct($db, $productId);
        $history = getStockHistory($db, $productId);
    }
}
?>
<div class="page-header">
    <a href="index.php?page=inventory" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="grid-2">
    <div class="card">
        <h2 class="mb-16">Product: <strong><?= e($product['name']) ?></strong></h2>
        <h3 class="mb-16">Current Stock: <strong><?= (int) $product['stock_quantity'] ?></strong></h3>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="type">Adjustment Type</label>
                <select id="type" name="type" class="form-control">
                    <option value="purchase">Purchase (add stock)</option>
                    <option value="adjustment">Adjustment (add stock)</option>
                    <option value="return">Return (remove stock)</option>
                    <option value="damage">Damage (remove stock)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="quantity">Quantity</label>
                <input type="number" id="quantity" name="quantity" class="form-control" min="1" required>
            </div>
            <div class="form-group">
                <label for="reason">Reason</label>
                <textarea id="reason" name="reason" class="form-control" placeholder="Optional reason for adjustment..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Adjustment</button>
        </form>
    </div>

    <div class="card">
        <h2 class="mb-16">Stock History</h2>
        <?php if (empty($history)): ?>
            <p class="muted">No history yet.</p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>From</th>
                            <th>To</th>
                            <th>By</th>
                            <?php if (isSuperAdmin()): ?><th>Store</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td><?= e(date('Y-m-d H:i', strtotime($h['created_at']))) ?></td>
                                <td><span class="badge badge-info"><?= e(ucfirst($h['type'])) ?></span></td>
                                <td><?= (int) $h['quantity'] ?></td>
                                <td><?= (int) $h['previous_stock'] ?></td>
                                <td><?= (int) $h['new_stock'] ?></td>
                                <td><?= e($h['user_name']) ?></td>
                                <?php if (isSuperAdmin()): ?>
                                <td><span class="badge badge-primary"><?= e($h['store_name'] ?? 'Store #' . $h['store_id']) ?></span></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
