<?php
require_once 'config/database.php';

// Create new admin user
$username = 'admin';
$password = 'admin123';
$email = 'admin@example.com';
$full_name = 'System Administrator';

// Generate new password hash
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // First, delete existing admin user if exists
    $stmt = $pdo->prepare("DELETE FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    // Insert new admin user
    $stmt = $pdo->prepare("
        INSERT INTO users (username, password, email, full_name, role, is_active) 
        VALUES (?, ?, ?, ?, 'admin', TRUE)
    ");
    $stmt->execute([$username, $password_hash, $email, $full_name]);
    
    echo "New admin user created successfully!<br>";
    echo "Username: " . $username . "<br>";
    echo "Password: " . $password . "<br>";
    echo "Password Hash: " . $password_hash . "<br>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 