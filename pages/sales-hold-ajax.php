<?php declare(strict_types=1);
header('Content-Type: application/json');
try {
    requireAjaxLogin();
    $csrf = $_POST['_csrf'] ?? '';
    if (!validate_csrf($csrf)) {
        throw new Exception('Invalid security token. Please refresh the page.');
    }
    $data = json_decode($_POST['cart'] ?? '[]', true);
    if (empty($data)) { throw new Exception('Cart is empty.'); }
    $stmt = $db->prepare("INSERT INTO held_sales (cashier_id, cashier_name, items, subtotal, discount, tax, total, store_id) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$_SESSION['user_id'], userName(), json_encode($data), (float)($_POST['subtotal']??0), (int)($_POST['discount_pct']??0), (float)($_POST['tax']??0), (float)($_POST['total']??0), activeStoreId()]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
