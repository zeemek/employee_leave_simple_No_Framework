<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get employee profile
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$employee_profile = $stmt->fetch();

// Initialize leave balances for new employees if not exists
$stmt = $pdo->prepare("
    INSERT IGNORE INTO leave_balances (employee_id, leave_type_id, balance, year)
    SELECT ?, id, max_days, YEAR(CURRENT_DATE)
    FROM leave_types
");
$stmt->execute([$user_id]);

// Handle leave application
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_leave'])) {
    $leave_type_id = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];

    // Calculate number of days
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $days = $end->diff($start)->days + 1;

    // Check leave balance
    $stmt = $pdo->prepare("
        SELECT COALESCE(lb.balance, lt.max_days) as balance
        FROM leave_types lt
        LEFT JOIN leave_balances lb ON lt.id = lb.leave_type_id 
            AND lb.employee_id = ? 
            AND lb.year = YEAR(CURRENT_DATE)
        WHERE lt.id = ?
    ");
    $stmt->execute([$user_id, $leave_type_id]);
    $balance = $stmt->fetchColumn();

    if ($balance >= $days) {
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Insert leave request
            $stmt = $pdo->prepare("
                INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, reason) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $leave_type_id, $start_date, $end_date, $reason]);
            
            // Update leave balance
            $stmt = $pdo->prepare("
                INSERT INTO leave_balances (employee_id, leave_type_id, balance, year)
                VALUES (?, ?, ?, YEAR(CURRENT_DATE))
                ON DUPLICATE KEY UPDATE balance = balance - ?
            ");
            $stmt->execute([$user_id, $leave_type_id, $balance - $days, $days]);
            
            $pdo->commit();
            $success = "Leave application submitted successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error submitting leave request: " . $e->getMessage();
        }
    } else {
        $error = "Insufficient leave balance! You have {$balance} days remaining.";
    }
}

// Get leave types
$stmt = $pdo->query("SELECT * FROM leave_types");
$leave_types = $stmt->fetchAll();

// Get leave balances
$stmt = $pdo->prepare("
    SELECT lt.name, lt.id, COALESCE(lb.balance, lt.max_days) as balance, lt.max_days
    FROM leave_types lt
    LEFT JOIN leave_balances lb ON lt.id = lb.leave_type_id 
        AND lb.employee_id = ? 
        AND lb.year = YEAR(CURRENT_DATE)
");
$stmt->execute([$user_id]);
$leave_balances = $stmt->fetchAll();

// Get leave history
$stmt = $pdo->prepare("
    SELECT lr.*, lt.name as leave_type 
    FROM leave_requests lr 
    JOIN leave_types lt ON lr.leave_type_id = lt.id 
    WHERE lr.employee_id = ? 
    ORDER BY lr.created_at DESC
");
$stmt->execute([$user_id]);
$leave_history = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - Leave Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            <!-- Employee Profile -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Employee Profile</h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Full Name:</strong><br>
                            <?php echo htmlspecialchars($employee_profile['full_name']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Username:</strong><br>
                            <?php echo htmlspecialchars($employee_profile['username']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Email:</strong><br>
                            <?php echo htmlspecialchars($employee_profile['email']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Role:</strong><br>
                            <?php echo ucfirst($employee_profile['role']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Status:</strong><br>
                            <span class="badge bg-<?php echo $employee_profile['is_active'] ? 'success' : 'warning'; ?>">
                                <?php echo $employee_profile['is_active'] ? 'Active' : 'Pending Activation'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-8">
                <h2>Employee Dashboard</h2>
                
                <!-- Leave Balances -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Leave Balances</h4>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Leave Type</th>
                                    <th>Balance</th>
                                    <th>Maximum Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leave_balances as $balance): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($balance['name']); ?></td>
                                        <td><?php echo $balance['balance']; ?></td>
                                        <td><?php echo $balance['max_days']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Apply for Leave -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Apply for Leave</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="leave_type" class="form-label">Leave Type</label>
                                <select class="form-select" id="leave_type" name="leave_type" required>
                                    <?php foreach ($leave_types as $type): ?>
                                        <option value="<?php echo $type['id']; ?>">
                                            <?php echo htmlspecialchars($type['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                            <div class="mb-3">
                                <label for="reason" class="form-label">Reason</label>
                                <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                            </div>
                            <button type="submit" name="apply_leave" class="btn btn-primary">Submit Leave Request</button>
                        </form>
                    </div>
                </div>

                <!-- Leave History -->
                <div class="card">
                    <div class="card-header">
                        <h4>Leave History</h4>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Leave Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leave_history as $leave): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($leave['leave_type']); ?></td>
                                        <td><?php echo $leave['start_date']; ?></td>
                                        <td><?php echo $leave['end_date']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $leave['status'] == 'approved' ? 'success' : 
                                                    ($leave['status'] == 'rejected' ? 'danger' : 'warning'); 
                                            ?>">
                                                <?php echo ucfirst($leave['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($leave['reason']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 