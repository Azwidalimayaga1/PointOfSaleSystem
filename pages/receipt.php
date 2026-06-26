<?php

declare(strict_types=1);

$saleId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$saleId) redirect('index.php?page=dashboard');

$stmt = $db->prepare("SELECT * FROM sales WHERE id = ? AND store_id = ?");
$stmt->execute([$saleId, activeStoreId()]);
$sale = $stmt->fetch();

if (!$sale) redirect('index.php?page=dashboard');

$stmt = $db->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
$stmt->execute([$saleId]);
$items = $stmt->fetchAll();
?>
<div class="page-header">
    <h1><i class="fas fa-receipt"></i> Receipt</h1>
    <div>
        <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        <a href="index.php?page=sales" class="btn btn-success"><i class="fas fa-shopping-cart"></i> New Sale</a>
        <a href="index.php?page=dashboard" class="btn btn-outline"><i class="fas fa-home"></i> Dashboard</a>
    </div>
</div>

<div class="receipt-box" id="receipt">
    <div class="store-name"><?= e(STORE_NAME) ?></div>
    <div class="store-info"><?= nl2br(e(STORE_ADDRESS)) ?></div>
    <div class="store-info"><?= e(STORE_CONTACT) ?></div>

    <div class="receipt-header">
        <strong>RECEIPT</strong><br>
        <?= e($sale['receipt_number']) ?><br>
        <?= e(date('Y-m-d H:i', strtotime($sale['created_at']))) ?><br>
        Cashier: <?= e($sale['cashier_name']) ?>
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
            <?php foreach ($items as $item): ?>
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
        <div class="flex-between">
            <span>Subtotal</span>
            <span><?= money((float) $sale['subtotal']) ?></span>
        </div>
        <?php if ((float) $sale['discount'] > 0): ?>
        <div class="flex-between">
            <span>Discount<?php if ($sale['discount_type'] === 'percentage'): ?> (<?= round(((float) $sale['discount'] / ((float) $sale['subtotal'] > 0 ? (float) $sale['subtotal'] : 1)) * 100) ?>%)<?php endif; ?></span>
            <span>-<?= money((float) $sale['discount']) ?></span>
        </div>
        <?php endif; ?>
        <div class="flex-between">
            <span>VAT (<?= (float) $sale['tax_rate'] ?>%)</span>
            <span><?= money((float) $sale['tax']) ?></span>
        </div>
        <div class="flex-between fs-18 fw-bold" style="border-top:2px solid var(--gray-800);padding-top:8px;margin-top:8px">
            <span>Total</span>
            <span><?= money((float) $sale['total']) ?></span>
        </div>
    </div>

    <div class="mt-12">
        <div class="flex-between">
            <span>Payment: <?= e(ucfirst($sale['payment_method'])) ?></span>
        </div>
        <?php if ((float) $sale['cash_amount'] > 0): ?>
            <div class="flex-between">
                <span>Cash</span><span><?= money((float) $sale['cash_amount']) ?></span>
            </div>
        <?php endif; ?>
        <?php if ((float) $sale['card_amount'] > 0): ?>
            <div class="flex-between">
                <span>Card</span><span><?= money((float) $sale['card_amount']) ?></span>
            </div>
        <?php endif; ?>
        <?php if ((float) $sale['change_amount'] > 0): ?>
            <div class="flex-between">
                <span>Change</span><span><?= money((float) $sale['change_amount']) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <div class="receipt-footer">
        <?= nl2br(e(RECEIPT_FOOTER)) ?>
    </div>
</div>
