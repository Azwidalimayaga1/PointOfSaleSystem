<?php

declare(strict_types=1);

$userId = (int) CURRENT_USER_ID;
$storeId = activeStoreId();
$heldSales = getHeldSales($db, $userId, $storeId);

$flash = $_SESSION['pos_flash'] ?? null;
unset($_SESSION['pos_flash']);
?>
<?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="page-header">
    <h1><i class="fas fa-pause-circle"></i> Held Sales</h1>
    <a href="index.php?page=sales" class="btn btn-success"><i class="fas fa-cash-register"></i> POS</a>
</div>

<?php if (empty($heldSales)): ?>
<div class="card">
    <div class="empty-state">
        <i class="fas fa-pause-circle"></i>
        <p>No held sales. You can hold a sale from the POS screen to resume it later.</p>
        <a href="index.php?page=sales" class="btn btn-success"><i class="fas fa-cash-register"></i> Go to POS</a>
    </div>
</div>
<?php else: ?>
<div class="held-sales-grid">
    <?php foreach ($heldSales as $hs):
        $cartItems = json_decode($hs['cart_data'], true) ?? [];
        $itemCount = count($cartItems);
        $itemNames = array_map(fn($i) => $i['name'] ?? '', array_slice($cartItems, 0, 3));
        $namePreview = implode(', ', $itemNames);
        if ($itemCount > 3) $namePreview .= '...';
    ?>
    <div class="held-sale-card">
        <div class="held-header">
            <span class="held-id">#<?= (int) $hs['id'] ?></span>
            <span class="held-time"><?= e(date('M j, g:i A', strtotime($hs['created_at']))) ?></span>
        </div>
        <div class="held-subtotal"><?= money((float) $hs['subtotal']) ?></div>
        <div class="held-items">
            <i class="fas fa-box"></i> <?= $itemCount ?> item(s): <?= e($namePreview ?: 'Empty cart') ?>
        </div>
        <div class="held-actions">
            <button class="btn btn-sm btn-success" onclick="resumeHeldSale(<?= (int) $hs['id'] ?>)">
                <i class="fas fa-play"></i> Resume
            </button>
            <button class="btn btn-sm btn-danger" onclick="deleteHeldSale(<?= (int) $hs['id'] ?>)">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
function resumeHeldSale(id) {
    // Store the held sale ID in sessionStorage for the POS to pick up
    sessionStorage.setItem('resumeHeldSaleId', id.toString());
    window.location.href = 'index.php?page=sales';
}

function deleteHeldSale(id) {
    if (!confirm('Delete this held sale? This cannot be undone.')) return;
    const formData = new FormData();
    formData.append('action', 'delete_held_sale');
    formData.append('held_id', id.toString());
    formData.append('_csrf', '<?= csrf_token() ?>');
    fetch('index.php?page=sales&action=ajax', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast('Held sale deleted.', 'success');
                location.reload();
            } else {
                showToast(d.error || 'Error deleting held sale.', 'danger');
            }
        })
        .catch(() => showToast('An error occurred.', 'danger'));
}
</script>
