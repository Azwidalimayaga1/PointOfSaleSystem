<?php

declare(strict_types=1);

// Mark as read via POST only
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['_csrf'] ?? '';
    if (!validate_csrf($csrf)) {
        redirect('index.php?page=admin-messages');
    }

    if (isset($_POST['mark_read'])) {
        $stmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE id = ?");
        $stmt->execute([(int) $_POST['mark_read']]);
        redirect('index.php?page=admin-messages');
    }

    if (isset($_POST['mark_all_read'])) {
        $db->exec("UPDATE messages SET is_read = 1");
        redirect('index.php?page=admin-messages');
    }
}

$messages = getAllMessages($db);
$unread = getAllUnreadMessages($db);
?>
<div class="page-header">
    <h1><i class="fas fa-envelope"></i> Messages from Staff</h1>
    <?php if (!empty($unread)): ?>
        <form method="post" action="index.php?page=admin-messages" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="mark_all_read" value="1">
            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-check-double"></i> Mark All Read</button>
        </form>
    <?php endif; ?>
</div>

<?php if (empty($messages)): ?>
    <div class="card">
        <p class="muted">No messages from staff yet.</p>
    </div>
<?php else: ?>
    <?php foreach ($messages as $msg): ?>
        <div class="card" style="<?= (int) $msg['is_read'] ? '' : 'border-left:4px solid var(--primary);background:var(--bg-info-light)' ?>">
            <div style="display:flex;justify-content:space-between;align-items:start;gap:12px">
                <div style="flex:1">
                    <div style="display:flex;gap:12px;align-items:center;margin-bottom:8px;flex-wrap:wrap">
                        <strong style="font-size:15px"><i class="fas fa-user"></i> <?= e($msg['sender_name']) ?></strong>
                        <span style="font-size:12px;color:var(--gray-400)">
                            <i class="far fa-clock"></i> <?= e(date('Y-m-d H:i', strtotime($msg['created_at']))) ?>
                        </span>
                        <?php if (!(int) $msg['is_read']): ?>
                            <span class="badge badge-danger">New</span>
                        <?php else: ?>
                            <span class="badge badge-gray">Read</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:14px;line-height:1.6;background:var(--gray-50);padding:14px;border-radius:8px">
                        <?= nl2br(e($msg['message'])) ?>
                    </div>
                </div>
                <?php if (!(int) $msg['is_read']): ?>
                    <form method="post" action="index.php?page=admin-messages" style="display:inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="mark_read" value="<?= (int) $msg['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-success" style="white-space:nowrap"><i class="fas fa-check"></i> Mark Read</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
