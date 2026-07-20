<?php declare(strict_types=1);

if (!defined('DB_HOST')) {
    require __DIR__ . '/../config.php';
    require __DIR__ . '/../functions.php';
}

header('Content-Type: application/json');

requireAjaxLogin();

$barcode = trim($_POST['barcode'] ?? '');
$barcodeType = trim($_POST['barcode_type'] ?? 'Code 128');
$storeId = (int) ($_POST['store_id'] ?? activeStoreId());

if (!$barcode) {
    echo json_encode(['valid' => false, 'message' => 'No barcode provided.']);
    exit;
}

if (!canAccessStore($storeId)) {
    echo json_encode(['valid' => false, 'message' => 'Unauthorized store access.']);
    exit;
}

$validation = validateBarcodeFormat($barcode, $barcodeType);

if (!$validation['valid']) {
    echo json_encode([
        'valid' => false,
        'message' => 'Invalid format for ' . $barcodeType . '. Expected: ' . $validation['expected'],
        'expected' => $validation['expected'],
        'length' => strlen($barcode),
        'type' => $barcodeType,
    ]);
    exit;
}

$stmt = $db->prepare("SELECT id, product_id FROM product_barcodes WHERE barcode = ? AND store_id = ? LIMIT 1");
$stmt->execute([$barcode, $storeId]);
$existing = $stmt->fetch();

$duplicate = false;
$existingProductId = null;
if ($existing) {
    $duplicate = true;
    $existingProductId = (int) $existing['product_id'];
}

echo json_encode([
    'valid' => true,
    'message' => 'Barcode format is valid.',
    'length' => strlen($barcode),
    'type' => $barcodeType,
    'duplicate' => $duplicate,
    'existing_product_id' => $existingProductId,
]);
