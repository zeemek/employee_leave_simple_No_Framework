<?php
require_once 'config/database.php';

// Test database connection
echo "Testing database connection...<br>";
try {
    $pdo->query("SELECT 1");
    echo "Database connection successful!<br><br>";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Test admin credentials
echo "Testing admin credentials...<br>";
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
$stmt->execute();
$admin = $stmt->fetch();

if ($admin) {
    echo "Admin user found!<br>";
    echo "Username: " . $admin['username'] . "<br>";
    echo "Password hash: " . $admin['password'] . "<br>";
    echo "Is active: " . ($admin['is_active'] ? 'Yes' : 'No') . "<br>";
    echo "Role: " . $admin['role'] . "<br>";
    
    // Test password verification
    $password = 'admin123';
    if (password_verify($password, $admin['password'])) {
        echo "Password verification successful!<br>";
    } else {
        echo "Password verification failed!<br>";
    }
} else {
    echo "Admin user not found!<br>";
}

// List all users
echo "<br>All users in database:<br>";
$stmt = $pdo->query("SELECT id, username, email, role, is_active FROM users");
$users = $stmt->fetchAll();
foreach ($users as $user) {
    echo "ID: {$user['id']}, Username: {$user['username']}, Email: {$user['email']}, Role: {$user['role']}, Active: " . ($user['is_active'] ? 'Yes' : 'No') . "<br>";
}
?> 