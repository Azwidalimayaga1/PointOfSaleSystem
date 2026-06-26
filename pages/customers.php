<?php declare(strict_types=1);
$search = $_GET['search'] ?? '';
$sql = "SELECT * FROM customers WHERE store_id = ?";
$params = [activeStoreId()];
if ($search) { $sql .= " AND (name LIKE ? OR phone LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= " ORDER BY name ASC";
$stmt = $db->prepare($sql); $stmt->execute($params);
$customers = $stmt->fetchAll();
?>
<div class="page-header"><h1><i class="fas fa-user-friends"></i> Customers</h1><a href="index.php?page=customer-form" class="btn btn-primary"><i class="fas fa-plus"></i> Add Customer</a></div>
<div class="card">
<div class="search-bar" style="margin-bottom:16px">
<form method="get" style="display:flex;gap:10px;width:100%">
<input type="hidden" name="page" value="customers">
<input type="text" name="search" class="form-control" placeholder="Search by name or phone..." value="<?= e($search) ?>">
<button class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
<?php if ($search): ?><a href="index.php?page=customers" class="btn btn-outline"><i class="fas fa-times"></i> Clear</a><?php endif; ?>
</form>
</div>
<?php if (empty($customers)): ?>
<p style="text-align:center;padding:40px;color:var(--text-secondary)"><i class="fas fa-users" style="font-size:40px;display:block;margin-bottom:12px;opacity:0.3"></i> No customers found.</p>
<?php else: ?>
<div class="table-container"><table><thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Visits</th><th>Total Spent</th><th></th></tr></thead><tbody>
<?php foreach ($customers as $c): ?>
<tr>
<td><a href="index.php?page=customer-view&id=<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a></td>
<td><?= e($c['phone'] ?? '-') ?></td>
<td><?= e($c['email'] ?? '-') ?></td>
<td><?= (int) $c['visit_count'] ?></td>
<td><?= money((float) $c['total_spent']) ?></td>
<td><a href="index.php?page=customer-form&id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-edit"></i></a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</div>
