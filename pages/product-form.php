<?php

declare(strict_types=1);

$editProduct = null;
$errors = [];
$success = '';

// Handle delete
if (isset($_POST['delete_id'])) {
    requireRole('super_admin', 'store_admin', 'manager');
    if (!validate_csrf($_POST['_csrf'] ?? '')) {
        redirect('index.php?page=products');
    }
    $delProduct = getProduct($db, (int) $_POST['delete_id']);
    if (isStoreAdmin() && $delProduct && (int) $delProduct['store_id'] !== currentUserStoreId()) {
        accessDenied();
    }
    $stmt = $db->prepare("DELETE FROM products WHERE id = ? AND store_id = ?");
    $stmt->execute([(int) $_POST['delete_id'], activeStoreId()]);
    logAction($db, 'product_delete', 'product', (int) $_POST['delete_id'], 'Deleted product: ' . ($delProduct['name'] ?? ''));
    redirect('index.php?page=products');
}

// Load product for editing
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    $editProduct = getProduct($db, $id);
    // Store admin can only edit products in their own store
    if ($editProduct && isStoreAdmin() && (int) $editProduct['store_id'] !== currentUserStoreId()) {
        accessDenied();
    }
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id'])) {
    if (!validate_csrf($_POST['_csrf'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $barcodeType = trim($_POST['barcode_type'] ?? '');
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
            if (isStoreAdmin()) {
                $stmt = $db->prepare("UPDATE products SET name=?, barcode=?, category=?, price=?, cost_price=?, stock_quantity=?, low_stock_threshold=?, supplier=?, status=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND store_id=?");
                $stmt->execute([$name, $barcode ?: null, $category ?: null, $price, $cost_price, $stock_quantity, $low_stock_threshold, $supplier ?: null, $status, $productId, currentUserStoreId()]);
            } else {
                $stmt = $db->prepare("UPDATE products SET name=?, barcode=?, category=?, price=?, cost_price=?, stock_quantity=?, low_stock_threshold=?, supplier=?, status=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND store_id=?");
                $stmt->execute([$name, $barcode ?: null, $category ?: null, $price, $cost_price, $stock_quantity, $low_stock_threshold, $supplier ?: null, $status, $productId, activeStoreId()]);
            }
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
        // Sync barcode to product_barcodes table
        if ($barcode && $editProduct) {
            $pid = (int) $editProduct['id'];
            $sid = (int) $editProduct['store_id'];
            if (!$barcodeType) {
                $digits = preg_replace('/[^0-9]/', '', $barcode);
                $len = strlen($barcode);
                if (ctype_digit($barcode)) {
                    if ($len === 13) $barcodeType = 'EAN-13';
                    elseif ($len === 12) $barcodeType = 'UPC-A';
                    elseif ($len === 8) $barcodeType = 'EAN-8';
                    elseif ($len === 14) $barcodeType = 'ITF-14';
                    else $barcodeType = 'Code 128';
                } else {
                    $barcodeType = (strlen($barcode) <= 20) ? 'Code 39' : 'Code 128';
                }
            }
            $existing = $db->prepare("SELECT id FROM product_barcodes WHERE product_id = ? AND store_id = ? AND barcode = ?");
            $existing->execute([$pid, $sid, $barcode]);
            if (!$existing->fetch()) {
                $db->prepare("INSERT INTO product_barcodes (product_id, store_id, barcode, barcode_type, is_primary, created_by) VALUES (?,?,?,?,1,?)")
                    ->execute([$pid, $sid, $barcode, $barcodeType, CURRENT_USER_ID]);
                logBarcodeAction($db, $pid, (int) $db->lastInsertId(), 'barcode_create', null, $barcode, $sid, 'Created ' . $barcodeType . ' barcode via product form');
            }
        }
    }
}

$p = $editProduct;

// Load existing barcodes for this product
$existingBarcodes = [];
if ($p) {
    $existingBarcodes = getProductAllBarcodes($db, (int) $p['id'], isSuperAdmin() ? null : activeStoreId());
}
?>
<div class="page-header">
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
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int) ($p['id'] ?? 0) ?>">
        <div class="form-row">
            <div class="form-group">
                <label for="name">Product Name *</label>
                <input type="text" id="name" name="name" class="form-control" required value="<?= e($p['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="barcode">Barcode</label>
                <div style="display:flex;gap:8px;align-items:center">
                    <input type="text" id="barcode" name="barcode" class="form-control" value="<?= e($p['barcode'] ?? '') ?>" placeholder="Type or scan a barcode" style="flex:1;min-width:0" inputmode="barcode">
                    <input type="hidden" id="barcode_type" name="barcode_type" value="">
                    <button type="button" id="btn-scan-barcode" class="btn btn-primary" onclick="openProductBarcodeScanner()" title="Scan with camera" style="white-space:nowrap;flex-shrink:0;display:flex;align-items:center;gap:6px">
                        <span id="scan-status-dot" class="scan-dot-idle"></span>
                        <i class="fas fa-camera"></i> Scan
                    </button>
                </div>
                <div id="barcode-hint" class="fs-12 text-muted" style="margin-top:4px;min-height:18px">
                    <span id="barcode-hint-text">Type a barcode or click Scan to use the camera</span>
                </div>
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
        <div class="form-group">
            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> <?= $p ? 'Update Product' : 'Add Product' ?></button>
        </div>
    </form>
