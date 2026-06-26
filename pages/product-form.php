<?php

declare(strict_types=1);

$editProduct = null;
$errors = [];
$success = '';

// Handle delete
if (isset($_POST['delete_id'])) {
    requireRole('admin', 'manager');
    $delProduct = getProduct($db, (int) $_POST['delete_id']);
    $stmt = $db->prepare("DELETE FROM products WHERE id = ? AND store_id = ?");
    $stmt->execute([(int) $_POST['delete_id'], activeStoreId()]);
    logAction($db, 'product_delete', 'product', (int) $_POST['delete_id'], 'Deleted product: ' . ($delProduct['name'] ?? ''));
    redirect('index.php?page=products');
}

// Load product for editing
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    $editProduct = getProduct($db, $id);
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id'])) {
    $name = trim($_POST['name'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $cost_price = (float) ($_POST['cost_price'] ?? 0);
    $stock_quantity = (int) ($_POST['stock_quantity'] ?? 0);
    $low_stock_threshold = (int) ($_POST['low_stock_threshold'] ?? 10);
    $supplier = trim($_POST['supplier'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $productId = (int) ($_POST['product_id'] ?? 0);

    if (!$name) $errors[] = 'Product name is required.';
    if ($price <= 0) $errors[] = 'Price must be greater than 0.';

    if (empty($errors)) {
        if ($productId > 0) {
            $stmt = $db->prepare("UPDATE products SET name=?, barcode=?, category=?, price=?, cost_price=?, stock_quantity=?, low_stock_threshold=?, supplier=?, status=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND store_id=?");
            $stmt->execute([$name, $barcode ?: null, $category ?: null, $price, $cost_price, $stock_quantity, $low_stock_threshold, $supplier ?: null, $status, $productId, activeStoreId()]);
            logAction($db, 'product_update', 'product', $productId, 'Updated product: ' . $name . ' (price: ' . $price . ', stock: ' . $stock_quantity . ')');
            $success = 'Product updated successfully.';
            $editProduct = getProduct($db, $productId);
        } else {
            $stmt = $db->prepare("INSERT INTO products (name, barcode, category, price, cost_price, stock_quantity, low_stock_threshold, supplier, status, store_id) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$name, $barcode ?: null, $category ?: null, $price, $cost_price, $stock_quantity, $low_stock_threshold, $supplier ?: null, $status, activeStoreId()]);
            $newId = (int) $db->lastInsertId();
            logAction($db, 'product_create', 'product', $newId, 'Created product: ' . $name . ' (price: ' . $price . ', stock: ' . $stock_quantity . ')');
            $success = 'Product added successfully.';
            $editProduct = getProduct($db, $newId);
        }
    }
}

$p = $editProduct;
?>
<div class="page-header">
    <h1><i class="fas fa-<?= $p ? 'edit' : 'plus' ?>"></i> <?= $p ? 'Edit Product' : 'Add Product' ?></h1>
    <a href="index.php?page=products" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card">
    <form method="post">
        <input type="hidden" name="product_id" value="<?= (int) ($p['id'] ?? 0) ?>">
        <div class="form-row">
            <div class="form-group">
                <label for="name">Product Name *</label>
                <input type="text" id="name" name="name" class="form-control" required value="<?= e($p['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="barcode">Barcode / SKU</label>
                <input type="text" id="barcode" name="barcode" class="form-control" value="<?= e($p['barcode'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="category">Category</label>
                <input type="text" id="category" name="category" class="form-control" list="cat-list" value="<?= e($p['category'] ?? '') ?>">
                <datalist id="cat-list">
                    <?php foreach (getCategories($db) as $cat): ?>
                        <option value="<?= e($cat) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-group">
                <label for="supplier">Supplier</label>
                <input type="text" id="supplier" name="supplier" class="form-control" value="<?= e($p['supplier'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="price">Selling Price *</label>
                <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" required value="<?= e((string) ($p['price'] ?? '0')) ?>">
            </div>
            <div class="form-group">
                <label for="cost_price">Cost Price</label>
                <input type="number" id="cost_price" name="cost_price" class="form-control" step="0.01" min="0" value="<?= e((string) ($p['cost_price'] ?? '0')) ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="stock_quantity">Stock Quantity</label>
                <input type="number" id="stock_quantity" name="stock_quantity" class="form-control" min="0" value="<?= (int) ($p['stock_quantity'] ?? 0) ?>">
            </div>
            <div class="form-group">
                <label for="low_stock_threshold">Low Stock Threshold</label>
                <input type="number" id="low_stock_threshold" name="low_stock_threshold" class="form-control" min="1" value="<?= (int) ($p['low_stock_threshold'] ?? 10) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-control">
                <option value="active" <?= ($p['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($p['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> <?= $p ? 'Update Product' : 'Add Product' ?></button>
    </form>
</div>
