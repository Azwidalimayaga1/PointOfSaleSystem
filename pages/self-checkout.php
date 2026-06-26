<?php declare(strict_types=1);

if (!SELF_CHECKOUT_ENABLED) {
    redirect('index.php?page=login');
}

// Admin unlock logic
$scLocked = !isset($_SESSION['sc_unlocked']) || $_SESSION['sc_unlocked'] !== true;
$scUnlockError = '';

if (isset($_POST['sc_unlock'])) {
    $scUsername = trim($_POST['sc_username'] ?? '');
    $scPassword = $_POST['sc_password'] ?? '';
    if ($scUsername && $scPassword) {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active' AND role IN ('admin', 'manager', 'store_admin')");
        $stmt->execute([$scUsername]);
        $scUser = $stmt->fetch();
        if ($scUser && password_verify($scPassword, $scUser['password'])) {
            $_SESSION['sc_unlocked'] = true;
            $scLocked = false;
        } else {
            $scUnlockError = 'Invalid credentials or insufficient permissions.';
        }
    } else {
        $scUnlockError = 'Please enter username and password.';
    }
}

// Handle exit unlock (POST from exit modal)
if (isset($_POST['sc_exit_unlock'])) {
    $scUsername = trim($_POST['sc_exit_username'] ?? '');
    $scPassword = $_POST['sc_exit_password'] ?? '';
    if ($scUsername && $scPassword) {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active' AND role IN ('admin', 'manager', 'store_admin')");
        $stmt->execute([$scUsername]);
        $scUser = $stmt->fetch();
        if ($scUser && password_verify($scPassword, $scUser['password'])) {
            unset($_SESSION['sc_unlocked']);
            redirect('index.php?page=' . $exitPage);
        } else {
            $scUnlockError = 'Invalid credentials.';
        }
    } else {
        $scUnlockError = 'Please enter username and password.';
    }
}

// Receipt view within self-checkout
$receiptId = isset($_GET['receipt_id']) ? (int) $_GET['receipt_id'] : 0;
if ($receiptId) {
    $rStmt = $db->prepare("SELECT * FROM sales WHERE id = ? AND store_id = ?");
    $rStmt->execute([$receiptId, activeStoreId()]);
    $rSale = $rStmt->fetch();
    if ($rSale) {
        $rStmt = $db->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
        $rStmt->execute([$receiptId]);
        $rItems = $rStmt->fetchAll();
    }
}

$products = getProducts($db);
$categories = getCategories($db);

$flash = $_SESSION['pos_flash'] ?? null;
unset($_SESSION['pos_flash']);

