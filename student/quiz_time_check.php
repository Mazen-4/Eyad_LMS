<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['student', 'admin']);
$conn = getDbConnection();

header('Content-Type: application/json');

$quizId = (int)($_GET['quiz_id'] ?? 0);
if ($quizId <= 0) {
    echo json_encode(['remaining_seconds' => 0]);
    exit;
}

$quizStmt = $conn->prepare('SELECT time_limit_minutes FROM quizzes WHERE id = ? AND status = "active" LIMIT 1');
$quizStmt->bind_param('i', $quizId);
$quizStmt->execute();
$quizRow = $quizStmt->get_result()->fetch_assoc();

if (!$quizRow) {
    echo json_encode(['remaining_seconds' => 0]);
    exit;
}

$timeLimitMinutes = (int)($quizRow['time_limit_minutes'] ?? 0);
if ($timeLimitMinutes <= 0) {
    echo json_encode(['remaining_seconds' => 0]);
    exit;
}

$attemptStmt = $conn->prepare(
    'SELECT started_at FROM quiz_attempts 
     WHERE student_id = ? AND quiz_id = ? AND status = "in_progress" 
     ORDER BY id DESC LIMIT 1'
);
$attemptStmt->bind_param('ii', $user['id'], $quizId);
$attemptStmt->execute();
$attempt = $attemptStmt->get_result()->fetch_assoc();

$startedAt = $attempt['started_at'] ?? '';
$startedAtTimestamp = $startedAt !== '' ? strtotime($startedAt) : false;

if ($startedAtTimestamp === false) {
    echo json_encode(['remaining_seconds' => 0]);
    exit;
}

$elapsedSeconds = max(0, time() - $startedAtTimestamp);
$remainingSeconds = max(0, ($timeLimitMinutes * 60) - $elapsedSeconds);

echo json_encode(['remaining_seconds' => $remainingSeconds]);
