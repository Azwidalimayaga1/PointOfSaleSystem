<?php

declare(strict_types=1);

// Mark as read
if (isset($_GET['mark_read'])) {
    $stmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE id = ?");
    $stmt->execute([(int) $_GET['mark_read']]);
    redirect('index.php?page=admin-messages');
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $db->exec("UPDATE messages SET is_read = 1");
    redirect('index.php?page=admin-messages');
}

$messages = getAllMessages($db);
$unread = getAllUnreadMessages($db);
?>
<div class="page-header">
    <h1><i class="fas fa-envelope"></i> Messages from Staff</h1>
    <?php if (!empty($unread)): ?>
        <a href="?page=admin-messages&mark_all_read=1" class="btn btn-sm btn-primary">
            <i class="fas fa-check-double"></i> Mark All Read
        </a>
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
                    <a href="?page=admin-messages&mark_read=<?= (int) $msg['id'] ?>" class="btn btn-sm btn-success" style="white-space:nowrap">
                        <i class="fas fa-check"></i> Mark Read
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
