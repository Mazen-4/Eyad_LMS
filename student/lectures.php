<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['student']);
$conn = getDbConnection();

$studentGroupId = (int)($user['group_id'] ?? 0);

if ($studentGroupId > 0) {
    $lecturesResult = $conn->prepare('SELECT l.id, l.title, l.description, l.drive_folder_id, l.display_order FROM lectures l INNER JOIN lecture_folder_access lfa ON lfa.lecture_id = l.id WHERE l.status = "active" AND lfa.group_id = ? ORDER BY l.display_order, l.id');
    $lecturesResult->bind_param('i', $studentGroupId);
    $lecturesResult->execute();
    $lecturesResult = $lecturesResult->get_result();
} else {
    $lecturesResult = $conn->query('SELECT id, title, description, drive_folder_id, display_order FROM lectures WHERE status = "active" ORDER BY display_order, id');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lectures - Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/student_nav.php'; ?>

    <div class="container py-4">
        <h1 class="mb-3">My Lectures</h1>
        <p class="text-muted">These lectures are available for your assigned group.</p>

        <?php if ($lecturesResult->num_rows > 0): ?>
            <div class="row g-3">
                <?php while ($lecture = $lecturesResult->fetch_assoc()): ?>
                    <div class="col-12 col-md-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($lecture['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($lecture['description'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php if (!empty($lecture['drive_folder_id'])): ?>
                                    <a href="https://drive.google.com/drive/folders/<?php echo htmlspecialchars($lecture['drive_folder_id'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-outline-primary btn-sm w-100 w-md-auto">Open Lecture Folder</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No lectures are available for your group yet.</div>
        <?php endif; ?>
    </div>
</body>
</html>
