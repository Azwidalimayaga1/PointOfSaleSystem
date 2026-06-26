<?php declare(strict_types=1);
$id = (int) ($_GET['id'] ?? 0);
if (!$id) { redirect('index.php?page=customers'); }
$stmt = $db->prepare("SELECT * FROM customers WHERE id = ? AND store_id = ?"); $stmt->execute([$id, activeStoreId()]);
$c = $stmt->fetch();
if (!$c) { $_SESSION['pos_flash'] = ['type'=>'danger','message'=>'Customer not found.']; redirect('index.php?page=customers'); }

$salesStmt = $db->prepare("SELECT * FROM sales WHERE customer_id = ? AND store_id = ? ORDER BY created_at DESC");
$salesStmt->execute([$id, activeStoreId()]);
$sales = $salesStmt->fetchAll();
?>
<div class="page-header"><h1><i class="fas fa-user"></i> <?= e($c['name']) ?></h1><a href="index.php?page=customers" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a></div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:16px">
<div class="card" style="text-align:center"><div style="font-size:24px;font-weight:700;color:var(--primary)"><?= (int) $c['visit_count'] ?></div><div style="font-size:13px;color:var(--text-secondary)">Visits</div></div>
<div class="card" style="text-align:center"><div style="font-size:24px;font-weight:700;color:var(--success)"><?= money((float) $c['total_spent']) ?></div><div style="font-size:13px;color:var(--text-secondary)">Total Spent</div></div>
<div class="card" style="text-align:center"><div style="font-size:14px;font-weight:500"><?= e($c['phone'] ?? '-') ?></div><div style="font-size:13px;color:var(--text-secondary)">Phone</div></div>
<div class="card" style="text-align:center"><div style="font-size:14px;font-weight:500"><?= e($c['email'] ?? '-') ?></div><div style="font-size:13px;color:var(--text-secondary)">Email</div></div>
</div>
<?php if ($c['address']): ?><div class="card" style="margin-bottom:16px"><strong>Address:</strong> <?= e($c['address']) ?></div><?php endif; ?>
<div class="card"><h3>Purchase History</h3>
<?php if (empty($sales)): ?>
<p style="text-align:center;padding:20px;color:var(--text-secondary)">No purchases yet.</p>
<?php else: ?>
<div class="table-container"><table><thead><tr><th>Date</th><th>Receipt</th><th>Total</th><th>Payment</th></tr></thead><tbody>
<?php foreach ($sales as $s): ?>
<tr><td><?= e(date('Y-m-d H:i', strtotime($s['created_at']))) ?></td><td><?= e($s['receipt_number']) ?></td><td><?= money((float) $s['total']) ?></td><td><?= ucfirst(e($s['payment_method'])) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</div>
