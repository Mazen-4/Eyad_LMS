<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['student']);
$conn = getDbConnection();

$quizAttemptsTableResult = $conn->query("SHOW TABLES LIKE 'quiz_attempts'");
if ($quizAttemptsTableResult && $quizAttemptsTableResult->num_rows === 0) {
    $conn->query("CREATE TABLE quiz_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        quiz_id INT NOT NULL,
        score INT NOT NULL DEFAULT 0,
        total_questions INT NOT NULL DEFAULT 0,
        score_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        submitted_at TIMESTAMP NULL DEFAULT NULL,
        started_at TIMESTAMP NULL DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'submitted',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY student_id (student_id),
        KEY quiz_id (quiz_id)
    )");
} else {
    $quizAttemptStartedAtResult = $conn->query("SHOW COLUMNS FROM quiz_attempts LIKE 'started_at'");
    if ($quizAttemptStartedAtResult && $quizAttemptStartedAtResult->num_rows === 0) {
        $conn->query('ALTER TABLE quiz_attempts ADD COLUMN started_at TIMESTAMP NULL DEFAULT NULL');
    }

    $quizAttemptStatusResult = $conn->query("SHOW COLUMNS FROM quiz_attempts LIKE 'status'");
    if ($quizAttemptStatusResult && $quizAttemptStatusResult->num_rows === 0) {
        $conn->query("ALTER TABLE quiz_attempts ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'submitted'");
    }

    $quizAttemptTotalQuestionsResult = $conn->query("SHOW COLUMNS FROM quiz_attempts LIKE 'total_questions'");
    if ($quizAttemptTotalQuestionsResult && $quizAttemptTotalQuestionsResult->num_rows === 0) {
        $conn->query('ALTER TABLE quiz_attempts ADD COLUMN total_questions INT NOT NULL DEFAULT 0');
    }

    $quizAttemptScorePercentResult = $conn->query("SHOW COLUMNS FROM quiz_attempts LIKE 'score_percent'");
    if ($quizAttemptScorePercentResult && $quizAttemptScorePercentResult->num_rows === 0) {
        $conn->query('ALTER TABLE quiz_attempts ADD COLUMN score_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00');
    }
}

$studentGroupIds = array_values(array_unique(array_filter(array_map('intval', $user['group_ids'] ?? []))));
if (empty($studentGroupIds) && !empty($user['group_id'])) {
    $studentGroupIds = [(int)$user['group_id']];
}
$success = '';
$error = '';