$exitPage = isLoggedIn() ? 'dashboard' : 'login';
$selfCheckoutUserId = 0;
try {
    $stmt = $db->prepare("SELECT id FROM users WHERE username = 'selfcheckout'");
    $stmt->execute();
    $u = $stmt->fetch();
    $selfCheckoutUserId = $u ? (int) $u['id'] : 0;
    if (!$selfCheckoutUserId) {
        $db->exec("INSERT IGNORE INTO users (username, password, full_name, role, status) VALUES ('selfcheckout', '', 'Self-Checkout', 'cashier', 'active')");
        $stmt = $db->query("SELECT id FROM users WHERE username = 'selfcheckout'");
        $u = $stmt->fetch();
        $selfCheckoutUserId = $u ? (int) $u['id'] : 0;
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>Self Checkout &mdash; <?= e(STORE_NAME) ?></title>
    <script>if(localStorage.getItem('pos-theme')==='dark')document.documentElement.setAttribute('data-theme','dark')</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="self-checkout">
    <?php if ($scLocked): ?>
    <div class="sc-lock">
        <div class="sc-lock-box">
            <div class="sc-lock-icon"><i class="fas fa-lock"></i></div>
            <h2>Self Checkout Locked</h2>
            <p>An authorized staff member must unlock the terminal.</p>
            <?php if ($scUnlockError): ?>
                <div class="sc-lock-error"><?= e($scUnlockError) ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="text" name="sc_username" class="form-control" placeholder="Username" required autocomplete="off" autofocus>
                <input type="password" name="sc_password" class="form-control" placeholder="Password" required>
                <button type="submit" name="sc_unlock" class="btn btn-primary sc-lock-btn"><i class="fas fa-unlock"></i> Unlock</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="sc-topbar">
        <div class="sc-topbar-brand">
            <i class="fas fa-cash-register"></i>
            <span>Self Checkout &mdash; <?= e(STORE_NAME) ?></span>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
            <button class="btn-theme" onclick="toggleTheme()" title="Toggle theme" id="theme-toggle">
                <i class="fas fa-moon"></i>
            </button>
            <button class="btn btn-outline btn-sm" onclick="scExitPrompt()"><i class="fas fa-times"></i> Exit</button>
        </div>
    </div>

    <!-- Exit Password Modal -->
    <div class="modal-overlay" id="sc-exit-modal">
        <div class="modal" style="width:380px">
            <h3 style="margin-bottom:12px"><i class="fas fa-lock"></i> Authorize Exit</h3>
            <?php if ($scUnlockError && (isset($_POST['sc_exit_unlock']))): ?>
                <div class="sc-lock-error" style="margin-bottom:12px"><?= e($scUnlockError) ?></div>
            <?php endif; ?>
            <form method="post" onsubmit="return document.getElementById('sc-exit-pw').value.length > 0">
                <input type="text" name="sc_exit_username" class="form-control" style="margin-bottom:10px" placeholder="Username" required autocomplete="off">
                <input type="password" id="sc-exit-pw" name="sc_exit_password" class="form-control" style="margin-bottom:16px" placeholder="Password" required>
                <div style="display:flex;gap:10px">
                    <button type="button" class="btn btn-outline sc-btn-lg" onclick="document.getElementById('sc-exit-modal').classList.remove('show')">Cancel</button>
                    <button type="submit" name="sc_exit_unlock" class="btn btn-danger sc-btn-lg"><i class="fas fa-sign-out-alt"></i> Exit</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> sc-flash"><?= e($flash['message']) ?></div>
    <?php endif; ?>

<?php if ($receiptId && isset($rSale)): ?>
    <div class="sc-receipt">
        <div class="sc-receipt-success">
            <i class="fas fa-check-circle"></i>
            <h2>Payment Successful!</h2>
            <p>Thank you for your purchase.</p>
        </div>
        <div class="receipt-box">
            <div class="store-name"><?= e(STORE_NAME) ?></div>
            <div class="store-info"><?= nl2br(e(STORE_ADDRESS)) ?></div>
            <div class="store-info"><?= e(STORE_CONTACT) ?></div>
            <div class="receipt-header">
                <strong>RECEIPT</strong><br>
                <?= e($rSale['receipt_number']) ?><br>
                <?= e(date('Y-m-d H:i', strtotime($rSale['created_at']))) ?><br>
                Cashier: <?= e($rSale['cashier_name']) ?>
            </div>
            <table>
                <thead>
                    <tr>
                        <td><strong>Item</strong></td>
                        <td align="center"><strong>Qty</strong></td>
                        <td align="right"><strong>Price</strong></td>
                        <td align="right"><strong>Total</strong></td>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rItems as $item): ?>
                        <tr>
                            <td><?= e($item['product_name']) ?></td>
                            <td align="center"><?= (int) $item['quantity'] ?></td>
                            <td align="right"><?= money((float) $item['price']) ?></td>
                            <td align="right"><?= money((float) $item['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="receipt-total">
                <div class="receipt-line"><span>Subtotal</span><span><?= money((float) $rSale['subtotal']) ?></span></div>
                <?php if ((float) $rSale['discount'] > 0): ?>
                <div class="receipt-line"><span>Discount</span><span>-<?= money((float) $rSale['discount']) ?></span></div>
                <?php endif; ?>
                <div class="receipt-line"><span>VAT (<?= (float) $rSale['tax_rate'] ?>%)</span><span><?= money((float) $rSale['tax']) ?></span></div>
                <div class="receipt-line receipt-line-total"><span>Total</span><span><?= money((float) $rSale['total']) ?></span></div>
            </div>
            <div class="receipt-footer">
                Payment: <?= e(ucfirst($rSale['payment_method'])) ?>
                <?php if ($rSale['customer_name']): ?>
                    <br>Customer: <?= e($rSale['customer_name']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="sc-receipt-actions">
            <a href="index.php?page=self-checkout" class="btn btn-primary"><i class="fas fa-redo"></i> Start New Sale</a>
            <button class="btn btn-outline" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        </div>
    </div>
<?php else: ?>
    <div class="sc-body">
        <div class="sc-products">
            <div class="sc-search">
                <input type="text" id="sc-search" class="form-control" placeholder="Search products or scan barcode..." autofocus oninput="scFilter()">
                <select id="sc-cat" class="form-control" onchange="scFilter()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sc-grid" id="sc-grid">
                <?php foreach ($products as $p): ?>
                <div class="sc-card" data-id="<?= (int) $p['id'] ?>" data-name="<?= e(strtolower($p['name'])) ?>" data-barcode="<?= e(strtolower($p['barcode'] ?? '')) ?>" data-cat="<?= e(strtolower($p['category'] ?? '')) ?>" data-price="<?= (float) $p['price'] ?>" data-stock="<?= (int) $p['stock_quantity'] ?>" data-dname="<?= e($p['name']) ?>">
                    <?php if ($p['image']): ?>
                        <img src="<?= e($p['image']) ?>" alt="" class="sc-card-img" loading="lazy">
                    <?php endif; ?>
                    <h4 class="sc-card-name"><?= e($p['name']) ?></h4>
                    <div class="sc-card-sku"><?= e($p['barcode'] ?: '—') ?></div>
                    <div class="sc-card-price"><?= money((float) $p['price']) ?></div>
                    <div class="sc-card-stock <?= (int) $p['stock_quantity'] <= (int) ($p['low_stock_threshold'] ?? 10) ? 'low' : 'ok' ?>">
                        <?= (int) $p['stock_quantity'] ?> in stock
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="sc-cart" id="sc-cart">
            <div class="sc-cart-head">
                <i class="fas fa-shopping-basket"></i> Your Cart
                <span class="sc-cart-count" id="sc-cart-count">0</span>
            </div>
            <div class="sc-cart-items" id="sc-cart-items">
                <div class="sc-cart-empty">
                    <i class="fas fa-cart-plus"></i>
                    <p>Tap products to add them to your cart</p>
                </div>
            </div>
            <div class="sc-cart-foot" id="sc-cart-foot" style="display:none">
                <div class="sc-summary">
                    <div class="sc-summary-row"><span>Items</span><span id="sc-sum-count">0</span></div>
                    <div class="sc-summary-row"><span>Subtotal</span><span id="sc-sum-sub"><?= money(0) ?></span></div>
                    <div class="sc-summary-row"><span>VAT (<?= TAX_RATE ?>%)</span><span id="sc-sum-tax"><?= money(0) ?></span></div>
                    <div class="sc-summary-row sc-sum-total"><span>Total</span><span id="sc-sum-total"><?= money(0) ?></span></div>
                </div>
                <div class="sc-cart-btns">
                    <button class="btn btn-outline sc-btn-lg" onclick="scClear()"><i class="fas fa-trash"></i> Clear</button>
                    <button class="btn btn-success sc-btn-lg sc-btn-pay" onclick="scPay()"><i class="fas fa-credit-card"></i> Pay Now</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<div class="modal-overlay" id="sc-pay-modal">
    <div class="modal" style="width:480px">
        <h3><i class="fas fa-credit-card"></i> Payment</h3>
        <div class="sc-step-indicator">
            <div class="sc-step-dot active" id="sc-step-dot-1"></div>
            <div class="sc-step-dot" id="sc-step-dot-2"></div>
            <div class="sc-step-dot" id="sc-step-dot-3"></div>
        </div>
        <div class="sc-pay-summary" id="sc-pay-summary"></div>

        <!-- Step 1: Photo -->
        <div id="sc-photo-step">
            <p class="sc-step-title">
                <i class="fas fa-camera"></i> Step 1: Customer Photo
            </p>
            <div class="sc-camera-area">
                <div class="sc-camera-preview" id="sc-camera-preview">
                    <video id="sc-video" autoplay playsinline></video>
                    <canvas id="sc-canvas"></canvas>
                    <img id="sc-captured-img">
                </div>
                <div class="sc-camera-msg" id="sc-camera-msg">
                    <i class="fas fa-camera"></i>
                    <p>Click below to open camera and take a photo</p>
                </div>
                <div class="sc-camera-actions">
                    <button id="sc-cam-btn" class="btn btn-primary" onclick="scOpenCam()"><i class="fas fa-camera"></i> Take Photo</button>
                    <button id="sc-capture-btn" class="btn btn-success" style="display:none" onclick="scCapture()"><i class="fas fa-camera"></i> Capture</button>
                    <button id="sc-retake-btn" class="btn btn-outline" style="display:none" onclick="scRetake()"><i class="fas fa-redo"></i> Retake</button>
                </div>
                <div class="sc-photo-status" id="sc-photo-status"></div>
            </div>
        </div>

        <!-- Step 2: Customer Info -->
        <div class="sc-step-section" id="sc-customer-step">
            <p class="sc-step-title">
                <i class="fas fa-user"></i> Step 2: Your Details <span class="sc-step-optional">(optional)</span>
            </p>
            <div class="form-group">
                <label for="sc-cust-name">Name</label>
                <input type="text" id="sc-cust-name" class="form-control" placeholder="Your name">
            </div>
            <div class="form-group">
                <label for="sc-cust-email">Email</label>
                <input type="email" id="sc-cust-email" class="form-control" placeholder="your@email.com">
            </div>
            <div class="form-group">
                <label for="sc-cust-phone">Phone</label>
                <input type="tel" id="sc-cust-phone" class="form-control" placeholder="Phone number">
            </div>
            <div class="sc-step-actions">
                <button class="btn btn-primary sc-btn-lg" onclick="scNextToPayment()">
                    <i class="fas fa-arrow-right"></i> Continue to Payment
                </button>
                <button class="btn btn-outline" onclick="scSkipCustomer()"><i class="fas fa-forward"></i> Skip</button>
            </div>
        </div>

        <!-- Step 3: Payment Method -->
        <div class="sc-step-section" id="sc-pay-step">
            <p class="sc-step-title">
                <i class="fas fa-money-bill"></i> Step 3: Payment Method
            </p>
            <div class="payment-option">
                <input type="radio" name="sc_method" id="sc-m-cash" value="cash" checked onchange="scTogglePayMethod()">
                <label for="sc-m-cash"><i class="fas fa-money-bill"></i> Cash</label>
                <input type="radio" name="sc_method" id="sc-m-card" value="card" onchange="scTogglePayMethod()">
                <label for="sc-m-card"><i class="fas fa-credit-card"></i> Card</label>
            </div>
            <div id="sc-cash-input">
                <div class="form-group">
                    <label for="sc-cash">Amount Tendered</label>
                    <input type="number" id="sc-cash" class="form-control" step="0.01" min="0" placeholder="0.00" oninput="scCalcChange()" autofocus>
                </div>
                <div class="sc-change-display" id="sc-change-display"></div>
            </div>
            <div id="sc-card-input">
                <div class="form-group">
                    <label for="sc-card">Card Number (last 4 digits)</label>
                    <input type="text" id="sc-card" class="form-control" maxlength="4" placeholder="e.g. 1234" inputmode="numeric" autocomplete="off">
                </div>
            </div>
            <div class="sc-step-actions">
                <button class="btn btn-success sc-btn-lg" onclick="scComplete()">
                    <i class="fas fa-check"></i> Complete Payment
                </button>
                <button class="btn btn-outline" onclick="scClosePay()"><i class="fas fa-times"></i> Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal-overlay" id="sc-confirm-modal">
    <div class="modal sc-confirm-modal">
        <div class="sc-confirm-icon">
            <i class="fas fa-receipt"></i>
        </div>
        <h3>Confirm Payment</h3>
        <div class="sc-confirm-box">
            <div class="sc-confirm-total-row">
                <span>Total Due</span>
                <span id="sc-confirm-total"></span>
            </div>
            <div class="sc-confirm-details">
                <div class="sc-confirm-row">
                    <span>Payment Method</span>
                    <span id="sc-confirm-method"></span>
                </div>
                <div class="sc-confirm-row" id="sc-confirm-amount-row">
                    <span>Amount Tendered</span>
                    <span id="sc-confirm-amount"></span>
                </div>
                <div class="sc-confirm-row" id="sc-confirm-change-row">
                    <span>Change Due</span>
                    <span id="sc-confirm-change"></span>
                </div>
            </div>
        </div>
        <div class="sc-step-actions">
            <button class="btn btn-success sc-btn-lg" onclick="scConfirmPayment()">
                <i class="fas fa-check-circle"></i> Confirm Payment
            </button>
            <button class="btn btn-outline sc-btn-lg" onclick="scCloseConfirm()">
                <i class="fas fa-times"></i> Cancel
            </button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="sc-success-modal">
    <div class="modal sc-success-modal">
        <div class="sc-success-icon"><i class="fas fa-check-circle"></i></div>
        <h3>Payment Successful!</h3>
        <p>Thank you for your purchase.</p>
        <div id="sc-success-details"></div>
        <div class="sc-success-actions">
            <button class="btn btn-primary" onclick="scNew()"><i class="fas fa-redo"></i> New Sale</button>
            <a id="sc-receipt-link" class="btn btn-outline" target="_blank"><i class="fas fa-receipt"></i> Receipt</a>
        </div>
        <div class="sc-countdown" id="sc-countdown">
            Redirecting to receipt in <span id="sc-countdown-secs">3</span> seconds&hellip;
            <button class="sc-countdown-cancel" onclick="scCancelRedirect()">Cancel</button>
        </div>
    </div>
</div>

<script>
function csrfToken() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
}
let cart = [];
let scPhotoData = '';
let scStream = null;

const scGrid = document.getElementById('sc-grid');
if (scGrid) {
    scGrid.addEventListener('click', function(e) {
        const card = e.target.closest('.sc-card');
        if (!card) return;
        const id = parseInt(card.dataset.id);
        const name = card.dataset.dname;
        const price = parseFloat(card.dataset.price);
        const stock = parseInt(card.dataset.stock);
        if (stock <= 0) { alert('Out of stock'); return; }
        const ex = cart.find(i => i.id === id);
        if (ex) {
            if (ex.qty >= stock) { alert('Not enough stock'); return; }
            ex.qty++;
        } else {
            cart.push({ id, name, price, qty: 1, stock });
        }
        scUpdate();
        card.style.transform = 'scale(0.95)';
        setTimeout(() => card.style.transform = '', 150);
    });
}

const scSearch = document.getElementById('sc-search');
if (scSearch) {
    scSearch.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && this.value.trim()) {
            const val = this.value.toLowerCase().trim();
            const cards = document.querySelectorAll('.sc-card');
            for (const card of cards) {
                if (card.dataset.barcode === val) {
                    card.click();
                    this.value = '';
                    break;
                }
            }
        }
    });
}

