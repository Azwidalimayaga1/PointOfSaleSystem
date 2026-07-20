<?php

declare(strict_types=1);

$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';

if (isSuperAdmin()) {
    $sql = "SELECT p.*, s.name as store_name FROM products p JOIN stores s ON s.id = p.store_id WHERE 1=1";
    $params = [];
} else {
    $sql = "SELECT * FROM products WHERE store_id = ?";
    $params = [activeStoreId()];
}

if ($search) {
    $sql .= " AND (name LIKE ? OR barcode LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter === 'low') {
    $sql .= " AND stock_quantity <= low_stock_threshold";
} elseif ($filter === 'out') {
    $sql .= " AND stock_quantity = 0";
}

$sql .= " ORDER BY name ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>
<div class="card">
    <form method="get" class="search-bar">
        <input type="hidden" name="page" value="inventory">
        <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?= e($search) ?>" aria-label="Search products">
        <select name="filter" class="form-control" aria-label="Stock filter">
            <option value="">All Stock</option>
            <option value="low" <?= $filter === 'low' ? 'selected' : '' ?>>Low Stock</option>
            <option value="out" <?= $filter === 'out' ? 'selected' : '' ?>>Out of Stock</option>
        </select>
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
        <a href="index.php?page=inventory" class="btn btn-outline"><i class="fas fa-times"></i> Clear</a>
    </form>
</div>

<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Barcode</th>
                    <?php if (isSuperAdmin()): ?><th>Store</th><?php endif; ?>
                    <th>Current Stock</th>
                    <th>Threshold</th>
                    <th>Stock Value</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="<?= isSuperAdmin() ? 8 : 7 ?>" class="text-center p-40 text-muted">No products found.</td></tr>
                <?php endif; ?>
                <?php foreach ($products as $p): ?>
                    <?php
                        $stockVal = (float) $p['stock_quantity'] * (float) $p['cost_price'];
                        $isLow = (int) $p['stock_quantity'] <= (int) $p['low_stock_threshold'];
                        $isOut = (int) $p['stock_quantity'] === 0;
                    ?>
                    <tr>
                        <td><strong><?= e($p['name']) ?></strong></td>
                        <td><?= e($p['barcode'] ?? '-') ?></td>
                        <?php if (isSuperAdmin()): ?>
                        <td><span class="badge badge-primary"><?= e($p['store_name'] ?? 'Store #' . $p['store_id']) ?></span></td>
                        <?php endif; ?>
                        <td>
                            <?php if ($isOut): ?>
                                <span class="badge badge-danger">Out of Stock</span>
                            <?php elseif ($isLow): ?>
                                <span class="badge badge-warning"><?= (int) $p['stock_quantity'] ?> (Low)</span>
                            <?php else: ?>
                                <span class="badge badge-success"><?= (int) $p['stock_quantity'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $p['low_stock_threshold'] ?></td>
                        <td><?= money($stockVal) ?></td>
                        <td>
                            <span class="badge <?= $p['status'] === 'active' ? 'badge-success' : 'badge-gray' ?>">
                                <?= e(ucfirst($p['status'] ?? 'active')) ?>
                            </span>
                        </td>
                        <td>
                            <a href="index.php?page=stock-adjustment&id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-warning" title="Adjust stock for this product">
                                <i class="fas fa-cubes"></i> Adjust
                            </a>
                            <a href="index.php?page=product-form&id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-primary" title="Edit product">
                                <i class="fas fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                    <?php if ($isLow || $isOut): ?>
                        <tr class="bg-danger-row">
                            <td colspan="<?= isSuperAdmin() ? 8 : 7 ?>" class="info-row">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php if ($isOut): ?>
                                    This product is out of stock!
                                <?php else: ?>
                                    Low stock: only <?= (int) $p['stock_quantity'] ?> remaining (threshold: <?= (int) $p['low_stock_threshold'] ?>)
                                <?php endif; ?>
                                <a href="index.php?page=stock-adjustment&id=<?= (int) $p['id'] ?>" class="ml-8">Restock now</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