</div>

<?php if ($p): ?>
<!-- Barcode Management Section -->
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-barcode"></i> Barcode Management</h2>
        <div class="d-flex gap-6">
            <button type="button" class="btn btn-primary btn-sm" onclick="openProductBarcodeScanner()"><i class="fas fa-camera"></i> Scan</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="generateBarcode(<?= (int) $p['id'] ?>)"><i class="fas fa-wand-magic"></i> Auto-Generate</button>
            <?php if ($p['barcode']): ?>
            <a href="index.php?page=barcode-print&product_id=<?= (int) $p['id'] ?>&count=1" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-print"></i> Print Label</a>
            <?php endif; ?>
        </div>
    </div>

    <div id="barcode-validation-msg" class="d-none"></div>

    <div class="barcode-form-row">
        <div class="form-group barcode-value-group">
            <label for="barcode-input">Barcode Value</label>
            <div class="d-flex gap-6">
                <input type="text" id="barcode-input" class="form-control" placeholder="Enter or scan barcode">
                <button type="button" class="btn btn-primary" onclick="openProductBarcodeScanner()" title="Scan with camera" style="white-space:nowrap;flex-shrink:0"><i class="fas fa-camera"></i></button>
            </div>
        </div>
        <div class="form-group barcode-type-group">
            <label for="barcode-type">Barcode Type</label>
            <select id="barcode-type" class="form-control">
                <?php foreach (getRecommendedBarcodeTypes() as $bt => $bl): ?>
                <option value="<?= e($bt) ?>" <?= $bt === 'Code 128' ? 'selected' : '' ?>><?= e($bl) ?></option>
                <?php endforeach; ?>
                <option disabled>──────────</option>
                <?php foreach (getBarcodeTypes() as $bt => $bl): ?>
                <option value="<?= e($bt) ?>"><?= e($bt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="d-flex gap-6 mb-16 flex-wrap">
        <button type="button" class="btn btn-primary btn-sm" onclick="addBarcode(<?= (int) $p['id'] ?>)" id="btn-add-barcode"><i class="fas fa-plus"></i> Add Barcode</button>
        <button type="button" class="btn btn-outline btn-sm" onclick="generateBarcodePreview()"><i class="fas fa-image"></i> Preview</button>
        <button type="button" class="btn btn-outline btn-sm" onclick="generateBarcode(<?= (int) $p['id'] ?>)"><i class="fas fa-wand-magic"></i> Auto-Generate</button>
        <button type="button" class="btn btn-outline btn-sm" onclick="validateBarcodeInput()"><i class="fas fa-check-circle"></i> Validate</button>
    </div>

    <div id="barcode-preview-area" class="barcode-preview-area d-none">
        <div class="barcode-preview-header">Barcode Preview</div>
        <div id="barcode-preview" class="barcode-preview"></div>
    </div>

    <?php if (!empty($existingBarcodes)): ?>
    <div class="existing-barcodes">
        <div class="barcode-preview-header">Existing Barcodes</div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Barcode</th>
                        <th>Type</th>
                        <th>Primary</th>
                        <th>Created By</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($existingBarcodes as $eb): ?>
                    <tr>
                        <td><strong><?= e($eb['barcode']) ?></strong></td>
                        <td><span class="badge badge-info"><?= e($eb['barcode_type']) ?></span></td>
                        <td>
                            <?php if ($eb['is_primary']): ?>
                            <span class="badge badge-success"><i class="fas fa-check"></i> Primary</span>
                            <?php else: ?>
                            <button class="btn btn-sm btn-ghost" onclick="setPrimaryBarcode(<?= (int) $p['id'] ?>, <?= (int) $eb['id'] ?>)" title="Set as primary"><i class="fas fa-star"></i></button>
                            <?php endif; ?>
                        </td>
                        <td class="fs-12 text-muted"><?= e($eb['created_by_name'] ?? 'System') ?></td>
                        <td class="fs-12 text-muted"><?= e(date('Y-m-d', strtotime($eb['created_at']))) ?></td>
                        <td>
                            <div class="d-flex gap-4">
                                <button class="btn btn-sm btn-ghost" onclick="previewExistingBarcode('<?= e($eb['barcode']) ?>', '<?= e($eb['barcode_type']) ?>')" title="Preview"><i class="fas fa-eye"></i></button>
                                <a href="index.php?page=barcode-print&product_id=<?= (int) $p['id'] ?>&count=1" target="_blank" class="btn btn-sm btn-ghost" title="Print label"><i class="fas fa-print"></i></a>
                                <button class="btn btn-sm btn-ghost text-danger" onclick="deleteBarcode(<?= (int) $eb['id'] ?>, <?= (int) $p['id'] ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="barcode-empty-state">
        <i class="fas fa-barcode"></i>
        <p>No barcodes assigned yet. Scan, type, or auto-generate a barcode above.</p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Barcode Scanner Modal (shared with product barcode) -->
<div class="modal-overlay" id="scanner-modal">
    <div class="modal" style="max-width:500px;text-align:center">
        <h3><i class="fas fa-camera"></i> Scan Barcode</h3>
        <div id="scanner-viewport" style="position:relative;width:100%;max-width:400px;margin:12px auto;border-radius:8px;overflow:hidden;background:#000"></div>
        <div id="scanner-status" class="fs-13 text-muted mb-12">Position a barcode in front of the camera</div>
        <div class="d-flex gap-10 justify-center">
            <button class="btn btn-outline" onclick="closeProductBarcodeScanner()"><i class="fas fa-times"></i> Cancel</button>
        </div>
    </div>
</div>

<script src="js/quagga2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
let _productScannerRunning = false;
let _barcodeAddCallback = null;
let _productCameraCandidate = '';
let _productCameraCandidateHits = 0;
let _productDetectionHandler = null;

function stopProductBarcodeScanner() {
    if (_productDetectionHandler && typeof Quagga.offDetected === 'function') {
        Quagga.offDetected(_productDetectionHandler);
    }
    _productDetectionHandler = null;
    if (_productScannerRunning) {
        try { Quagga.stop(); } catch (e) {}
        _productScannerRunning = false;
    }
}

function openProductBarcodeScanner(callback) {
    _barcodeAddCallback = callback || null;
    const modal = document.getElementById('scanner-modal');
    modal.classList.add('show');
    document.getElementById('scanner-status').textContent = 'Starting camera...';

    if (_productScannerRunning) return;
    _productScannerRunning = true;
    _productCameraCandidate = '';
    _productCameraCandidateHits = 0;

    Quagga.init({
        inputStream: {
            name: 'Live',
            type: 'LiveStream',
            target: document.getElementById('scanner-viewport'),
            constraints: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
            area: { top: '20%', right: '10%', left: '10%', bottom: '20%' }
        },
        locator: { patchSize: 'medium', halfSample: false },
        numOfWorkers: Math.min(4, navigator.hardwareConcurrency || 2),
        decoder: { readers: ['ean_reader', 'ean_8_reader', 'code_128_reader', 'code_39_reader', 'code_93_reader', 'codabar_reader', 'upc_reader', 'upc_e_reader', 'i2of5_reader'] },
        locate: true
    }, function (err) {
        if (err) {
            document.getElementById('scanner-status').textContent = 'Camera error: ' + (err.message || 'Unable to access camera.');
            _productScannerRunning = false;
            return;
        }
        document.getElementById('scanner-status').textContent = 'Position a barcode in front of the camera';
        Quagga.start();
    });

    _productDetectionHandler = function (data) {
        const code = data.codeResult.code;
        if (!code) return;
        if (code === _productCameraCandidate) _productCameraCandidateHits++;
        else {
            _productCameraCandidate = code;
            _productCameraCandidateHits = 1;
        }
        if (_productCameraCandidateHits < 2) {
            document.getElementById('scanner-status').textContent = 'Confirming barcode…';
            return;
        }
        stopProductBarcodeScanner();
        document.getElementById('scanner-status').textContent = 'Scanned: ' + code;
        document.getElementById('scanner-modal').classList.remove('show');
        document.getElementById('barcode').value = code;
        var detected = detectBarcodeType(code);
        if (detected) document.getElementById('barcode_type').value = detected;
        updateScanStatus(code, detected);
        if (_barcodeAddCallback) {
            _barcodeAddCallback(code);
            _barcodeAddCallback = null;
        } else {
            if (document.getElementById('barcode-input')) {
                document.getElementById('barcode-input').value = code;
            }
        }
    };
    Quagga.onDetected(_productDetectionHandler);
}

function closeProductBarcodeScanner() {
    stopProductBarcodeScanner();
    document.getElementById('scanner-modal').classList.remove('show');
}

function validateBarcodeInput() {
    const barcode = document.getElementById('barcode-input').value.trim() || document.getElementById('barcode').value.trim();
    const type = document.getElementById('barcode-type').value;
    if (!barcode) {
        showToast('Enter a barcode value first.', 'warning');
        return;
    }
    showToast('Validating barcode...', 'info');
    const formData = new FormData();
    formData.append('barcode', barcode);
    formData.append('barcode_type', type);

    fetch('index.php?page=barcode-validate', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            const msgArea = document.getElementById('barcode-validation-msg');
            msgArea.classList.remove('d-none');
            if (data.valid) {
                if (data.duplicate) {
                    msgArea.className = 'alert alert-warning';
                    msgArea.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Barcode format is valid but already exists for another product in this store.';
                } else {
                    msgArea.className = 'alert alert-success';
                    msgArea.innerHTML = '<i class="fas fa-check-circle"></i> Barcode is valid. Length: ' + data.length + ' chars.';
                }
            } else {
                msgArea.className = 'alert alert-danger';
                msgArea.innerHTML = '<i class="fas fa-times-circle"></i> ' + data.message;
            }
        })
        .catch(() => showToast('Validation failed.', 'danger'));
}

