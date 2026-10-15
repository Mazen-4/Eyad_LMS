<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/course_content_access.php';

$user = requireLogin(['student', 'admin']);
$conn = getDbConnection();

$isAdmin = ($user['role'] === 'admin');
$studentGroupIds = array_values(array_unique(array_filter(array_map('intval', $user['group_ids'] ?? []))));
if (empty($studentGroupIds) && !empty($user['group_id'])) {
    $studentGroupIds = [(int)$user['group_id']];
}

$resources = getAccessibleResources($conn, $studentGroupIds, $isAdmin);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resources - Student</title>
    <link rel="icon" href="<?php echo htmlspecialchars(appAssetUrl('Images/eyad_logo1.jpeg?v=' . filemtime(__DIR__ . '/../Images/eyad_logo1.jpeg')), ENT_QUOTES, 'UTF-8'); ?>" type="image/jpeg">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars(appAssetUrl('Images/eyad_logo1.jpeg?v=' . filemtime(__DIR__ . '/../Images/eyad_logo1.jpeg')), ENT_QUOTES, 'UTF-8'); ?>" type="image/jpeg">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars(appAssetUrl('Images/eyad_logo1.jpeg?v=' . filemtime(__DIR__ . '/../Images/eyad_logo1.jpeg')), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" href="<?php echo htmlspecialchars(appAssetUrl('favicon.ico?v=' . filemtime(__DIR__ . '/../favicon.ico')), ENT_QUOTES, 'UTF-8'); ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(appVersionedAssetUrl('assets/css/theme.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/student_nav.php'; ?>

    <div class="container py-4">
        <a href="dashboard.php" class="btn btn-outline-secondary mb-3">&larr; Back to Dashboard</a>
        <h1 class="mb-3">My Resources</h1>
        <p class="text-muted">These PDFs are available directly to your group. Course-linked resources appear in their course.</p>

        <?php if ($resources !== []): ?>
            <div class="row g-3">
                <?php foreach ($resources as $resource): ?>
                    <div class="col-12 col-md-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($resource['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($resource['description'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php if (!empty($resource['pdf_path'])): ?>
                                    <a href="resource_download.php?resource_id=<?php echo (int) $resource['id']; ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm w-100 w-md-auto">Open PDF</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No resources are available for your group yet.</div>
        <?php endif; ?>
    </div>
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
