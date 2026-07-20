<?php

declare(strict_types=1);

// Load POS customization from store settings
$posStoreSettings = getStoreSettings($db, activeStoreId());
$posEnableDiscounts = (int) ($posStoreSettings['enable_discounts'] ?? 1);
$posEnableCoupons = (int) ($posStoreSettings['enable_coupons'] ?? 1);
$posEnableHeldSales = (int) ($posStoreSettings['enable_held_sales'] ?? 1);
$posEnableSaleSound = (int) ($posStoreSettings['enable_sale_sound'] ?? 1);
$posSaleSoundVolume = min(100, max(0, (int) ($posStoreSettings['sale_sound_volume'] ?? 50)));
$posShowImages = (int) ($posStoreSettings['show_product_images_on_pos'] ?? 1);
$posGridSize = $posStoreSettings['product_grid_size'] ?? 'medium';
$posDefaultCategory = $posStoreSettings['default_category'] ?? '';
$posAutoFocusBarcode = (int) ($posStoreSettings['auto_focus_barcode'] ?? 1);
$posEnableCash = (int) ($posStoreSettings['enable_cash_payments'] ?? 1);
$posEnableCard = (int) ($posStoreSettings['enable_card_payments'] ?? 1);
$posEnableMobile = (int) ($posStoreSettings['enable_mobile_payments'] ?? 1);
$posDefaultPaymentMethod = $posStoreSettings['default_payment_method'] ?? 'cash';
$posDiscountMode = $posStoreSettings['discount_mode'] ?? 'both';
$posCashierCanDiscount = (int) ($posStoreSettings['cashier_can_apply_discounts'] ?? 0);
$posCashierCanHold = (int) ($posStoreSettings['cashier_can_hold_sales'] ?? 1);
$posCashierCanViewStock = (int) ($posStoreSettings['cashier_can_view_stock'] ?? 0);

$products = $db->prepare("SELECT * FROM products WHERE status = 'active' AND store_id = ? ORDER BY name ASC");
$products->execute([activeStoreId()]);
$products = $products->fetchAll();
$categories = $db->prepare("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' AND status = 'active' AND store_id = ? ORDER BY category");
$categories->execute([activeStoreId()]);
$categories = $categories->fetchAll(PDO::FETCH_COLUMN);

// Flash messages
$flash = $_SESSION['pos_flash'] ?? null;
unset($_SESSION['pos_flash']);
?>
<?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>">
        <?= e($flash['message']) ?>
        <?php if (isset($flash['receipt_id'])): ?>
            <a href="index.php?page=receipt&id=<?= (int) $flash['receipt_id'] ?>" style="margin-left:12px;font-weight:700;color:inherit">
                <i class="fas fa-receipt"></i> View Receipt
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<section class="sales-workspace-header" aria-labelledby="sales-workspace-title">
    <div>
        <p class="workspace-eyebrow"><i class="fas fa-circle" aria-hidden="true"></i> Point of Sale</p>
        <h1 id="sales-workspace-title">Build a new sale</h1>
        <p>Search, scan, or select a product to begin.</p>
    </div>
    <div class="sales-workspace-actions">
        <?php if ($posEnableHeldSales && $posCashierCanHold): ?>
        <a href="index.php?page=held-sales" class="btn btn-outline"><i class="fas fa-pause-circle"></i> Held Sales</a>
        <?php endif; ?>
        <a href="index.php?page=products" class="btn btn-outline"><i class="fas fa-boxes"></i> Products</a>
    </div>
</section>

<!-- Barcode Scanner Bar -->
<div class="scanner-bar" id="scanner-bar">
    <div class="scanner-bar-left">
        <div class="scanner-status" id="scanner-status-indicator">
            <span class="status-dot active" id="scanner-dot"></span>
            <span id="scanner-status-text">Ready to scan</span>
        </div>
        <div class="scanner-input-group">
            <i class="fas fa-barcode scanner-input-icon"></i>
            <input type="text" id="global-barcode-input" class="form-control scanner-input" placeholder="Scan or type barcode..." autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
            <button class="btn btn-primary btn-sm" onclick="lookupManualBarcode()" title="Look up barcode"><i class="fas fa-search"></i></button>
            <button class="btn btn-outline btn-sm" onclick="openBarcodeScanner()" title="Scan with camera"><i class="fas fa-camera"></i></button>
        </div>
    </div>
    <div class="scanner-bar-right" id="last-scanned-preview" style="display:none">
        <div class="last-scanned">
            <span class="last-scanned-label">Last scanned:</span>
            <span class="last-scanned-name" id="last-scanned-name"></span>
            <span class="last-scanned-price" id="last-scanned-price"></span>
        </div>
    </div>
</div>

<!-- Hidden input that captures scanner focus -->
<input type="text" id="hidden-scanner-focus" style="position:fixed;left:-9999px;width:1px;height:1px;opacity:0" autocomplete="off">