function expireTimedOutAttempts($conn, $studentId, $quizId = null) {
    if ($quizId !== null) {
        $stmt = $conn->prepare('SELECT qa.id FROM quiz_attempts qa INNER JOIN quizzes q ON q.id = qa.quiz_id WHERE qa.student_id = ? AND qa.quiz_id = ? AND qa.status = "in_progress" AND q.time_limit_minutes > 0 AND qa.started_at IS NOT NULL AND TIMESTAMPADD(MINUTE, q.time_limit_minutes, qa.started_at) <= NOW()');
        $stmt->bind_param('ii', $studentId, $quizId);
    } else {
        $stmt = $conn->prepare('SELECT qa.id FROM quiz_attempts qa INNER JOIN quizzes q ON q.id = qa.quiz_id WHERE qa.student_id = ? AND qa.status = "in_progress" AND q.time_limit_minutes > 0 AND qa.started_at IS NOT NULL AND TIMESTAMPADD(MINUTE, q.time_limit_minutes, qa.started_at) <= NOW()');
        $stmt->bind_param('i', $studentId);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $expiredAttemptIds = [];
    while ($row = $result->fetch_assoc()) {
        $expiredAttemptIds[] = (int)($row['id'] ?? 0);
    }

    if (empty($expiredAttemptIds)) {
        return;
    }

    $updateStmt = $conn->prepare('UPDATE quiz_attempts SET status = "expired", score = 0, total_questions = 0, score_percent = 0.00, submitted_at = NOW() WHERE id = ?');
    foreach ($expiredAttemptIds as $attemptId) {
        $updateStmt->bind_param('i', $attemptId);
        $updateStmt->execute();
    }
}

expireTimedOutAttempts($conn, $user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'submit_quiz';
    $quizId = (int)($_POST['quiz_id'] ?? 0);
    $answers = $_POST['answer'] ?? [];
    $startedAt = $_POST['started_at'] ?? '';

    if ($action === 'expire_attempt') {
        $expiredAttemptStmt = $conn->prepare('SELECT id FROM quiz_attempts WHERE student_id = ? AND quiz_id = ? AND status = "in_progress" ORDER BY id DESC LIMIT 1');
        $expiredAttemptStmt->bind_param('ii', $user['id'], $quizId);
        $expiredAttemptStmt->execute();
        $expiredAttempt = $expiredAttemptStmt->get_result()->fetch_assoc();

        if ($expiredAttempt) {
            $markExpiredStmt = $conn->prepare('UPDATE quiz_attempts SET status = "expired", score = 0, total_questions = 0, score_percent = 0.00, submitted_at = NOW() WHERE id = ?');
            $markExpiredStmt->bind_param('i', $expiredAttempt['id']);
            $markExpiredStmt->execute();
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Quiz attempt marked as expired.']);
        exit;
    }

    if ($action === 'start_attempt') {
        if ($quizId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid quiz.']);
            exit;
        }

        if (!empty($studentGroupIds)) {
            $placeholders = implode(', ', array_fill(0, count($studentGroupIds), '?'));
            $quizCheck = $conn->prepare('SELECT id, time_limit_minutes, max_attempts FROM quizzes WHERE id = ? AND group_id IN (' . $placeholders . ') AND status = "active" LIMIT 1');
            $params = array_merge([$quizId], $studentGroupIds);
            bindPreparedParams($quizCheck, $params);
            $quizCheck->execute();
            $quiz = $quizCheck->get_result()->fetch_assoc();
        } else {
            $quiz = null;
        }

        if (!$quiz) {
            if (!empty($studentGroupIds)) {
                $placeholders = implode(', ', array_fill(0, count($studentGroupIds), '?'));
                $quizCheck = $conn->prepare('SELECT q.id, q.time_limit_minutes, q.max_attempts FROM quizzes q LEFT JOIN quiz_group_access qga ON qga.quiz_id = q.id WHERE q.id = ? AND q.status = "active" AND (q.group_id IN (' . $placeholders . ') OR qga.group_id IN (' . $placeholders . ')) LIMIT 1');
                $params = array_merge([$quizId], $studentGroupIds, $studentGroupIds);
                bindPreparedParams($quizCheck, $params);
                $quizCheck->execute();
                $quiz = $quizCheck->get_result()->fetch_assoc();
            }
        }

        if (!$quiz) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'This quiz is not available for your group.']);
            exit;
        }

        $maxAttempts = (int)($quiz['max_attempts'] ?? 0);
        $attemptCountStmt = $conn->prepare('SELECT COUNT(*) AS attempts FROM quiz_attempts WHERE student_id = ? AND quiz_id = ? AND status != "in_progress"');
        $attemptCountStmt->bind_param('ii', $user['id'], $quizId);
        $attemptCountStmt->execute();
        $attemptCount = $attemptCountStmt->get_result()->fetch_assoc();
        $attemptsSoFar = (int)($attemptCount['attempts'] ?? 0);

        $extraAttemptsStmt = $conn->prepare('SELECT extra_attempts FROM quiz_extra_attempts WHERE quiz_id = ? AND student_id = ? LIMIT 1');
        $extraAttemptsStmt->bind_param('ii', $quizId, $user['id']);
        $extraAttemptsStmt->execute();
        $extraAttemptsRow = $extraAttemptsStmt->get_result()->fetch_assoc();
        $extraAttemptsAvailable = (int)($extraAttemptsRow['extra_attempts'] ?? 0);
        $availableAttemptLimit = $maxAttempts + $extraAttemptsAvailable;

        if ($availableAttemptLimit > 0 && $attemptsSoFar >= $availableAttemptLimit) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'You have reached the maximum number of attempts for this quiz.']);
            exit;
        }

        expireTimedOutAttempts($conn, $user['id'], $quizId);

        $activeAttemptStmt = $conn->prepare('SELECT id, quiz_id, started_at FROM quiz_attempts WHERE student_id = ? AND status = "in_progress" ORDER BY id DESC LIMIT 1');
        $activeAttemptStmt->bind_param('i', $user['id']);
        $activeAttemptStmt->execute();
        $activeAttempt = $activeAttemptStmt->get_result()->fetch_assoc();

        if ($activeAttempt) {
            if ((int)($activeAttempt['quiz_id'] ?? 0) === $quizId) {
                $resumeStartedAt = $activeAttempt['started_at'] ?? '';
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Continuing your existing attempt.', 'started_at' => $resumeStartedAt]);
                exit;
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'You already have another quiz in progress. Finish it before starting a new one.']);
            exit;
        }

        $startedAtValue = $startedAt !== '' ? $startedAt : date('Y-m-d H:i:s');
        $startAttemptStmt = $conn->prepare('INSERT INTO quiz_attempts (student_id, quiz_id, score, submitted_at, started_at, status) VALUES (?, ?, 0, NULL, ?, "in_progress")');
        $startAttemptStmt->bind_param('iis', $user['id'], $quizId, $startedAtValue);
        $startAttemptStmt->execute();

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Attempt started.', 'started_at' => $startedAtValue]);
        exit;
    }

    if ($quizId <= 0) {
        $error = 'Invalid quiz.';
    } else {
        if (!empty($studentGroupIds)) {
            $placeholders = implode(', ', array_fill(0, count($studentGroupIds), '?'));
            $quizCheck = $conn->prepare('SELECT id, time_limit_minutes, max_attempts FROM quizzes WHERE id = ? AND group_id IN (' . $placeholders . ') AND status = "active" LIMIT 1');
            $params = array_merge([$quizId], $studentGroupIds);
            bindPreparedParams($quizCheck, $params);
            $quizCheck->execute();
            $quiz = $quizCheck->get_result()->fetch_assoc();
        } else {
            $quiz = null;
        }

        if (!$quiz) {
            if (!empty($studentGroupIds)) {
                $placeholders = implode(', ', array_fill(0, count($studentGroupIds), '?'));
                $quizCheck = $conn->prepare('SELECT q.id, q.time_limit_minutes, q.max_attempts FROM quizzes q LEFT JOIN quiz_group_access qga ON qga.quiz_id = q.id WHERE q.id = ? AND q.status = "active" AND (q.group_id IN (' . $placeholders . ') OR qga.group_id IN (' . $placeholders . ')) LIMIT 1');
                $params = array_merge([$quizId], $studentGroupIds, $studentGroupIds);
                bindPreparedParams($quizCheck, $params);
                $quizCheck->execute();
                $quiz = $quizCheck->get_result()->fetch_assoc();
            }
        }

        if (!$quiz) {
            $error = 'This quiz is not available for your group.';
        } else {
            $maxAttempts = (int)($quiz['max_attempts'] ?? 0);
            $attemptCountStmt = $conn->prepare('SELECT COUNT(*) AS attempts FROM quiz_attempts WHERE student_id = ? AND quiz_id = ? AND status != "in_progress"');
            $attemptCountStmt->bind_param('ii', $user['id'], $quizId);
            $attemptCountStmt->execute();
            $attemptCount = $attemptCountStmt->get_result()->fetch_assoc();
            $attemptsSoFar = (int)($attemptCount['attempts'] ?? 0);

            $extraAttemptsStmt = $conn->prepare('SELECT extra_attempts FROM quiz_extra_attempts WHERE quiz_id = ? AND student_id = ? LIMIT 1');
            $extraAttemptsStmt->bind_param('ii', $quizId, $user['id']);
            $extraAttemptsStmt->execute();
            $extraAttemptsRow = $extraAttemptsStmt->get_result()->fetch_assoc();
            $extraAttemptsAvailable = (int)($extraAttemptsRow['extra_attempts'] ?? 0);
            $availableAttemptLimit = $maxAttempts + $extraAttemptsAvailable;

            if ($availableAttemptLimit > 0 && $attemptsSoFar >= $availableAttemptLimit) {
                $error = 'You have reached the maximum number of attempts for this quiz.';
            } else {
                $timeLimitMinutes = (int)($quiz['time_limit_minutes'] ?? 0);
                if ($timeLimitMinutes > 0 && $startedAt !== '') {
                    $startedAtTimestamp = strtotime($startedAt);
                    $nowTimestamp = time();
                    if ($startedAtTimestamp !== false && ($nowTimestamp - $startedAtTimestamp) > ($timeLimitMinutes * 60)) {
                        $error = 'The time limit for this quiz has expired.';
                    }
                }

                if ($error === '') {
                    $timeLimitMinutes = (int)($quiz['time_limit_minutes'] ?? 0);
                    if ($timeLimitMinutes > 0 && $startedAt === '') {
                        $error = 'Please start the quiz before submitting your answers.';
                    }
                }

                if ($error === '') {
                    $questionsResult = $conn->prepare('SELECT id, question, image_path, choice_1, choice_2, choice_3, choice_4, correct_answer FROM questions WHERE quiz_id = ?');
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

                    $scorePercent = $total > 0 ? round(($score / $total) * 100, 2) : 0.00;

                    $activeAttemptStmt = $conn->prepare('SELECT id FROM quiz_attempts WHERE student_id = ? AND quiz_id = ? AND status = "in_progress" ORDER BY id DESC LIMIT 1');
                    $activeAttemptStmt->bind_param('ii', $user['id'], $quizId);
                    $activeAttemptStmt->execute();
                    $activeAttempt = $activeAttemptStmt->get_result()->fetch_assoc();

                    if ($activeAttempt) {
                        $updateAttempt = $conn->prepare('UPDATE quiz_attempts SET score = ?, total_questions = ?, score_percent = ?, submitted_at = NOW(), started_at = ?, status = "submitted" WHERE id = ?');
                        $updateAttempt->bind_param('iidsi', $score, $total, $scorePercent, $startedAt, $activeAttempt['id']);
                        $updateAttempt->execute();
                    } else {
                        $insertAttempt = $conn->prepare('INSERT INTO quiz_attempts (student_id, quiz_id, score, total_questions, score_percent, submitted_at, started_at, status) VALUES (?, ?, ?, ?, ?, NOW(), ?, "submitted")');
                        $insertAttempt->bind_param('iiiids', $user['id'], $quizId, $score, $total, $scorePercent, $startedAt);
                        $insertAttempt->execute();
                    }

                    $success = 'Quiz submitted successfully. Your score: ' . $score . '/' . $total . ' (' . number_format($scorePercent, 2) . '%)';
                }
            }
        }
    }
}

