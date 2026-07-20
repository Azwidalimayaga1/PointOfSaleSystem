<?php

declare(strict_types=1);

$month = (int) (isset($_GET['month']) ? $_GET['month'] : date('n'));
$year = (int) (isset($_GET['year']) ? $_GET['year'] : date('Y'));
$view = $_GET['view'] ?? 'month';
if ($month < 1) $month = 1;
if ($month > 12) $month = 12;
if ($year < 2020) $year = (int) date('Y');

$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$stores = [];
if (isSuperAdmin()) {
    $stores = $db->query("SELECT id, name FROM stores ORDER BY name ASC")->fetchAll();
}

// Get users for assignment
$userList = [];
if (isSuperAdmin()) {
    $userList = $db->query("SELECT id, full_name, role FROM users WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();
} else {
    $uStmt = $db->prepare("SELECT id, full_name, role FROM users WHERE (store_id = ? OR role = 'super_admin') AND status = 'active' ORDER BY full_name ASC");
    $uStmt->execute([currentUserStoreId()]);
    $userList = $uStmt->fetchAll();
}

// Filter reminder types by store settings
$calStoreSettings = getStoreSettings($db, activeStoreId());
$calReminderCats = $calStoreSettings['reminder_categories'] ?? [];
$allReminderTypes = [
    'stock_check' => 'Stock Check',
    'supplier_followup' => 'Supplier Follow-up',
    'staff_task' => 'Staff Task',
    'store_meeting' => 'Store Meeting',
    'promotion' => 'Promotion',
    'customer_followup' => 'Customer Follow-up',
    'system_task' => 'System Task',
    'other' => 'Other',
];
if (!empty($calReminderCats)) {
    $filtered = [];
    foreach ($allReminderTypes as $k => $v) {
        if (in_array($k, $calReminderCats, true)) {
            $filtered[$k] = $v;
        }
    }
    // Always include 'other'
    $filtered['other'] = 'Other';
    $allReminderTypes = $filtered;
}
?>
<div class="page-header">
    <h1><i class="fas fa-calendar-alt"></i> Calendar</h1>
    <div class="d-flex gap-8">
        <button class="btn btn-primary" onclick="openAddReminderModal()"><i class="fas fa-plus"></i> Add Reminder</button>
        <a href="?page=calendar&view=month&month=<?= $year . '&year=' . $year ?>" class="btn btn-sm btn-outline <?= $view === 'month' ? 'btn-primary' : '' ?>">Month</a>
        <a href="?page=calendar&view=week&month=<?= $month ?>&year=<?= $year ?>" class="btn btn-sm btn-outline <?= $view === 'week' ? 'btn-primary' : '' ?>">Week</a>
        <a href="?page=calendar&view=day&month=<?= $month ?>&year=<?= $year ?>" class="btn btn-sm btn-outline <?= $view === 'day' ? 'btn-primary' : '' ?>">Day</a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-16">
    <div class="d-flex gap-10 flex-wrap align-center">
        <?php if (isSuperAdmin()): ?>
        <select id="filter-store" class="form-control" style="width:auto;min-width:140px" onchange="loadReminders()">
            <option value="">All Stores</option>
            <?php foreach ($stores as $s): ?>
                <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <select id="filter-priority" class="form-control" style="width:auto;min-width:120px" onchange="loadReminders()">
            <option value="">All Priorities</option>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
            <option value="urgent">Urgent</option>
        </select>
        <select id="filter-status" class="form-control" style="width:auto;min-width:120px" onchange="loadReminders()">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="completed">Completed</option>
            <option value="overdue">Overdue</option>
            <option value="cancelled">Cancelled</option>
        </select>
        <select id="filter-user" class="form-control" style="width:auto;min-width:140px" onchange="loadReminders()">
            <option value="">All Users</option>
            <?php foreach ($userList as $u): ?>
                <option value="<?= (int) $u['id'] ?>"><?= e($u['full_name']) ?> (<?= e(ucfirst($u['role'])) ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<?php if ($view === 'day'): ?>
<?php
$day = (int) (isset($_GET['day']) ? $_GET['day'] : (int) date('j'));
$dayDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
$dayName = date('l, F j, Y', strtotime($dayDate));
?>
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-calendar-day"></i> <?= e($dayName) ?></h2>
        <div class="d-flex gap-6">
            <a href="?page=calendar&view=day&month=<?= $month ?>&year=<?= $year ?>&day=<?= $day - 1 ?>" class="btn btn-sm btn-outline"><i class="fas fa-chevron-left"></i> Previous</a>
            <a href="?page=calendar&view=day&month=<?= $month ?>&year=<?= $year ?>&day=<?= $day + 1 ?>" class="btn btn-sm btn-outline">Next <i class="fas fa-chevron-right"></i></a>
        </div>
    </div>
    <div id="day-reminders-list" class="reminder-list" data-date="<?= e($dayDate) ?>"></div>
</div>
<?php elseif ($view === 'week'): ?>
<?php
$firstDayOfMonth = strtotime("$year-$month-01");
$weekStart = isset($_GET['week_start']) ? strtotime($_GET['week_start']) : $firstDayOfMonth;
$weekDays = [];
for ($i = 0; $i < 7; $i++) {
    $ts = strtotime("+$i day", $weekStart);
    $weekDays[] = date('Y-m-d', $ts);
}
?>
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-calendar-week"></i> Week of <?= e(date('M j', $weekStart)) ?></h2>
        <div class="d-flex gap-6">
            <a href="?page=calendar&view=week&month=<?= $month ?>&year=<?= $year ?>&week_start=<?= e(date('Y-m-d', strtotime('-7 days', $weekStart))) ?>" class="btn btn-sm btn-outline"><i class="fas fa-chevron-left"></i> Previous</a>
            <a href="?page=calendar&view=week&month=<?= $month ?>&year=<?= $year ?>&week_start=<?= e(date('Y-m-d', strtotime('+7 days', $weekStart))) ?>" class="btn btn-sm btn-outline">Next <i class="fas fa-chevron-right"></i></a>
        </div>
    </div>
    <div class="week-grid">
        <?php foreach ($weekDays as $wd): ?>
        <div class="week-day" data-date="<?= e($wd) ?>">
            <div class="week-day-header"><?= e(date('D', strtotime($wd))) ?> <strong><?= e(date('j', strtotime($wd))) ?></strong></div>
            <div class="week-day-reminders"></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<!-- Monthly Calendar Grid -->
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-calendar"></i> <?= e(date('F Y', strtotime("$year-$month-01"))) ?></h2>
        <div class="d-flex gap-6">
            <a href="?page=calendar&view=month&month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-sm btn-outline"><i class="fas fa-chevron-left"></i></a>
            <a href="?page=calendar&view=month&month=<?= (int) date('n') ?>&year=<?= (int) date('Y') ?>" class="btn btn-sm btn-outline">Today</a>
            <a href="?page=calendar&view=month&month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-sm btn-outline"><i class="fas fa-chevron-right"></i></a>
        </div>
    </div>
    <div class="calendar-grid" id="calendar-grid" data-month="<?= $month ?>" data-year="<?= $year ?>">
        <div class="calendar-day-header">Sun</div>
        <div class="calendar-day-header">Mon</div>
        <div class="calendar-day-header">Tue</div>
        <div class="calendar-day-header">Wed</div>
        <div class="calendar-day-header">Thu</div>
        <div class="calendar-day-header">Fri</div>
        <div class="calendar-day-header">Sat</div>
    </div>
</div>
<?php endif; ?>

<!-- Today's Reminders Sidebar -->
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-clock"></i> Today's Reminders (<?= e(date('M j, Y')) ?>)</h2>
    </div>
    <div id="today-reminders-list"></div>
</div>

<!-- Add/Edit Reminder Modal -->
<div class="modal-overlay" id="reminder-modal">
    <div class="modal" style="width:520px">
        <h3 id="reminder-modal-title"><i class="fas fa-calendar-plus"></i> Add Reminder</h3>
        <form id="reminder-form" onsubmit="return saveReminder(event)">
            <input type="hidden" name="id" id="reminder-id" value="0">
            <input type="hidden" name="action" id="reminder-action" value="create">
            <div class="form-group">
                <label for="reminder-title">Title *</label>
                <input type="text" id="reminder-title" name="title" class="form-control" required maxlength="255">
            </div>
            <div class="form-group">
                <label for="reminder-description">Description</label>
                <textarea id="reminder-description" name="description" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="reminder-date">Date *</label>
                    <input type="date" id="reminder-date" name="reminder_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="reminder-time">Time</label>
                    <input type="time" id="reminder-time" name="reminder_time" class="form-control">
                </div>
            </div>
            <?php if (isSuperAdmin()): ?>
            <div class="form-group">
                <label for="reminder-store">Store</label>
                <select id="reminder-store" name="store_id" class="form-control" onchange="loadAssignedUsers()">
                    <?php foreach ($stores as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label for="reminder-assigned">Assign To</label>
                <select id="reminder-assigned" name="assigned_to" class="form-control">
                    <option value="">-- Unassigned --</option>
                    <?php foreach ($userList as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"><?= e($u['full_name']) ?> (<?= e(ucfirst($u['role'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="reminder-priority">Priority</label>
                    <select id="reminder-priority" name="priority" class="form-control">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="reminder-type">Type</label>
                    <select id="reminder-type" name="reminder_type" class="form-control">
                        <?php foreach ($allReminderTypes as $rk => $rv): ?>
                            <option value="<?= e($rk) ?>" <?= $rk === 'other' ? 'selected' : '' ?>><?= e($rv) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_store_wide" value="1">
                    Store-wide reminder (visible to all staff in this store)
                </label>
            </div>
            <div class="d-flex gap-10">
                <button type="submit" class="btn btn-success flex-1 justify-center"><i class="fas fa-save"></i> Save Reminder</button>
                <button type="button" class="btn btn-outline" onclick="closeReminderModal()"><i class="fas fa-times"></i> Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="delete-modal">
    <div class="modal" style="width:380px">
        <h3><i class="fas fa-trash text-danger"></i> Delete Reminder</h3>
        <p class="mb-16">Are you sure you want to delete this reminder? This action cannot be undone.</p>
        <input type="hidden" id="delete-id" value="0">
        <div class="d-flex gap-10">
            <button class="btn btn-danger flex-1 justify-center" onclick="confirmDelete()"><i class="fas fa-trash"></i> Delete</button>
            <button class="btn btn-outline flex-1 justify-center" onclick="closeDeleteModal()"><i class="fas fa-times"></i> Cancel</button>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= csrf_token() ?>';

document.addEventListener('DOMContentLoaded', function () {
    loadReminders();
    loadTodayReminders();
});

function loadReminders() {
    const month = document.getElementById('calendar-grid')?.dataset.month || <?= $month ?>;
    const year = document.getElementById('calendar-grid')?.dataset.year || <?= $year ?>;
    const store = document.getElementById('filter-store')?.value || '';
    const priority = document.getElementById('filter-priority')?.value || '';
    const status = document.getElementById('filter-status')?.value || '';
    const userId = document.getElementById('filter-user')?.value || '';
    const view = '<?= $view ?>';

    let url = `index.php?page=calendar&action=ajax&action=list&month=${month}&year=${year}&view=${view}`;
    if (store) url += `&store_id=${store}`;
    if (priority) url += `&priority=${priority}`;
    if (status) url += `&status=${status}`;
    if (userId) url += `&user_id=${userId}`;

    <?php if ($view === 'day'): ?>
    const dayEl = document.getElementById('day-reminders-list');
    if (dayEl) url += `&day=${dayEl.dataset.date.split('-')[2]}`;
    <?php elseif ($view === 'week'): ?>
    const firstWeekDay = document.querySelector('.week-day');
    if (firstWeekDay) url += `&week_start=${firstWeekDay.dataset.date}`;
    <?php endif; ?>

    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderReminders(data.reminders);
            }
        });
}

function renderReminders(reminders) {
    <?php if ($view === 'month'): ?>
    renderMonthGrid(reminders);
    <?php elseif ($view === 'week'): ?>
    renderWeekGrid(reminders);
    <?php elseif ($view === 'day'): ?>
    renderDayList(reminders);
    <?php endif; ?>
}

function renderMonthGrid(reminders) {
    const month = parseInt(document.getElementById('calendar-grid').dataset.month);
    const year = parseInt(document.getElementById('calendar-grid').dataset.year);
    const grid = document.getElementById('calendar-grid');

    // Remove old day cells (keep header row)
    grid.querySelectorAll('.calendar-day').forEach(el => el.remove());

    const firstDay = new Date(year, month - 1, 1).getDay();
    const daysInMonth = new Date(year, month, 0).getDate();
    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];

    // Empty cells for days before the 1st
    for (let i = 0; i < firstDay; i++) {
        const empty = document.createElement('div');
        empty.className = 'calendar-day empty';
        grid.appendChild(empty);
    }

    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const cell = document.createElement('div');
        cell.className = 'calendar-day';
        if (dateStr === todayStr) cell.classList.add('today');

        const dayReminders = reminders.filter(r => r.reminder_date === dateStr);
        const hasOverdue = dayReminders.some(r => r.status === 'pending' && r.reminder_date < todayStr);
        if (hasOverdue) cell.classList.add('has-overdue');

        let html = `<div class="calendar-day-num">${d}</div>`;
        if (dayReminders.length > 0) {
            html += `<div class="calendar-day-reminders">`;
            const show = dayReminders.slice(0, 3);
            show.forEach(r => {
                const priClass = r.priority === 'urgent' ? 'pri-urgent' : r.priority === 'high' ? 'pri-high' : r.priority === 'medium' ? 'pri-medium' : 'pri-low';
                const statusClass = r.status === 'completed' ? 'completed' : r.status === 'overdue' || (r.status === 'pending' && r.reminder_date < todayStr) ? 'overdue' : '';
                html += `<div class="reminder-chip ${priClass} ${statusClass}" onclick="viewReminder(${r.id})" title="${escHtml(r.title)}">${escHtml(r.title)}</div>`;
            });
            if (dayReminders.length > 3) {
                html += `<div class="reminder-more">+${dayReminders.length - 3} more</div>`;
            }
            html += `</div>`;
        }
        cell.innerHTML = html;
        cell.addEventListener('dblclick', () => openAddReminderModal(dateStr));
        grid.appendChild(cell);
    }
}

