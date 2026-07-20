<?php

declare(strict_types=1);

$isSuper = isSuperAdmin();
$isStoreAdmin = isStoreAdmin();
$couponId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$editMode = $couponId > 0;

$coupon = [];
if ($editMode) {
    $coupon = getCoupon($db, $couponId);
    if (!$coupon) {
        $_SESSION['pos_flash'] = ['type' => 'danger', 'message' => 'Coupon not found.'];
        redirect('index.php?page=coupons');
    }
    // Check permissions
    if ($isStoreAdmin) {
        $userStoreId = currentUserStoreId();
        $couponStoreId = $coupon['store_id'] ? (int) $coupon['store_id'] : null;
        if ($couponStoreId !== null && $couponStoreId !== $userStoreId) {
            accessDenied();
        }
    }
}

$stores = $isSuper ? getStores($db) : [];
$products = [];
$categories = [];

// Load products and categories for product/category-specific coupons
if ($isSuper || $isStoreAdmin) {
    $storeId = $editMode ? ((int) ($coupon['store_id'] ?? 0) ?: activeStoreId()) : activeStoreId();
    $stmt = $db->prepare("SELECT id, name FROM products WHERE status = 'active' AND store_id = ? ORDER BY name");
    $stmt->execute([$storeId]);
    $products = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' AND store_id = ? ORDER BY category");
    $stmt->execute([$storeId]);
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['_csrf'] ?? '';
    if (!validate_csrf($csrf)) {
        $errors[] = 'Invalid security token.';
    }

    $code = strtoupper(preg_replace('/\s+/', '', trim($_POST['code'] ?? '')));
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $discountType = $_POST['discount_type'] ?? 'percentage';
    $discountValue = (float) ($_POST['discount_value'] ?? 0);
    $minimumSpend = (float) ($_POST['minimum_spend'] ?? 0);
    $maximumDiscount = $_POST['maximum_discount'] !== '' ? (float) $_POST['maximum_discount'] : null;
    $storeId = $isSuper ? ((int) ($_POST['store_id'] ?? 0) ?: null) : currentUserStoreId();
    $appliesTo = $_POST['applies_to'] ?? 'entire_sale';
    $productId = $appliesTo === 'product' ? ((int) ($_POST['product_id'] ?? 0) ?: null) : null;
    $categoryId = $appliesTo === 'category' ? (trim($_POST['category_id'] ?? '') ?: null) : null;
    $usageLimit = (int) ($_POST['usage_limit'] ?? 0);
    $startDate = trim($_POST['start_date'] ?? '') ?: null;
    $endDate = trim($_POST['end_date'] ?? '') ?: null;
    $status = $_POST['status'] ?? 'active';

    // Validation
    if (!$code) $errors[] = 'Coupon code is required.';
    if (!preg_match('/^[A-Z0-9_-]+$/', $code)) $errors[] = 'Coupon code can only contain letters, numbers, hyphens, and underscores.';
    if (!$name) $errors[] = 'Coupon name is required.';
    if (!in_array($discountType, ['percentage', 'fixed'], true)) $errors[] = 'Invalid discount type.';
    if ($discountValue <= 0) $errors[] = 'Discount value must be greater than 0.';
    if ($discountType === 'percentage' && $discountValue > 100) $errors[] = 'Percentage discount cannot exceed 100%.';
    if ($maximumDiscount !== null && $maximumDiscount < 0) $errors[] = 'Maximum discount cannot be negative.';

    // Check unique code (excluding current coupon)
    $stmt = $db->prepare("SELECT id FROM discount_coupons WHERE code = ?" . ($editMode ? " AND id != ?" : ""));
    $params = [$code];
    if ($editMode) $params[] = $couponId;
    $stmt->execute($params);
    if ($stmt->fetch()) $errors[] = 'Coupon code "' . e($code) . '" already exists. Please use a different code.';

    if (empty($errors)) {
        if ($editMode) {
            $sql = "UPDATE discount_coupons SET code=?, name=?, description=?, discount_type=?, discount_value=?, minimum_spend=?, maximum_discount=?, store_id=?, applies_to=?, product_id=?, category_id=?, usage_limit=?, start_date=?, end_date=?, status=? WHERE id=?";
            $db->prepare($sql)->execute([$code, $name, $description, $discountType, $discountValue, $minimumSpend, $maximumDiscount, $storeId, $appliesTo, $productId, $categoryId, $usageLimit, $startDate, $endDate, $status, $couponId]);
            logAction($db, 'coupon_updated', 'coupon', $couponId, 'Coupon ' . $code . ' updated');
            $success = 'Coupon updated successfully.';
        } else {
            $sql = "INSERT INTO discount_coupons (code, name, description, discount_type, discount_value, minimum_spend, maximum_discount, store_id, applies_to, product_id, category_id, usage_limit, start_date, end_date, status, created_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $db->prepare($sql)->execute([$code, $name, $description, $discountType, $discountValue, $minimumSpend, $maximumDiscount, $storeId, $appliesTo, $productId, $categoryId, $usageLimit, $startDate, $endDate, $status, CURRENT_USER_ID]);
            $newId = (int) $db->lastInsertId();
            logAction($db, 'coupon_created', 'coupon', $newId, 'Coupon ' . $code . ' created');
            $_SESSION['pos_flash'] = ['type' => 'success', 'message' => 'Coupon created successfully.'];
            redirect('index.php?page=coupon-form&id=' . $newId);
        }
    }
}

// Load values for edit or after failed submission
$v = function($field) use ($coupon, $editMode) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return $_POST[$field] ?? '';
    }
    return $editMode ? ($coupon[$field] ?? '') : '';
};
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="page-header">
    <h1><i class="fas fa-tag"></i> <?= $editMode ? 'Edit Coupon: ' . e($coupon['code']) : 'New Coupon' ?></h1>
    <a href="index.php?page=coupons" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Coupons</a>
