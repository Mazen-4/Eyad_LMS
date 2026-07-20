<?php
require_once __DIR__ . '/../config/database.php';

function ensureUserGroupAccessTable() {
    $conn = getDbConnection();
    if ($conn === null) {
        return;
    }

    $conn->query("CREATE TABLE IF NOT EXISTS user_group_access (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        group_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_group (user_id, group_id),
        KEY user_id (user_id),
        KEY group_id (group_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function bindPreparedParams($stmt, array $params) {
    if (empty($params)) {
        return;
    }

    $types = '';
    $references = [];
    foreach ($params as $key => $value) {
        $types .= is_int($value) ? 'i' : 's';
        $references[$key] = &$params[$key];
    }

    $stmt->bind_param($types, ...$references);
}

function getUserGroupIds($userId, $conn = null) {
    $userId = (int)$userId;
    if ($userId <= 0) {
        return [];
    }

    $conn = $conn ?: getDbConnection();
    if ($conn === null) {
        return [];
    }

    $groupIds = [];

    $primaryGroupStmt = $conn->prepare('SELECT group_id FROM users WHERE id = ? LIMIT 1');
    $primaryGroupStmt->bind_param('i', $userId);
    $primaryGroupStmt->execute();
    $primaryGroupResult = $primaryGroupStmt->get_result();
    $primaryGroupRow = $primaryGroupResult->fetch_assoc();

    if (!empty($primaryGroupRow['group_id'])) {
        $groupIds[] = (int)$primaryGroupRow['group_id'];
    }

    $accessStmt = $conn->prepare('SELECT group_id FROM user_group_access WHERE user_id = ? ORDER BY group_id');
    $accessStmt->bind_param('i', $userId);
    $accessStmt->execute();
    $accessResult = $accessStmt->get_result();
    while ($accessRow = $accessResult->fetch_assoc()) {
        $groupIds[] = (int)$accessRow['group_id'];
    }

    return array_values(array_unique(array_filter($groupIds, static function ($groupId) {
        return $groupId > 0;
    })));
}

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

    if (!empty($user['id'])) {
        ensureUserGroupAccessTable();
    }

    if (($user['role'] ?? '') === 'student' && empty($user['group_id']) && !empty($user['id'])) {
        $freshUser = dbFetchOne('SELECT id, name, username, role, group_id FROM users WHERE id = ? LIMIT 1', [(int)$user['id']]);
        if ($freshUser) {
            setLoggedInUser($freshUser);
            $user = currentUser();
        }
    }

    return $user;
}

function authenticateUser($username, $password) {
    $conn = getDbConnection();
    if ($conn === null) {
        return null;
    }

    $stmt = $conn->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
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
    ensureUserGroupAccessTable();

    $userId = (int)($user['id'] ?? 0);
    $groupIds = [];
    if ($userId > 0) {
        $groupIds = getUserGroupIds($userId);
    }

    if (empty($groupIds) && isset($user['group_id'])) {
        $groupIds = [(int)$user['group_id']];
    }

    $_SESSION['user'] = [
        'id' => $userId,
        'name' => $user['name'] ?? '',
        'username' => $user['username'] ?? '',
        'role' => $user['role'] ?? '',
        'group_id' => !empty($groupIds) ? (int)$groupIds[0] : null,
        'group_ids' => $groupIds,
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
