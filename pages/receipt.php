<?php

declare(strict_types=1);

$saleId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$saleId) {
    if (isCashier()) {
        redirect('index.php?page=sales');
    }
    redirect('index.php?page=dashboard');
}

$stmt = $db->prepare("SELECT * FROM sales WHERE id = ? AND store_id = ?");
$stmt->execute([$saleId, activeStoreId()]);
$sale = $stmt->fetch();

if (!$sale) redirect('index.php?page=dashboard');

$stmt = $db->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
$stmt->execute([$saleId]);
$items = $stmt->fetchAll();

// Load receipt customization from store settings
$receiptStoreSettings = getStoreSettings($db, activeStoreId());

$receiptLogo = $receiptStoreSettings['receipt_logo_path'] ?? ($receiptStoreSettings['logo_path'] ?? '');
$receiptDisplayName = $receiptStoreSettings['store_display_name'] ?? STORE_NAME;
$receiptAddress = $receiptStoreSettings['physical_address'] ?? STORE_ADDRESS;
$receiptContact = $receiptStoreSettings['contact_number'] ?? STORE_CONTACT;
$receiptEmail = $receiptStoreSettings['email_address'] ?? '';
$receiptFooter = $receiptStoreSettings['receipt_footer'] ?? RECEIPT_FOOTER;
$receiptThankYou = $receiptStoreSettings['thank_you_message'] ?? '';
$receiptReturnPolicy = $receiptStoreSettings['return_policy'] ?? '';
$receiptExchangePolicy = $receiptStoreSettings['exchange_policy'] ?? '';
$receiptSocial = $receiptStoreSettings['social_media_handles'] ?? '';
$receiptWhatsApp = $receiptStoreSettings['whatsapp_number'] ?? '';
$showCashier = (int) ($receiptStoreSettings['show_cashier_name_on_receipt'] ?? 1);
$showDiscount = (int) ($receiptStoreSettings['show_discount_on_receipt'] ?? 1);
$showQR = (int) ($receiptStoreSettings['show_qr_on_receipt'] ?? 0);
$showCustomer = (int) ($receiptStoreSettings['show_customer_details_on_receipt'] ?? 0);
?>
<div class="page-header">
    <div>
        <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        <a href="index.php?page=sales" class="btn btn-success"><i class="fas fa-shopping-cart"></i> New Sale</a>
        <a href="index.php?page=dashboard" class="btn btn-outline"><i class="fas fa-home"></i> Dashboard</a>
    </div>
</div>

<div class="receipt-box" id="receipt">
    <?php if ($receiptLogo): ?>
        <div style="text-align:center;margin-bottom:8px">
            <img src="<?= e($receiptLogo) ?>" alt="Store Logo" style="max-height:60px;max-width:200px">
        </div>
    <?php endif; ?>
    <div class="store-name"><?= e($receiptDisplayName) ?></div>
    <?php if ($receiptAddress): ?>
        <div class="store-info"><?= nl2br(e($receiptAddress)) ?></div>
    <?php endif; ?>
    <?php if ($receiptContact): ?>
        <div class="store-info"><?= e($receiptContact) ?></div>
    <?php endif; ?>
    <?php if ($receiptEmail): ?>
        <div class="store-info"><?= e($receiptEmail) ?></div>
    <?php endif; ?>

    <div class="receipt-header">
        <strong>RECEIPT</strong><br>
        <?= e($sale['receipt_number']) ?><br>
        <?= e(date('Y-m-d H:i', strtotime($sale['created_at']))) ?><br>
        <?php if ($showCashier): ?>
            Cashier: <?= e($sale['cashier_name']) ?>
        <?php endif; ?>
    </div>

    <?php if ($showCustomer && $sale['customer_name']): ?>
        <div style="font-size:11px;margin-bottom:8px;color:var(--text-secondary)">
            Customer: <?= e($sale['customer_name']) ?>
            <?php if ($sale['customer_phone']): ?> | <?= e($sale['customer_phone']) ?><?php endif; ?>
        </div>
    <?php endif; ?>

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
        <?php if ($showDiscount && ((float) $sale['discount'] > 0 || ($sale['discount_code'] ?? ''))): ?>
        <?php if ($sale['discount_code'] ?? ''): ?>
        <div class="flex-between">
            <span>Discount Code: <strong><?= e($sale['discount_code']) ?></strong></span>
            <span class="text-success">-<?= money((float) ($sale['discount_amount'] ?: $sale['discount'])) ?></span>
        </div>
        <?php else: ?>
        <div class="flex-between">
            <span>Discount<?php if ($sale['discount_type'] === 'percentage'): ?> (<?= round(((float) $sale['discount'] / ((float) $sale['subtotal'] > 0 ? (float) $sale['subtotal'] : 1)) * 100) ?>%)<?php endif; ?></span>
            <span>-<?= money((float) $sale['discount']) ?></span>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        <?php if ($showDiscount && (float) $sale['subtotal_before_discount'] > 0 && (float) $sale['subtotal_before_discount'] !== (float) $sale['subtotal']): ?>
        <div class="flex-between fs-11 text-muted">
            <span>Subtotal before discount</span>
            <span><?= money((float) $sale['subtotal_before_discount']) ?></span>
        </div>
        <div class="flex-between fs-11 text-muted">
            <span>Total after discount</span>
            <span><?= money((float) $sale['total_after_discount']) ?></span>
        </div>
        <?php endif; ?>
        <div class="flex-between">
            <span>VAT (<?= (float) $sale['tax_rate'] ?>%)</span>
            <span><?= money((float) $sale['tax']) ?></span>
        </div>
        <div class="flex-between fs-18 fw-bold" style="border-top:2px solid var(--gray-800);padding-top:8px;margin-top:8px">
            <span>Total Paid</span>
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

    <?php if ($receiptThankYou): ?>
        <div class="receipt-footer" style="margin-top:12px">
            <?= nl2br(e($receiptThankYou)) ?>
        </div>
    <?php endif; ?>

    <?php if ($receiptSocial || $receiptWhatsApp): ?>
        <div style="text-align:center;font-size:11px;color:var(--text-secondary);margin-top:8px">
            <?php if ($receiptSocial): ?><div><?= e($receiptSocial) ?></div><?php endif; ?>
            <?php if ($receiptWhatsApp): ?><div>WhatsApp: <?= e($receiptWhatsApp) ?></div><?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($receiptFooter): ?>
        <div class="receipt-footer">
            <?= nl2br(e($receiptFooter)) ?>
        </div>
    <?php endif; ?>

    <?php if ($receiptReturnPolicy): ?>
        <div style="font-size:10px;color:var(--text-secondary);margin-top:8px;text-align:center">
            <strong>Returns:</strong> <?= nl2br(e($receiptReturnPolicy)) ?>
        </div>
    <?php endif; ?>

    <?php if ($receiptExchangePolicy): ?>
        <div style="font-size:10px;color:var(--text-secondary);margin-top:4px;text-align:center">
            <strong>Exchanges:</strong> <?= nl2br(e($receiptExchangePolicy)) ?>
        </div>
    <?php endif; ?>

    <?php if ($showQR): ?>
        <div style="text-align:center;margin-top:12px;font-size:10px;color:var(--text-secondary)">
            <i class="fas fa-qrcode" style="font-size:32px;display:block;margin-bottom:4px"></i>
            Receipt #<?= e($sale['receipt_number']) ?>
        </div>
    <?php endif; ?>
</div>
