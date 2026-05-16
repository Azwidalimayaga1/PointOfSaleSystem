<?php

declare(strict_types=1);

$products = $db->query("SELECT * FROM products WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$categories = $db->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' AND status = 'active' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Flash messages
$flash = $_SESSION['pos_flash'] ?? null;
unset($_SESSION['pos_flash']);
?>
<div class="page-header">
    <h1><i class="fas fa-shopping-cart"></i> Sales Screen</h1>
</div>

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

<div class="sales-layout">
    <div>
        <div class="card">
            <div class="search-bar" style="margin-bottom:16px">
                <input type="text" id="product-search" class="form-control" placeholder="Search products by name or barcode..." oninput="filterProducts()">
                <select id="category-filter" class="form-control" onchange="filterProducts()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="product-grid" id="product-grid">
                <?php foreach ($products as $p): ?>
                    <div class="product-card" data-name="<?= e(strtolower($p['name'])) ?>" data-barcode="<?= e(strtolower($p['barcode'] ?? '')) ?>" data-category="<?= e(strtolower($p['category'] ?? '')) ?>" onclick="addToCart(<?= (int) $p['id'] ?>, '<?= e($p['name']) ?>', <?= (float) $p['price'] ?>, <?= (int) $p['stock_quantity'] ?>)">
                        <h4><?= e($p['name']) ?></h4>
                        <div class="sku"><?= e($p['barcode'] ?? 'No barcode') ?></div>
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
        <div class="cart-header">
            <i class="fas fa-shopping-basket"></i> Current Sale
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
        <div class="cart-actions" id="cart-actions" style="display:none">
            <div class="form-group" style="margin:0">
                <label style="font-size:13px">Discount (%)</label>
                <input type="number" id="discount-input" class="form-control" min="0" max="100" value="0" onchange="updateCart()" style="padding:8px">
            </div>
            <button class="btn btn-success" style="justify-content:center" onclick="showPaymentModal()">
                <i class="fas fa-check"></i> Checkout
            </button>
            <button class="btn btn-danger" style="justify-content:center" onclick="clearCart()">
                <i class="fas fa-trash"></i> Clear Cart
            </button>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal-overlay" id="payment-modal">
    <div class="modal">
        <h3><i class="fas fa-credit-card"></i> Complete Payment</h3>
        <div id="payment-summary" style="margin-bottom:16px"></div>
        <div class="form-group">
            <label>Payment Method</label>
            <div class="payment-option">
                <input type="radio" name="payment_method" id="pm-cash" value="cash" checked onchange="togglePayment()">
                <label for="pm-cash"><i class="fas fa-money-bill"></i> Cash</label>
                <input type="radio" name="payment_method" id="pm-card" value="card" onchange="togglePayment()">
                <label for="pm-card"><i class="fas fa-credit-card"></i> Card</label>
                <input type="radio" name="payment_method" id="pm-mixed" value="mixed" onchange="togglePayment()">
                <label for="pm-mixed"><i class="fas fa-random"></i> Mixed</label>
            </div>
        </div>
        <div class="form-group" id="cash-input-group">
            <label for="cash-amount">Amount Tendered (Cash)</label>
            <input type="number" id="cash-amount" class="form-control" step="0.01" min="0" oninput="calcChange()">
        </div>
        <div class="mixed-inputs" id="mixed-inputs">
            <div class="form-group" style="margin:0">
                <label>Cash Amount</label>
                <input type="number" id="mixed-cash" class="form-control" step="0.01" min="0" oninput="calcChange()">
            </div>
            <div class="form-group" style="margin:0">
                <label>Card Amount</label>
                <input type="number" id="mixed-card" class="form-control" step="0.01" min="0" oninput="calcChange()">
            </div>
        </div>
        <div id="change-display" style="margin-bottom:12px"></div>
        <div style="display:flex;gap:10px">
            <button class="btn btn-success" style="flex:1;justify-content:center" onclick="completeSale()">
                <i class="fas fa-check"></i> Complete Sale
            </button>
            <button class="btn btn-outline" onclick="closePaymentModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
        </div>
    </div>
</div>

<script>
let cart = [];

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
}

