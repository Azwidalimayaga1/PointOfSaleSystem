<?php declare(strict_types=1);
header('Content-Type: application/json');
try {
    requireAjaxLogin();
    $id = (int) ($_POST['held_id'] ?? 0);
    if (!$id) { throw new Exception('Invalid held sale ID.'); }
    $stmt = $db->prepare("DELETE FROM held_sales WHERE id = ? AND cashier_id = ? AND store_id = ?");
    $stmt->execute([$id, $_SESSION['user_id'], activeStoreId()]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
