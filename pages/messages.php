<?php

declare(strict_types=1);

$userId = (int) ($_SESSION['user']['id'] ?? 0);
$senderName = userName() ?? 'Unknown';
$error = '';
$success = '';

// Handle sending a message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message = trim($_POST['message'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$message) {
        $error = 'Please enter a message.';
    } elseif (!$password) {
        $error = 'Please enter your password to verify.';
    } else {
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $stmt = $db->prepare("INSERT INTO messages (sender_id, sender_name, message) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $senderName, $message]);
            $success = 'Message sent to admin successfully.';
        } else {
            $error = 'Incorrect password. Message not sent.';
        }
    }
}

$messages = getUserMessages($db, $userId);
?>
<div class="page-header">
    <h1><i class="fas fa-envelope"></i> My Messages</h1>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h2 class="mb-16">Send Message to Admin</h2>
        <form method="post">
            <div class="form-group">
                <label for="message">Message</label>
                <textarea id="message" name="message" class="form-control" rows="4" placeholder="Type your message here..." required></textarea>
            </div>
            <div class="form-group">
                <label for="password">Confirm Password <span class="fw-normal text-muted">(re-enter to verify)</span></label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <button type="submit" name="send_message" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Message</button>
        </form>
    </div>

    <div class="card">
        <h2 class="mb-16">Sent Messages</h2>
        <?php if (empty($messages)): ?>
            <p class="muted">No messages sent yet.</p>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <div class="p-14 border rounded mb-10">
                    <div class="d-flex justify-between" style="align-items:start;gap:12px">
                        <div class="flex-1">
                            <div class="fs-14" style="line-height:1.5"><?= nl2br(e($msg['message'])) ?></div>
                            <div class="fs-12 text-muted mt-6">
                                <i class="far fa-clock"></i> <?= e(date('Y-m-d H:i', strtotime($msg['created_at']))) ?>
                                <?php if ((int) $msg['is_read']): ?>
                                    <span class="badge badge-success ml-8">Read</span>
                                <?php else: ?>
                                    <span class="badge badge-warning ml-8">Unread</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
