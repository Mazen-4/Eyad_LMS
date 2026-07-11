<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['admin']);
$conn = getDbConnection();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $level = trim($_POST['level'] ?? '');

    if ($name === '' || $level === '') {
        $error = 'Both the group name and level are required.';
    } else {
        $check = $conn->prepare('SELECT id FROM `groups` WHERE name = ? LIMIT 1');
        $check->bind_param('s', $name);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();

        if ($existing) {
            $error = 'This group name already exists.';
        } else {
            $stmt = $conn->prepare('INSERT INTO `groups` (name, level) VALUES (?, ?)');
            $stmt->bind_param('ss', $name, $level);
            $stmt->execute();
            $success = 'Group created successfully.';
        }
    }
}

$groupsResult = $conn->query('SELECT id, name, level, created_at FROM `groups` ORDER BY name');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Groups - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/admin_nav.php'; ?>

    <div class="container py-4">
        <h1 class="mb-3">Groups</h1>
        <p class="text-muted">Create the groups that control access to lectures, resources, and quizzes.</p>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Add Group</h5>
                <form method="post" class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Group Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Morning Batch" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Level</label>
                        <input type="text" name="level" class="form-control" placeholder="e.g. Basic, Advanced 1, Advanced 2" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Save Group</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Existing Groups</h5>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Level</th>
                                <th>Created</th>
                                <th>Students</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($groupsResult->num_rows > 0): ?>
                                <?php while ($group = $groupsResult->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($group['level'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(formatDisplayDateTime($group['created_at']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php
                                            $studentsStmt = $conn->prepare('SELECT name, username, status FROM users WHERE role = "student" AND group_id = ? ORDER BY name');
                                            $studentsStmt->bind_param('i', $group['id']);
                                            $studentsStmt->execute();
                                            $studentsForGroup = $studentsStmt->get_result();

                                            if ($studentsForGroup->num_rows > 0): ?>
                                                <ul class="mb-0 ps-3">
                                                    <?php while ($student = $studentsForGroup->fetch_assoc()): ?>
                                                        <li>
                                                            <?php echo htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8'); ?>
                                                            <small class="text-muted">(<?php echo htmlspecialchars($student['username'], ENT_QUOTES, 'UTF-8'); ?>)</small>
                                                        </li>
                                                    <?php endwhile; ?>
                                                </ul>
                                            <?php else: ?>
                                                <span class="text-muted">No students assigned</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-muted">No groups created yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