function renderWeekGrid(reminders) {
    document.querySelectorAll('.week-day').forEach(dayEl => {
        const dateStr = dayEl.dataset.date;
        const container = dayEl.querySelector('.week-day-reminders');
        const dayReminders = reminders.filter(r => r.reminder_date === dateStr);
        const today = new Date().toISOString().split('T')[0];

        if (dayReminders.length === 0) {
            container.innerHTML = '<div class="fs-12 text-muted p-8">No reminders</div>';
            return;
        }

        let html = '';
        dayReminders.forEach(r => {
            const priClass = r.priority === 'urgent' ? 'pri-urgent' : r.priority === 'high' ? 'pri-high' : r.priority === 'medium' ? 'pri-medium' : 'pri-low';
            const isOverdue = r.status === 'pending' && r.reminder_date < today;
            html += `<div class="reminder-item ${priClass} ${isOverdue ? 'overdue' : ''}" onclick="viewReminder(${r.id})">
                <div class="reminder-time">${r.reminder_time ? r.reminder_time.substring(0, 5) : ''}</div>
                <div class="reminder-title">${escHtml(r.title)}</div>
                <div class="reminder-status"><span class="badge ${r.status === 'completed' ? 'badge-success' : isOverdue ? 'badge-danger' : 'badge-info'}">${r.status === 'completed' ? 'Done' : isOverdue ? 'Overdue' : r.status}</span></div>
            </div>`;
        });
        container.innerHTML = html;
    });
}

