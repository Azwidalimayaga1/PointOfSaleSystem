<?php

declare(strict_types=1);

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$stockFilter = $_GET['stock'] ?? '';

$products = getProducts($db, $search, $category, $stockFilter);
$categories = getCategories($db);
?>
<div class="page-header">
    <h1><i class="fas fa-box"></i> Products</h1>
    <a href="index.php?page=product-form" class="btn btn-primary">
        <i class="fas fa-plus"></i> Add Product
    </a>
</div>

<div class="card">
    <form method="get" class="search-bar">
        <input type="hidden" name="page" value="products">
        <input type="text" name="search" class="form-control" placeholder="Search by name or barcode..." value="<?= e($search) ?>">
        <select name="category" class="form-control">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="stock" class="form-control">
            <option value="">All Stock</option>
            <option value="low" <?= $stockFilter === 'low' ? 'selected' : '' ?>>Low Stock</option>
            <option value="out" <?= $stockFilter === 'out' ? 'selected' : '' ?>>Out of Stock</option>
        </select>
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
        <a href="index.php?page=products" class="btn btn-outline"><i class="fas fa-times"></i> Clear</a>
    </form>
</div>

<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Barcode</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Cost</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--gray-400)">No products found.</td></tr>
                <?php endif; ?>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><strong><?= e($p['name']) ?></strong></td>
                        <td><?= e($p['barcode'] ?? '-') ?></td>
                        <td><span class="badge badge-gray"><?= e($p['category'] ?? '-') ?></span></td>
                        <td><?= money((float) $p['price']) ?></td>
                        <td><?= money((float) $p['cost_price']) ?></td>
                        <td>
                            <?php if ((int) $p['stock_quantity'] === 0): ?>
                                <span class="badge badge-danger">Out</span>
                            <?php elseif ((int) $p['stock_quantity'] <= (int) $p['low_stock_threshold']): ?>
                                <span class="badge badge-warning"><?= (int) $p['stock_quantity'] ?></span>
                            <?php else: ?>
                                <span class="badge badge-success"><?= (int) $p['stock_quantity'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $p['status'] === 'active' ? 'badge-success' : 'badge-gray' ?>">
                                <?= e(ucfirst($p['status'] ?? 'active')) ?>
                            </span>
                        </td>
                        <td>
                            <a href="index.php?page=product-form&id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="index.php?page=stock-adjustment&id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-warning">
                                <i class="fas fa-cubes"></i>
                            </a>
                            <form method="post" action="index.php?page=product-form" style="display:inline" onsubmit="return confirm('Delete this product?')">
                                <input type="hidden" name="delete_id" value="<?= (int) $p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