<div class="sales-layout">
    <div>
        <div class="card">
            <div class="search-bar mb-16">
                <input type="text" id="product-search" class="form-control" placeholder="Search products by name or barcode..." oninput="filterProducts()" aria-label="Search products">
                <select id="category-filter" class="form-control" onchange="filterProducts()" aria-label="Category filter">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= $posDefaultCategory === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" id="btn-scan-barcode" onclick="openBarcodeScanner()" title="Scan barcode with camera" style="white-space:nowrap;flex-shrink:0">
                    <i class="fas fa-camera"></i> Scan
                </button>
            </div>
            <div class="product-grid product-grid-<?= e($posGridSize) ?>" id="product-grid">
                <?php foreach ($products as $p): ?>
                    <div class="product-card <?= (int) $p['stock_quantity'] <= 0 ? 'is-sold-out' : '' ?>" role="button" tabindex="<?= (int) $p['stock_quantity'] > 0 ? '0' : '-1' ?>" aria-label="Add <?= e($p['name']) ?> to sale, <?= money((float) $p['price']) ?>, <?= (int) $p['stock_quantity'] > 0 ? (int) $p['stock_quantity'] . ' in stock' : 'out of stock' ?>" aria-disabled="<?= (int) $p['stock_quantity'] <= 0 ? 'true' : 'false' ?>" data-name="<?= e(strtolower($p['name'])) ?>" data-barcode="<?= e(strtolower($p['barcode'] ?? '')) ?>" data-category="<?= e(strtolower($p['category'] ?? '')) ?>" onclick="addToCart(<?= (int) $p['id'] ?>, '<?= e($p['name']) ?>', <?= (float) $p['price'] ?>, <?= (int) $p['stock_quantity'] ?>)">
                        <?php if ($posShowImages && $p['image']): ?><img src="<?= e($p['image']) ?>" alt="" class="product-card-img"><?php endif; ?>
                        <h4><?= e($p['name']) ?></h4>
                        <div class="sku"><?php if ($p['barcode']): ?><span class="barcode-display"><?= e($p['barcode']) ?></span><?php else: ?><span class="text-muted">No barcode</span><?php endif; ?></div>
                        <div class="price"><?= money((float) $p['price']) ?></div>
                        <div class="stock <?= (int) $p['stock_quantity'] <= (int) $p['low_stock_threshold'] ? 'low' : 'ok' ?>">
                            <i class="fas fa-cube"></i> <?= (int) $p['stock_quantity'] ?> in stock
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="cart-panel" id="cart-panel">
        <div class="cart-header" aria-live="polite">
            <i class="fas fa-shopping-basket"></i> Current Sale
            <span class="cart-sale-count"><span id="cart-header-count">0</span> items</span>
        </div>
        <div class="cart-items" id="cart-items">
            <div class="cart-empty">
                <i class="fas fa-cart-plus"></i>
                <p>Click on a product to add it to the cart.</p>
            </div>
        </div>
        <div class="cart-summary" id="cart-summary" style="display:none">
            <div class="summary-row">
                <span>Items:</span>
                <span id="cart-count">0</span>
            </div>
            <div class="summary-row">
                <span>Subtotal:</span>
                <span id="cart-subtotal"><?= money(0) ?></span>
            </div>
            <div class="summary-row">
                <span>Discount (<span id="discount-pct">0</span>%):</span>
                <span id="cart-discount"><?= money(0) ?></span>
            </div>
            <div class="summary-row">
                <span>VAT (<?= TAX_RATE ?>%):</span>
                <span id="cart-tax"><?= money(0) ?></span>
            </div>
            <div class="summary-row total">
                <span>Total:</span>
                <span id="cart-total"><?= money(0) ?></span>
            </div>
        </div>
        <div class="cart-actions d-flex flex-col gap-8" id="cart-actions" style="display:none">
            <?php if ($posEnableDiscounts || $posEnableCoupons): ?>
            <!-- Coupon / Discount Section -->
            <div class="discount-section">
                <div class="discount-toggle" onclick="toggleDiscountSection()">
                    <i class="fas fa-tag"></i> <span>Add Coupon or Discount</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </div>
                <div class="discount-body" id="discount-body">
                    <?php if ($posEnableCoupons && $posDiscountMode !== 'manual_only'): ?>
                    <div class="form-group m-0">
                        <label for="coupon-input" class="fs-13">Coupon Code</label>
                        <div class="d-flex gap-6">
                            <input type="text" id="coupon-input" class="form-control" placeholder="Enter coupon code" style="padding:8px;text-transform:uppercase" onkeydown="if(event.key==='Enter'){event.preventDefault();applyCoupon()}">
                            <button class="btn btn-sm btn-primary" onclick="applyCoupon()" id="btn-apply-coupon" style="white-space:nowrap"><i class="fas fa-check"></i> Apply</button>
                        </div>
                        <div id="coupon-feedback" class="fs-12 mt-4"></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($posEnableDiscounts && $posDiscountMode !== 'coupon_only'): ?>
                    <div class="form-group m-0">
                        <label for="discount-input" class="fs-13">Manual Discount (%)</label>
                        <input type="number" id="discount-input" class="form-control" min="0" max="100" value="0" onchange="updateCart()" style="padding:8px" <?php if (isCashier() && !$posCashierCanDiscount): ?>disabled<?php endif; ?>>
                        <?php if (isCashier() && !$posCashierCanDiscount): ?>
                        <div class="fs-11 text-muted mt-2">Use a coupon code above. Manual discount requires admin.</div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <button class="btn btn-success justify-center" onclick="showPaymentModal()">
                <i class="fas fa-check"></i> Checkout
            </button>
            <?php if (isCashier() && $posEnableHeldSales && $posCashierCanHold): ?>
            <button class="hold-sale-btn" id="btn-hold-sale" onclick="holdSale()">
                <i class="fas fa-pause"></i> Hold Sale
            </button>
            <?php endif; ?>
            <button class="btn btn-danger justify-center" onclick="clearCart()">
                <i class="fas fa-trash"></i> Clear Cart
            </button>
        </div>
    </div>