function renderDayList(reminders) {
    const container = document.getElementById('day-reminders-list');
    const today = new Date().toISOString().split('T')[0];

    if (reminders.length === 0) {
        container.innerHTML = '<div class="empty-state"><i class="fas fa-calendar-check"></i><p>No reminders for this day.</p></div>';
        return;
    }

    let html = '';
    reminders.forEach(r => {
        const priClass = r.priority === 'urgent' ? 'pri-urgent' : r.priority === 'high' ? 'pri-high' : r.priority === 'medium' ? 'pri-medium' : 'pri-low';
        const isOverdue = r.status === 'pending' && r.reminder_date < today;
        const statusClass = r.status === 'completed' ? 'completed' : isOverdue ? 'overdue' : '';
        html += `<div class="reminder-item ${priClass} ${statusClass}" onclick="viewReminder(${r.id})">
            <div class="d-flex align-center gap-10">
                <span class="priority-dot ${priClass}"></span>
                <div>
                    <div class="fw-semibold">${escHtml(r.title)}</div>
                    <div class="fs-12 text-muted">${r.reminder_time ? r.reminder_time.substring(0, 5) : 'All day'} &middot; ${escHtml(r.assigned_user_name || 'Unassigned')}</div>
                </div>
            </div>
            <div class="ml-auto d-flex align-center gap-8">
                <span class="badge ${r.status === 'completed' ? 'badge-success' : isOverdue ? 'badge-danger' : r.status === 'pending' ? 'badge-info' : 'badge-gray'}">${isOverdue ? 'Overdue' : r.status}</span>
                ${r.status !== 'completed' ? `<button class="btn btn-sm btn-success" onclick="event.stopPropagation();completeReminder(${r.id})"><i class="fas fa-check"></i></button>` : ''}
                <button class="btn btn-sm btn-outline" onclick="event.stopPropagation();editReminder(${r.id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-danger" onclick="event.stopPropagation();deleteReminder(${r.id})"><i class="fas fa-trash"></i></button>
            </div>
        </div>`;
    });
    container.innerHTML = html;
}

