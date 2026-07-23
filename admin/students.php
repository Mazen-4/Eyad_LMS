<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['admin']);
$conn = getDbConnection();

$success = '';
$error = '';
$editingStudentId = (int)($_GET['edit'] ?? 0);
$deletingStudentId = (int)($_GET['delete'] ?? 0);
$transferStudentId = (int)($_GET['transfer'] ?? 0);

ensureUserGroupAccessTable();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $selectedGroupIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['group_ids'] ?? [])))));
    $primaryGroupId = (int)($_POST['group_id'] ?? 0);

    if ($primaryGroupId <= 0 && !empty($selectedGroupIds)) {
        $primaryGroupId = $selectedGroupIds[0];
    }

    if ($action === 'transfer') {
        $studentIdToTransfer = (int)($_POST['student_id'] ?? 0);
        $targetGroupId = (int)($_POST['target_group_id'] ?? 0);

        if ($studentIdToTransfer > 0 && $targetGroupId > 0) {
            $transferStmt = $conn->prepare('UPDATE users SET group_id = ? WHERE id = ? AND role = "student"');
            $transferStmt->bind_param('ii', $targetGroupId, $studentIdToTransfer);
            $transferStmt->execute();

            $accessStmt = $conn->prepare('INSERT IGNORE INTO user_group_access (user_id, group_id) VALUES (?, ?)');
            $accessStmt->bind_param('ii', $studentIdToTransfer, $targetGroupId);
            $accessStmt->execute();

            $success = 'Student assigned to the selected group successfully.';
        } else {
            $error = 'Please choose a student and a target group.';
        }
    } else {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $parentPhone = trim($_POST['parent_phone'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if ($name === '' || $username === '' || empty($selectedGroupIds)) {
            $error = 'Please fill in the required fields and choose at least one group.';
        } else {
            if ($action === 'edit' && $studentId > 0) {
                $check = $conn->prepare('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
                $check->bind_param('si', $username, $studentId);
                $check->execute();
                $existing = $check->get_result()->fetch_assoc();

                if ($existing) {
                    $error = 'This username is already taken.';
                } else {
                    if ($password !== '') {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare('UPDATE users SET name = ?, username = ?, password = ?, phone = ?, parent_phone = ?, group_id = ?, status = ? WHERE id = ?');
                        $stmt->bind_param('sssssisi', $name, $username, $hashedPassword, $phone, $parentPhone, $primaryGroupId, $status, $studentId);
                    } else {
                        $stmt = $conn->prepare('UPDATE users SET name = ?, username = ?, phone = ?, parent_phone = ?, group_id = ?, status = ? WHERE id = ?');
                        $stmt->bind_param('ssssisi', $name, $username, $phone, $parentPhone, $primaryGroupId, $status, $studentId);
                    }
                    $stmt->execute();

                    $clearAccessStmt = $conn->prepare('DELETE FROM user_group_access WHERE user_id = ?');
                    $clearAccessStmt->bind_param('i', $studentId);
                    $clearAccessStmt->execute();

                    $insertAccessStmt = $conn->prepare('INSERT IGNORE INTO user_group_access (user_id, group_id) VALUES (?, ?)');
                    foreach ($selectedGroupIds as $groupId) {
                        $gid = (int)$groupId;
                        if ($gid > 0) {
                            $insertAccessStmt->bind_param('ii', $studentId, $gid);
                            $insertAccessStmt->execute();
                        }
                    }

                    $success = 'Student account updated successfully.';
                }
            } else {
                if ($password === '') {
                    $error = 'Password is required for a new student.';
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
                        $stmt->bind_param('sssssis', $name, $username, $hashedPassword, $phone, $parentPhone, $primaryGroupId, $status);
                        $stmt->execute();
                        $studentId = $stmt->insert_id;

                        $insertAccessStmt = $conn->prepare('INSERT IGNORE INTO user_group_access (user_id, group_id) VALUES (?, ?)');
                        foreach ($selectedGroupIds as $groupId) {
                            $gid = (int)$groupId;
                            if ($gid > 0) {
                                $insertAccessStmt->bind_param('ii', $studentId, $gid);
                                $insertAccessStmt->execute();
                            }
                        }

                        $success = 'Student account created successfully.';
                    }
                }
            }
        }
    }
}

if ($deletingStudentId > 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $clearAccessStmt = $conn->prepare('DELETE FROM user_group_access WHERE user_id = ?');
    $clearAccessStmt->bind_param('i', $deletingStudentId);
    $clearAccessStmt->execute();

    $deleteStmt = $conn->prepare('DELETE FROM users WHERE id = ? AND role = "student"');
    $deleteStmt->bind_param('i', $deletingStudentId);
    $deleteStmt->execute();
    $success = 'Student account deleted successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'transfer') {
    $studentIdToTransfer = (int)($_POST['student_id'] ?? 0);
    $targetGroupId = (int)($_POST['target_group_id'] ?? 0);

    if ($studentIdToTransfer > 0 && $targetGroupId > 0) {
        $transferStmt = $conn->prepare('UPDATE users SET group_id = ? WHERE id = ? AND role = "student"');
        $transferStmt->bind_param('ii', $targetGroupId, $studentIdToTransfer);
        $transferStmt->execute();

        $accessStmt = $conn->prepare('INSERT IGNORE INTO user_group_access (user_id, group_id) VALUES (?, ?)');
        $accessStmt->bind_param('ii', $studentIdToTransfer, $targetGroupId);
        $accessStmt->execute();

        $success = 'Student assigned to the selected group successfully.';
    } else {
        $error = 'Please choose a student and a target group.';
    }
}

$groupsResult = $conn->query('SELECT id, name, level FROM `groups` ORDER BY name');
$studentsResult = $conn->query('SELECT u.id, u.name, u.username, u.phone, u.parent_phone, u.status, u.group_id FROM users u WHERE u.role = "student" ORDER BY u.created_at DESC');

$editingStudent = null;
$selectedGroupIds = [];
if ($editingStudentId > 0) {
    $editingStmt = $conn->prepare('SELECT id, name, username, phone, parent_phone, group_id, status FROM users WHERE id = ? AND role = "student" LIMIT 1');
    $editingStmt->bind_param('i', $editingStudentId);
    $editingStmt->execute();
    $editingStudent = $editingStmt->get_result()->fetch_assoc();

    if ($editingStudent) {
        $accessStmt = $conn->prepare('SELECT group_id FROM user_group_access WHERE user_id = ? ORDER BY group_id');
        $accessStmt->bind_param('i', $editingStudentId);
        $accessStmt->execute();
        $accessResult = $accessStmt->get_result();
        while ($accessRow = $accessResult->fetch_assoc()) {
            $selectedGroupIds[(int)$accessRow['group_id']] = true;
        }

        $selectedGroupIds = array_keys($selectedGroupIds);
        if (empty($selectedGroupIds) && !empty($editingStudent['group_id'])) {
            $selectedGroupIds = [(int)$editingStudent['group_id']];
        }
    }
}
$primaryGroupId = (int)($editingStudent['group_id'] ?? 0);
if ($primaryGroupId <= 0 && !empty($selectedGroupIds)) {
    $primaryGroupId = (int)$selectedGroupIds[0];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Students - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css?v=2" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/admin_nav.php'; ?>

    <div class="container py-4">
        <h1 class="mb-3">Students</h1>
        <p class="text-muted">Create and review student accounts for each learning group.</p>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title"><?php echo $editingStudent ? 'Edit Student' : 'Create Student'; ?></h5>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="<?php echo $editingStudent ? 'edit' : 'create'; ?>">
                    <?php if ($editingStudent): ?>
                        <input type="hidden" name="student_id" value="<?php echo (int)$editingStudent['id']; ?>">
                    <?php endif; ?>
                    <div class="col-md-4">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($editingStudent['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($editingStudent['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" <?php echo $editingStudent ? '' : 'required'; ?>>
                        <?php if ($editingStudent): ?>
                            <div class="form-text">Leave blank to keep the current password.</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?php echo (($editingStudent['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (($editingStudent['status'] ?? 'active') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($editingStudent['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Parent Phone</label>
                        <input type="text" name="parent_phone" class="form-control" value="<?php echo htmlspecialchars($editingStudent['parent_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Primary Group</label>
                        <select name="group_id" class="form-select">
                            <option value="">Select primary group</option>
                            <?php
                            $groupsResult->data_seek(0);
                            while ($group = $groupsResult->fetch_assoc()):
                            ?>
                                <option value="<?php echo (int)$group['id']; ?>" <?php echo ((int)($primaryGroupId ?? 0) === (int)$group['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($group['name'] . ' (' . ($group['level'] ?? 'General') . ')', ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Assigned Groups</label>
                        <div class="row g-2">
                            <?php
                            $groupsResult->data_seek(0);
                            while ($group = $groupsResult->fetch_assoc()):
                                $groupId = (int)$group['id'];
                            ?>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="group_ids[]" value="<?php echo $groupId; ?>" id="student_group_<?php echo $groupId; ?>" <?php echo in_array($groupId, $selectedGroupIds, true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="student_group_<?php echo $groupId; ?>">
                                            <?php echo htmlspecialchars($group['name'] . ' (' . ($group['level'] ?? 'General') . ')', ENT_QUOTES, 'UTF-8'); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><?php echo $editingStudent ? 'Update Student' : 'Save Student'; ?></button>
                        <?php if ($editingStudent): ?>
                            <a href="students.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Move Student to Another Group</h5>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="transfer">
                    <div class="col-md-5">
                        <label class="form-label">Student</label>
                        <select name="student_id" class="form-select" required>
                            <option value="">Select student</option>
                            <?php
                            $transferStudentsResult = $conn->query('SELECT id, name, username FROM users WHERE role = "student" ORDER BY name');
                            while ($transferStudent = $transferStudentsResult->fetch_assoc()):
                            ?>
                                <option value="<?php echo (int)$transferStudent['id']; ?>"><?php echo htmlspecialchars($transferStudent['name'] . ' (' . $transferStudent['username'] . ')', ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Target Group</label>
                        <select name="target_group_id" class="form-select" required>
                            <option value="">Select group</option>
                            <?php
                            $groupsResult->data_seek(0);
                            while ($group = $groupsResult->fetch_assoc()):
                            ?>
                                <option value="<?php echo (int)$group['id']; ?>"><?php echo htmlspecialchars($group['name'] . ' (' . ($group['level'] ?? 'General') . ')', ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Move Student</button>
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
                                        <td>
                                            <?php
                                            $studentGroupListStmt = $conn->prepare('SELECT g.name, g.level FROM user_group_access uga INNER JOIN `groups` g ON g.id = uga.group_id WHERE uga.user_id = ? ORDER BY g.name');
                                            $studentGroupListStmt->bind_param('i', $student['id']);
                                            $studentGroupListStmt->execute();
                                            $studentGroupListResult = $studentGroupListStmt->get_result();
                                            $studentGroups = [];
                                            while ($studentGroupRow = $studentGroupListResult->fetch_assoc()) {
                                                $studentGroups[] = $studentGroupRow['name'] . (!empty($studentGroupRow['level']) ? ' (' . $studentGroupRow['level'] . ')' : '');
                                            }
                                            if (!empty($studentGroups)) {
                                                echo htmlspecialchars(implode(', ', $studentGroups), ENT_QUOTES, 'UTF-8');
                                            } else {
                                                echo htmlspecialchars($student['group_id'] ? 'Unassigned' : 'Unassigned', ENT_QUOTES, 'UTF-8');
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($student['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <a href="students.php?edit=<?php echo (int)$student['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <a href="students.php?delete=<?php echo (int)$student['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this student account?');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-muted">No students found yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
