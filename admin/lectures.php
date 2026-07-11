<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['admin']);
$conn = getDbConnection();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $driveFolderId = trim($_POST['drive_folder_id'] ?? '');
    $displayOrder = (int)($_POST['display_order'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    $selectedGroups = $_POST['group_ids'] ?? [];

    if ($title === '') {
        $error = 'Lecture title is required.';
    } else {
        $stmt = $conn->prepare('INSERT INTO lectures (title, description, drive_folder_id, display_order, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('sssis', $title, $description, $driveFolderId, $displayOrder, $status);
        $stmt->execute();
        $lectureId = $stmt->insert_id;

        if (!empty($selectedGroups)) {
            $accessStmt = $conn->prepare('INSERT INTO lecture_folder_access (lecture_id, group_id) VALUES (?, ?)');
            foreach ($selectedGroups as $groupId) {
                $gid = (int)$groupId;
                if ($gid > 0) {
                    $accessStmt->bind_param('ii', $lectureId, $gid);
                    $accessStmt->execute();
                }
            }
        }

        $success = 'Lecture created successfully.';
    }
}

$groupsResult = $conn->query('SELECT id, name, level FROM `groups` ORDER BY name');
$lecturesResult = $conn->query('SELECT l.id, l.title, l.description, l.drive_folder_id, l.display_order, l.status, l.created_at FROM lectures l ORDER BY l.display_order, l.created_at DESC');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lectures - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/admin_nav.php'; ?>

    <div class="container py-4">
        <h1 class="mb-3">Lectures</h1>
        <p class="text-muted">Create lectures and link them to the groups that should access them.</p>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Add Lecture</h5>
                <form method="post" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Drive Folder ID</label>
                        <input type="text" name="drive_folder_id" class="form-control" placeholder="Google Drive folder id">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="display_order" class="form-control" value="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Visible to Groups</label>
                        <div class="row g-2">
                            <?php while ($group = $groupsResult->fetch_assoc()): ?>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="group_ids[]" value="<?php echo (int)$group['id']; ?>" id="group_<?php echo (int)$group['id']; ?>">
                                        <label class="form-check-label" for="group_<?php echo (int)$group['id']; ?>">
                                            <?php echo htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($group['level'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>)
                                        </label>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Save Lecture</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Existing Lectures</h5>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Order</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($lecturesResult->num_rows > 0): ?>
                                <?php while ($lecture = $lecturesResult->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($lecture['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int)$lecture['display_order']; ?></td>
                                        <td><?php echo htmlspecialchars($lecture['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(formatDisplayDateTime($lecture['created_at']), ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-muted">No lectures created yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
