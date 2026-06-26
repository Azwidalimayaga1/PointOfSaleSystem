<?php declare(strict_types=1);
header('Content-Type: application/json');
try {
    $data = json_decode($_POST['cart'] ?? '[]', true);
    if (empty($data)) { throw new Exception('Cart is empty.'); }
    $user = $_SESSION['user'];
    $stmt = $db->prepare("INSERT INTO held_sales (cashier_id, cashier_name, items, subtotal, discount, tax, total, store_id) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$user['id'], $user['display_name'] ?? $user['full_name'], json_encode($data), (float)($_POST['subtotal']??0), (int)($_POST['discount_pct']??0), (float)($_POST['tax']??0), (float)($_POST['total']??0), activeStoreId()]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
