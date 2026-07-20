<?php declare(strict_types=1);

if (!defined('DB_HOST')) {
    require __DIR__ . '/../config.php';
    require __DIR__ . '/../functions.php';
}

header('Content-Type: application/json');

requireAjaxLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$csrf = $_POST['_csrf'] ?? '';
if (!validate_csrf($csrf)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

$action = $_POST['action'] ?? 'save';
$productId = (int) ($_POST['product_id'] ?? 0);
$storeId = (int) ($_POST['store_id'] ?? activeStoreId());
$barcode = trim($_POST['barcode'] ?? '');
$barcodeType = trim($_POST['barcode_type'] ?? 'Code 128');
$isPrimary = !empty($_POST['is_primary']);
$barcodeId = (int) ($_POST['barcode_id'] ?? 0);
$generateImage = !empty($_POST['generate_image']);
$autoGenerate = !empty($_POST['auto_generate']);

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
    exit;
}

if (!canAccessStore($storeId)) {
    jsonAccessDenied();
}

$product = getProduct($db, $productId);
if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found.']);
    exit;
}
if ((int) $product['store_id'] !== $storeId && !isSuperAdmin()) {
    jsonAccessDenied();
}

$userRole = userRole();
if (isCashier() && !isSuperAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Cashiers cannot edit barcode details.']);
    exit;
}

if ($action === 'delete' && $barcodeId > 0) {
    $stmt = $db->prepare("SELECT * FROM product_barcodes WHERE id = ? AND store_id = ?");
    $stmt->execute([$barcodeId, $storeId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        echo json_encode(['success' => false, 'message' => 'Barcode not found.']);
        exit;
    }
    $db->prepare("DELETE FROM product_barcodes WHERE id = ? AND store_id = ?")->execute([$barcodeId, $storeId]);
    logBarcodeAction($db, $productId, $barcodeId, 'barcode_delete', $existing['barcode'], null, $storeId, 'Deleted barcode: ' . $existing['barcode']);
    echo json_encode(['success' => true, 'message' => 'Barcode deleted successfully.']);
    exit;
}

if ($action === 'set_primary' && $barcodeId > 0) {
    $db->prepare("UPDATE product_barcodes SET is_primary = 0 WHERE product_id = ? AND store_id = ?")->execute([$productId, $storeId]);
    $db->prepare("UPDATE product_barcodes SET is_primary = 1 WHERE id = ? AND store_id = ?")->execute([$barcodeId, $storeId]);
    logBarcodeAction($db, $productId, $barcodeId, 'barcode_set_primary', null, null, $storeId, 'Set barcode ID ' . $barcodeId . ' as primary');
    echo json_encode(['success' => true, 'message' => 'Primary barcode updated.']);
    exit;
}

if (!$barcode) {
    echo json_encode(['success' => false, 'message' => 'Barcode value is required.']);
    exit;
}

$validation = validateBarcodeFormat($barcode, $barcodeType);
if (!$validation['valid']) {
    echo json_encode(['success' => false, 'message' => 'Barcode does not match selected type. Expected: ' . $validation['expected']]);
    exit;
}

$stmt = $db->prepare("SELECT id, product_id FROM product_barcodes WHERE barcode = ? AND store_id = ?");
$stmt->execute([$barcode, $storeId]);
$existing = $stmt->fetch();
if ($existing && (int) $existing['product_id'] !== $productId) {
    echo json_encode(['success' => false, 'message' => 'This barcode is already assigned to another product in this store.']);
    exit;
}

$barcodeImage = null;
if ($generateImage) {
    $barcodeImage = generateBarcodeImagePath($barcode, $barcodeType);
    $fullPath = __DIR__ . '/../' . $barcodeImage;
    $dir = dirname($fullPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $height = 80;
    $width = max(strlen($barcode) * 11 + 40, 200);
    $barWidth = ($width - 40) / (strlen($barcode) * 11);
    $bars = '';
    for ($i = 0; $i < strlen($barcode); $i++) {
        $char = $barcode[$i];
        $bin = sprintf('%07b', ord($char));
        for ($b = 0; $b < 7; $b++) {
            if ($bin[$b] === '1') {
                $x = 20 + ($i * 11 + $b) * $barWidth;
                $bars .= '<rect x="' . number_format($x, 2, '.', '') . '" y="8" width="' . number_format($barWidth + 0.5, 2, '.', '') . '" height="' . ($height - 16) . '" fill="#000"/>';
            }
        }
    }
    $svgContent = '<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . ($height + 20) . '" viewBox="0 0 ' . $width . ' ' . ($height + 20) . '">
<rect width="100%" height="100%" fill="white"/>
' . $bars . '
<text x="50%" y="' . ($height + 14) . '" text-anchor="middle" font-family="monospace" font-size="10" fill="#000">' . e($barcode) . '</text>
</svg>';
    @file_put_contents($fullPath, $svgContent);
}

$stmt = $db->prepare("SELECT id FROM product_barcodes WHERE product_id = ? AND barcode = ? AND store_id = ?");
$stmt->execute([$productId, $barcode, $storeId]);
$existingRow = $stmt->fetch();

if ($existingRow) {
    $stmt = $db->prepare("UPDATE product_barcodes SET barcode_type = ?, updated_at = NOW(), barcode_image = COALESCE(?, barcode_image), is_primary = ? WHERE id = ?");
    $stmt->execute([$barcodeType, $barcodeImage, $isPrimary ? 1 : 0, (int) $existingRow['id']]);
    logBarcodeAction($db, $productId, (int) $existingRow['id'], 'barcode_update', null, $barcode, $storeId, 'Updated barcode type to ' . $barcodeType);
    $barcodeId = (int) $existingRow['id'];
} else {
    $stmt = $db->prepare("INSERT INTO product_barcodes (product_id, store_id, barcode, barcode_type, barcode_image, is_primary, created_by) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$productId, $storeId, $barcode, $barcodeType, $barcodeImage, $isPrimary ? 1 : 0, CURRENT_USER_ID]);
    $barcodeId = (int) $db->lastInsertId();
    logBarcodeAction($db, $productId, $barcodeId, 'barcode_create', null, $barcode, $storeId, 'Created ' . $barcodeType . ' barcode');
}

// Only sync to products.barcode if this is primary OR product has no barcode yet
$currentProductBarcode = $product['barcode'] ?? '';
if ($isPrimary || !$currentProductBarcode) {
    $db->prepare("UPDATE products SET barcode = ?, barcode_type = ?, barcode_image = COALESCE(?, barcode_image), barcode_auto_generated = ?, barcode_updated_at = NOW() WHERE id = ?")
       ->execute([$barcode, $barcodeType, $barcodeImage, $autoGenerate ? 1 : 0, $productId]);
}

echo json_encode([
    'success' => true,
    'message' => 'Barcode saved successfully.',
    'barcode_id' => $barcodeId,
    'barcode' => $barcode,
    'barcode_type' => $barcodeType,
    'is_primary' => $isPrimary,
    'image_url' => $barcodeImage,
]);
