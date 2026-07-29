<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['admin']);
$conn = getDbConnection();

$deleteType = isset($_GET['delete_type']) ? $_GET['delete_type'] : '';
$deleteId = isset($_GET['delete_id']) ? (int)$_GET['delete_id'] : 0;
$deleteSuccess = '';
$deleteError = '';

$conn->query("CREATE TABLE IF NOT EXISTS lecture_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    lecture_id INT NOT NULL,
    watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY student_id (student_id),
    KEY lecture_id (lecture_id)
)");

if ($deleteType === 'lecture' && $deleteId > 0) {
    $deleteStmt = $conn->prepare('DELETE FROM lecture_views WHERE id = ?');
    $deleteStmt->bind_param('i', $deleteId);
    $deleteStmt->execute();
    $deleteSuccess = 'Lecture view entry deleted successfully.';
} elseif ($deleteType === 'quiz' && $deleteId > 0) {
    $deleteAnswersStmt = $conn->prepare('DELETE FROM quiz_attempt_answers WHERE attempt_id = ?');
    $deleteAnswersStmt->bind_param('i', $deleteId);
    $deleteAnswersStmt->execute();

    $deleteStmt = $conn->prepare('DELETE FROM quiz_attempts WHERE id = ?');
    $deleteStmt->bind_param('i', $deleteId);
    $deleteStmt->execute();
    $deleteSuccess = 'Quiz attempt entry deleted successfully.';
} elseif ($deleteType !== '' && $deleteId > 0) {
    $deleteError = 'Invalid activity record type.';
}

$activityQuery = "
SELECT
    'lecture' AS source_type,
    lv.id AS source_id,
    'Lecture Watched' AS action_type,
    u.name AS student_name,
    u.username AS student_username,
    l.title AS item_title,
    CONCAT('Lecture watched by the student.') AS details,
    lv.watched_at AS action_time
FROM lecture_views lv
INNER JOIN users u ON u.id = lv.student_id
INNER JOIN lectures l ON l.id = lv.lecture_id
UNION ALL
SELECT
    'quiz' AS source_type,
    qa.id AS source_id,
    'Quiz Taken' AS action_type,
    u.name AS student_name,
    u.username AS student_username,
    q.title AS item_title,
    CONCAT('Score: ', qa.score, '/', qa.total_questions, ' (', qa.score_percent, '%) — ', qa.status) AS details,
    qa.submitted_at AS action_time
FROM quiz_attempts qa
INNER JOIN users u ON u.id = qa.student_id
INNER JOIN quizzes q ON q.id = qa.quiz_id
WHERE qa.status != 'in_progress'
ORDER BY action_time DESC
LIMIT 200
";

$activityResult = $conn->query($activityQuery);

$lectureCountResult = $conn->query('SELECT COUNT(*) AS total FROM lecture_views');
$lectureCountRow = $lectureCountResult ? $lectureCountResult->fetch_assoc() : null;
$lectureCount = $lectureCountRow ? (int)$lectureCountRow['total'] : 0;
$quizCountResult = $conn->query('SELECT COUNT(*) AS total FROM quiz_attempts WHERE status != "in_progress"');
$quizCountRow = $quizCountResult ? $quizCountResult->fetch_assoc() : null;
$quizCount = $quizCountRow ? (int)$quizCountRow['total'] : 0;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activity Log - Admin</title>
    <link rel="icon" href="<?php echo htmlspecialchars(appAssetUrl('Images/eyad_logo1.jpeg?v=' . filemtime(__DIR__ . '/../Images/eyad_logo1.jpeg')), ENT_QUOTES, 'UTF-8'); ?>" type="image/jpeg">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars(appAssetUrl('Images/eyad_logo1.jpeg?v=' . filemtime(__DIR__ . '/../Images/eyad_logo1.jpeg')), ENT_QUOTES, 'UTF-8'); ?>" type="image/jpeg">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars(appAssetUrl('Images/eyad_logo1.jpeg?v=' . filemtime(__DIR__ . '/../Images/eyad_logo1.jpeg')), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" href="<?php echo htmlspecialchars(appAssetUrl('favicon.ico?v=' . filemtime(__DIR__ . '/../favicon.ico')), ENT_QUOTES, 'UTF-8'); ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(appVersionedAssetUrl('assets/css/theme.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/admin_nav.php'; ?>

    <div class="container py-4">
        <a href="dashboard.php" class="btn btn-outline-secondary mb-3">&larr; Back to Dashboard</a>
        <div class="d-flex align-items-start justify-content-between mb-3">
            <div>
                <h1 class="mb-1">Activity Log</h1>
                <p class="text-muted mb-0">Review recent student lecture views and quiz attempts.</p>
            </div>
        </div>

        <?php if (!empty($deleteSuccess)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($deleteSuccess, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php elseif (!empty($deleteError)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($deleteError, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Lecture Views</h5>
                        <p class="card-text display-6 mb-0"><?php echo htmlspecialchars((string)$lectureCount, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Quiz Attempts</h5>
                        <p class="card-text display-6 mb-0"><?php echo htmlspecialchars((string)$quizCount, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">Recent Actions</h5>
                <?php if ($activityResult && $activityResult->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th scope="col">Time</th>
                                    <th scope="col">Student</th>
                                    <th scope="col">Action</th>
                                    <th scope="col">Item</th>
                                    <th scope="col">Details</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $activityResult->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(date('d/m/Y h:i A', strtotime($row['action_time'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['student_name'] . ' (' . $row['student_username'] . ')', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['action_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['item_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['details'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-nowrap">
                                            <a href="activity_log.php?delete_type=<?php echo rawurlencode((string)($row['source_type'] ?? '')); ?>&delete_id=<?php echo (int)($row['source_id'] ?? 0); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this activity record?');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-secondary mb-0" role="alert">
                        No activity has been recorded yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