function generateBarcodePreview() {
    const barcode = document.getElementById('barcode-input').value.trim() || document.getElementById('barcode').value.trim();
    const type = document.getElementById('barcode-type').value;
    if (!barcode) {
        showToast('Enter a barcode value first.', 'warning');
        return;
    }
    const previewArea = document.getElementById('barcode-preview-area');
    const preview = document.getElementById('barcode-preview');
    previewArea.classList.remove('d-none');

    if (type === 'QR Code' || type === 'DataMatrix' || type === 'Aztec Code' || type === 'MaxiCode' || type === 'PDF417') {
        preview.innerHTML = '<div class="barcode-2d-placeholder"><i class="fas fa-qrcode"></i><span>' + escHtml(barcode) + ' (' + type + ')</span></div>';
    } else {
        try {
            preview.innerHTML = '<svg id="barcode-svg-preview"></svg>';
            JsBarcode('#barcode-svg-preview', barcode, {
                format: type.toLowerCase().replace(/-/g, '').replace(' ', ''),
                width: 2,
                height: 60,
                displayValue: true,
                fontSize: 14,
                margin: 10,
            });
        } catch (e) {
            preview.innerHTML = '<div class="barcode-2d-placeholder"><i class="fas fa-barcode"></i><span>' + escHtml(barcode) + ' (' + type + ' - preview limited)</span></div>';
        }
    }
}

