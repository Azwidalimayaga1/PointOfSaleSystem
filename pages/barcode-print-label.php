<?php declare(strict_types=1);

if (!defined('DB_HOST')) {
    require __DIR__ . '/../config.php';
    require __DIR__ . '/../functions.php';
}

requireLogin();

$productId = (int) ($_GET['product_id'] ?? 0);
$labelCount = max(1, min(50, (int) ($_GET['count'] ?? 1)));
$format = $_GET['format'] ?? 'single';

if ($productId <= 0) {
    die('Invalid product ID.');
}

$product = getProduct($db, $productId);
if (!$product) {
    die('Product not found.');
}
if (!canAccessStore((int) $product['store_id'])) {
    accessDenied();
}

$barcodes = getProductPrimaryBarcode($db, $productId, (int) $product['store_id']);

$storeName = STORE_NAME;
$price = (float) $product['price'];
$productName = $product['name'];
$barcodeValue = $product['barcode'] ?? ($barcodes['barcode'] ?? '');
$barcodeType = $product['barcode_type'] ?? ($barcodes['barcode_type'] ?? 'Code 128');
$barcodeImage = $product['barcode_image'] ?? ($barcodes['barcode_image'] ?? '');
$sku = $product['barcode'] ?? '';

$showBarcodeImage = $barcodeImage && file_exists(__DIR__ . '/../' . $barcodeImage);

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Barcode Label - <?= e($productName) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Arial', sans-serif; padding: 20px; background: #fff; }
        .label-container { display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-start; }
        .label {
            width: 280px;
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 12px;
            background: white;
            page-break-inside: avoid;
            text-align: center;
        }
        .label .store-name { font-size: 11px; font-weight: 700; color: #333; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
        .label .product-name { font-size: 10px; color: #666; margin-bottom: 6px; line-height: 1.3; max-height: 28px; overflow: hidden; }
        .label .price { font-size: 18px; font-weight: 800; color: #000; margin-bottom: 6px; }
        .label .barcode-img { width: 100%; max-height: 60px; object-fit: contain; margin-bottom: 4px; }
        .label .barcode-placeholder {
            width: 100%; height: 50px; background: repeating-linear-gradient(90deg, #000 0, #000 2px, #fff 2px, #fff 5px);
            margin-bottom: 4px;
        }
        .label .barcode-number { font-family: 'Courier New', monospace; font-size: 12px; font-weight: 600; color: #000; letter-spacing: 1px; }
        .label .barcode-type { font-size: 8px; color: #999; margin-top: 2px; }
        .label .sku { font-size: 8px; color: #aaa; margin-top: 1px; }
        .print-header { text-align: center; margin-bottom: 20px; display: none; }
        .print-header h2 { font-size: 16px; }
        .no-print { text-align: center; margin-bottom: 16px; }
        @media print {
            .no-print { display: none; }
            .print-header { display: block; }
            .label { border: 1px solid #ccc; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print Labels</button>
        <button onclick="window.close()" class="btn btn-outline">Close</button>
    </div>
    <div class="print-header">
        <h2><?= e($storeName) ?> - Barcode Labels</h2>
        <p><?= e($productName) ?> (<?= e($barcodeValue) ?>)</p>
    </div>
    <div class="label-container">
        <?php for ($i = 0; $i < $labelCount; $i++): ?>
        <div class="label">
            <div class="store-name"><?= e($storeName) ?></div>
            <div class="product-name"><?= e($productName) ?></div>
            <div class="price"><?= money($price) ?></div>
            <?php if ($showBarcodeImage): ?>
                <img src="<?= e($barcodeImage) ?>" alt="" class="barcode-img">
            <?php else: ?>
                <div class="barcode-placeholder"></div>
            <?php endif; ?>
            <div class="barcode-number"><?= e($barcodeValue) ?></div>
            <div class="barcode-type"><?= e($barcodeType) ?></div>
            <?php if ($sku): ?>
            <div class="sku">SKU: <?= e($sku) ?></div>
            <?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</body>
</html>