</div>

<div id="sales-status" class="sr-only" aria-live="polite" aria-atomic="true"></div>

<?php if (isCashier()):
// Cashier POS dashboard panel: recent sales, today summary, quick actions
$cUserId = (int) CURRENT_USER_ID;
$cStoreId = activeStoreId();
$cRecentSales = getCashierRecentSales($db, $cUserId, $cStoreId, 5);
$cTodaySales = getCashierSalesStats($db, $cUserId, $cStoreId);
?>
<div class="pos-dashboard-panel">
    <div class="panel-card">
        <h3><i class="fas fa-clock"></i> Recent Sales</h3>
        <div>
            <?php if (empty($cRecentSales)): ?>
            <div class="fs-13 text-muted">No recent sales found.</div>
            <?php else: ?>
            <?php foreach ($cRecentSales as $rs): ?>
            <div class="pos-recent-sale-item">
                <div>
                    <a href="index.php?page=receipt&id=<?= (int) $rs['id'] ?>" class="sale-receipt"><strong>#<?= e($rs['receipt_number']) ?></strong></a>
                    <span class="sale-time"><?= e(date('g:i A', strtotime($rs['created_at']))) ?></span>
                </div>
                <span class="sale-total"><?= money((float) $rs['total']) ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            <div class="mt-8">
                <a href="index.php?page=cashier-stats" class="btn btn-sm btn-outline w-full justify-center"><i class="fas fa-chart-line"></i> View My Stats</a>
            </div>
        </div>
    </div>
    <div class="panel-card">
        <h3><i class="fas fa-chart-simple"></i> Today's Summary</h3>
        <div class="d-flex flex-col gap-6">
            <div class="flex-between fs-13">
                <span class="text-muted">Sales</span>
                <span class="fw-bold"><?= money($cTodaySales['today_sales']) ?></span>
            </div>
            <div class="flex-between fs-13">
                <span class="text-muted">Transactions</span>
                <span class="fw-bold"><?= $cTodaySales['today_transactions'] ?></span>
            </div>
            <div class="flex-between fs-13">
                <span class="text-muted">Avg Sale</span>
                <span class="fw-bold"><?= money($cTodaySales['average_sale']) ?></span>
            </div>
            <div class="flex-between fs-13">
                <span class="text-muted">Discounts Given</span>
                <span class="fw-bold text-success"><?= money($cTodaySales['today_discounts']) ?></span>
            </div>
            <div class="flex-between fs-13">
                <span class="text-muted">Held Sales</span>
                <span class="fw-bold"><?= $cTodaySales['held_sales'] ?></span>
            </div>
        </div>
        <div class="mt-12 d-flex gap-6">
            <a href="index.php?page=held-sales" class="btn btn-sm btn-outline flex-1 justify-center"><i class="fas fa-pause-circle"></i> Held Sales</a>
            <a href="index.php?page=calendar" class="btn btn-sm btn-outline flex-1 justify-center"><i class="fas fa-calendar-alt"></i> Reminders</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Payment Modal -->
<div class="modal-overlay" id="payment-modal">
    <div class="modal">
        <h3><i class="fas fa-credit-card"></i> Complete Payment</h3>
        <div id="payment-summary" class="mb-16"></div>
        <div class="form-group" role="radiogroup" aria-label="Payment method">
            <div class="payment-option">
                <?php if ($posEnableCash): ?>
                <input type="radio" name="payment_method" id="pm-cash" value="cash" <?= $posDefaultPaymentMethod === 'cash' ? 'checked' : '' ?> onchange="togglePayment()">
                <label for="pm-cash"><i class="fas fa-money-bill"></i> Cash</label>
                <?php endif; ?>
                <?php if ($posEnableCard): ?>
                <input type="radio" name="payment_method" id="pm-card" value="card" <?= $posDefaultPaymentMethod === 'card' ? 'checked' : '' ?> onchange="togglePayment()">
                <label for="pm-card"><i class="fas fa-credit-card"></i> Card</label>
                <?php endif; ?>
                <?php if ($posEnableMobile): ?>
                <input type="radio" name="payment_method" id="pm-mobile" value="mobile" <?= $posDefaultPaymentMethod === 'mobile' ? 'checked' : '' ?> onchange="togglePayment()">
                <label for="pm-mobile"><i class="fas fa-mobile-alt"></i> Mobile</label>
                <?php endif; ?>
                <?php if ($posEnableCash && $posEnableCard): ?>
                <input type="radio" name="payment_method" id="pm-mixed" value="mixed" <?= $posDefaultPaymentMethod === 'mixed' ? 'checked' : '' ?> onchange="togglePayment()">
                <label for="pm-mixed"><i class="fas fa-random"></i> Mixed</label>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-group" id="cash-input-group">
            <label for="cash-amount">Amount Tendered (Cash)</label>
            <input type="number" id="cash-amount" class="form-control" step="0.01" min="0" oninput="calcChange()">
        </div>
        <div class="mixed-inputs" id="mixed-inputs">
            <div class="form-group" style="margin:0">
                <label for="mixed-cash">Cash Amount</label>
                <input type="number" id="mixed-cash" class="form-control" step="0.01" min="0" oninput="calcChange()">
            </div>
            <div class="form-group" style="margin:0">
                <label for="mixed-card">Card Amount</label>
                <input type="number" id="mixed-card" class="form-control" step="0.01" min="0" oninput="calcChange()">
            </div>
        </div>
        <div id="change-display" style="margin-bottom:12px"></div>
        <div class="d-flex gap-10">
            <button class="btn btn-success flex-1 justify-center" onclick="completeSale()">
                <i class="fas fa-check"></i> Complete Sale
            </button>
            <button class="btn btn-outline" onclick="closePaymentModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
        </div>
    </div>
