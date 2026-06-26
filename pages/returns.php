<?php

declare(strict_types=1);

$user = $_SESSION['user'] ?? [];
$userRole = $_SESSION['user']['role'] ?? userRole();
$receiptNumber = $_GET['receipt'] ?? $_POST['receipt'] ?? '';
$sale = null;
$saleItems = [];
$success = '';
$error = '';
$pendingReturn = null;

// Look up sale
if ($receiptNumber) {
    $stmt = $db->prepare("SELECT * FROM sales WHERE receipt_number = ? AND store_id = ?");
    $stmt->execute([$receiptNumber, activeStoreId()]);
    $sale = $stmt->fetch();

    if ($sale) {
        $stmt = $db->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
        $stmt->execute([$sale['id']]);
        $saleItems = $stmt->fetchAll();
    } else {
        $error = 'Sale not found.';
    }
}

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf = $_POST['_csrf'] ?? '';
    if (!validate_csrf($csrf)) {
        $error = 'Invalid security token. Please refresh and try again.';
    }

    $receiptNumber = $_POST['receipt'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $resolution = $_POST['resolution'] ?? '';
    $selectedItems = $_POST['selected_items'] ?? [];
    $refundAmount = (float) ($_POST['refund_amount'] ?? 0);
    $exchangeProductId = (int) ($_POST['exchange_product_id'] ?? 0);
    $exchangeQty = (int) ($_POST['exchange_qty'] ?? 0);

    if (!$receiptNumber || !$reason || !$resolution || empty($selectedItems)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Look up sale
        $stmt = $db->prepare("SELECT * FROM sales WHERE receipt_number = ? AND store_id = ?");
        $stmt->execute([$receiptNumber, activeStoreId()]);
        $sale = $stmt->fetch();

        if (!$sale) {
            $error = 'Sale not found.';
        } else {
            $stmt = $db->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
            $stmt->execute([$sale['id']]);
            $allItems = $stmt->fetchAll();

            // Build items JSON for selected items
            $returnItems = [];
            foreach ($allItems as $item) {
                if (in_array((string) $item['id'], $selectedItems, true)) {
                    $returnItems[] = [
                        'id' => $item['id'],
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'],
                        'qty' => (int) $item['quantity'],
                        'price' => (float) $item['price'],
                        'total' => (float) $item['total'],
                    ];
                }
            }

            $exchangeProductName = '';
            if ($resolution === 'exchange' && $exchangeProductId) {
                $pStmt = $db->prepare("SELECT name, stock_quantity FROM products WHERE id = ? AND store_id = ?");
                $pStmt->execute([$exchangeProductId, activeStoreId()]);
                $exProd = $pStmt->fetch();
                if (!$exProd) {
                    $error = 'Exchange product not found.';
                } elseif ($exProd['stock_quantity'] < $exchangeQty) {
                    $error = 'Not enough stock for exchange product.';
                } else {
                    $exchangeProductName = $exProd['name'];
                }
            }

            if (!$error) {
                if ($userRole === 'admin') {
                    // Admin processes immediately
                    try {
                        $db->beginTransaction();

                        $stmt = $db->prepare("INSERT INTO return_requests (sale_id, receipt_number, cashier_id, cashier_name, items, reason, resolution, refund_amount, exchange_product_id, exchange_product_name, exchange_qty, status, admin_id, admin_notes, store_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,'approved',?,?,?)");
                        $stmt->execute([
                            $sale['id'], $receiptNumber, $user['id'], userName(),
                            json_encode($returnItems), $reason, $resolution, $refundAmount,
                            $resolution === 'exchange' ? $exchangeProductId : null,
                            $resolution === 'exchange' ? $exchangeProductName : null,
                            $resolution === 'exchange' ? $exchangeQty : 0,
                            $user['id'], $_POST['admin_notes'] ?? 'Processed by admin',
                            activeStoreId()
                        ]);

                        processReturnApproval($db, $returnItems, $reason, $resolution, $exchangeProductId, $exchangeQty, $user);

                        $db->commit();
                        $success = 'Return processed successfully.';
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = 'Error: ' . $e->getMessage();
                    }
                } else {
                    // Cashier submits for approval
                    $stmt = $db->prepare("INSERT INTO return_requests (sale_id, receipt_number, cashier_id, cashier_name, items, reason, resolution, refund_amount, exchange_product_id, exchange_product_name, exchange_qty, status, store_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,'pending',?)");
                    $stmt->execute([
                        $sale['id'], $receiptNumber, $user['id'], userName(),
                        json_encode($returnItems), $reason, $resolution, $refundAmount,
                        $resolution === 'exchange' ? $exchangeProductId : null,
                        $resolution === 'exchange' ? $exchangeProductName : null,
                        $resolution === 'exchange' ? $exchangeQty : 0,
                        activeStoreId()
                    ]);
                    $success = 'Return request sent to admin for approval.';
                }
            }
        }
    }
}