</div>

<div class="card max-w-lg">
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group">
                <label for="code">Coupon Code *</label>
                <input type="text" id="code" name="code" class="form-control" required maxlength="100" value="<?= e($v('code')) ?>" placeholder="e.g. SAVE10" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase().replace(/\\s/g, '')">
            </div>
            <div class="form-group">
                <label for="name">Coupon Name *</label>
                <input type="text" id="name" name="name" class="form-control" required maxlength="255" value="<?= e($v('name')) ?>" placeholder="e.g. 10% Off Sale">
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" class="form-control" rows="2" placeholder="Optional description of this coupon"><?= e($v('description')) ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="discount_type">Discount Type *</label>
                <select id="discount_type" name="discount_type" class="form-control" onchange="toggleDiscountType()">
                    <option value="percentage" <?= $v('discount_type') === 'percentage' ? 'selected' : '' ?>>Percentage (%)</option>
                    <option value="fixed" <?= $v('discount_type') === 'fixed' ? 'selected' : '' ?>>Fixed Amount (<?= CURRENCY ?>)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="discount_value">Discount Value *</label>
                <input type="number" id="discount_value" name="discount_value" class="form-control" step="0.01" min="0.01" required value="<?= e((string) ((float) ($v('discount_value') ?: 0))) ?>">
            </div>
        </div>

        <div class="form-row" id="max-discount-row">
            <div class="form-group">
                <label for="maximum_discount">Maximum Discount (optional)</label>
                <input type="number" id="maximum_discount" name="maximum_discount" class="form-control" step="0.01" min="0" value="<?= e($v('maximum_discount') !== '' && $v('maximum_discount') !== null ? (string) ((float) $v('maximum_discount')) : '') ?>" placeholder="Leave empty for no limit">
            </div>
            <div class="form-group">
                <label for="minimum_spend">Minimum Spend (optional)</label>
                <input type="number" id="minimum_spend" name="minimum_spend" class="form-control" step="0.01" min="0" value="<?= e((string) ((float) ($v('minimum_spend') ?: 0))) ?>" placeholder="<?= CURRENCY ?> 0.00">
            </div>
        </div>

        <?php if ($isSuper): ?>
        <div class="form-group">
            <label for="store_id">Store</label>
            <select id="store_id" name="store_id" class="form-control" onchange="updateStoreProducts()">
                <option value="0">All Stores (Super Admin only)</option>
                <?php foreach ($stores as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) ($v('store_id') ?: 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="fs-12 text-muted mt-4">Super Admin can create coupons for all stores or a specific store.</div>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="applies_to">Applies To</label>
            <select id="applies_to" name="applies_to" class="form-control" onchange="toggleAppliesTo()">
                <option value="entire_sale" <?= $v('applies_to') === 'entire_sale' ? 'selected' : '' ?>>Entire Sale</option>
                <option value="product" <?= $v('applies_to') === 'product' ? 'selected' : '' ?>>Specific Product</option>
                <option value="category" <?= $v('applies_to') === 'category' ? 'selected' : '' ?>>Specific Category</option>
            </select>
        </div>

        <div class="form-group" id="product-select-group" style="display:<?= $v('applies_to') === 'product' ? 'block' : 'none' ?>">
            <label for="product_id">Select Product</label>
            <select id="product_id" name="product_id" class="form-control">
                <option value="">-- Select Product --</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= (int) ($v('product_id') ?: 0) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" id="category-select-group" style="display:<?= $v('applies_to') === 'category' ? 'block' : 'none' ?>">
            <label for="category_id">Select Category</label>
            <select id="category_id" name="category_id" class="form-control">
                <option value="">-- Select Category --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>" <?= $v('category_id') === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="usage_limit">Usage Limit (0 = unlimited)</label>
                <input type="number" id="usage_limit" name="usage_limit" class="form-control" min="0" value="<?= e((string) ((int) ($v('usage_limit') ?: 0))) ?>">
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="active" <?= $v('status') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $v('status') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" class="form-control" value="<?= e($v('start_date')) ?>">
            </div>
            <div class="form-group">
                <label for="end_date">End Date / Expiry</label>
                <input type="date" id="end_date" name="end_date" class="form-control" value="<?= e($v('end_date')) ?>">
            </div>
        </div>

        <div class="d-flex gap-8">
            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> <?= $editMode ? 'Update Coupon' : 'Create Coupon' ?></button>
            <a href="index.php?page=coupons" class="btn btn-outline"><i class="fas fa-times"></i> Cancel</a>
        </div>
    </form>
</div>

<script>
function toggleDiscountType() {
    const type = document.getElementById('discount_type').value;
    const valLabel = document.querySelector('label[for="discount_value"]');
    if (type === 'percentage') {
        valLabel.textContent = 'Discount Value (%) *';
        document.getElementById('discount_value').setAttribute('max', '100');
    } else {
        valLabel.textContent = 'Discount Value (<?= CURRENCY ?>) *';
        document.getElementById('discount_value').removeAttribute('max');
    }
}

function toggleAppliesTo() {
    const val = document.getElementById('applies_to').value;
    document.getElementById('product-select-group').style.display = val === 'product' ? 'block' : 'none';
    document.getElementById('category-select-group').style.display = val === 'category' ? 'block' : 'none';
}

function updateStoreProducts() {
    // Reload page with selected store for product/category lists
    // Simple approach: show a note that products will filter on save
}

<?php if (!$editMode): ?>
toggleDiscountType();
<?php endif; ?>
</script>
