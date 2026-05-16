<?php

declare(strict_types=1);

$productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$product = getProduct($db, $productId);
if (!$product) redirect('index.php?page=inventory');

$success = '';
$errors = [];
$history = getStockHistory($db, $productId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? 'adjustment';
    $quantity = (int) ($_POST['quantity'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $user = $_SESSION['user'];

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

        recordStockAdjustment($db, $productId, $user['id'], $user['full_name'], $type, $quantity, $prevStock, $newStock, $reason);

        $success = 'Stock adjusted successfully.';
        $product = getProduct($db, $productId);
        $history = getStockHistory($db, $productId);
    }
}
?>
<div class="page-header">
    <h1><i class="fas fa-cubes"></i> Stock Adjustment: <?= e($product['name']) ?></h1>
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
        <h2 style="margin-bottom:16px">Current Stock: <strong><?= (int) $product['stock_quantity'] ?></strong></h2>
        <form method="post">
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
        <h2 style="margin-bottom:16px">Stock History</h2>
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