function loadTodayReminders() {
    const today = new Date().toISOString().split('T')[0];
    const store = document.getElementById('filter-store')?.value || '';

    let url = `index.php?page=calendar&action=ajax&_csrf=${csrfToken}&action=list&view=day&month=${new Date().getMonth() + 1}&year=${new Date().getFullYear()}&day=${new Date().getDate()}&status=pending`;
    if (store) url += `&store_id=${store}`;

    fetch(url)
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('today-reminders-list');
            if (!data.success || data.reminders.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-check-circle text-success"></i><p>No pending reminders for today.</p></div>';
                return;
            }
            let html = '<div class="d-grid gap-8">';
            data.reminders.forEach(r => {
                const priClass = r.priority === 'urgent' ? 'pri-urgent' : r.priority === 'high' ? 'pri-high' : r.priority === 'medium' ? 'pri-medium' : 'pri-low';
                html += `<div class="reminder-item ${priClass} p-10" style="border:1px solid var(--border-color);border-radius:var(--radius-sm)">
                    <div class="d-flex align-center gap-8">
                        <span class="priority-dot ${priClass}"></span>
                        <div>
                            <div class="fw-semibold fs-13">${escHtml(r.title)}</div>
                            <div class="fs-11 text-muted">${r.reminder_time ? r.reminder_time.substring(0, 5) : 'All day'} ${r.store_name ? '&middot; ' + escHtml(r.store_name) : ''}</div>
                        </div>
                    </div>
                    <div class="ml-auto">
                        <button class="btn btn-sm btn-success" onclick="completeReminder(${r.id});this.closest('.reminder-item').remove()"><i class="fas fa-check"></i> Done</button>
                    </div>
                </div>`;
            });
            html += '</div>';
            container.innerHTML = html;
        });
}

