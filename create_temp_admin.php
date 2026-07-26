<?php
require __DIR__ . '/config/database.php';
$conn = getDbConnection();
if ($conn === null) {
    echo "FAIL: no DB connection\n";
    exit(1);
}
$username = 'tempadmin';
$password = 'TempPass123!';
$name = 'Temporary Admin';
$role = 'admin';
$status = 'active';
$stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->fetch_assoc()) {
    echo "EXISTS: tempadmin already exists\n";
    exit(0);
}
$hashed = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare('INSERT INTO users (name, username, password, role, status) VALUES (?, ?, ?, ?, ?)');
$stmt->bind_param('sssss', $name, $username, $hashed, $role, $status);
if ($stmt->execute()) {
    echo "CREATED: tempadmin with password TempPass123!\n";
} else {
    echo "FAIL: could not create tempadmin\n";
}