</div>

<!-- Barcode Scanner Modal -->
<div class="modal-overlay" id="scanner-modal">
    <div class="modal" style="max-width:500px;text-align:center">
        <h3><i class="fas fa-camera"></i> Scan Barcode</h3>
        <div id="scanner-viewport" style="position:relative;width:100%;max-width:400px;margin:12px auto;border-radius:8px;overflow:hidden;background:#000"></div>
        <div id="scanner-status" class="fs-13 text-muted mb-12">Position a barcode in front of the camera</div>
        <div class="d-flex gap-10 justify-center">
            <button class="btn btn-outline" onclick="closeBarcodeScanner()"><i class="fas fa-times"></i> Cancel</button>
        </div>
    </div>
</div>

<script src="js/quagga2.min.js"></script>
<script>
let cart = [];
let _activeCoupon = null;
let _scannerRunning = false;
let _barcodeBuffer = '';
let _barcodeTimer = null;
let _lastScanTime = 0;
let _lastScannedCode = '';
let _cameraCandidate = '';
let _cameraCandidateHits = 0;
let _cameraDetectionHandler = null;
const SCAN_DEBOUNCE_MS = 350;
const SCAN_INPUT_TIMEOUT = 100;

const productGrid = document.getElementById('product-grid');
if (productGrid) {
    productGrid.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const card = e.target.closest('.product-card');
        if (!card || card.getAttribute('aria-disabled') === 'true') return;
        e.preventDefault();
        card.click();
    });
}

function playScanSound(success) {
    try {
        var ctx = new (window.AudioContext || window.webkitAudioContext)();
        var now = ctx.currentTime;
        var vol = 0.3;
        var gainNode = ctx.createGain();
        gainNode.gain.setValueAtTime(vol, now);
        gainNode.gain.exponentialRampToValueAtTime(0.01, now + 0.5);
        gainNode.connect(ctx.destination);

        if (success) {
            [1200, 1500].forEach(function(freq, i) {
                var osc = ctx.createOscillator();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, now + i * 0.08);
                var oscGain = ctx.createGain();
                oscGain.gain.setValueAtTime(0.4, now + i * 0.08);
                oscGain.gain.exponentialRampToValueAtTime(0.01, now + i * 0.08 + 0.4);
                osc.connect(oscGain);
                oscGain.connect(gainNode);
                osc.start(now + i * 0.08);
                osc.stop(now + i * 0.08 + 0.5);
            });
        } else {
            var osc = ctx.createOscillator();
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(300, now);
            osc.frequency.exponentialRampToValueAtTime(100, now + 0.3);
            osc.connect(gainNode);
            osc.start(now);
            osc.stop(now + 0.3);
        }
    } catch (e) {}
}

function setScannerStatus(text, type) {
    var dot = document.getElementById('scanner-dot');
    var textEl = document.getElementById('scanner-status-text');
    textEl.textContent = text;
    dot.className = 'status-dot';
    if (type === 'success') dot.classList.add('success');
    else if (type === 'error') dot.classList.add('error');
    else if (type === 'busy') dot.classList.add('busy');
    else dot.classList.add('active');
}

function showLastScanned(name, price) {
    var preview = document.getElementById('last-scanned-preview');
    document.getElementById('last-scanned-name').textContent = name;
    document.getElementById('last-scanned-price').textContent = formatMoney(price);
    preview.style.display = 'block';
    preview.style.animation = 'none';
    setTimeout(function() { preview.style.animation = ''; }, 10);
}

function hideLastScanned() {
    document.getElementById('last-scanned-preview').style.display = 'none';
}

function refocusScanner() {
    var input = document.getElementById('global-barcode-input');
    if (input) {
        input.focus();
        input.select();
    }
}