function scFilter() {
    const q = document.getElementById('sc-search').value.toLowerCase();
    const c = document.getElementById('sc-cat').value.toLowerCase();
    document.querySelectorAll('.sc-card').forEach(el => {
        const match = (!q || el.dataset.name.includes(q) || el.dataset.barcode.includes(q)) && (!c || el.dataset.cat === c);
        el.style.display = match ? '' : 'none';
    });
}

function scUpdate() {
    const count = cart.reduce((s, i) => s + i.qty, 0);
    document.getElementById('sc-cart-count').textContent = count;
    document.getElementById('sc-sum-count').textContent = count;
    const itemsEl = document.getElementById('sc-cart-items');
    const foot = document.getElementById('sc-cart-foot');
    if (!cart.length) {
        itemsEl.innerHTML = '<div class="sc-cart-empty"><i class="fas fa-cart-plus"></i><p>Tap products to add them to your cart</p></div>';
        foot.style.display = 'none';
        return;
    }
    foot.style.display = 'block';
    let html = '', sub = 0;
    cart.forEach((item, i) => {
        const lt = item.price * item.qty;
        sub += lt;
        html += `<div class="sc-cart-item">
            <div class="sci-info">
                <div class="sci-name">${esc(item.name)}</div>
                <div class="sci-price">${fmt(item.price)}</div>
            </div>
            <div class="sci-qty">
                <button onclick="scQty(${i},-1)">-</button>
                <span>${item.qty}</span>
                <button onclick="scQty(${i},1)">+</button>
            </div>
            <div class="sci-total">${fmt(lt)}</div>
            <button class="sci-rm" onclick="scRm(${i})">&times;</button>
        </div>`;
    });
    itemsEl.innerHTML = html;
    const tax = sub * (<?= TAX_RATE ?> / 100);
    const total = sub + tax;
    document.getElementById('sc-sum-sub').textContent = fmt(sub);
    document.getElementById('sc-sum-tax').textContent = fmt(tax);
    document.getElementById('sc-sum-total').textContent = fmt(total);
    window._scSub = sub; window._scTax = tax; window._scTotal = total;
}

