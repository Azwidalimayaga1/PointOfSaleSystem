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

$barcode = trim($_POST['barcode'] ?? '');
$barcodeType = trim($_POST['barcode_type'] ?? 'Code 128');
$productId = (int) ($_POST['product_id'] ?? 0);
$storeId = (int) ($_POST['store_id'] ?? activeStoreId());
$autoGenerate = !empty($_POST['auto_generate']);

if ($productId > 0) {
    $product = getProduct($db, $productId);
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
        exit;
    }
    if (!canAccessStore((int) $product['store_id'])) {
        jsonAccessDenied();
    }
}

if (!canAccessStore($storeId)) {
    jsonAccessDenied();
}

if ($autoGenerate && !$barcode) {
    $barcode = generateInternalBarcode($db, $storeId, $productId);
    $barcodeType = 'Code 128';
} elseif (!$barcode) {
    echo json_encode(['success' => false, 'message' => 'Barcode value is required or enable auto-generate.']);
    exit;
}

$validation = validateBarcodeFormat($barcode, $barcodeType);
if (!$validation['valid']) {
    echo json_encode(['success' => false, 'message' => 'Barcode does not match selected type. Expected: ' . $validation['expected']]);
    exit;
}

$filePath = generateBarcodeImagePath($barcode, $barcodeType);
$fullPath = __DIR__ . '/../' . $filePath;

$svgContent = '';

if (in_array($barcodeType, ['QR Code', 'DataMatrix', 'Aztec Code', 'MaxiCode', 'PDF417'])) {
    $size = 300;
    $svgContent = '<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">
<rect width="100%" height="100%" fill="white"/>
<text x="50%" y="40%" text-anchor="middle" font-family="monospace" font-size="14" fill="#333">' . e($barcodeType) . '</text>
<text x="50%" y="55%" text-anchor="middle" font-family="monospace" font-size="12" fill="#666">' . e($barcode) . '</text>
<text x="50%" y="70%" text-anchor="middle" font-family="monospace" font-size="10" fill="#999">(2D barcode - see label)</text>
</svg>';
} else {
    if (in_array($barcodeType, ['UPC-A', 'UPC-E', 'EAN-13', 'EAN-8'])) {
        $encoded = $barcode;
    } else {
        $encoded = $barcode;
    }
    $height = 80;
    $charWidth = 11;
    $width = max(strlen($encoded) * $charWidth + 40, 200);
    $barWidth = ($width - 40) / (strlen($encoded) * 11);
    $bars = '';
    for ($i = 0; $i < strlen($encoded); $i++) {
        $char = $encoded[$i];
        $bin = sprintf('%07b', ord($char));
        for ($b = 0; $b < 7; $b++) {
            if ($bin[$b] === '1') {
                $x = 20 + ($i * 11 + $b) * $barWidth;
                $bars .= '<rect x="' . $x . '" y="8" width="' . ($barWidth + 0.5) . '" height="' . ($height - 16) . '" fill="#000"/>';
            }
        }
    }
    $svgContent = '<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . ($height + 20) . '" viewBox="0 0 ' . $width . ' ' . ($height + 20) . '">
<rect width="100%" height="100%" fill="white"/>
' . $bars . '
<text x="50%" y="' . ($height + 14) . '" text-anchor="middle" font-family="monospace" font-size="10" fill="#000">' . e($barcode) . '</text>
</svg>';
}

$dir = dirname($fullPath);
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}
$result = @file_put_contents($fullPath, $svgContent);

if ($result === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to generate barcode image. Check directory permissions.']);
    exit;
}

if ($productId > 0) {
    $stmt = $db->prepare("UPDATE products SET barcode_image = ? WHERE id = ?");
    $stmt->execute([$filePath, $productId]);
    $stmt = $db->prepare("UPDATE product_barcodes SET barcode_image = ? WHERE product_id = ? AND barcode = ?");
    $stmt->execute([$filePath, $productId, $barcode]);
    logBarcodeAction($db, $productId, null, 'barcode_generate', null, $barcode, $storeId, 'Generated ' . $barcodeType . ' barcode');
}

echo json_encode([
    'success' => true,
    'message' => 'Barcode generated successfully.',
    'barcode' => $barcode,
    'barcode_type' => $barcodeType,
    'image_url' => $filePath,
    'full_url' => $filePath,
]);