// Global barcode scanner input handler
function handleBarcodeInput(value) {
    var code = value.trim();
    if (!code) return;

    // Suppress accidental duplicate reads, but never discard a different item
    // merely because it was scanned quickly after the prior one.
    var now = Date.now();
    if (code === _lastScannedCode && now - _lastScanTime < SCAN_DEBOUNCE_MS) return;
    _lastScannedCode = code;
    _lastScanTime = now;
    _barcodeBuffer = '';

    document.getElementById('global-barcode-input').value = '';
    setScannerStatus('Looking up...', 'busy');
    processBarcodeScan(code);
}

function processBarcodeScan(code) {
    var formData = new FormData();
    formData.append('barcode', code);

    fetch('index.php?page=barcode-lookup', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.found && data.product) {
                var p = data.product;
                addToCart(p.id, p.name, p.price, p.stock_quantity);
                setScannerStatus('Scanned: ' + escHtml(p.name), 'success');
                showLastScanned(p.name, p.price);
                showToast(escHtml(p.name) + ' - ' + formatMoney(p.price), 'success');
                playScanSound(true);
            } else {
                var msg = data.message || 'Product not found';
                setScannerStatus(msg, 'error');
                showToast(msg, 'danger');
                playScanSound(false);
            }
            setTimeout(refocusScanner, 100);
        })
        .catch(function() {
            setScannerStatus('Lookup error', 'error');
            showToast('Error looking up barcode.', 'danger');
            playScanSound(false);
            setTimeout(refocusScanner, 100);
        });
}

function lookupManualBarcode() {
    var input = document.getElementById('global-barcode-input');
    var code = input.value.trim();
    if (!code) {
        showToast('Enter a barcode to look up.', 'warning');
        return;
    }
    input.value = '';
    setScannerStatus('Looking up...', 'busy');
    processBarcodeScan(code);
}

// Listen for keyboard barcode scanner input
document.addEventListener('DOMContentLoaded', function() {
    var input = document.getElementById('global-barcode-input');
    if (input) {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleBarcodeInput(input.value);
            }
        });
        input.addEventListener('input', function() {
            _barcodeBuffer = input.value;
            if (_barcodeTimer) clearTimeout(_barcodeTimer);
            _barcodeTimer = setTimeout(function() {
                if (input.value.length > 5) {
                    handleBarcodeInput(input.value);
                }
            }, SCAN_INPUT_TIMEOUT);
        });
        setTimeout(function() { input.focus(); }, 300);
    }

    // Global key listener for scanner (works even when input not focused)
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey || e.altKey || e.metaKey) return;
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;

        var input = document.getElementById('global-barcode-input');
        if (!input) return;
        if (e.key === 'Enter') {
            if (_barcodeBuffer.length > 3) {
                e.preventDefault();
                handleBarcodeInput(_barcodeBuffer);
                _barcodeBuffer = '';
                input.value = '';
            }
            return;
        }
        if (e.key.length === 1) {
            _barcodeBuffer += e.key;
            // USB/Bluetooth scanners often send keystrokes while no field has
            // focus. Mirror those characters into the visible scan field.
            input.value = _barcodeBuffer;
            if (_barcodeTimer) clearTimeout(_barcodeTimer);
            _barcodeTimer = setTimeout(function() {
                if (_barcodeBuffer.length > 3) {
                    handleBarcodeInput(_barcodeBuffer);
                }
                _barcodeBuffer = '';
                input.value = '';
            }, 200);
        }
    });
});

function stopCameraScanner() {
    if (_cameraDetectionHandler && typeof Quagga.offDetected === 'function') {
        Quagga.offDetected(_cameraDetectionHandler);
    }
    _cameraDetectionHandler = null;
    if (_scannerRunning) {
        try { Quagga.stop(); } catch (e) {}
        _scannerRunning = false;
    }
}

function openBarcodeScanner() {
    const modal = document.getElementById('scanner-modal');
    modal.classList.add('show');
    document.getElementById('scanner-status').textContent = 'Starting camera...';

    if (_scannerRunning) return;
    _scannerRunning = true;
    _cameraCandidate = '';
    _cameraCandidateHits = 0;

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
            document.getElementById('scanner-status').textContent = 'Camera error: ' + (err.message || 'Unable to access camera. Check permissions.');
            _scannerRunning = false;
            return;
        }
        document.getElementById('scanner-status').textContent = 'Position a barcode in front of the camera';
        Quagga.start();
    });

    _cameraDetectionHandler = function (data) {
        const code = data.codeResult.code;
        if (!code) return;
        // A second matching camera read greatly reduces false positives from
        // low-quality frames without slowing a normal scan noticeably.
        if (code === _cameraCandidate) _cameraCandidateHits++;
        else {
            _cameraCandidate = code;
            _cameraCandidateHits = 1;
        }
        if (_cameraCandidateHits < 2) {
            document.getElementById('scanner-status').textContent = 'Confirming barcode…';
            return;
        }
        stopCameraScanner();
        document.getElementById('scanner-status').textContent = 'Scanned: ' + code;
        document.getElementById('scanner-modal').classList.remove('show');
        handleBarcodeInput(code);
    };
    Quagga.onDetected(_cameraDetectionHandler);
}

