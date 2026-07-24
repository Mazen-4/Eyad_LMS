<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['admin']);
$conn = getDbConnection();

$success = '';
$error = '';
$editingAdminId = (int)($_GET['edit'] ?? 0);
$deletingAdminId = (int)($_GET['delete'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $adminId = (int)($_POST['admin_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if ($name === '' || $username === '' || ($action !== 'edit' && $password === '')) {
        $error = 'Please fill in the required fields.';
    } else {
        $check = $conn->prepare('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
        $check->bind_param('si', $username, $adminId);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();

        if ($existing) {
            $error = 'This username is already taken.';
        } else {
            if ($action === 'edit' && $adminId > 0) {
                if ($adminId === (int)$user['id'] && $status !== 'active') {
                    $error = 'Your own admin account must remain active.';
                } elseif ($password !== '') {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare('UPDATE users SET name = ?, username = ?, password = ?, status = ? WHERE id = ? AND role = "admin"');
                    $stmt->bind_param('ssssi', $name, $username, $hashedPassword, $status, $adminId);
                    $stmt->execute();
                    $success = 'Admin account updated successfully.';
                } else {
                    $stmt = $conn->prepare('UPDATE users SET name = ?, username = ?, status = ? WHERE id = ? AND role = "admin"');
                    $stmt->bind_param('sssi', $name, $username, $status, $adminId);
                    $stmt->execute();
                    $success = 'Admin account updated successfully.';
                }
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('INSERT INTO users (name, username, password, role, status) VALUES (?, ?, ?, "admin", ?)');
                $stmt->bind_param('ssss', $name, $username, $hashedPassword, $status);
                $stmt->execute();
                $success = 'Admin account created successfully.';
            }
        }
    }
}

if ($deletingAdminId > 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($deletingAdminId === (int)$user['id']) {
        $error = 'You cannot delete your own admin account.';
    } else {
        $adminCountResult = $conn->query('SELECT COUNT(*) AS admin_count FROM users WHERE role = "admin"');
        $adminCount = (int)$adminCountResult->fetch_assoc()['admin_count'];

        if ($adminCount <= 1) {
            $error = 'The last admin account cannot be deleted.';
        } else {
            $deleteStmt = $conn->prepare('DELETE FROM users WHERE id = ? AND role = "admin"');
            $deleteStmt->bind_param('i', $deletingAdminId);
            $deleteStmt->execute();
            $success = 'Admin account deleted successfully.';
        }
    }
}

$editingAdmin = null;
if ($editingAdminId > 0) {
    $editingStmt = $conn->prepare('SELECT id, name, username, status FROM users WHERE id = ? AND role = "admin" LIMIT 1');
    $editingStmt->bind_param('i', $editingAdminId);
    $editingStmt->execute();
    $editingAdmin = $editingStmt->get_result()->fetch_assoc();
}

$adminsResult = $conn->query('SELECT id, name, username, status, created_at FROM users WHERE role = "admin" ORDER BY created_at DESC');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admins - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(appVersionedAssetUrl('assets/css/theme.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/admin_nav.php'; ?>

    <div class="container py-4">
        <h1 class="mb-3">Admins</h1>
        <p class="text-muted">Create additional admin accounts for the LMS.</p>

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
                <h5 class="card-title"><?php echo $editingAdmin ? 'Edit Admin' : 'Create Admin'; ?></h5>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="<?php echo $editingAdmin ? 'edit' : 'create'; ?>">
                    <?php if ($editingAdmin): ?>
                        <input type="hidden" name="admin_id" value="<?php echo (int)$editingAdmin['id']; ?>">
                    <?php endif; ?>
                    <div class="col-md-4">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($editingAdmin['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($editingAdmin['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Password<?php echo $editingAdmin ? ' (optional)' : ''; ?></label>
                        <input type="password" name="password" class="form-control" <?php echo $editingAdmin ? '' : 'required'; ?>>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?php echo (($editingAdmin['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (($editingAdmin['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><?php echo $editingAdmin ? 'Update Admin' : 'Save Admin'; ?></button>
                        <?php if ($editingAdmin): ?>
                            <a href="admins.php" class="btn btn-outline-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Existing Admins</h5>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($adminsResult->num_rows > 0): ?>
                                <?php while ($admin = $adminsResult->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($admin['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($admin['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(formatDisplayDateTime($admin['created_at']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-nowrap">
                                            <a href="admins.php?edit=<?php echo (int)$admin['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <?php if ((int)$admin['id'] !== (int)$user['id']): ?>
                                                <a href="admins.php?delete=<?php echo (int)$admin['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this admin account?');">Delete</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-muted">No admins created yet.</td></tr>
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