function updateCart() {
    const discountPct = parseFloat(document.getElementById('discount-input').value) || 0;
    const container = document.getElementById('cart-items');
    const summary = document.getElementById('cart-summary');
    const actions = document.getElementById('cart-actions');

    if (cart.length === 0) {
        container.innerHTML = '<div class="cart-empty"><i class="fas fa-cart-plus"></i><p>Click on a product to add it to the cart.</p></div>';
        summary.style.display = 'none';
        actions.style.display = 'none';
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
                    <button onclick="changeQty(${i}, -1)">-</button>
                    <span>${item.qty}</span>
                    <button onclick="changeQty(${i}, 1)">+</button>
                </div>
                <div class="cart-item-total">${formatMoney(lineTotal)}</div>
                <button class="cart-item-remove" onclick="removeItem(${i})">&times;</button>
            </div>
        `;
    });

    container.innerHTML = html;

    const discount = subtotal * (discountPct / 100);
    const afterDiscount = subtotal - discount;
    const tax = afterDiscount * (<?= TAX_RATE ?> / 100);
    const total = afterDiscount + tax;

    document.getElementById('cart-count').textContent = count;
    document.getElementById('cart-subtotal').textContent = formatMoney(subtotal);
    document.getElementById('discount-pct').textContent = discountPct;
    document.getElementById('cart-discount').textContent = formatMoney(discount);
    document.getElementById('cart-tax').textContent = formatMoney(tax);
    document.getElementById('cart-total').textContent = formatMoney(total);

    window._cartTotal = total;
    window._cartSubtotal = subtotal;
    window._cartDiscount = discount;
    window._cartTax = tax;
    window._cartDiscountPct = discountPct;
}

function changeQty(index, delta) {
    const item = cart[index];
    const newQty = item.qty + delta;
    if (newQty <= 0) { cart.splice(index, 1); }
    else if (newQty > item.stock) { alert('Not enough stock.'); }
    else { item.qty = newQty; }
    updateCart();
}

function removeItem(index) {
    cart.splice(index, 1);
    updateCart();
}

function clearCart() {
    if (cart.length === 0) return;
    if (!confirm('Clear the entire cart?')) return;
    cart = [];
    updateCart();
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

    document.getElementById('payment-summary').innerHTML = `
        <div style="display:flex;justify-content:space-between;font-size:16px">
            <strong>Total Due:</strong>
            <strong style="font-size:22px">${formatMoney(window._cartTotal)}</strong>
        </div>
    `;
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
            html = `<div class="alert alert-success" style="margin:0;text-align:center;font-size:18px"><strong>Change: ${formatMoney(change)}</strong></div>`;
        } else if (tendered > 0) {
            html = `<div class="alert alert-danger" style="margin:0;text-align:center">Insufficient amount</div>`;
        }
    } else if (method === 'mixed') {
        const cash = parseFloat(document.getElementById('mixed-cash').value) || 0;
        const card = parseFloat(document.getElementById('mixed-card').value) || 0;
        const totalPaid = cash + card;
        change = cash - (total - card);
        if (totalPaid >= total && total > 0) {
            html = `<div class="alert alert-success" style="margin:0;text-align:center;font-size:18px"><strong>Change: ${formatMoney(change)}</strong></div>`;
        } else if (totalPaid > 0) {
            html = `<div class="alert alert-danger" style="margin:0;text-align:center">Remaining: ${formatMoney(total - totalPaid)}</div>`;
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
    } else if (method === 'card') {
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

    fetch('index.php?page=sales&action=ajax', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'index.php?page=sales';
            } else {
                alert(data.error || 'Sale failed.');
            }
        })
        .catch(() => alert('An error occurred.'));
}

function formatMoney(n) {
    return '<?= CURRENCY ?> ' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function escHtml(s) {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
}
</script>