function closeBarcodeScanner() {
    stopCameraScanner();
    document.getElementById('scanner-modal').classList.remove('show');
}

function showToast(message, type) {
    var container = document.querySelector('.toast-container');
    if (!container) {
        var div = document.createElement('div');
        div.className = 'toast-container';
        document.body.appendChild(div);
    }
    var toast = document.createElement('div');
    toast.className = 'toast ' + (type || 'success');
    toast.innerHTML = message;
    document.querySelector('.toast-container').appendChild(toast);
    setTimeout(function() { toast.style.opacity = '0'; setTimeout(function() { toast.remove(); }, 300); }, 4000);
}

function toggleDiscountSection() {
    const body = document.getElementById('discount-body');
    const toggle = document.querySelector('.discount-toggle');
    body.classList.toggle('show');
    toggle.classList.toggle('open');
}

function applyCoupon() {
    const input = document.getElementById('coupon-input');
    const code = input.value.trim().toUpperCase();
    const feedback = document.getElementById('coupon-feedback');
    const btn = document.getElementById('btn-apply-coupon');

    if (!code) {
        feedback.innerHTML = '<span class="text-danger">Please enter a coupon code.</span>';
        return;
    }

    if (cart.length === 0) {
        feedback.innerHTML = '<span class="text-danger">Add items to cart first.</span>';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    const formData = new FormData();
    formData.append('action', 'validate_coupon');
    formData.append('coupon_code', code);
    formData.append('subtotal', (window._cartSubtotal || 0).toString());
    formData.append('_csrf', '<?= csrf_token() ?>');

    fetch('index.php?page=sales&action=ajax', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Apply';
            if (data.success) {
                _activeCoupon = {
                    code: data.coupon_code,
                    discount_amount: data.discount_amount,
                    discount_type: data.discount_type
                };
                feedback.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> ' + escHtml(data.message) + ' (-' + formatMoney(data.discount_amount) + ')</span> <button class="btn btn-sm btn-ghost text-danger" onclick="removeCoupon()" title="Remove coupon"><i class="fas fa-times"></i></button>';
                // Recalculate totals with coupon
                updateCartWithCoupon(data.discount_amount, data.final_total, code);
            } else {
                _activeCoupon = null;
                feedback.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle"></i> ' + escHtml(data.message) + '</span>';
                updateCart();
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Apply';
            feedback.innerHTML = '<span class="text-danger">An error occurred. Please try again.</span>';
        });
}

function removeCoupon() {
    _activeCoupon = null;
    document.getElementById('coupon-input').value = '';
    document.getElementById('coupon-feedback').innerHTML = '';
    updateCart();
}

function updateCartWithCoupon(discountAmount, finalTotal, code) {
    const subtotal = window._cartSubtotal || 0;
    const discount = discountAmount || 0;
    const afterDiscount = subtotal - discount;
    const tax = afterDiscount * (<?= TAX_RATE ?> / 100);
    const total = finalTotal + tax;

    document.getElementById('cart-discount').textContent = '-' + formatMoney(discount);
    document.getElementById('discount-pct').textContent = code;
    document.getElementById('cart-tax').textContent = formatMoney(tax);
    document.getElementById('cart-total').textContent = formatMoney(total);

    window._cartTotal = total;
    window._cartDiscount = discount;
    window._cartTax = tax;
    window._cartDiscountPct = 0;
    window._cartCouponCode = code;
}

function filterProducts() {
    const search = document.getElementById('product-search').value.toLowerCase();
    const cat = document.getElementById('category-filter').value.toLowerCase();
    document.querySelectorAll('.product-card').forEach(card => {
        const name = card.dataset.name;
        const barcode = card.dataset.barcode;
        const category = card.dataset.category;
        const matchSearch = !search || name.includes(search) || barcode.includes(search);
        const matchCat = !cat || category === cat;
        card.style.display = matchSearch && matchCat ? '' : 'none';
    });
}

function addToCart(id, name, price, stock) {
    if (stock <= 0) { alert('This product is out of stock.'); return; }
    const existing = cart.find(item => item.id === id);
    if (existing) {
        if (existing.qty >= stock) { alert('Not enough stock.'); return; }
        existing.qty++;
    } else {
        cart.push({ id, name, price, qty: 1, stock });
    }
    updateCart();
    announceSalesStatus(`${name} added to the current sale.`);
}

function updateCart() {
    const discountPct = parseFloat(document.getElementById('discount-input').value) || 0;
    const container = document.getElementById('cart-items');
    const summary = document.getElementById('cart-summary');
    const actions = document.getElementById('cart-actions');

    // Clear coupon when cart changes
    if (_activeCoupon) {
        _activeCoupon = null;
        document.getElementById('coupon-input').value = '';
        document.getElementById('coupon-feedback').innerHTML = '';
    }

    if (cart.length === 0) {
        container.innerHTML = '<div class="cart-empty"><i class="fas fa-cart-plus"></i><p>Click on a product to add it to the cart.</p></div>';
        summary.style.display = 'none';
        actions.style.display = 'none';
        document.getElementById('cart-header-count').textContent = '0';
        return;
    }

    summary.style.display = 'block';
    actions.style.display = 'flex';

    let html = '';
    let subtotal = 0;
    let count = 0;

    cart.forEach((item, i) => {
        const lineTotal = item.price * item.qty;
        subtotal += lineTotal;
        count += item.qty;
        html += `
            <div class="cart-item">
                <div class="cart-item-info">
                    <div class="name">${escHtml(item.name)}</div>
                    <div class="price">${formatMoney(item.price)}</div>
                </div>
                <div class="cart-item-qty">
                    <button onclick="changeQty(${i}, -1)" aria-label="Decrease quantity">-</button>
                    <span>${item.qty}</span>
                    <button onclick="changeQty(${i}, 1)" aria-label="Increase quantity">+</button>
                </div>
                <div class="cart-item-total">${formatMoney(lineTotal)}</div>
                <button class="cart-item-remove" onclick="removeItem(${i})" aria-label="Remove item">&times;</button>
            </div>
        `;
    });

    container.innerHTML = html;

    const discount = subtotal * (discountPct / 100);
    const afterDiscount = subtotal - discount;
    const tax = afterDiscount * (<?= TAX_RATE ?> / 100);
    const total = afterDiscount + tax;

    document.getElementById('cart-count').textContent = count;
    document.getElementById('cart-header-count').textContent = count;
    document.getElementById('cart-subtotal').textContent = formatMoney(subtotal);
    if (_activeCoupon) {
        document.getElementById('discount-pct').textContent = _activeCoupon.code;
        document.getElementById('cart-discount').textContent = '-' + formatMoney(_activeCoupon.discount_amount);
    } else {
        document.getElementById('discount-pct').textContent = discountPct > 0 ? discountPct : '0';
        document.getElementById('cart-discount').textContent = discountPct > 0 ? formatMoney(discount) : '<?= money(0) ?>';
    }
    document.getElementById('cart-tax').textContent = formatMoney(tax);
    document.getElementById('cart-total').textContent = formatMoney(total);

    window._cartTotal = total;
    window._cartSubtotal = subtotal;
    window._cartDiscount = _activeCoupon ? _activeCoupon.discount_amount : discount;
    window._cartTax = tax;
    window._cartDiscountPct = _activeCoupon ? 0 : discountPct;
    window._cartCouponCode = _activeCoupon ? _activeCoupon.code : null;
}

function changeQty(index, delta) {
    const item = cart[index];
    const newQty = item.qty + delta;
    if (newQty <= 0) { cart.splice(index, 1); }
    else if (newQty > item.stock) { alert('Not enough stock.'); }
    else { item.qty = newQty; }
    updateCart();
    announceSalesStatus(`${item.name}, quantity ${item.qty}.`);
}

function removeItem(index) {
    const item = cart[index];
    cart.splice(index, 1);
    updateCart();
    announceSalesStatus(`${item.name} removed from the current sale.`);
}

function clearCart() {
    if (cart.length === 0) return;
    if (!confirm('Clear the entire cart?')) return;
    cart = [];
    updateCart();
}

function announceSalesStatus(message) {
    const status = document.getElementById('sales-status');
    if (status) status.textContent = message;
}

function showPaymentModal() {
    if (cart.length === 0) return;
    document.getElementById('payment-modal').classList.add('show');
    document.getElementById('cash-amount').value = '';
    document.getElementById('mixed-cash').value = '';
    document.getElementById('mixed-card').value = '';
    document.getElementById('change-display').innerHTML = '';
    document.getElementById('pm-cash').checked = true;
    togglePayment();

    let summaryHtml = `
        <div class="flex-between fs-14 mb-4">
            <span>Subtotal:</span>
            <span>${formatMoney(window._cartSubtotal)}</span>
        </div>`;
    if (window._cartCouponCode) {
        summaryHtml += `
        <div class="flex-between fs-14 mb-4">
            <span>Coupon: <strong>${escHtml(window._cartCouponCode)}</strong></span>
            <span class="text-success">-${formatMoney(window._cartDiscount)}</span>
        </div>`;
    } else if (window._cartDiscountPct > 0) {
        summaryHtml += `
        <div class="flex-between fs-14 mb-4">
            <span>Discount (${window._cartDiscountPct}%)</span>
            <span class="text-danger">-${formatMoney(window._cartDiscount)}</span>
        </div>`;
    }
    summaryHtml += `
        <div class="flex-between fs-18 fw-bold" style="border-top:2px solid var(--text-primary);padding-top:8px">
            <span>Total Due:</span>
            <span>${formatMoney(window._cartTotal)}</span>
        </div>`;
    document.getElementById('payment-summary').innerHTML = summaryHtml;
}

function closePaymentModal() {
    document.getElementById('payment-modal').classList.remove('show');
}

function togglePayment() {
    const method = document.querySelector('input[name="payment_method"]:checked').value;
    document.getElementById('cash-input-group').style.display = method === 'cash' ? 'block' : 'none';
    document.getElementById('mixed-inputs').classList.toggle('show', method === 'mixed');
    calcChange();
}

function calcChange() {
    const method = document.querySelector('input[name="payment_method"]:checked').value;
    const total = window._cartTotal || 0;
    let change = 0;
    let html = '';

    if (method === 'cash') {
        const tendered = parseFloat(document.getElementById('cash-amount').value) || 0;
        change = tendered - total;
        if (tendered >= total && total > 0) {
            html = `<div class="alert alert-success m-0 text-center fs-18"><strong>Change: ${formatMoney(change)}</strong></div>`;
        } else if (tendered > 0) {
            html = `<div class="alert alert-danger m-0 text-center">Insufficient amount</div>`;
        }
    } else if (method === 'mixed') {
        const cash = parseFloat(document.getElementById('mixed-cash').value) || 0;
        const card = parseFloat(document.getElementById('mixed-card').value) || 0;
        const totalPaid = cash + card;
        change = cash - (total - card);
        if (totalPaid >= total && total > 0) {
            html = `<div class="alert alert-success m-0 text-center fs-18"><strong>Change: ${formatMoney(change)}</strong></div>`;
        } else if (totalPaid > 0) {
            html = `<div class="alert alert-danger m-0 text-center">Remaining: ${formatMoney(total - totalPaid)}</div>`;
        }
    }

    document.getElementById('change-display').innerHTML = html;
}

function completeSale() {
    const method = document.querySelector('input[name="payment_method"]:checked').value;
    let cashAmount = 0, cardAmount = 0, changeAmount = 0;
    const total = window._cartTotal || 0;

    if (method === 'cash') {
        cashAmount = parseFloat(document.getElementById('cash-amount').value) || 0;
        if (cashAmount < total) { alert('Insufficient cash amount.'); return; }
        changeAmount = cashAmount - total;
    } else if (method === 'card' || method === 'mobile') {
        cardAmount = total;
    } else if (method === 'mixed') {
        cashAmount = parseFloat(document.getElementById('mixed-cash').value) || 0;
        cardAmount = parseFloat(document.getElementById('mixed-card').value) || 0;
        if (cashAmount + cardAmount < total) { alert('Insufficient payment amount.'); return; }
        changeAmount = cashAmount - (total - cardAmount);
    }

    // Send to server
    const formData = new FormData();
    formData.append('action', 'complete_sale');
    formData.append('cart', JSON.stringify(cart));
    formData.append('payment_method', method);
    formData.append('cash_amount', cashAmount.toString());
    formData.append('card_amount', cardAmount.toString());
    formData.append('change_amount', changeAmount.toString());
    formData.append('subtotal', (window._cartSubtotal || 0).toString());
    formData.append('discount', (window._cartDiscount || 0).toString());
    formData.append('discount_pct', (window._cartDiscountPct || 0).toString());
    formData.append('tax', (window._cartTax || 0).toString());
    formData.append('total', total.toString());
    formData.append('coupon_code', (window._cartCouponCode || '').toString());
    formData.append('_csrf', '<?= csrf_token() ?>');

    fetch('index.php?page=sales&action=ajax', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showSaleSuccessModal({
                    sale_id: data.sale_id,
                    receipt_number: data.receipt_number,
                    formatted_total: formatMoney(window._cartTotal),
                    formatted_discount: formatMoney(window._cartDiscount),
                    discount_code: window._cartCouponCode || null,
                    payment_method: method.charAt(0).toUpperCase() + method.slice(1)
                });
                cart = [];
                updateCart();
                closePaymentModal();
            } else {
                showToast(data.error || 'Sale failed.', 'danger');
            }
        })
        .catch(() => showToast('An error occurred.', 'danger'));
}

