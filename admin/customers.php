<?php
/**
 * Customer Management (Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/User.php';

Auth::requirePermission('customers.manage');

$user = new User();
$db   = Database::getInstance();

$filters = [
    'search' => $_GET['search'] ?? null,
    'role'   => $_GET['role'] ?? null,
    'status' => $_GET['status'] ?? null
];

$page   = (int)($_GET['page'] ?? 1);
$limit  = 20;
$offset = ($page - 1) * $limit;

$where  = ['1=1'];
$params = [];

if (!empty($filters['search'])) {
    $where[]    = '(u.email LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?)';
    $searchTerm = '%' . $filters['search'] . '%';
    $params[]   = $searchTerm;
    $params[]   = $searchTerm;
    $params[]   = $searchTerm;
}

if (!empty($filters['role'])) {
    $where[]  = 'r.name = ?';
    $params[] = $filters['role'];
}

if ($filters['status'] === 'active') {
    $where[] = 'u.is_active = 1';
} elseif ($filters['status'] === 'inactive') {
    $where[] = 'u.is_active = 0';
}

$whereClause = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT u.*, p.first_name, p.last_name, p.phone, p.student_id, p.department,
           r.name as role_name, r.display_name as role_display_name,
           (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
           (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE user_id = u.id AND status NOT IN ('cancelled', 'refunded')) as total_spent
    FROM users u
    LEFT JOIN user_profiles p ON u.id = p.user_id
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE $whereClause
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([...$params, $limit, $offset]);
$customers = $stmt->fetchAll();

$stmt = $db->prepare("
    SELECT COUNT(*) FROM users u
    LEFT JOIN user_profiles p ON u.id = p.user_id
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE $whereClause
");
$stmt->execute($params);
$total      = $stmt->fetchColumn();
$totalPages = ceil($total / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-gray-50">

<?php $activePage = 'customers'; require_once __DIR__ . '/../includes/admin_nav.php'; ?>

<div class="admin-main px-6 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-extrabold text-gray-900">Customer Management</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-person-plus me-2"></i>Add User
        </button>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, email..."
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="customer" <?php echo ($_GET['role'] ?? '') === 'customer' ? 'selected' : ''; ?>>Customer</option>
                        <option value="delivery_partner" <?php echo ($_GET['role'] ?? '') === 'delivery_partner' ? 'selected' : ''; ?>>Delivery Partner</option>
                        <option value="admin" <?php echo ($_GET['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" <?php echo ($_GET['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($_GET['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Customers Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold">
                                        <?php echo htmlspecialchars(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')); ?>
                                    </div>
                                    <?php if ($customer['phone']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($customer['phone']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $customer['role_name'] === 'admin' ? 'danger' : ($customer['role_name'] === 'delivery_partner' ? 'warning' : 'primary'); ?>">
                                        <?php echo htmlspecialchars($customer['role_display_name'] ?? $customer['role_name']); ?>
                                    </span>
                                </td>
                                <td><?php echo $customer['total_orders']; ?></td>
                                <td>₹<?php echo number_format($customer['total_spent'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $customer['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $customer['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('j M Y', strtotime($customer['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewCustomer(<?php echo $customer['id']; ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-<?php echo $customer['is_active'] ? 'warning' : 'success'; ?>"
                                            onclick="toggleStatus(<?php echo $customer['id']; ?>, <?php echo $customer['is_active'] ? '0' : '1'; ?>)">
                                        <i class="bi bi-<?php echo $customer['is_active'] ? 'pause' : 'play'; ?>"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-0 bg-primary text-white rounded-top">
                <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Add New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="addUserAlert" class="d-none"></div>
                <form id="addUserForm">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required minlength="2" maxlength="100" placeholder="Rahul">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" required minlength="2" maxlength="100" placeholder="Sharma">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required placeholder="user@goa.bits-pilani.ac.in">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="tel" name="phone" class="form-control" placeholder="10-digit mobile">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required id="roleSelect" onchange="handleRoleChange(this.value)">
                                <option value="customer">Customer</option>
                                <option value="delivery_partner">Delivery Partner</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-12" id="studentIdField">
                            <label class="form-label fw-semibold">Student ID</label>
                            <input type="text" name="student_id" class="form-control" placeholder="2022AAGPS001H">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required minlength="8" placeholder="Min 8 characters">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4" id="addUserSubmit" onclick="submitAddUser()">
                    <i class="bi bi-person-check me-2"></i>Create User
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    async function viewCustomer(userId) {
        window.open('customer_detail.php?id=' + userId, '_blank');
    }

    async function toggleStatus(userId, status) {
        if (!confirm('Are you sure you want to ' + (status ? 'activate' : 'deactivate') + ' this user?')) return;
        try {
            const response = await fetch('../api/customers.php?action=toggle_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId, is_active: status })
            });
            const result = await response.json();
            if (result.success) { location.reload(); } else { alert(result.message); }
        } catch (e) { alert('Failed to update user status'); }
    }

    function handleRoleChange(role) {
        const field = document.getElementById('studentIdField');
        if (field) field.style.display = (role === 'customer') ? '' : 'none';
    }

    async function submitAddUser() {
        const form    = document.getElementById('addUserForm');
        const alertEl = document.getElementById('addUserAlert');
        const btn     = document.getElementById('addUserSubmit');

        if (!form.checkValidity()) { form.reportValidity(); return; }

        const data = {};
        new FormData(form).forEach((v, k) => data[k] = v.trim());

        btn.disabled    = true;
        btn.innerHTML   = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';
        alertEl.className = 'd-none';

        try {
            const res    = await fetch('../api/customers.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.success) {
                alertEl.className   = 'alert alert-success';
                alertEl.textContent = result.message || 'User created successfully!';
                form.reset();
                setTimeout(() => location.reload(), 1500);
            } else {
                alertEl.className   = 'alert alert-danger';
                alertEl.textContent = result.message || 'Failed to create user.';
            }
        } catch (e) {
            alertEl.className   = 'alert alert-danger';
            alertEl.textContent = 'Network error. Please try again.';
        } finally {
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-person-check me-2"></i>Create User';
        }
    }
</script>
</body>
</html>
