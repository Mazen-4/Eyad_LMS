<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['student', 'admin']);
$conn = getDbConnection();

$isAdmin = ($user['role'] === 'admin');
$studentGroupIds = array_values(array_unique(array_filter(array_map('intval', $user['group_ids'] ?? []))));
if (empty($studentGroupIds) && !empty($user['group_id'])) {
    $studentGroupIds = [(int)$user['group_id']];
}

if (!empty($studentGroupIds) && !$isAdmin) {
    $placeholders = implode(', ', array_fill(0, count($studentGroupIds), '?'));
    $resourcesStmt = $conn->prepare('SELECT DISTINCT r.id, r.title, r.description, r.pdf_path FROM resources r LEFT JOIN resource_group_access rga ON rga.resource_id = r.id WHERE r.status = "active" AND (r.group_id IN (' . $placeholders . ') OR rga.group_id IN (' . $placeholders . ')) ORDER BY r.created_at DESC');
    $params = array_merge($studentGroupIds, $studentGroupIds);
    bindPreparedParams($resourcesStmt, $params);
    $resourcesStmt->execute();
    $resourcesResult = $resourcesStmt->get_result();
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
    <link href="<?php echo htmlspecialchars(appAssetUrl('assets/css/theme.css?v=2'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
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
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
