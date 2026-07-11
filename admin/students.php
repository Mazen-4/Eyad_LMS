<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['admin']);
$conn = getDbConnection();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $parentPhone = trim($_POST['parent_phone'] ?? '');
    $groupId = (int)($_POST['group_id'] ?? 0);
    $status = $_POST['status'] ?? 'active';

    if ($name === '' || $username === '' || $password === '' || $groupId <= 0) {
        $error = 'Please fill in the required fields and choose a group.';
    } else {
        $check = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $check->bind_param('s', $username);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();

        if ($existing) {
            $error = 'This username is already taken.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO users (name, username, password, phone, parent_phone, group_id, role, status) VALUES (?, ?, ?, ?, ?, ?, "student", ?)');
            $stmt->bind_param('sssssis', $name, $username, $hashedPassword, $phone, $parentPhone, $groupId, $status);
            $stmt->execute();
            $success = 'Student account created successfully.';
        }
    }
}

$groupsResult = $conn->query('SELECT id, name, level FROM `groups` ORDER BY name');
$studentsResult = $conn->query('SELECT u.id, u.name, u.username, u.phone, u.parent_phone, u.status, g.name AS group_name, g.level AS group_level FROM users u LEFT JOIN `groups` g ON g.id = u.group_id WHERE u.role = "student" ORDER BY u.created_at DESC');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Students - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/admin_nav.php'; ?>

    <div class="container py-4">
        <h1 class="mb-3">Students</h1>
        <p class="text-muted">Create and review student accounts for each learning group.</p>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Create Student</h5>
                <form method="post" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Parent Phone</label>
                        <input type="text" name="parent_phone" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Group</label>
                        <select name="group_id" class="form-select" required>
                            <option value="">Select group</option>
                            <?php while ($group = $groupsResult->fetch_assoc()): ?>
                                <option value="<?php echo (int)$group['id']; ?>"><?php echo htmlspecialchars($group['name'] . ' (' . ($group['level'] ?? 'General') . ')', ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Save Student</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Existing Students</h5>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Group</th>
                                <th>Status</th>
                                <th>Phone</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($studentsResult->num_rows > 0): ?>
                                <?php while ($student = $studentsResult->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($student['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(($student['group_name'] ?? 'Unassigned') . (!empty($student['group_level']) ? ' (' . $student['group_level'] . ')' : ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($student['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($student['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-muted">No students found yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