$products = $db->query("SELECT id, name, price, stock_quantity FROM products WHERE status = 'active' AND store_id = " . activeStoreId() . " ORDER BY name")->fetchAll();
$recentSales = $db->query("SELECT receipt_number, cashier_name, total, created_at FROM sales WHERE store_id = " . activeStoreId() . " ORDER BY created_at DESC LIMIT 10")->fetchAll();
$myPending = [];
if ($userRole === 'cashier') {
    $stmt = $db->prepare("SELECT * FROM return_requests WHERE cashier_id = ? AND store_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$user['id'], activeStoreId()]);
    $myPending = $stmt->fetchAll();
}
?>
<div class="page-header">
    <h1><i class="fas fa-undo-alt"></i> Returns & Exchanges</h1>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($userRole === 'cashier' && !empty($myPending)): ?>
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-clock"></i> My Pending Requests</h2>
    </div>
    <div class="table-container">
        <table>
            <thead><tr><th>Receipt</th><th>Reason</th><th>Resolution</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach ($myPending as $rq): ?>
                    <tr>
                        <td><?= e($rq['receipt_number']) ?></td>
                        <td><span class="badge badge-<?= $rq['reason'] === 'damage' ? 'danger' : 'info' ?>"><?= e(ucfirst($rq['reason'])) ?></span></td>
                        <td><?= e(ucfirst($rq['resolution'])) ?></td>
                        <td><?= money((float) $rq['refund_amount']) ?></td>
                        <td>
                            <span class="badge badge-<?= $rq['status'] === 'approved' ? 'success' : ($rq['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                <?= e(ucfirst($rq['status'])) ?>
                            </span>
                        </td>
                        <td><?= e(date('Y-m-d H:i', strtotime($rq['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-search"></i> Find Sale</h2>
    </div>
    <form method="get" class="search-bar">
        <input type="hidden" name="page" value="returns">
        <input type="text" id="receipt-search" name="receipt" class="form-control" placeholder="Enter receipt number..." value="<?= e($receiptNumber) ?>" style="min-width:250px" aria-label="Receipt number">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Look Up</button>
    </form>
    <?php if (!empty($recentSales)): ?>
        <div class="mt-12 fs-13 text-muted">Recent sales:</div>
        <div class="d-flex flex-wrap gap-6 mt-6">
            <?php foreach ($recentSales as $rs): ?>
                <a href="?page=returns&receipt=<?= e($rs['receipt_number']) ?>" class="btn btn-sm btn-outline"><?= e($rs['receipt_number']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($sale): ?>
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-receipt"></i> Sale: <?= e($sale['receipt_number']) ?></h2>
        <span class="fs-14 text-muted"><?= e($sale['cashier_name']) ?> &middot; <?= e(date('Y-m-d H:i', strtotime($sale['created_at']))) ?></span>
    </div>

    <form method="post" id="return-form" onsubmit="return validateReturn()">
        <?= csrf_field() ?>
        <input type="hidden" name="receipt" value="<?= e($receiptNumber) ?>">
        <input type="hidden" name="action" value="submit_return">

        <div class="table-container">
            <table>
                <thead><tr><th style="width:40px">Select</th><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach ($saleItems as $item): ?>
                        <tr>
                            <td><input type="checkbox" name="selected_items[]" value="<?= (int) $item['id'] ?>" onchange="calcRefund()" class="item-checkbox" data-total="<?= (float) $item['total'] ?>"></td>
                            <td><?= e($item['product_name']) ?></td>
                            <td><?= (int) $item['quantity'] ?></td>
                            <td><?= money((float) $item['price']) ?></td>
                            <td><?= money((float) $item['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="form-row mt-16">
            <div class="form-group m-0">
                <label for="return-reason">Reason</label>
                <select id="return-reason" name="reason" class="form-control" required onchange="toggleResolution()">
                    <option value="">Select reason...</option>
                    <option value="return">Return (customer brought back)</option>
                    <option value="damage">Damage (item is damaged)</option>
                </select>
            </div>
            <div class="form-group m-0 d-none" id="resolution-group">
                <label for="return-resolution">Resolution</label>
                <select id="return-resolution" name="resolution" class="form-control" required onchange="toggleExchange()">
                    <option value="">Select resolution...</option>
                    <option value="refund">Refund</option>
                    <option value="exchange">Exchange item</option>
                </select>
            </div>
        </div>

        <div id="refund-details" class="d-none mt-16">
            <div class="form-row">
                <div class="form-group">
                    <label>Refund Amount</label>
                    <input type="number" name="refund_amount" id="refund-amount" class="form-control" step="0.01" min="0" readonly>
                </div>
            </div>
        </div>

        <div id="exchange-details" class="d-none mt-16">
            <div class="form-row">
                <div class="form-group">
                    <label for="exchange-product">Exchange Product</label>
                    <select id="exchange-product" name="exchange_product_id" class="form-control">
                        <option value="">Select product...</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" data-stock="<?= (int) $p['stock_quantity'] ?>">
                                <?= e($p['name']) ?> (R<?= number_format((float) $p['price'], 2) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="exchange-qty">Exchange Quantity</label>
                    <input type="number" id="exchange-qty" name="exchange_qty" class="form-control" min="1" value="1">
                </div>
            </div>
        </div>

        <?php if ($userRole === 'admin'): ?>
        <div class="form-group mt-16">
            <label for="admin-notes">Admin Notes (optional)</label>
            <input type="text" id="admin-notes" name="admin_notes" class="form-control" placeholder="Notes about this return...">
        </div>
        <?php endif; ?>

        <div class="d-flex gap-10 mt-16">
            <button type="submit" class="btn btn-<?= $userRole === 'admin' ? 'success' : 'warning' ?> flex-1 justify-center">
                <i class="fas fa-<?= $userRole === 'admin' ? 'check' : 'paper-plane' ?>"></i>
                <?= $userRole === 'admin' ? 'Process Return' : 'Submit for Approval' ?>
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if ($userRole === 'admin'): ?>
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-list"></i> Pending Approval Requests</h2>
    </div>
    <?php
    $pendingRequests = $db->query("SELECT * FROM return_requests WHERE status = 'pending' AND store_id = " . activeStoreId() . " ORDER BY created_at DESC")->fetchAll();
    ?>
    <?php if (empty($pendingRequests)): ?>
        <p class="muted">No pending requests.</p>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead><tr><th>Receipt</th><th>Cashier</th><th>Items</th><th>Reason</th><th>Resolution</th><th>Amount</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($pendingRequests as $rq): ?>
                        <?php $items = json_decode($rq['items'], true); ?>
                        <tr>
                            <td><?= e($rq['receipt_number']) ?></td>
                            <td><?= e($rq['cashier_name']) ?></td>
                            <td><?= implode(', ', array_column($items, 'product_name')) ?></td>
                            <td><span class="badge badge-<?= $rq['reason'] === 'damage' ? 'danger' : 'info' ?>"><?= e(ucfirst($rq['reason'])) ?></span></td>
                            <td><?= e(ucfirst($rq['resolution'])) ?></td>
                            <td><?= money((float) $rq['refund_amount']) ?></td>
                            <td><?= e(date('Y-m-d H:i', strtotime($rq['created_at']))) ?></td>
                            <td>
                                <form method="post" action="index.php?page=admin-returns" style="display:inline" onsubmit="return confirm('Approve this return? Stock adjustments will be applied.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="approve" value="<?= (int) $rq['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i></button>
                                </form>
                                <form method="post" action="index.php?page=admin-returns" style="display:inline" onsubmit="return confirm('Reject this return?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="reject" value="<?= (int) $rq['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-times"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
function toggleResolution() {
    const reason = document.querySelector('select[name="reason"]').value;
    const group = document.getElementById('resolution-group');
    if (reason === 'return' || reason === 'damage') {
        group.classList.remove('d-none');
    } else {
        group.classList.add('d-none');
        document.getElementById('refund-details').classList.add('d-none');
        document.getElementById('exchange-details').classList.add('d-none');
    }
}

function toggleExchange() {
    const resolution = document.querySelector('select[name="resolution"]').value;
    const reason = document.querySelector('select[name="reason"]').value;
    document.getElementById('refund-details').classList.toggle('d-none', !(reason === 'return' && resolution === 'refund'));
    document.getElementById('exchange-details').classList.toggle('d-none', resolution !== 'exchange');
}

function calcRefund() {
    let total = 0;
    document.querySelectorAll('.item-checkbox:checked').forEach(cb => {
        total += parseFloat(cb.dataset.total || 0);
    });
    document.getElementById('refund-amount').value = total.toFixed(2);

    // Disable reason/resolution if no items selected
    const hasItems = total > 0;
    document.querySelector('select[name="reason"]').disabled = !hasItems;
    document.querySelector('select[name="resolution"]').disabled = !hasItems;
}

function validateReturn() {
    if (document.querySelectorAll('.item-checkbox:checked').length === 0) {
        alert('Please select at least one item to return.');
        return false;
    }
    if (!document.querySelector('select[name="reason"]').value) {
        alert('Please select a reason.');
        return false;
    }
    if (!document.querySelector('select[name="resolution"]').value) {
        alert('Please select a resolution.');
        return false;
    }
    const resolution = document.querySelector('select[name="resolution"]').value;
    if (resolution === 'exchange' && !document.querySelector('select[name="exchange_product_id"]').value) {
        alert('Please select an exchange product.');
        return false;
    }
    return true;
}
</script>