function scQty(i, d) {
    const item = cart[i];
    const n = item.qty + d;
    if (n <= 0) { cart.splice(i, 1); }
    else if (n > item.stock) { alert('Not enough stock'); }
    else { item.qty = n; }
    scUpdate();
}

function scRm(i) { cart.splice(i, 1); scUpdate(); }
function scClear() { if (!cart.length || !confirm('Clear cart?')) return; cart = []; scUpdate(); }

function scExitPrompt() {
    document.getElementById('sc-exit-modal').classList.add('show');
    setTimeout(function() {
        var el = document.querySelector('#sc-exit-modal input[name="sc_exit_username"]');
        if (el) el.focus();
    }, 200);
}

/* Camera */
async function scOpenCam() {
    const preview = document.getElementById('sc-camera-preview');
    const video = document.getElementById('sc-video');
    const camBtn = document.getElementById('sc-cam-btn');
    const captureBtn = document.getElementById('sc-capture-btn');
    const msg = document.getElementById('sc-camera-msg');
    try {
        scStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', width: 640, height: 480 } });
        video.srcObject = scStream;
        preview.style.display = 'block';
        msg.style.display = 'none';
        camBtn.style.display = 'none';
        captureBtn.style.display = 'inline-flex';
        document.getElementById('sc-captured-img').style.display = 'none';
        document.getElementById('sc-retake-btn').style.display = 'none';
        scPhotoData = '';
        document.getElementById('sc-photo-status').innerHTML = '';
    } catch (e) {
        document.getElementById('sc-photo-status').innerHTML = '<span style="color:var(--danger)">Camera unavailable. You can proceed without a photo.</span>';
    }
}

