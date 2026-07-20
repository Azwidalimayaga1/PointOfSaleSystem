<?php

declare(strict_types=1);

header('Content-Type: application/json');

try {
    requireAjaxLogin();

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'validate_coupon') {
        $code = trim($_POST['coupon_code'] ?? '');
        $subtotal = (float) ($_POST['subtotal'] ?? 0);
        $storeId = activeStoreId();

        if (empty($code)) {
            throw new \Exception('Please enter a coupon code.');
        }

        $result = validateCoupon($db, $code, $subtotal, $storeId);
        if (!$result['success']) {
            echo json_encode(['success' => false, 'message' => $result['message']]);
            exit;
        }

        $coupon = $result['coupon'];
        $calcResult = calculateCouponDiscount($coupon, $subtotal);

        echo json_encode([
            'success' => true,
            'message' => 'Coupon applied successfully.',
            'coupon_code' => $coupon['code'],
            'coupon_name' => $coupon['name'],
            'discount_type' => $calcResult['discount_type'],
            'discount_value' => $calcResult['discount_value'],
            'discount_amount' => $calcResult['discount_amount'],
            'subtotal_before_discount' => $calcResult['subtotal_before_discount'],
            'final_total' => $calcResult['total_after_discount'],
        ]);
        exit;
    }

    // Toggle coupon status (activate/deactivate)
    if ($action === 'toggle_status') {
        $csrf = $_POST['_csrf'] ?? '';
        if (!validate_csrf($csrf)) {
            throw new \Exception('Invalid security token.');
        }

        $couponId = (int) ($_POST['coupon_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';

        if (!in_array($newStatus, ['active', 'inactive', 'expired'], true)) {
            throw new \Exception('Invalid status.');
        }

        $coupon = getCoupon($db, $couponId);
        if (!$coupon) {
            throw new \Exception('Coupon not found.');
        }

        // Check permissions
        if (!isSuperAdmin() && isStoreAdmin()) {
            $userStoreId = currentUserStoreId();
            $couponStoreId = $coupon['store_id'] ? (int) $coupon['store_id'] : null;
            if ($couponStoreId !== null && $couponStoreId !== $userStoreId) {
                throw new \Exception('You can only manage coupons for your own store.');
            }
        } elseif (!isSuperAdmin() && !isStoreAdmin()) {
            throw new \Exception('Access denied.');
        }

        $db->prepare("UPDATE discount_coupons SET status = ? WHERE id = ?")->execute([$newStatus, $couponId]);
        logAction($db, 'coupon_status_' . $newStatus, 'coupon', $couponId, 'Coupon ' . $coupon['code'] . ' status set to ' . $newStatus);

        echo json_encode(['success' => true, 'message' => 'Coupon status updated to ' . $newStatus . '.']);
        exit;
    }

    // Delete coupon
    if ($action === 'delete') {
        $csrf = $_POST['_csrf'] ?? '';
        if (!validate_csrf($csrf)) {
            throw new \Exception('Invalid security token.');
        }

        $couponId = (int) ($_POST['coupon_id'] ?? 0);
        $coupon = getCoupon($db, $couponId);
        if (!$coupon) {
            throw new \Exception('Coupon not found.');
        }

        // Check if coupon has been used
        $stmt = $db->prepare("SELECT COUNT(*) FROM sales WHERE discount_coupon_id = ?");
        $stmt->execute([$couponId]);
        $usedCount = (int) $stmt->fetchColumn();

        if ($usedCount > 0) {
            throw new \Exception('Cannot delete this coupon. It has been used in ' . $usedCount . ' sale(s). Deactivate it instead.');
        }

        // Check permissions
        if (!isSuperAdmin() && isStoreAdmin()) {
            $userStoreId = currentUserStoreId();
            $couponStoreId = $coupon['store_id'] ? (int) $coupon['store_id'] : null;
            if ($couponStoreId !== null && $couponStoreId !== $userStoreId) {
                throw new \Exception('Access denied.');
            }
        } elseif (!isSuperAdmin() && !isStoreAdmin()) {
            throw new \Exception('Access denied.');
        }

        $db->prepare("DELETE FROM discount_coupons WHERE id = ?")->execute([$couponId]);
        logAction($db, 'coupon_deleted', 'coupon', $couponId, 'Coupon ' . $coupon['code'] . ' deleted');

        echo json_encode(['success' => true, 'message' => 'Coupon deleted successfully.']);
        exit;
    }

    throw new \Exception('Invalid action.');

} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