function detectBarcodeType(barcode) {
    if (!barcode) return '';
    var digits = barcode.replace(/[^0-9]/g, '');
    var len = barcode.length;
    if (/^[0-9]+$/.test(barcode)) {
        if (len === 13) return digits.substring(0, 3) === '978' || digits.substring(0, 3) === '979' ? 'ISBN' : 'EAN-13';
        if (len === 12) return 'UPC-A';
        if (len === 8) return 'EAN-8';
        if (len === 14) return 'ITF-14';
        if (len === 10 && /^[0-9X]+$/i.test(barcode)) return 'ISBN';
        if (len === 6) return 'UPC-E';
    }
    if (/^[A-D].*[A-D]$/.test(barcode)) return 'Codabar';
    return len <= 20 ? 'Code 39' : 'Code 128';
}

function updateScanStatus(barcode, type) {
    var dot = document.getElementById('scan-status-dot');
    var hint = document.getElementById('barcode-hint-text');
    if (!barcode) {
        dot.className = 'scan-dot-idle';
        hint.textContent = 'Type a barcode or click Scan to use the camera';
        return;
    }
    dot.className = 'scan-dot-success';
    hint.innerHTML = '<span style="color:#16a34a">\u2713 Barcode: ' + escHtml(barcode) + (type ? ' (' + type + ')' : '') + '</span>';
    setTimeout(function() { dot.className = 'scan-dot-idle'; }, 3000);
}

document.getElementById('barcode').addEventListener('input', function() {
    var val = this.value.trim();
    var typeSel = document.getElementById('barcode_type');
    if (val) {
        var detected = detectBarcodeType(val);
        typeSel.value = detected || '';
        updateScanStatus(val, detected);
    } else {
        typeSel.value = '';
        updateScanStatus('', '');
    }
});

