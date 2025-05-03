<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

// Get admin profile
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin_profile = $stmt->fetch();

// Get total employees count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'employee'");
$total_employees = $stmt->fetch()['total'];

// Get active employees count
$stmt = $pdo->query("SELECT COUNT(*) as active FROM users WHERE role = 'employee' AND is_active = TRUE");
$active_employees = $stmt->fetch()['active'];

// Get pending employees count
$stmt = $pdo->query("SELECT COUNT(*) as pending FROM users WHERE role = 'employee' AND is_active = FALSE");
$pending_employees_count = $stmt->fetch()['pending'];

// Get recent leave requests
$stmt = $pdo->query("
    SELECT lr.*, u.full_name, lt.name as leave_type 
    FROM leave_requests lr 
    JOIN users u ON lr.employee_id = u.id 
    JOIN leave_types lt ON lr.leave_type_id = lt.id 
    ORDER BY lr.created_at DESC 
    LIMIT 5
");
$recent_leaves = $stmt->fetchAll();

// Get all employees
$stmt = $pdo->query("
    SELECT * FROM users 
    WHERE role = 'employee' 
    ORDER BY created_at DESC
");
$all_employees = $stmt->fetchAll();

// Handle employee activation
if (isset($_POST['activate_employee'])) {
    $employee_id = $_POST['employee_id'];
    $stmt = $pdo->prepare("UPDATE users SET is_active = TRUE WHERE id = ?");
    $stmt->execute([$employee_id]);
}

// Handle employee deactivation
if (isset($_POST['deactivate_employee'])) {
    $employee_id = $_POST['employee_id'];
    $stmt = $pdo->prepare("UPDATE users SET is_active = FALSE WHERE id = ?");
    $stmt->execute([$employee_id]);
}

// Handle employee deletion
if (isset($_POST['delete_employee'])) {
    $employee_id = $_POST['employee_id'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'employee'");
    $stmt->execute([$employee_id]);
}

// Handle leave approval/rejection
if (isset($_POST['action'])) {
    $leave_id = $_POST['leave_id'];
    $action = $_POST['action'];
    
    $stmt = $pdo->prepare("UPDATE leave_requests SET status = ? WHERE id = ?");
    $stmt->execute([$action, $leave_id]);
}

// Get pending employees
$stmt = $pdo->query("SELECT * FROM users WHERE is_active = FALSE AND role = 'employee'");
$pending_employees = $stmt->fetchAll();

// Get pending leave requests
$stmt = $pdo->query("
    SELECT lr.*, u.full_name, lt.name as leave_type 
    FROM leave_requests lr 
    JOIN users u ON lr.employee_id = u.id 
    JOIN leave_types lt ON lr.leave_type_id = lt.id 
    WHERE lr.status = 'pending'
");
$pending_leaves = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Leave Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .employee-list {
            display: none;
        }
        .employee-list.show {
            display: block;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Leave Management System</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <!-- Admin Profile -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Admin Profile</h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Full Name:</strong><br>
                            <?php echo htmlspecialchars($admin_profile['full_name']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Username:</strong><br>
                            <?php echo htmlspecialchars($admin_profile['username']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Email:</strong><br>
                            <?php echo htmlspecialchars($admin_profile['email']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Role:</strong><br>
                            <?php echo ucfirst($admin_profile['role']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Status:</strong><br>
                            <span class="badge bg-success">Active</span>
                        </div>
                    </div>
                </div>

                <!-- Employee Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Employee Statistics</h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Total Employees:</strong><br>
                            <a href="#" class="badge bg-primary text-decoration-none" id="showEmployees"><?php echo $total_employees; ?></a>
                        </div>
                        <div class="mb-3">
                            <strong>Active Employees:</strong><br>
                            <span class="badge bg-success"><?php echo $active_employees; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Pending Activation:</strong><br>
                            <span class="badge bg-warning"><?php echo $pending_employees_count; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Recent Leave Requests -->
                <div class="card">
                    <div class="card-header">
                        <h4>Recent Leave Requests</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_leaves)): ?>
                            <p>No recent leave requests.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($recent_leaves as $leave): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($leave['full_name']); ?></h6>
                                            <small class="text-<?php 
                                                echo $leave['status'] == 'approved' ? 'success' : 
                                                    ($leave['status'] == 'rejected' ? 'danger' : 'warning'); 
                                            ?>">
                                                <?php echo ucfirst($leave['status']); ?>
                                            </small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars($leave['leave_type']); ?></p>
                                        <small>
                                            <?php echo $leave['start_date']; ?> to <?php echo $leave['end_date']; ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-8">
                <h2>Admin Dashboard</h2>
                
                <!-- All Employees List -->
                <div class="card mb-4 employee-list" id="employeeList">
                    <div class="card-header">
                        <h4>All Employees</h4>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_employees as $employee): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($employee['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['username']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $employee['is_active'] ? 'success' : 'warning'; ?>">
                                                <?php echo $employee['is_active'] ? 'Active' : 'Pending'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!$employee['is_active']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                    <button type="submit" name="activate_employee" class="btn btn-success btn-sm">Activate</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                    <button type="submit" name="deactivate_employee" class="btn btn-warning btn-sm">Deactivate</button>
                                                </form>
                                            <?php endif; ?>
                                            <a href="edit_employee.php?id=<?php echo $employee['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this employee?');">
                                                <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                <button type="submit" name="delete_employee" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pending Employee Approvals -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Pending Employee Approvals</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_employees)): ?>
                            <p>No pending employee approvals.</p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_employees as $employee): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($employee['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($employee['username']); ?></td>
                                            <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                    <button type="submit" name="activate_employee" class="btn btn-success">Activate</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pending Leave Requests -->
                <div class="card">
                    <div class="card-header">
                        <h4>Pending Leave Requests</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_leaves)): ?>
                            <p>No pending leave requests.</p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Reason</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_leaves as $leave): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($leave['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($leave['leave_type']); ?></td>
                                            <td><?php echo $leave['start_date']; ?></td>
                                            <td><?php echo $leave['end_date']; ?></td>
                                            <td><?php echo htmlspecialchars($leave['reason']); ?></td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                                    <input type="hidden" name="action" value="approved">
                                                    <button type="submit" class="btn btn-success">Approve</button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                                    <input type="hidden" name="action" value="rejected">
                                                    <button type="submit" class="btn btn-danger">Reject</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('showEmployees').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('employeeList').classList.toggle('show');
        });
    </script>
</body>
</html> 