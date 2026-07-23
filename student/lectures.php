<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['student', 'admin']);
$conn = getDbConnection();

$isAdmin = ($user['role'] === 'admin');
$studentGroupIds = array_values(array_unique(array_filter(array_map('intval', $user['group_ids'] ?? []))));
if (empty($studentGroupIds) && !empty($user['group_id'])) {
    $studentGroupIds = [(int)$user['group_id']];
}

function normalizeDriveLink($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('#/view(\?.*)?$#i', '/preview?rm=minimal', $value);
    $value = preg_replace('#/preview(\?.*)?$#i', '/preview?rm=minimal', $value);

    if (preg_match('#^https?://#i', $value)) {
        if (preg_match('#/folders/([^/?#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/drive/folders/' . $matches[1];
        }

        if (preg_match('#/file/d/([^/?#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/file/d/' . $matches[1] . '/preview?rm=minimal';
        }

        if (preg_match('#[?&]id=([^&#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/file/d/' . $matches[1] . '/preview?rm=minimal';
        }

        return $value;
    }

    if (preg_match('/^[a-zA-Z0-9\-_]+$/', $value)) {
        return 'https://drive.google.com/drive/folders/' . $value;
    }

    return $value;
}

if (!empty($studentGroupIds) && !$isAdmin) {
    $placeholders = implode(', ', array_fill(0, count($studentGroupIds), '?'));
    $lecturesStmt = $conn->prepare('SELECT l.id, l.title, l.description, l.drive_folder_id, l.display_order FROM lectures l INNER JOIN lecture_folder_access lfa ON lfa.lecture_id = l.id WHERE l.status = "active" AND lfa.group_id IN (' . $placeholders . ') ORDER BY l.display_order, l.id');
    bindPreparedParams($lecturesStmt, $studentGroupIds);
    $lecturesStmt->execute();
    $lecturesResult = $lecturesStmt->get_result();
} else {
    $lecturesResult = $conn->query('SELECT id, title, description, drive_folder_id, display_order FROM lectures WHERE status = "active" ORDER BY display_order, id');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sessions - Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css?v=2" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/student_nav.php'; ?>

    <div class="container py-4">
        <h1 class="mb-3">My Sessions</h1>
        <p class="text-muted">These sessions are available for your assigned group.</p>

        <?php if ($lecturesResult->num_rows > 0): ?>
            <div class="row g-3">
                <?php while ($lecture = $lecturesResult->fetch_assoc()): ?>
                    <div class="col-12 col-md-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($lecture['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($lecture['description'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php if (!empty($lecture['drive_folder_id'])): ?>
                                    <a href="lecture_player.php?lecture_id=<?php echo (int)$lecture['id']; ?>" class="btn btn-outline-primary btn-sm w-100 w-md-auto">Open Session</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No sessions are available for your group yet.</div>
        <?php endif; ?>
    </div>
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
