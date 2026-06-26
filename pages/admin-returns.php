<?php

declare(strict_types=1);

$user = $_SESSION['user'];

// Handle approve/reject
if (isset($_GET['approve'])) {
    $id = (int) $_GET['approve'];
    $stmt = $db->prepare("SELECT * FROM return_requests WHERE id = ? AND status = 'pending' AND store_id = ?");
    $stmt->execute([$id, activeStoreId()]);
    $rq = $stmt->fetch();

    if ($rq) {
        try {
            $db->beginTransaction();

            $items = json_decode($rq['items'], true);
            processReturnApproval($db, $items, $rq['reason'], $rq['resolution'], (int) ($rq['exchange_product_id'] ?? 0), (int) ($rq['exchange_qty'] ?? 0), $user);

            $stmt = $db->prepare("UPDATE return_requests SET status = 'approved', admin_id = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$user['id'], $_GET['notes'] ?? 'Approved by admin', $id]);

            $db->commit();
            $_SESSION['pos_flash'] = ['type' => 'success', 'message' => 'Return request approved.'];
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['pos_flash'] = ['type' => 'danger', 'message' => 'Error approving return: ' . $e->getMessage()];
        }
    }
    redirect('index.php?page=returns');
}

if (isset($_GET['reject'])) {
    $id = (int) $_GET['reject'];
    $stmt = $db->prepare("UPDATE return_requests SET status = 'rejected', admin_id = ?, admin_notes = ?, updated_at = NOW() WHERE id = ? AND status = 'pending'");
    $stmt->execute([$user['id'], $_GET['notes'] ?? 'Rejected by admin', $id]);
    $_SESSION['pos_flash'] = ['type' => 'info', 'message' => 'Return request rejected.'];
    redirect('index.php?page=returns');
}

// If no action, redirect back
redirect('index.php?page=returns');