function scCapture() {
    const video = document.getElementById('sc-video');
    const canvas = document.getElementById('sc-canvas');
    const img = document.getElementById('sc-captured-img');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0);
    scPhotoData = canvas.toDataURL('image/jpeg', 0.8);
    img.src = scPhotoData;
    img.style.display = 'block';
    video.style.display = 'none';
    if (scStream) { scStream.getTracks().forEach(t => t.stop()); scStream = null; }
    document.getElementById('sc-capture-btn').style.display = 'none';
    document.getElementById('sc-retake-btn').style.display = 'inline-flex';
    document.getElementById('sc-photo-status').innerHTML = '<span style="color:var(--success)"><i class="fas fa-check-circle"></i> Photo captured</span>';
    document.getElementById('sc-cam-btn').style.display = 'none';
    // Step indicator
    document.getElementById('sc-step-dot-1').classList.add('active');
    document.getElementById('sc-step-dot-2').classList.add('active');
    // Show customer step
    document.getElementById('sc-customer-step').style.display = 'block';
    document.getElementById('sc-customer-step').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function scRetake() {
    const video = document.getElementById('sc-video');
    const img = document.getElementById('sc-captured-img');
    const captureBtn = document.getElementById('sc-capture-btn');
    img.style.display = 'none';
    video.style.display = 'block';
    document.getElementById('sc-retake-btn').style.display = 'none';
    document.getElementById('sc-photo-status').innerHTML = '';
    document.getElementById('sc-customer-step').style.display = 'none';
    document.getElementById('sc-pay-step').style.display = 'none';
    document.getElementById('sc-step-dot-2').classList.remove('active');
    scPhotoData = '';
    scOpenCam();
}

