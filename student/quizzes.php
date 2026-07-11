<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['student']);
$conn = getDbConnection();

$studentGroupId = (int)($user['group_id'] ?? 0);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quizId = (int)($_POST['quiz_id'] ?? 0);
    $answers = $_POST['answer'] ?? [];

    if ($quizId <= 0) {
        $error = 'Invalid quiz.';
    } else {
        $quizCheck = $conn->prepare('SELECT id FROM quizzes WHERE id = ? AND group_id = ? AND status = "active" LIMIT 1');
        $quizCheck->bind_param('ii', $quizId, $studentGroupId);
        $quizCheck->execute();
        $quizResult = $quizCheck->get_result();

        if ($quizResult->num_rows === 0) {
            $error = 'This quiz is not available for your group.';
        } else {
            $questionsResult = $conn->prepare('SELECT id, question, choice_1, choice_2, choice_3, choice_4, correct_answer FROM questions WHERE quiz_id = ?');
            $questionsResult->bind_param('i', $quizId);
            $questionsResult->execute();
            $questions = $questionsResult->get_result();

            $score = 0;
            $total = 0;
            while ($question = $questions->fetch_assoc()) {
                $total++;
                $selected = trim($answers[$question['id']] ?? '');
                if ($selected !== '' && $selected === $question['correct_answer']) {
                    $score++;
                }
            }

            $insertAttempt = $conn->prepare('INSERT INTO quiz_attempts (student_id, quiz_id, score) VALUES (?, ?, ?)');
            $insertAttempt->bind_param('iii', $user['id'], $quizId, $score);
            $insertAttempt->execute();

            $success = 'Quiz submitted successfully. Your score: ' . $score . '/' . $total;
        }
    }
}

if ($studentGroupId > 0) {
    $quizzesStmt = $conn->prepare('SELECT id, title FROM quizzes WHERE status = "active" AND group_id = ? ORDER BY created_at DESC');
    $quizzesStmt->bind_param('i', $studentGroupId);
    $quizzesStmt->execute();
    $quizzesResult = $quizzesStmt->get_result();
} else {
    $quizzesResult = null;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quizzes - Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/student_nav.php'; ?>

    <div class="container py-4">
        <h1 class="mb-3">My Quizzes</h1>
        <p class="text-muted">These quizzes are available for your assigned group.</p>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (!empty($quizzesResult) && $quizzesResult->num_rows > 0): ?>
            <?php while ($quiz = $quizzesResult->fetch_assoc()): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                        <form method="post" class="mt-3">
                            <input type="hidden" name="quiz_id" value="<?php echo (int)$quiz['id']; ?>">
                            <?php
                            $questionsStmt = $conn->prepare('SELECT id, question, choice_1, choice_2, choice_3, choice_4 FROM questions WHERE quiz_id = ?');
                            $questionsStmt->bind_param('i', $quiz['id']);
                            $questionsStmt->execute();
                            $questions = $questionsStmt->get_result();
                            ?>
                            <?php if ($questions->num_rows > 0): ?>
                                <?php while ($question = $questions->fetch_assoc()): ?>
                                    <div class="border rounded p-3 mb-3">
                                        <p class="fw-bold mb-3"><?php echo htmlspecialchars($question['question'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <div class="row g-2">
                                            <?php foreach (['choice_1' => 'Choice 1', 'choice_2' => 'Choice 2', 'choice_3' => 'Choice 3', 'choice_4' => 'Choice 4'] as $field => $label): ?>
                                                <div class="col-12 col-md-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="answer[<?php echo (int)$question['id']; ?>]" value="<?php echo htmlspecialchars(substr($field, -1), ENT_QUOTES, 'UTF-8'); ?>" id="q<?php echo (int)$question['id']; ?>_<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <label class="form-check-label" for="q<?php echo (int)$question['id']; ?>_<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars($question[$field] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-muted">No questions found for this quiz yet.</p>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary">Submit Quiz</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="alert alert-info">No quizzes are available for your group yet.</div>
        <?php endif; ?>
    </div>
</body>
</html>
