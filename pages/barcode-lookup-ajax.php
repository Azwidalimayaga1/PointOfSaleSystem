<?php declare(strict_types=1);

if (!defined('DB_HOST')) {
    require __DIR__ . '/../config.php';
    require __DIR__ . '/../functions.php';
}

header('Content-Type: application/json');

requireAjaxLogin();

$barcode = trim($_POST['barcode'] ?? '');

if (!$barcode) {
    echo json_encode(['found' => false, 'error_code' => 'invalid_barcode', 'message' => 'No barcode provided.']);
    exit;
}

$sanitized = preg_replace('/[^0-9A-Za-z\-]/', '', $barcode);
if ($sanitized !== $barcode) {
    echo json_encode(['found' => false, 'error_code' => 'invalid_barcode', 'message' => 'Barcode contains invalid characters.']);
    exit;
}

$storeId = activeStoreId();
$isSuper = isSuperAdmin();

$product = findProductByBarcode($db, $barcode, $storeId);

if (!$product) {
    if (!$isSuper) {
        echo json_encode(['found' => false, 'error_code' => 'barcode_not_found', 'message' => 'Product not found for this barcode.']);
        exit;
    }
    $altStores = $db->prepare("SELECT pb.store_id, s.name as store_name FROM product_barcodes pb JOIN stores s ON s.id = pb.store_id WHERE pb.barcode = ?");
    $altStores->execute([$barcode]);
    $foundInStores = $altStores->fetchAll();
    if (!empty($foundInStores)) {
        echo json_encode(['found' => false, 'error_code' => 'unauthorized_store', 'message' => 'Barcode exists in another store.', 'found_in_stores' => $foundInStores]);
        exit;
    }
    $altProd = $db->prepare("SELECT store_id FROM products WHERE barcode = ?");
    $altProd->execute([$barcode]);
    $altRow = $altProd->fetch();
    if ($altRow) {
        echo json_encode(['found' => false, 'error_code' => 'unauthorized_store', 'message' => 'Barcode exists in another store.', 'store_id' => (int) $altRow['store_id']]);
        exit;
    }
    echo json_encode(['found' => false, 'error_code' => 'barcode_not_found', 'message' => 'Product not found for this barcode.']);
    exit;
}

if ($product['status'] !== 'active') {
    echo json_encode(['found' => false, 'error_code' => 'product_inactive', 'message' => 'This product is inactive and cannot be sold.']);
    exit;
}

if ((int) $product['stock_quantity'] <= 0) {
    echo json_encode([
        'found' => false,
        'error_code' => 'product_out_of_stock',
        'message' => 'This product is out of stock.',
        'product' => [
            'id' => (int) $product['id'],
            'name' => $product['name'],
            'stock_quantity' => 0,
        ]
    ]);
    exit;
}

echo json_encode([
    'found' => true,
    'product' => [
        'id' => (int) $product['id'],
        'name' => $product['name'],
        'barcode' => $product['barcode'],
        'barcode_type' => $product['barcode_type'],
        'price' => (float) $product['price'],
        'stock_quantity' => (int) $product['stock_quantity'],
        'status' => $product['status'],
        'image' => $product['image'],
        'category' => $product['category'],
        'store_id' => (int) $product['store_id'],
        'cost_price' => (float) ($product['cost_price'] ?? 0),
        'low_stock_threshold' => (int) ($product['low_stock_threshold'] ?? 10),
    ]
]);