$activeQuizAttempt = null;
if (!empty($studentGroupIds)) {
    $placeholders = implode(', ', array_fill(0, count($studentGroupIds), '?'));
    $quizzesStmt = $conn->prepare('SELECT DISTINCT q.id, q.title, q.time_limit_minutes, q.max_attempts FROM quizzes q LEFT JOIN quiz_group_access qga ON qga.quiz_id = q.id WHERE q.status = "active" AND (q.group_id IN (' . $placeholders . ') OR qga.group_id IN (' . $placeholders . ')) ORDER BY q.created_at DESC');
    $params = array_merge($studentGroupIds, $studentGroupIds);
    bindPreparedParams($quizzesStmt, $params);
    $quizzesStmt->execute();
    $quizzesResult = $quizzesStmt->get_result();

    $activeAttemptStmt = $conn->prepare('SELECT quiz_id FROM quiz_attempts WHERE student_id = ? AND status = "in_progress" LIMIT 1');
    $activeAttemptStmt->bind_param('i', $user['id']);
    $activeAttemptStmt->execute();
    $activeQuizAttempt = $activeAttemptStmt->get_result()->fetch_assoc();
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
    <link href="../assets/css/theme.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/student_nav.php'; ?>

    <div class="container py-4">
        <h1 class="mb-3">My Quizzes</h1>
        <p class="text-muted">These quizzes are available for your assigned group.</p>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($quizzesResult) && $quizzesResult->num_rows > 0): ?>
            <?php
            $quizRows = [];
            while ($quizRow = $quizzesResult->fetch_assoc()) {
                $quizRows[] = $quizRow;
            }
            $quizRows = array_values($quizRows);
            $attemptedQuizIds = [];
            if (!empty($quizRows)) {
                $attemptedQuizIdsStmt = $conn->prepare('SELECT DISTINCT quiz_id FROM quiz_attempts WHERE student_id = ? AND status != "in_progress"');
                $attemptedQuizIdsStmt->bind_param('i', $user['id']);
                $attemptedQuizIdsStmt->execute();
                $attemptedQuizIdsResult = $attemptedQuizIdsStmt->get_result();
                while ($attemptedQuiz = $attemptedQuizIdsResult->fetch_assoc()) {
                    $attemptedQuizIds[(int)$attemptedQuiz['quiz_id']] = true;
                }
            }
            usort($quizRows, function ($a, $b) use ($attemptedQuizIds) {
                $aAttempted = isset($attemptedQuizIds[(int)($a['id'] ?? 0)]);
                $bAttempted = isset($attemptedQuizIds[(int)($b['id'] ?? 0)]);
                if ($aAttempted === $bAttempted) {
                    return 0;
                }
                return $aAttempted ? 1 : -1;
            });
            foreach ($quizRows as $quiz):
            ?>
                <div class="card shadow-sm mb-4 <?php echo isset($attemptedQuizIds[(int)($quiz['id'] ?? 0)]) ? '' : 'border-danger'; ?>">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                            <?php if (!isset($attemptedQuizIds[(int)($quiz['id'] ?? 0)])): ?>
                                <span class="badge bg-danger">Not attempted</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted small mb-3">
                            <?php
                            $timeLimitLabel = ((int)($quiz['time_limit_minutes'] ?? 0) > 0) ? ((int)$quiz['time_limit_minutes'] . ' minute' . (((int)$quiz['time_limit_minutes'] > 1) ? 's' : '')) : 'No time limit';
                            $attemptLabel = ((int)($quiz['max_attempts'] ?? 0) > 0) ? ((int)$quiz['max_attempts'] . ' attempt' . (((int)$quiz['max_attempts'] > 1) ? 's' : '')) : 'Unlimited attempts';
                            echo htmlspecialchars('Time limit: ' . $timeLimitLabel . ' | Max attempts: ' . $attemptLabel, ENT_QUOTES, 'UTF-8');
                            ?>
                        </p>
                        <?php
                        $quizAttemptStateStmt = $conn->prepare('SELECT started_at, status FROM quiz_attempts WHERE student_id = ? AND quiz_id = ? ORDER BY id DESC LIMIT 1');
                        $quizAttemptStateStmt->bind_param('ii', $user['id'], $quiz['id']);
                        $quizAttemptStateStmt->execute();
                        $quizAttemptState = $quizAttemptStateStmt->get_result()->fetch_assoc();
                        $quizActiveStartedAt = $quizAttemptState['started_at'] ?? '';
                        $quizActiveStatus = $quizAttemptState['status'] ?? '';
                        ?>
                        <form method="post" class="mt-3 quiz-form" data-time-limit-minutes="<?php echo (int)($quiz['time_limit_minutes'] ?? 0); ?>" data-active-started-at="<?php echo htmlspecialchars($quizActiveStartedAt, ENT_QUOTES, 'UTF-8'); ?>" data-has-active-attempt="<?php echo ($quizActiveStatus === 'in_progress') ? '1' : '0'; ?>">
                            <input type="hidden" name="quiz_id" value="<?php echo (int)$quiz['id']; ?>">
                            <input type="hidden" name="started_at" class="started-at-input" value="<?php echo htmlspecialchars($quizActiveStartedAt, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                                <button type="button" class="btn btn-primary start-quiz-btn" data-quiz-id="<?php echo (int)$quiz['id']; ?>" <?php echo (!empty($activeQuizAttempt) && ((int)($activeQuizAttempt['quiz_id'] ?? 0) !== (int)$quiz['id'])) ? 'disabled' : ''; ?>><?php echo (!empty($activeQuizAttempt) && ((int)($activeQuizAttempt['quiz_id'] ?? 0) === (int)$quiz['id'])) ? 'Continue Attempt' : 'Start Attempt'; ?></button>
                                <button type="button" class="btn btn-outline-secondary toggle-attempts-btn">View Attempts</button>
                                <span class="quiz-timer text-danger fw-bold d-none"></span>
                            </div>
                            <?php if (!empty($activeQuizAttempt) && ((int)($activeQuizAttempt['quiz_id'] ?? 0) !== (int)$quiz['id'])): ?>
                                <p class="text-muted small mb-3">Finish your current quiz before starting another one.</p>
                            <?php endif; ?>
                            <div class="quiz-questions d-none">
                                <?php
                                $questionsStmt = $conn->prepare('SELECT id, question, image_path, choice_1, choice_2, choice_3, choice_4 FROM questions WHERE quiz_id = ?');
                                $questionsStmt->bind_param('i', $quiz['id']);
                                $questionsStmt->execute();
                                $questions = $questionsStmt->get_result();
                                ?>
                                <?php if ($questions->num_rows > 0): ?>
                                    <?php while ($question = $questions->fetch_assoc()): ?>
                                        <div class="border rounded p-3 mb-3">
                                            <p class="fw-bold mb-3"><?php echo htmlspecialchars($question['question'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <?php if (!empty($question['image_path'])): ?>
                                                <img src="../<?php echo htmlspecialchars($question['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Question image" class="img-fluid rounded mb-3" style="max-height: 260px;">
                                            <?php endif; ?>
                                            <div class="row g-2">
                                                <?php foreach (['choice_1' => 'Choice 1', 'choice_2' => 'Choice 2', 'choice_3' => 'Choice 3', 'choice_4' => 'Choice 4'] as $field => $label): ?>
                                                    <div class="col-12 col-md-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="answer[<?php echo (int)$question['id']; ?>]" value="<?php echo htmlspecialchars(substr($field, -1), ENT_QUOTES, 'UTF-8'); ?>" id="q<?php echo (int)$question['id']; ?>_<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <label class="form-check-label" for="q<?php echo (int)$question['id']; ?>_<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>">
                                                                <?php echo htmlspecialchars($question[$field] ?? '', ENT_QUOTES, 'UTF-8'); ?>
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
                            </div>

                            <?php
                            $attemptsStmt = $conn->prepare('SELECT score, total_questions, score_percent, submitted_at, status FROM quiz_attempts WHERE student_id = ? AND quiz_id = ? AND status != "in_progress" ORDER BY submitted_at DESC, id DESC');
                            $attemptsStmt->bind_param('ii', $user['id'], $quiz['id']);
                            $attemptsStmt->execute();
                            $attemptsResult = $attemptsStmt->get_result();
                            ?>
                            <?php if ($attemptsResult->num_rows > 0): ?>
                                <div class="mt-3 attempts-history d-none">
                                    <h6 class="mb-2">Your previous attempts</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Attempt</th>
                                                    <th>Score</th>
                                                    <th>Percent</th>
                                                    <th>Status</th>
                                                    <th>Submitted</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $attemptNumber = 1; ?>
                                                <?php while ($attempt = $attemptsResult->fetch_assoc()): ?>
                                                    <tr>
                                                        <td>#<?php echo $attemptNumber++; ?></td>
                                                        <td><?php echo (int)($attempt['score'] ?? 0) . '/' . (int)($attempt['total_questions'] ?? 0); ?></td>
                                                        <td><?php echo number_format((float)($attempt['score_percent'] ?? 0), 2) . '%'; ?></td>
                                                        <td><?php echo htmlspecialchars($attempt['status'] ?? 'submitted', ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars(!empty($attempt['submitted_at']) ? formatDisplayDateTime($attempt['submitted_at']) : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="mt-3 attempts-history d-none">
                                    <p class="text-muted mb-0">No attempts yet.</p>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">No quizzes are available for your group yet.</div>
        <?php endif; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('form.quiz-form').forEach(function (form) {
                const startedAtInput = form.querySelector('input.started-at-input');
                const startButton = form.querySelector('.start-quiz-btn');
                const toggleAttemptsButton = form.querySelector('.toggle-attempts-btn');
                const questionContainer = form.querySelector('.quiz-questions');
                const timerLabel = form.querySelector('.quiz-timer');
                const attemptsHistory = form.querySelector('.attempts-history');
                const timeLimitMinutes = parseInt(form.getAttribute('data-time-limit-minutes') || '0', 10);
                const activeStartedAt = form.getAttribute('data-active-started-at') || '';
                const hasActiveAttempt = form.getAttribute('data-has-active-attempt') === '1';

                if (!startedAtInput || !startButton || !questionContainer || !timerLabel) {
                    return;
                }

                if (toggleAttemptsButton && attemptsHistory) {
                    toggleAttemptsButton.addEventListener('click', function () {
                        attemptsHistory.classList.toggle('d-none');
                        toggleAttemptsButton.textContent = attemptsHistory.classList.contains('d-none') ? 'View Attempts' : 'Hide Attempts';
                    });
                }

                function startTimer(startedAtValue, showQuestions) {
                    if (timeLimitMinutes <= 0) {
                        if (showQuestions) {
                            questionContainer.classList.remove('d-none');
                        }
                        timerLabel.textContent = 'No time limit';
                        timerLabel.classList.remove('d-none');
                        return;
                    }

                    const quizId = startButton.getAttribute('data-quiz-id') || '';
                    const storageKey = 'quiz_timer_' + quizId;
                    const storedState = window.sessionStorage.getItem(storageKey);
                    let startDate = new Date();

                    function parseStartedAtToDate(value) {
                        const normalizedStartedAt = String(value || '').trim();
                        if (!normalizedStartedAt) {
                            return null;
                        }

                        const asNumber = Number(normalizedStartedAt);
                        if (!Number.isNaN(asNumber)) {
                            return new Date(asNumber);
                        }

                        const withT = normalizedStartedAt.replace(' ', 'T');
                        const parsedStartedAt = new Date(withT.includes('T') ? withT : withT + 'T00:00:00');
                        return Number.isNaN(parsedStartedAt.getTime()) ? null : parsedStartedAt;
                    }

                    const explicitStartDate = parseStartedAtToDate(startedAtValue);
                    const now = Date.now();
                    if (explicitStartDate) {
                        const diffMs = now - explicitStartDate.getTime();
                        if (Math.abs(diffMs) < 5 * 60 * 1000) {
                            startDate = explicitStartDate;
                        }
                    } else if (storedState) {
                        try {
                            const parsedState = JSON.parse(storedState);
                            if (parsedState && parsedState.startedAt) {
                                startDate = new Date(parseInt(parsedState.startedAt, 10));
                            }
                        } catch (e) {
                            // ignore invalid stored state
                        }
                    }

                    if (showQuestions) {
                        questionContainer.classList.remove('d-none');
                    }
                    timerLabel.classList.remove('d-none');
                    const deadline = new Date(startDate.getTime() + (timeLimitMinutes * 60000));

                    if (form._timerHandle) {
                        window.clearTimeout(form._timerHandle);
                    }

                    const persistStart = function (startTime) {
                        window.sessionStorage.setItem(storageKey, JSON.stringify({
                            startedAt: startTime,
                            limitMinutes: timeLimitMinutes
                        }));
                    };

                    persistStart(startDate.getTime());

                    const updateTimer = function () {
                        const remainingMs = deadline.getTime() - Date.now();
                        if (remainingMs <= 0) {
                            timerLabel.textContent = 'Time is up';
                            const allInputs = questionContainer.querySelectorAll('input, button');
                            allInputs.forEach(function (element) {
                                if (element.type === 'submit' || element.type === 'radio' || element.tagName === 'BUTTON') {
                                    element.disabled = true;
                                }
                            });

                            const expiredFormData = new FormData();
                            expiredFormData.append('action', 'expire_attempt');
                            expiredFormData.append('quiz_id', quizId);
                            fetch(window.location.href, {
                                method: 'POST',
                                body: expiredFormData
                            }).then(function () {
                                document.querySelectorAll('.start-quiz-btn').forEach(function (button) {
                                    button.disabled = false;
                                    button.textContent = 'Start Attempt';
                                });
                                window.sessionStorage.removeItem(storageKey);
                            });
                            return;
                        }

                        const minutes = Math.floor(remainingMs / 60000);
                        const seconds = Math.floor((remainingMs % 60000) / 1000);
                        timerLabel.textContent = 'Time remaining: ' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
                        form._timerHandle = window.setTimeout(updateTimer, 1000);
                    };

                    updateTimer();
                }

                if (hasActiveAttempt && activeStartedAt) {
                    startedAtInput.value = activeStartedAt;
                    startTimer(activeStartedAt, true);
                    startButton.disabled = true;
                    startButton.textContent = 'Continue Attempt';
                }

                startButton.addEventListener('click', function () {
                    const now = new Date();
                    const startedAtValue = now.toISOString().slice(0, 19).replace('T', ' ');
                    startedAtInput.value = startedAtValue;

                    const formData = new FormData();
                    formData.append('action', 'start_attempt');
                    formData.append('quiz_id', startButton.getAttribute('data-quiz-id') || '');
                    formData.append('started_at', startedAtValue);

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (result) {
                        if (!result.success) {
                            alert(result.message || 'Unable to start this quiz.');
                            return;
                        }

                        questionContainer.classList.remove('d-none');
                        timerLabel.classList.remove('d-none');
                        startButton.disabled = true;
                        startButton.textContent = 'Continue Attempt';

                        document.querySelectorAll('.start-quiz-btn').forEach(function (button) {
                            if (button !== startButton) {
                                button.disabled = true;
                            }
                        });

                        startTimer(result.started_at || startedAtValue, true);
                    })
                    .catch(function () {
                        alert('Unable to start this quiz right now.');
                    });
                });
            });
        });
    </script>
</body>
</html>
