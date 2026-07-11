<?php
require_once __DIR__ . '/../config/database.php';

function isLoggedIn() {
    return !empty($_SESSION['user']);
}

function currentUser() {
    return $_SESSION['user'] ?? null;
}

function requireLogin($allowedRoles = []) {
    if (!isLoggedIn()) {
        header('Location: ../public/login.php');
        exit;
    }

    $user = currentUser();

    if (!empty($allowedRoles) && !in_array($user['role'], $allowedRoles, true)) {
        header('Location: ../public/login.php');
        exit;
    }

    return $user;
}

function authenticateUser($username, $password) {
    $stmt = getDbConnection()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }

    return null;
}

function setLoggedInUser($user) {
    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'username' => $user['username'],
        'role' => $user['role'],
    ];
}

function logoutUser() {
    session_unset();
    session_destroy();
}

function formatDisplayDateTime($value) {
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '-';
    }

    return date('d/m/Y h:i A', $timestamp);
}

function redirectToRoleDashboard($user) {
    if ($user['role'] === 'admin') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: ../student/dashboard.php');
    }
    exit;
}
?>
