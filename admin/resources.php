<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['admin']);
$conn = getDbConnection();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $groupId = (int)($_POST['group_id'] ?? 0);
    $status = $_POST['status'] ?? 'active';

    if ($title === '' || $groupId <= 0) {
        $error = 'Title and group are required.';
    } else {
        $pdfPath = '';
        if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/pdfs/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = time() . '_' . basename($_FILES['pdf']['name']);
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['pdf']['tmp_name'], $targetPath)) {
                $pdfPath = 'uploads/pdfs/' . $fileName;
            } else {
                $error = 'Failed to upload PDF.';
            }
        }

        if ($error === '') {
            $stmt = $conn->prepare('INSERT INTO resources (title, description, pdf_path, group_id, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sssis', $title, $description, $pdfPath, $groupId, $status);
            $stmt->execute();
            $success = 'Resource created successfully.';
        }
    }
}

$groupsResult = $conn->query('SELECT id, name, level FROM `groups` ORDER BY name');
$resourcesResult = $conn->query('SELECT r.id, r.title, r.description, r.pdf_path, r.status, r.created_at, g.name AS group_name, g.level AS group_level FROM resources r LEFT JOIN `groups` g ON g.id = r.group_id ORDER BY r.created_at DESC');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resources - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/admin_nav.php'; ?>

    <div class="container py-4">
        <h1 class="mb-3">Resources</h1>
        <p class="text-muted">Upload PDFs and assign them to a group so students can access them.</p>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Add Resource</h5>
                <form method="post" enctype="multipart/form-data" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Group</label>
                        <select name="group_id" class="form-select" required>
                            <option value="">Select group</option>
                            <?php while ($group = $groupsResult->fetch_assoc()): ?>
                                <option value="<?php echo (int)$group['id']; ?>"><?php echo htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($group['level'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">PDF File</label>
                        <input type="file" name="pdf" class="form-control" accept="application/pdf" required>
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
                        <button type="submit" class="btn btn-primary">Save Resource</button>
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
                                <th>Group</th>
                                <th>Status</th>
                                <th>Created</th>
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
                                        <td><?php echo htmlspecialchars(($resource['group_name'] ?? '-') . ' (' . ($resource['group_level'] ?? '-') . ')', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($resource['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(formatDisplayDateTime($resource['created_at']), ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-muted">No resources created yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
