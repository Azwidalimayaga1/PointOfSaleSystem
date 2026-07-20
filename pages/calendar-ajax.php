<?php

declare(strict_types=1);

header('Content-Type: application/json');

try {
    requireAjaxLogin();

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // Only validate CSRF for state-changing actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf = $_POST['_csrf'] ?? '';
        if (!validate_csrf($csrf)) {
            throw new \Exception('Invalid security token. Please refresh the page.');
        }
    }

    switch ($action) {

        case 'list':
            $month = (int) ($_GET['month'] ?? date('n'));
            $year = (int) ($_GET['year'] ?? date('Y'));
            $storeFilter = isset($_GET['store_id']) ? (int) $_GET['store_id'] : null;
            $statusFilter = $_GET['status'] ?? '';
            $priorityFilter = $_GET['priority'] ?? '';
            $userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
            $view = $_GET['view'] ?? 'month';

            list($reminders, $stores) = getCalendarReminders($db, $month, $year, $storeFilter, $statusFilter, $priorityFilter, $userId, $view);
            echo json_encode(['success' => true, 'reminders' => $reminders, 'stores' => $stores]);
            break;

        case 'create':
        case 'edit':
            $id = (int) ($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $reminderDate = trim($_POST['reminder_date'] ?? '');
            $reminderTime = trim($_POST['reminder_time'] ?? '');
            $storeIdInput = isset($_POST['store_id']) ? (int) $_POST['store_id'] : null;
            $assignedTo = isset($_POST['assigned_to']) ? (int) $_POST['assigned_to'] : null;
            $priority = $_POST['priority'] ?? 'medium';
            $reminderType = $_POST['reminder_type'] ?? 'other';
            $isStoreWide = isset($_POST['is_store_wide']) ? 1 : 0;

            if (!$title) throw new \Exception('Title is required.');
            if (!$reminderDate) throw new \Exception('Date is required.');

            // Security: determine store_id
            if (isSuperAdmin()) {
                $finalStoreId = $storeIdInput ?: activeStoreId();
            } else {
                $finalStoreId = currentUserStoreId();
                // If assigning, user must be in same store
                if ($assignedTo) {
                    $uStmt = $db->prepare("SELECT store_id FROM users WHERE id = ?");
                    $uStmt->execute([$assignedTo]);
                    $u = $uStmt->fetch();
                    if (!$u || (int) $u['store_id'] !== $finalStoreId) {
                        throw new \Exception('Cannot assign reminder to user outside your store.');
                    }
                }
            }

            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO calendar_reminders (title, description, reminder_date, reminder_time, store_id, assigned_to_user_id, created_by_user_id, priority, reminder_type, is_store_wide) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$title, $description, $reminderDate, $reminderTime ?: null, $finalStoreId, $assignedTo ?: null, CURRENT_USER_ID, $priority, $reminderType, $isStoreWide]);
                $id = (int) $db->lastInsertId();
                logAction($db, 'reminder_created', 'calendar_reminder', $id, 'Created reminder: ' . $title);
                echo json_encode(['success' => true, 'message' => 'Reminder created.']);
            } else {
                if (!$id) throw new \Exception('Invalid reminder ID.');
                if (!canManageReminder($db, $id)) jsonAccessDenied();
                $stmt = $db->prepare("UPDATE calendar_reminders SET title=?, description=?, reminder_date=?, reminder_time=?, priority=?, reminder_type=?, is_store_wide=? WHERE id=?");
                $stmt->execute([$title, $description, $reminderDate, $reminderTime ?: null, $priority, $reminderType, $isStoreWide, $id]);
                logAction($db, 'reminder_updated', 'calendar_reminder', $id, 'Updated reminder: ' . $title);
                echo json_encode(['success' => true, 'message' => 'Reminder updated.']);
            }
            break;

        case 'delete':
            $id = (int) ($_POST['id'] ?? 0);
            if (!$id) throw new \Exception('Invalid reminder ID.');
            if (!canManageReminder($db, $id)) jsonAccessDenied();
            $stmt = $db->prepare("DELETE FROM calendar_reminders WHERE id = ?");
            $stmt->execute([$id]);
            logAction($db, 'reminder_deleted', 'calendar_reminder', $id, 'Deleted reminder ID: ' . $id);
            echo json_encode(['success' => true, 'message' => 'Reminder deleted.']);
            break;

        case 'complete':
            $id = (int) ($_POST['id'] ?? 0);
            if (!$id) throw new \Exception('Invalid reminder ID.');
            if (!canManageReminder($db, $id)) jsonAccessDenied();
            $stmt = $db->prepare("UPDATE calendar_reminders SET status = 'completed' WHERE id = ?");
            $stmt->execute([$id]);
            logAction($db, 'reminder_completed', 'calendar_reminder', $id, 'Completed reminder ID: ' . $id);
            echo json_encode(['success' => true, 'message' => 'Reminder marked as completed.']);
            break;

        case 'get':
            $id = (int) ($_GET['id'] ?? 0);
            if (!$id) throw new \Exception('Invalid reminder ID.');
            if (!canManageReminder($db, $id)) jsonAccessDenied();
            $stmt = $db->prepare("SELECT cr.*, u.full_name as assigned_user_name, cr2.full_name as created_by_name FROM calendar_reminders cr LEFT JOIN users u ON u.id = cr.assigned_to_user_id LEFT JOIN users cr2 ON cr2.id = cr.created_by_user_id WHERE cr.id = ?");
            $stmt->execute([$id]);
            $reminder = $stmt->fetch();
            if (!$reminder) throw new \Exception('Reminder not found.');
            echo json_encode(['success' => true, 'reminder' => $reminder]);
            break;

        case 'get_users':
            $storeId = isset($_GET['store_id']) ? (int) $_GET['store_id'] : activeStoreId();
            if (!isSuperAdmin()) {
                $storeId = currentUserStoreId();
            }
            $stmt = $db->prepare("SELECT id, full_name, role FROM users WHERE (store_id = ? OR role = 'super_admin') AND status = 'active' ORDER BY full_name ASC");
            $stmt->execute([$storeId]);
            $users = $stmt->fetchAll();
            echo json_encode(['success' => true, 'users' => $users]);
            break;

        default:
            throw new \Exception('Invalid action.');
    }
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getCalendarReminders(PDO $db, int $month, int $year, ?int $storeFilter, string $statusFilter, string $priorityFilter, ?int $userId, string $view): array
{
    $where = [];
    $params = [];

    // Role-based data access
    if (isSuperAdmin()) {
        if ($storeFilter) {
            $where[] = 'cr.store_id = ?';
            $params[] = $storeFilter;
        }
    } elseif (isStoreAdmin() || isManager()) {
        $where[] = 'cr.store_id = ?';
        $params[] = currentUserStoreId();
    } else {
        // Cashier: assigned reminders or store-wide reminders for their store
        $where[] = '(cr.assigned_to_user_id = ? OR (cr.is_store_wide = 1 AND cr.store_id = ?))';
        $params[] = CURRENT_USER_ID;
        $params[] = currentUserStoreId();
    }

    if ($view === 'month') {
        $where[] = 'MONTH(cr.reminder_date) = ? AND YEAR(cr.reminder_date) = ?';
        $params[] = $month;
        $params[] = $year;
    } elseif ($view === 'day') {
        $day = isset($_GET['day']) ? (int) $_GET['day'] : (int) date('j');
        $where[] = 'cr.reminder_date = ?';
        $params[] = sprintf('%04d-%02d-%02d', $year, $month, $day);
    } elseif ($view === 'week') {
        $weekStart = $_GET['week_start'] ?? date('Y-m-d');
        $weekEnd = date('Y-m-d', strtotime('+6 days', strtotime($weekStart)));
        $where[] = 'cr.reminder_date BETWEEN ? AND ?';
        $params[] = $weekStart;
        $params[] = $weekEnd;
    }

    if ($statusFilter) {
        $where[] = 'cr.status = ?';
        $params[] = $statusFilter;
    }
    if ($priorityFilter) {
        $where[] = 'cr.priority = ?';
        $params[] = $priorityFilter;
    }
    if ($userId) {
        $where[] = 'cr.assigned_to_user_id = ?';
        $params[] = $userId;
    }

    $sql = "SELECT cr.*, u.full_name as assigned_user_name, cr2.full_name as created_by_name, s.name as store_name
            FROM calendar_reminders cr
            LEFT JOIN users u ON u.id = cr.assigned_to_user_id
            LEFT JOIN users cr2 ON cr2.id = cr.created_by_user_id
            LEFT JOIN stores s ON s.id = cr.store_id";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= " ORDER BY cr.reminder_date ASC, cr.reminder_time ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $reminders = $stmt->fetchAll();

    // Get stores for filter (super_admin only)
    $stores = [];
    if (isSuperAdmin()) {
        $stores = $db->query("SELECT id, name FROM stores ORDER BY name ASC")->fetchAll();
    }

    return [$reminders, $stores];
}