/* Payment */
function scPay() {
    if (!cart.length) return;
    document.getElementById('sc-pay-modal').classList.add('show');
    document.getElementById('sc-pay-summary').innerHTML =
        `<div class="sc-pay-total">
            <strong>Total Due:</strong>
            <strong>${fmt(window._scTotal)}</strong>
        </div>`;
    // Reset payment modal
    scPhotoData = '';
    document.getElementById('sc-camera-preview').style.display = 'none';
    document.getElementById('sc-camera-msg').style.display = 'block';
    document.getElementById('sc-cam-btn').style.display = 'inline-flex';
    document.getElementById('sc-capture-btn').style.display = 'none';
    document.getElementById('sc-retake-btn').style.display = 'none';
    document.getElementById('sc-photo-status').innerHTML = '';
    document.getElementById('sc-customer-step').style.display = 'none';
    document.getElementById('sc-pay-step').style.display = 'none';
    document.getElementById('sc-card').value = '';
    document.getElementById('sc-cash').value = '';
    document.getElementById('sc-change-display').innerHTML = '';
    document.getElementById('sc-m-cash').checked = true;
    document.getElementById('sc-step-dot-2').classList.remove('active');
    document.getElementById('sc-step-dot-3').classList.remove('active');
    document.getElementById('sc-cust-name').value = '';
    document.getElementById('sc-cust-email').value = '';
    document.getElementById('sc-cust-phone').value = '';
    scTogglePayMethod();
    document.getElementById('sc-cash').focus();
}