document.getElementById('barcode-input').addEventListener('input', function() {
    var val = this.value.trim();
    if (val) {
        var detected = detectBarcodeType(val);
        var sel = document.getElementById('barcode-type');
        if (detected) sel.value = detected;
        var previewArea = document.getElementById('barcode-preview-area');
        if (previewArea.classList.contains('d-none')) {
            generateBarcodePreview();
        }
    }
});

function previewExistingBarcode(barcode, type) {
    document.getElementById('barcode-input').value = barcode;
    document.getElementById('barcode-type').value = type;
    generateBarcodePreview();
}

function addBarcode(productId) {
    const barcode = document.getElementById('barcode-input').value.trim();
    const type = document.getElementById('barcode-type').value;
    if (!barcode) {
        showToast('Enter a barcode value first.', 'warning');
        return;
    }
    if (!productId) {
        showToast('Save the product first, then add barcodes.', 'warning');
        return;
    }

    const btn = document.getElementById('btn-add-barcode');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const formData = new FormData();
    formData.append('action', 'save');
    formData.append('product_id', productId.toString());
    formData.append('barcode', barcode);
    formData.append('barcode_type', type);
    var hasBarcodes = <?= !empty($existingBarcodes) ? 'true' : 'false' ?>;
    formData.append('is_primary', hasBarcodes ? '0' : '1');
    formData.append('generate_image', '1');
    formData.append('_csrf', '<?= csrf_token() ?>');

    fetch('index.php?page=barcode-save', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus"></i> Add Barcode';
            if (data.success) {
                showToast('Barcode added: ' + data.barcode, 'success');
                document.getElementById('barcode').value = barcode;
                setTimeout(function() { location.reload(); }, 800);
            } else {
                showToast(data.message || 'Failed to save barcode.', 'danger');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus"></i> Add Barcode';
            showToast('Network error.', 'danger');
        });
}

function generateBarcode(productId) {
    if (!productId) {
        showToast('Save the product first.', 'warning');
        return;
    }
    const type = document.getElementById('barcode-type').value;
    showToast('Generating barcode...', 'info');

    const formData = new FormData();
    formData.append('action', 'generate');
    formData.append('product_id', productId.toString());
    formData.append('barcode_type', type);
    formData.append('auto_generate', '1');
    formData.append('_csrf', '<?= csrf_token() ?>');

    fetch('index.php?page=barcode-generate', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('barcode-input').value = data.barcode;
                document.getElementById('barcode').value = data.barcode;
                generateBarcodePreview();
                showToast('Barcode generated: ' + data.barcode, 'success');
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                showToast(data.message || 'Generation failed.', 'danger');
            }
        })
        .catch(() => showToast('Network error.', 'danger'));
}

function setPrimaryBarcode(productId, barcodeId) {
    if (!confirm('Set this as the primary barcode?')) return;
    const formData = new FormData();
    formData.append('action', 'set_primary');
    formData.append('product_id', productId.toString());
    formData.append('barcode_id', barcodeId.toString());
    formData.append('_csrf', '<?= csrf_token() ?>');

    fetch('index.php?page=barcode-save', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Primary barcode updated.', 'success');
                setTimeout(function() { location.reload(); }, 500);
            } else {
                showToast(data.message || 'Failed to update.', 'danger');
            }
        })
        .catch(() => showToast('Network error.', 'danger'));
}

function deleteBarcode(barcodeId, productId) {
    if (!confirm('Delete this barcode?')) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('product_id', productId.toString());
    formData.append('barcode_id', barcodeId.toString());
    formData.append('_csrf', '<?= csrf_token() ?>');

    fetch('index.php?page=barcode-save', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Barcode deleted.', 'success');
                setTimeout(function() { location.reload(); }, 500);
            } else {
                showToast(data.message || 'Failed to delete.', 'danger');
            }
        })
        .catch(() => showToast('Network error.', 'danger'));
}

function escHtml(s) {
    var div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
}

function showToast(message, type) {
    var container = document.querySelector('.toast-container');
    if (!container) {
        var div = document.createElement('div');
        div.className = 'toast-container';
        document.body.appendChild(div);
        container = div;
    }
    var toast = document.createElement('div');
    toast.className = 'toast ' + (type || 'success');
    toast.innerHTML = message;
    container.appendChild(toast);
    setTimeout(function() { toast.style.opacity = '0'; setTimeout(function() { toast.remove(); }, 300); }, 4000);
}
</script>