function openAddReminderModal(date) {
    document.getElementById('reminder-modal-title').innerHTML = '<i class="fas fa-calendar-plus"></i> Add Reminder';
    document.getElementById('reminder-id').value = '0';
    document.getElementById('reminder-action').value = 'create';
    document.getElementById('reminder-title').value = '';
    document.getElementById('reminder-description').value = '';
    document.getElementById('reminder-date').value = date || new Date().toISOString().split('T')[0];
    document.getElementById('reminder-time').value = '';
    document.getElementById('reminder-priority').value = 'medium';
    document.getElementById('reminder-type').value = 'other';
    document.getElementById('reminder-assigned').value = '';
    document.getElementById('reminder-modal').classList.add('show');
}

function closeReminderModal() {
    document.getElementById('reminder-modal').classList.remove('show');
}

function saveReminder(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('reminder-form'));
    formData.append('_csrf', csrfToken);

    fetch('index.php?page=calendar&action=ajax', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeReminderModal();
                showToast(data.message, 'success');
                loadReminders();
                loadTodayReminders();
            } else {
                alert(data.error || 'Error saving reminder.');
            }
        });
}

function viewReminder(id) {
    fetch(`index.php?page=calendar&action=ajax&action=get&id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const r = data.reminder;
                document.getElementById('reminder-modal-title').innerHTML = '<i class="fas fa-calendar-alt"></i> View/Edit Reminder';
                document.getElementById('reminder-id').value = r.id;
                document.getElementById('reminder-action').value = 'edit';
                document.getElementById('reminder-title').value = r.title;
                document.getElementById('reminder-description').value = r.description || '';
                document.getElementById('reminder-date').value = r.reminder_date;
                document.getElementById('reminder-time').value = r.reminder_time || '';
                document.getElementById('reminder-priority').value = r.priority;
                document.getElementById('reminder-type').value = r.reminder_type;
                if (document.getElementById('reminder-store')) document.getElementById('reminder-store').value = r.store_id || '';
                document.getElementById('reminder-assigned').value = r.assigned_to_user_id || '';
                document.getElementById('reminder-modal').classList.add('show');
            }
        });
}

function editReminder(id) {
    viewReminder(id);
}

function completeReminder(id) {
    const formData = new FormData();
    formData.append('action', 'complete');
    formData.append('id', id);
    formData.append('_csrf', csrfToken);

    fetch('index.php?page=calendar&action=ajax', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Reminder completed!', 'success');
                loadReminders();
                loadTodayReminders();
            } else {
                alert(data.error || 'Error.');
            }
        });
}

function deleteReminder(id) {
    document.getElementById('delete-id').value = id;
    document.getElementById('delete-modal').classList.add('show');
}

function closeDeleteModal() {
    document.getElementById('delete-modal').classList.remove('show');
}

function confirmDelete() {
    const id = document.getElementById('delete-id').value;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    formData.append('_csrf', csrfToken);

    fetch('index.php?page=calendar&action=ajax', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeDeleteModal();
                showToast('Reminder deleted.', 'info');
                loadReminders();
                loadTodayReminders();
            } else {
                alert(data.error || 'Error.');
            }
        });
}

function loadAssignedUsers() {
    const storeId = document.getElementById('reminder-store')?.value || '';
    fetch(`index.php?page=calendar&action=ajax&action=get_users&store_id=${storeId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const sel = document.getElementById('reminder-assigned');
                const currentVal = sel.value;
                sel.innerHTML = '<option value="">-- Unassigned --</option>';
                data.users.forEach(u => {
                    sel.innerHTML += `<option value="${u.id}">${escHtml(u.full_name)} (${escHtml(u.role)})</option>`;
                });
                sel.value = currentVal;
            }
        });
}

function escHtml(s) {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
}
</script>