function formatMoney(n) {
    return '<?= CURRENCY ?> ' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function escHtml(s) {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
}

// Hold Sale
function holdSale() {
    if (cart.length === 0) {
        showToast('Cart is empty. Add items to hold.', 'warning');
        return;
    }
    if (!confirm('Hold this sale? You can resume it later from Held Sales.')) return;

    const formData = new FormData();
    formData.append('action', 'hold_sale');
    formData.append('cart', JSON.stringify(cart));
    formData.append('subtotal', (window._cartSubtotal || 0).toString());
    formData.append('_csrf', '<?= csrf_token() ?>');

    fetch('index.php?page=sales&action=ajax', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Sale held. You can resume it from Held Sales.', 'success');
                cart = [];
                updateCart();
                setTimeout(function() {
                    window.location.href = 'index.php?page=held-sales';
                }, 1000);
            } else {
                showToast(data.error || 'Failed to hold sale.', 'danger');
            }
        })
        .catch(() => showToast('An error occurred.', 'danger'));
}

// Resume held sale on page load
document.addEventListener('DOMContentLoaded', function() {
    const resumeId = sessionStorage.getItem('resumeHeldSaleId');
    if (resumeId) {
        sessionStorage.removeItem('resumeHeldSaleId');
        fetch('index.php?page=sales&action=ajax', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=get_held_sale&held_id=' + encodeURIComponent(resumeId) + '&_csrf=<?= csrf_token() ?>'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.cart) {
                cart = data.cart;
                updateCart();
                showToast('Held sale #' + resumeId + ' loaded. Resume checkout.', 'success');
            } else {
                showToast('Held sale not found or expired.', 'danger');
            }
        })
        .catch(() => {});
    }
});
</script>
