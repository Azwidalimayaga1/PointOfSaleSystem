<?php

declare(strict_types=1);

$user = $_SESSION['user'] ?? [];
$userRole = $_SESSION['user']['role'] ?? userRole();

// Handle approve/reject via POST only
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['_csrf'] ?? '';
    if (!validate_csrf($csrf)) {
        $_SESSION['pos_flash'] = ['type' => 'danger', 'message' => 'Invalid security token. Please refresh and try again.'];
        redirect('index.php?page=returns');
    }

    if (isset($_POST['approve'])) {
        $id = (int) $_POST['approve'];
        $stmt = $db->prepare("SELECT * FROM return_requests WHERE id = ? AND status = 'pending' AND store_id = ?");
        $stmt->execute([$id, activeStoreId()]);
        $rq = $stmt->fetch();

        if ($rq) {
            try {
                $db->beginTransaction();

                $items = json_decode($rq['items'], true);
                processReturnApproval($db, $items, $rq['reason'], $rq['resolution'], (int) ($rq['exchange_product_id'] ?? 0), (int) ($rq['exchange_qty'] ?? 0), $user);

                $stmt = $db->prepare("UPDATE return_requests SET status = 'approved', admin_id = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user['id'], $_POST['notes'] ?? 'Approved by admin', $id]);

                $db->commit();
                logAction($db, 'return_approved', 'return_request', $id, 'Return request ' . $id . ' approved');
                $_SESSION['pos_flash'] = ['type' => 'success', 'message' => 'Return request approved.'];
            } catch (Exception $e) {
                $db->rollBack();
                logAction($db, 'return_approve_failed', 'return_request', $id, 'Failed to approve return ' . $id . ': ' . $e->getMessage());
                $_SESSION['pos_flash'] = ['type' => 'danger', 'message' => 'Error approving return: ' . $e->getMessage()];
            }
        }
        redirect('index.php?page=returns');
    }

    if (isset($_POST['reject'])) {
        $id = (int) $_POST['reject'];
        $stmt = $db->prepare("UPDATE return_requests SET status = 'rejected', admin_id = ?, admin_notes = ?, updated_at = NOW() WHERE id = ? AND status = 'pending'");
        $stmt->execute([$user['id'], $_POST['notes'] ?? 'Rejected by admin', $id]);
        logAction($db, 'return_rejected', 'return_request', $id, 'Return request ' . $id . ' rejected');
        $_SESSION['pos_flash'] = ['type' => 'info', 'message' => 'Return request rejected.'];
        redirect('index.php?page=returns');
    }
}

// If no action, redirect back
redirect('index.php?page=returns');
