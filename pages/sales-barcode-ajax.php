<?php declare(strict_types=1);

header('Content-Type: application/json');

$barcode = trim($_POST['barcode'] ?? '');

if (!$barcode) {
    echo json_encode(['found' => false, 'error' => 'No barcode provided.']);
    exit;
}

$stmt = $db->prepare("SELECT id, name, barcode, price, stock_quantity, status FROM products WHERE barcode = ? AND status = 'active' AND store_id = ? LIMIT 1");
$stmt->execute([$barcode, activeStoreId()]);
$product = $stmt->fetch();

if ($product) {
    echo json_encode([
        'found' => true,
        'product' => [
            'id' => (int) $product['id'],
            'name' => $product['name'],
            'barcode' => $product['barcode'],
            'price' => (float) $product['price'],
            'stock_quantity' => (int) $product['stock_quantity'],
        ]
    ]);
} else {
    echo json_encode(['found' => false, 'error' => 'Product not found for barcode: ' . $barcode]);
}
