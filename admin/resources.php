<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['admin']);
$conn = getDbConnection();

$success = '';
$error = '';
$editingResourceId = (int)($_GET['edit'] ?? 0);
$deletingResourceId = (int)($_GET['delete'] ?? 0);

$conn->query("CREATE TABLE IF NOT EXISTS resource_group_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_id INT NOT NULL,
    group_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_resource_group (resource_id, group_id),
    KEY group_id (group_id)
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $resourceId = (int)($_POST['resource_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $selectedGroups = array_map('intval', (array)($_POST['group_ids'] ?? []));
    $status = $_POST['status'] ?? 'active';

    if ($title === '') {
        $error = 'Resource title is required.';
    } elseif (empty($selectedGroups)) {
        $error = 'Please select at least one group for this resource.';
    } else {
        $pdfPath = '';
        if (isset($_FILES['pdf']) && is_array($_FILES['pdf']) && ($_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['pdf']['tmp_name'];
            $originalName = basename($_FILES['pdf']['name'] ?? '');
            $fileSize = (int)($_FILES['pdf']['size'] ?? 0);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if ($fileSize > 20 * 1024 * 1024) {
                $error = 'PDF must be 20MB or smaller.';
            } elseif ($extension !== 'pdf') {
                $error = 'Only PDF files are allowed.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $tmpName);
                finfo_close($finfo);

                if ($mimeType !== 'application/pdf') {
                    $error = 'Only valid PDF files are allowed.';
                }
            }

            if ($error === '') {
                $uploadDir = __DIR__ . '/../uploads/pdfs/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileName = time() . '_' . bin2hex(random_bytes(6)) . '.pdf';
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $pdfPath = 'uploads/pdfs/' . $fileName;

                    if ($action === 'edit' && $resourceId > 0) {
                        $oldFileStmt = $conn->prepare('SELECT pdf_path FROM resources WHERE id = ? LIMIT 1');
                        $oldFileStmt->bind_param('i', $resourceId);
                        $oldFileStmt->execute();
                        $oldFile = $oldFileStmt->get_result()->fetch_assoc();
                        if (!empty($oldFile['pdf_path'])) {
                            $oldFilePath = __DIR__ . '/../' . $oldFile['pdf_path'];
                            if (is_file($oldFilePath)) {
                                unlink($oldFilePath);
                            }
                        }
                    }
                } else {
                    $error = 'Failed to upload PDF file.';
                }
            }
        } elseif ($action === 'create') {
            $error = 'Please upload a PDF file.';
        }

        if ($error === '') {
            if ($action === 'edit' && $resourceId > 0) {
                if ($pdfPath !== '') {
                    $stmt = $conn->prepare('UPDATE resources SET title = ?, description = ?, pdf_path = ?, status = ? WHERE id = ?');
                    $stmt->bind_param('ssssi', $title, $description, $pdfPath, $status, $resourceId);
                } else {
                    $stmt = $conn->prepare('UPDATE resources SET title = ?, description = ?, status = ? WHERE id = ?');
                    $stmt->bind_param('sssi', $title, $description, $status, $resourceId);
                }
                $stmt->execute();

                $clearAccessStmt = $conn->prepare('DELETE FROM resource_group_access WHERE resource_id = ?');
                $clearAccessStmt->bind_param('i', $resourceId);
                $clearAccessStmt->execute();

                $accessStmt = $conn->prepare('INSERT INTO resource_group_access (resource_id, group_id) VALUES (?, ?)');
                foreach ($selectedGroups as $groupId) {
                    $gid = (int)$groupId;
                    if ($gid > 0) {
                        $accessStmt->bind_param('ii', $resourceId, $gid);
                        $accessStmt->execute();
                    }
                }

                $success = 'Resource updated successfully.';
            } else {
                $stmt = $conn->prepare('INSERT INTO resources (title, description, pdf_path, status) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('sssi', $title, $description, $pdfPath, $status);
                $stmt->execute();
                $resourceId = $stmt->insert_id;

                $accessStmt = $conn->prepare('INSERT INTO resource_group_access (resource_id, group_id) VALUES (?, ?)');
                foreach ($selectedGroups as $groupId) {
                    $gid = (int)$groupId;
                    if ($gid > 0) {
                        $accessStmt->bind_param('ii', $resourceId, $gid);
                        $accessStmt->execute();
                    }
                }

                $success = 'Resource created successfully.';
            }
        }
    }
}

if ($deletingResourceId > 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $resourceToDeleteStmt = $conn->prepare('SELECT pdf_path FROM resources WHERE id = ? LIMIT 1');
    $resourceToDeleteStmt->bind_param('i', $deletingResourceId);
    $resourceToDeleteStmt->execute();
    $resourceToDelete = $resourceToDeleteStmt->get_result()->fetch_assoc();

    if ($resourceToDelete && !empty($resourceToDelete['pdf_path'])) {
        $filePath = __DIR__ . '/../' . $resourceToDelete['pdf_path'];
        if (is_file($filePath)) {
            unlink($filePath);
        }
    }

    $clearAccessStmt = $conn->prepare('DELETE FROM resource_group_access WHERE resource_id = ?');
    $clearAccessStmt->bind_param('i', $deletingResourceId);
    $clearAccessStmt->execute();

    $deleteStmt = $conn->prepare('DELETE FROM resources WHERE id = ?');
    $deleteStmt->bind_param('i', $deletingResourceId);
    $deleteStmt->execute();
    $success = 'Resource deleted successfully.';
}

$groupsResult = $conn->query('SELECT id, name, level FROM `groups` ORDER BY name');
$groups = [];
while ($group = $groupsResult->fetch_assoc()) {
    $groups[] = $group;
}

$resourcesResult = $conn->query('SELECT r.id, r.title, r.description, r.pdf_path, r.status, r.created_at, r.group_id, g.name AS group_name, g.level AS group_level FROM resources r LEFT JOIN `groups` g ON g.id = r.group_id ORDER BY r.created_at DESC');

$editingResource = null;
$selectedGroupIds = [];
if ($editingResourceId > 0) {
    $editingStmt = $conn->prepare('SELECT id, title, description, pdf_path, status FROM resources WHERE id = ? LIMIT 1');
    $editingStmt->bind_param('i', $editingResourceId);
    $editingStmt->execute();
    $editingResource = $editingStmt->get_result()->fetch_assoc();

    if ($editingResource) {
        $accessStmt = $conn->prepare('SELECT group_id FROM resource_group_access WHERE resource_id = ? ORDER BY group_id');
        $accessStmt->bind_param('i', $editingResourceId);
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
    <title>Resources - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(appAssetUrl('assets/css/theme.css?v=2'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/admin_nav.php'; ?>

    <div class="container py-4">
        <h1 class="mb-3">Resources</h1>
        <p class="text-muted">Upload PDFs and assign them to one or more groups so students can access them.</p>

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
                <h5 class="card-title"><?php echo $editingResource ? 'Edit Resource' : 'Add Resource'; ?></h5>
                <form method="post" enctype="multipart/form-data" class="row g-3">
                    <input type="hidden" name="action" value="<?php echo $editingResource ? 'edit' : 'create'; ?>">
                    <?php if ($editingResource): ?>
                        <input type="hidden" name="resource_id" value="<?php echo (int)$editingResource['id']; ?>">
                    <?php endif; ?>
                    <div class="col-md-4">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($editingResource['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">PDF File</label>
                        <input type="file" name="pdf" class="form-control" accept="application/pdf" <?php echo $editingResource ? '' : 'required'; ?>>
                        <div class="form-text">Supported format: PDF. Maximum file size: 20MB.</div>
                        <?php if ($editingResource): ?>
                            <div class="form-text">Leave blank to keep the current PDF.</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?php echo (($editingResource['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (($editingResource['status'] ?? 'active') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($editingResource['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Visible to Groups</label>
                        <div class="row g-2">
                            <?php foreach ($groups as $group): ?>
                                <?php $groupId = (int)$group['id']; ?>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="group_ids[]" value="<?php echo $groupId; ?>" id="resource_group_<?php echo $groupId; ?>" <?php echo $editingResource && isset($selectedGroupIds[$groupId]) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="resource_group_<?php echo $groupId; ?>">
                                            <?php echo htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($group['level'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>)
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><?php echo $editingResource ? 'Update Resource' : 'Save Resource'; ?></button>
                        <?php if ($editingResource): ?>
                            <a href="resources.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Existing Resources</h5>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Groups</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($resourcesResult->num_rows > 0): ?>
                                <?php while ($resource = $resourcesResult->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($resource['title'], ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if (!empty($resource['pdf_path'])): ?>
                                                <br><small><a href="../<?php echo htmlspecialchars($resource['pdf_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Open PDF</a></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $resourceGroupsStmt = $conn->prepare('SELECT g.name, g.level FROM resource_group_access rga INNER JOIN `groups` g ON g.id = rga.group_id WHERE rga.resource_id = ? ORDER BY g.name');
                                            $resourceGroupsStmt->bind_param('i', $resource['id']);
                                            $resourceGroupsStmt->execute();
                                            $resourceGroupsResult = $resourceGroupsStmt->get_result();
                                            ?>
                                            <?php if ($resourceGroupsResult->num_rows > 0): ?>
                                                <ul class="mb-0 ps-3">
                                                    <?php while ($resourceGroup = $resourceGroupsResult->fetch_assoc()): ?>
                                                        <li><?php echo htmlspecialchars($resourceGroup['name'] . (!empty($resourceGroup['level']) ? ' (' . $resourceGroup['level'] . ')' : ''), ENT_QUOTES, 'UTF-8'); ?></li>
                                                    <?php endwhile; ?>
                                                </ul>
                                            <?php else: ?>
                                                <span class="text-muted"><?php echo htmlspecialchars(($resource['group_name'] ?? '-') . (!empty($resource['group_level']) ? ' (' . $resource['group_level'] . ')' : ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($resource['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(formatDisplayDateTime($resource['created_at']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <a href="resources.php?edit=<?php echo (int)$resource['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <a href="resources.php?delete=<?php echo (int)$resource['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this resource and its PDF file?');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-muted">No resources created yet.</td></tr>
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