function scClosePay() {
    document.getElementById('sc-pay-modal').classList.remove('show');
    if (scStream) { scStream.getTracks().forEach(t => t.stop()); scStream = null; }
}

function scTogglePayMethod() {
    const method = document.querySelector('input[name="sc_method"]:checked').value;
    document.getElementById('sc-card-input').style.display = method === 'card' ? 'block' : 'none';
    document.getElementById('sc-cash-input').style.display = method === 'cash' ? 'block' : 'none';
    scCalcChange();
}

function scCalcChange() {
    const total = window._scTotal || 0;
    const tendered = parseFloat(document.getElementById('sc-cash').value) || 0;
    const el = document.getElementById('sc-change-display');
    if (tendered >= total && total > 0) {
        el.innerHTML = `<div class="alert alert-success" style="margin:0;text-align:center;font-size:16px"><strong>Change: ${fmt(tendered - total)}</strong></div>`;
    } else if (tendered > 0) {
        el.innerHTML = `<div class="alert alert-danger" style="margin:0;text-align:center">Need ${fmt(total - tendered)} more</div>`;
    } else {
        el.innerHTML = '';
    }
}

/* Customer Step */
function scNextToPayment() {
    document.getElementById('sc-step-dot-2').classList.add('active');
    document.getElementById('sc-step-dot-3').classList.add('active');
    document.getElementById('sc-pay-step').style.display = 'block';
    document.getElementById('sc-pay-step').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function scSkipCustomer() {
    document.getElementById('sc-cust-name').value = '';
    document.getElementById('sc-cust-email').value = '';
    document.getElementById('sc-cust-phone').value = '';
    scNextToPayment();
}

let scRedirectTimer = null;
let scRedirectCountdown = 3;

function scStartCountdown(saleId) {
    scRedirectCountdown = 3;
    const secsEl = document.getElementById('sc-countdown-secs');
    document.getElementById('sc-countdown').style.display = 'block';
    secsEl.textContent = scRedirectCountdown;
    scRedirectTimer = setInterval(() => {
        scRedirectCountdown--;
        secsEl.textContent = scRedirectCountdown;
        if (scRedirectCountdown <= 0) {
            clearInterval(scRedirectTimer);
            window.location.href = 'index.php?page=self-checkout&receipt_id=' + saleId;
        }
    }, 1000);
}

function scCancelRedirect() {
    if (scRedirectTimer) {
        clearInterval(scRedirectTimer);
        scRedirectTimer = null;
    }
    document.getElementById('sc-countdown').style.display = 'none';
}

function scComplete() {
    const total = window._scTotal || 0;
    const method = document.querySelector('input[name="sc_method"]:checked').value;

    let cashAmount = 0, cardAmount = 0, changeAmount = 0, cardLast4 = '';
    if (method === 'card') {
        cardLast4 = document.getElementById('sc-card').value.trim();
        if (cardLast4.length !== 4 || !/^\d{4}$/.test(cardLast4)) { alert('Please enter the last 4 digits of the card.'); return; }
        cardAmount = total;
    } else {
        cashAmount = parseFloat(document.getElementById('sc-cash').value) || 0;
        if (cashAmount < total) { alert('Insufficient cash amount.'); return; }
        changeAmount = cashAmount - total;
    }

    // Show confirmation modal instead of browser confirm
    document.getElementById('sc-confirm-total').textContent = fmt(total);
    document.getElementById('sc-confirm-method').textContent = method === 'card' ? 'Card ending in ' + cardLast4 : 'Cash';
    document.getElementById('sc-confirm-amount').textContent = method === 'card' ? '' : fmt(cashAmount);
    document.getElementById('sc-confirm-change').textContent = method === 'card' ? '' : fmt(changeAmount);
    document.getElementById('sc-confirm-modal').classList.add('show');
    window._scPendingPayment = { method, cashAmount, cardAmount, changeAmount, cardLast4, total };
}

function scConfirmPayment() {
    const pp = window._scPendingPayment;
    if (!pp) return;
    scClosePay();
    scCloseConfirm();

    const fd = new FormData();
    fd.append('action', 'complete_self_checkout');
    fd.append('_csrf', csrfToken());
    fd.append('cart', JSON.stringify(cart));
    fd.append('payment_method', pp.method);
    fd.append('cash_amount', pp.cashAmount.toString());
    fd.append('card_amount', pp.cardAmount.toString());
    fd.append('change_amount', pp.changeAmount.toString());
    fd.append('card_last4', pp.cardLast4);
    fd.append('subtotal', (window._scSub || 0).toString());
    fd.append('tax', (window._scTax || 0).toString());
    fd.append('total', pp.total.toString());
    fd.append('photo', scPhotoData);
    fd.append('customer_name', document.getElementById('sc-cust-name').value.trim());
    fd.append('customer_email', document.getElementById('sc-cust-email').value.trim());
    fd.append('customer_phone', document.getElementById('sc-cust-phone').value.trim());

    fetch('index.php?page=self-checkout', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                let payDetail = pp.method === 'card' ? `Card ending in ${pp.cardLast4}` : `Cash ${fmt(pp.cashAmount)}`;
                document.getElementById('sc-success-details').innerHTML =
                    `<div style="font-size:15px;color:var(--gray-600)">
                        <div>Receipt: <strong>${esc(d.receipt_number)}</strong></div>
                        <div style="font-size:24px;font-weight:800;color:var(--text);margin-top:6px">${fmt(pp.total)}</div>
                        <div style="font-size:13px;color:var(--gray-400)">${payDetail}</div>
                    </div>`;
                document.getElementById('sc-receipt-link').href = 'index.php?page=self-checkout&receipt_id=' + d.sale_id;
                document.getElementById('sc-success-modal').classList.add('show');
                scStartCountdown(d.sale_id);
            } else {
                alert(d.error || 'Payment failed');
            }
        })
        .catch(() => alert('An error occurred'));
}

function scCloseConfirm() {
    document.getElementById('sc-confirm-modal').classList.remove('show');
    window._scPendingPayment = null;
}

document.getElementById('sc-confirm-modal').addEventListener('click', function(e) {
    if (e.target === this) scCloseConfirm();
});

function scNew() {
    scCancelRedirect();
    document.getElementById('sc-success-modal').classList.remove('show');
    cart = [];
    scPhotoData = '';
    scUpdate();
    document.getElementById('sc-search').value = '';
    document.getElementById('sc-search').focus();
    scFilter();
}

function fmt(n) { return '<?= CURRENCY ?> ' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function toggleTheme() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (isDark) {
        document.documentElement.removeAttribute('data-theme');
        localStorage.setItem('pos-theme', 'light');
    } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('pos-theme', 'dark');
    }
    updateThemeIcon();
}
function updateThemeIcon() {
    const btn = document.getElementById('theme-toggle');
    if (!btn) return;
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    btn.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
}
updateThemeIcon();
</script>
</body>
</html>
