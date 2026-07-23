<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['student', 'admin']);
$conn = getDbConnection();

$isAdmin = ($user['role'] === 'admin');
$attemptId = (int)($_GET['attempt_id'] ?? 0);
if ($attemptId <= 0) {
    header('Location: quizzes.php');
    exit;
}

if ($isAdmin) {
    $attemptStmt = $conn->prepare('SELECT qa.id, qa.quiz_id, qa.score, qa.total_questions, qa.score_percent, qa.submitted_at, qa.status, q.title AS quiz_title FROM quiz_attempts qa INNER JOIN quizzes q ON q.id = qa.quiz_id WHERE qa.id = ? AND qa.status != "in_progress" LIMIT 1');
    $attemptStmt->bind_param('i', $attemptId);
} else {
    $attemptStmt = $conn->prepare('SELECT qa.id, qa.quiz_id, qa.score, qa.total_questions, qa.score_percent, qa.submitted_at, qa.status, q.title AS quiz_title FROM quiz_attempts qa INNER JOIN quizzes q ON q.id = qa.quiz_id WHERE qa.id = ? AND qa.student_id = ? AND qa.status != "in_progress" LIMIT 1');
    $attemptStmt->bind_param('ii', $attemptId, $user['id']);
}
$attemptStmt->execute();
$attempt = $attemptStmt->get_result()->fetch_assoc();

if (!$attempt) {
    header('Location: quizzes.php');
    exit;
}

$questionStmt = $conn->prepare('SELECT q.id, q.question, q.image_path, q.choice_1, q.choice_2, q.choice_3, q.choice_4, q.correct_answer, a.selected_answer, a.is_correct FROM questions q LEFT JOIN quiz_attempt_answers a ON a.question_id = q.id AND a.attempt_id = ? WHERE q.quiz_id = ? ORDER BY q.id ASC');
$questionStmt->bind_param('ii', $attemptId, $attempt['quiz_id']);
$questionStmt->execute();
$questions = $questionStmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quiz Review - Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(appVersionedAssetUrl('assets/css/theme.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/student_nav.php'; ?>

    <div class="container py-4">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-3">
            <div>
                <h1 class="mb-1">Review Quiz: <?php echo htmlspecialchars($attempt['quiz_title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="text-muted mb-0">Learn from your attempt by reviewing each question, your answer, and the correct choice.</p>
            </div>
            <div class="text-end">
                <a class="btn btn-outline-secondary" href="quizzes.php">Back to Quizzes</a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Score</h5>
                        <p class="display-6 mb-0"><?php echo (int)$attempt['score']; ?>/<?php echo (int)$attempt['total_questions']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Percentage</h5>
                        <p class="display-6 mb-0"><?php echo number_format((float)$attempt['score_percent'], 2); ?>%</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Submitted</h5>
                        <p class="mb-0"><?php echo htmlspecialchars(formatDisplayDateTime($attempt['submitted_at']), ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="text-muted small mb-0"><?php echo htmlspecialchars(ucfirst($attempt['status']), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($questions->num_rows > 0): ?>
            <?php while ($question = $questions->fetch_assoc()): ?>
                <?php
                $selectedAnswer = trim($question['selected_answer'] ?? '');
                $correctAnswer = trim($question['correct_answer'] ?? '');
                $isCorrect = ($selectedAnswer !== '' && $selectedAnswer === $correctAnswer);
                $answerLabels = [
                    '1' => $question['choice_1'] ?? '',
                    '2' => $question['choice_2'] ?? '',
                    '3' => $question['choice_3'] ?? '',
                    '4' => $question['choice_4'] ?? '',
                ];
                $selectedLabel = $selectedAnswer !== '' ? ($answerLabels[$selectedAnswer] ?? '') : '';
                $correctLabel = $correctAnswer !== '' ? ($answerLabels[$correctAnswer] ?? '') : '';
                ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="card-title mb-0">Question <?php echo (int)$question['id']; ?></h5>
                            <span class="badge <?php echo $isCorrect ? 'bg-success' : 'bg-danger'; ?>"><?php echo $isCorrect ? 'Correct' : 'Incorrect'; ?></span>
                        </div>
                        <p class="mb-3 fw-semibold"><?php echo htmlspecialchars($question['question'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php if (!empty($question['image_path'])): ?>
                            <img src="../<?php echo htmlspecialchars($question['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Question image" class="img-fluid rounded mb-3" style="max-height: 320px;">
                        <?php endif; ?>
                        <div class="mb-3">
                            <p class="mb-1"><strong>Your answer:</strong> <?php echo $selectedLabel !== '' ? htmlspecialchars($selectedLabel, ENT_QUOTES, 'UTF-8') : '<span class="text-muted">No answer selected</span>'; ?></p>
                            <p class="mb-0"><strong>Correct answer:</strong> <?php echo $correctLabel !== '' ? htmlspecialchars($correctLabel, ENT_QUOTES, 'UTF-8') : '<span class="text-muted">Not available</span>'; ?></p>
                        </div>
                        <div class="list-group">
                            <?php foreach ($answerLabels as $key => $label): ?>
                                <?php
                                $isUserChoice = ($selectedAnswer !== '' && $selectedAnswer === $key);
                                $isCorrectChoice = ($correctAnswer !== '' && $correctAnswer === $key);
                                $itemClass = 'list-group-item';
                                if ($isCorrectChoice) {
                                    $itemClass .= ' list-group-item-success';
                                } elseif ($isUserChoice && !$isCorrectChoice) {
                                    $itemClass .= ' list-group-item-danger';
                                }
                                ?>
                                <div class="<?php echo htmlspecialchars($itemClass, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <?php if ($isUserChoice): ?>
                                                <div class="text-muted small">Your answer</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <?php if ($isCorrectChoice && $isUserChoice): ?>
                                                <span class="badge bg-success">Your correct answer</span>
                                            <?php elseif ($isCorrectChoice): ?>
                                                <span class="badge bg-success">Correct answer</span>
                                            <?php elseif ($isUserChoice): ?>
                                                <span class="badge bg-danger">Your answer</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="alert alert-secondary">No question details are available for this attempt.</div>
        <?php endif; ?>
    </div>
</body>
</html>
