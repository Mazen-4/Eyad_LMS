<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['student']);
$conn = getDbConnection();

$studentGroupId = (int)($user['group_id'] ?? 0);

if ($studentGroupId > 0) {
    $resourcesResult = $conn->prepare('SELECT id, title, description, pdf_path FROM resources WHERE status = "active" AND group_id = ? ORDER BY created_at DESC');
    $resourcesResult->bind_param('i', $studentGroupId);
    $resourcesResult->execute();
    $resourcesResult = $resourcesResult->get_result();
} else {
    $resourcesResult = $conn->query('SELECT id, title, description, pdf_path FROM resources WHERE status = "active" ORDER BY created_at DESC');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resources - Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/student_nav.php'; ?>

    <div class="container py-4">
        <h1 class="mb-3">My Resources</h1>
        <p class="text-muted">These PDFs are available for your assigned group.</p>

        <?php if ($resourcesResult->num_rows > 0): ?>
            <div class="row g-3">
                <?php while ($resource = $resourcesResult->fetch_assoc()): ?>
                    <div class="col-12 col-md-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($resource['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($resource['description'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php if (!empty($resource['pdf_path'])): ?>
                                    <a href="../<?php echo htmlspecialchars($resource['pdf_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-outline-primary btn-sm w-100 w-md-auto">Open PDF</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No resources are available for your group yet.</div>
        <?php endif; ?>
    </div>
</body>
</html>
