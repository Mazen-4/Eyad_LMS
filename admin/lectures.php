<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['admin']);
$conn = getDbConnection();

$success = '';
$error = '';
$editingLectureId = (int)($_GET['edit'] ?? 0);
$deletingLectureId = (int)($_GET['delete'] ?? 0);

function normalizeDriveLink($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('#/view(\?.*)?$#i', '/preview?rm=minimal', $value);
    $value = preg_replace('#/preview(\?.*)?$#i', '/preview?rm=minimal', $value);

    if (preg_match('#^https?://#i', $value)) {
        if (preg_match('#/file/d/([^/?#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/file/d/' . $matches[1] . '/preview?rm=minimal';
        }

        if (preg_match('#/drive/u/\d+/view\?usp=sharing&id=([^&#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/file/d/' . $matches[1] . '/preview?rm=minimal';
        }

        if (preg_match('#[?&]id=([^&#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/file/d/' . $matches[1] . '/preview?rm=minimal';
        }

        if (preg_match('#/drive/folders/([^/?#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/drive/folders/' . $matches[1];
        }

        if (preg_match('#/folders/([^/?#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/drive/folders/' . $matches[1];
        }

        return $value;
    }

    if (preg_match('/^[a-zA-Z0-9\-_]+$/', $value)) {
        return 'https://drive.google.com/drive/folders/' . $value;
    }

    return $value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $lectureId = (int)($_POST['lecture_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $driveFolderId = normalizeDriveLink(trim($_POST['drive_folder_id'] ?? ''));
    $displayOrder = (int)($_POST['display_order'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    $selectedGroups = (array)($_POST['group_ids'] ?? []);

    if ($title === '') {
        $error = 'Lecture title is required.';
    } else {
        if ($action === 'edit' && $lectureId > 0) {
            $stmt = $conn->prepare('UPDATE lectures SET title = ?, description = ?, drive_folder_id = ?, display_order = ?, status = ? WHERE id = ?');
            $stmt->bind_param('sssssi', $title, $description, $driveFolderId, $displayOrder, $status, $lectureId);
            $stmt->execute();

            $clearAccessStmt = $conn->prepare('DELETE FROM lecture_folder_access WHERE lecture_id = ?');
            $clearAccessStmt->bind_param('i', $lectureId);
            $clearAccessStmt->execute();

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

            $success = 'Lecture updated successfully.';
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
}

if ($deletingLectureId > 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $deleteAccessStmt = $conn->prepare('DELETE FROM lecture_folder_access WHERE lecture_id = ?');
    $deleteAccessStmt->bind_param('i', $deletingLectureId);
    $deleteAccessStmt->execute();

    $deleteStmt = $conn->prepare('DELETE FROM lectures WHERE id = ?');
    $deleteStmt->bind_param('i', $deletingLectureId);
    $deleteStmt->execute();

    $success = 'Lecture deleted successfully.';
}

$groupsResult = $conn->query('SELECT id, name, level FROM `groups` ORDER BY name');
$groups = [];
while ($group = $groupsResult->fetch_assoc()) {
    $groups[] = $group;
}

$lecturesResult = $conn->query('SELECT l.id, l.title, l.description, l.drive_folder_id, l.display_order, l.status, l.created_at FROM lectures l ORDER BY l.display_order, l.created_at DESC');

$editingLecture = null;
$selectedGroupIds = [];
if ($editingLectureId > 0) {
    $editingStmt = $conn->prepare('SELECT id, title, description, drive_folder_id, display_order, status FROM lectures WHERE id = ? LIMIT 1');
    $editingStmt->bind_param('i', $editingLectureId);
    $editingStmt->execute();
    $editingLecture = $editingStmt->get_result()->fetch_assoc();

    if ($editingLecture) {
        $accessStmt = $conn->prepare('SELECT group_id FROM lecture_folder_access WHERE lecture_id = ? ORDER BY group_id');
        $accessStmt->bind_param('i', $editingLectureId);
        $accessStmt->execute();
        $accessResult = $accessStmt->get_result();
        while ($accessRow = $accessResult->fetch_assoc()) {
            $selectedGroupIds[(int)$accessRow['group_id']] = true;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sessions - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(appVersionedAssetUrl('assets/css/theme.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/admin_nav.php'; ?>

    <div class="container py-4">
        <h1 class="mb-3">Sessions</h1>
        <p class="text-muted">Create sessions and link them to the groups that should access them.</p>

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
                <h5 class="card-title"><?php echo $editingLecture ? 'Edit Session' : 'Add Session'; ?></h5>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="<?php echo $editingLecture ? 'edit' : 'create'; ?>">
                    <?php if ($editingLecture): ?>
                        <input type="hidden" name="lecture_id" value="<?php echo (int)$editingLecture['id']; ?>">
                    <?php endif; ?>
                    <div class="col-md-4">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($editingLecture['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Drive Link or Folder ID</label>
                        <input type="text" name="drive_folder_id" class="form-control" placeholder="Google Drive link or folder ID" value="<?php echo htmlspecialchars($editingLecture['drive_folder_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="display_order" class="form-control" value="<?php echo (int)($editingLecture['display_order'] ?? 0); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?php echo (($editingLecture['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (($editingLecture['status'] ?? 'active') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($editingLecture['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Visible to Groups</label>
                        <div class="row g-2">
                            <?php foreach ($groups as $group): ?>
                                <?php $groupId = (int)$group['id']; ?>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="group_ids[]" value="<?php echo $groupId; ?>" id="group_<?php echo $groupId; ?>" <?php echo $editingLecture && isset($selectedGroupIds[$groupId]) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="group_<?php echo $groupId; ?>">
                                            <?php echo htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($group['level'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>)
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><?php echo $editingLecture ? 'Update Session' : 'Save Session'; ?></button>
                        <?php if ($editingLecture): ?>
                            <a href="lectures.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Existing Sessions</h5>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Order</th>
                                <th>Status</th>
                                <th>Groups</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($lecturesResult->num_rows > 0): ?>
                                <?php while ($lecture = $lecturesResult->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($lecture['title'], ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if ($lecture['description'] !== ''): ?>
                                                <div class="small text-muted"><?php echo htmlspecialchars($lecture['description'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo (int)$lecture['display_order']; ?></td>
                                        <td><?php echo htmlspecialchars($lecture['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php
                                            $lectureGroupsStmt = $conn->prepare('SELECT g.name, g.level FROM lecture_folder_access lfa INNER JOIN `groups` g ON g.id = lfa.group_id WHERE lfa.lecture_id = ? ORDER BY g.name');
                                            $lectureGroupsStmt->bind_param('i', $lecture['id']);
                                            $lectureGroupsStmt->execute();
                                            $lectureGroupsResult = $lectureGroupsStmt->get_result();
                                            ?>
                                            <?php if ($lectureGroupsResult->num_rows > 0): ?>
                                                <ul class="mb-0 ps-3">
                                                    <?php while ($lectureGroup = $lectureGroupsResult->fetch_assoc()): ?>
                                                        <li><?php echo htmlspecialchars($lectureGroup['name'] . (!empty($lectureGroup['level']) ? ' (' . $lectureGroup['level'] . ')' : ''), ENT_QUOTES, 'UTF-8'); ?></li>
                                                    <?php endwhile; ?>
                                                </ul>
                                            <?php else: ?>
                                                <span class="text-muted">No groups assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(formatDisplayDateTime($lecture['created_at']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <a href="lectures.php?edit=<?php echo (int)$lecture['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <a href="lectures.php?delete=<?php echo (int)$lecture['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this session and its group access links?');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-muted">No sessions created yet.</td></tr>
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
