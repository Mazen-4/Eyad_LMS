<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['student']);
$conn = getDbConnection();

$studentGroupId = (int)($user['group_id'] ?? 0);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'submit_quiz';
    $quizId = (int)($_POST['quiz_id'] ?? 0);
    $answers = $_POST['answer'] ?? [];
    $startedAt = $_POST['started_at'] ?? '';

    if ($action === 'start_attempt') {
        if ($quizId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid quiz.']);
            exit;
        }

        $quizCheck = $conn->prepare('SELECT id, time_limit_minutes, max_attempts FROM quizzes WHERE id = ? AND group_id = ? AND status = "active" LIMIT 1');
        $quizCheck->bind_param('ii', $quizId, $studentGroupId);
        $quizCheck->execute();
        $quiz = $quizCheck->get_result()->fetch_assoc();

        if (!$quiz) {
            $quizCheck = $conn->prepare('SELECT q.id, q.time_limit_minutes, q.max_attempts FROM quizzes q LEFT JOIN quiz_group_access qga ON qga.quiz_id = q.id WHERE q.id = ? AND q.status = "active" AND (q.group_id = ? OR qga.group_id = ?) LIMIT 1');
            $quizCheck->bind_param('iii', $quizId, $studentGroupId, $studentGroupId);
            $quizCheck->execute();
            $quiz = $quizCheck->get_result()->fetch_assoc();
        }

        if (!$quiz) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'This quiz is not available for your group.']);
            exit;
        }

        $maxAttempts = (int)($quiz['max_attempts'] ?? 0);
        $attemptCountStmt = $conn->prepare('SELECT COUNT(*) AS attempts FROM quiz_attempts WHERE student_id = ? AND quiz_id = ?');
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
        $quizCheck = $conn->prepare('SELECT id, time_limit_minutes, max_attempts FROM quizzes WHERE id = ? AND group_id = ? AND status = "active" LIMIT 1');
        $quizCheck->bind_param('ii', $quizId, $studentGroupId);
        $quizCheck->execute();
        $quiz = $quizCheck->get_result()->fetch_assoc();

        if (!$quiz) {
            $quizCheck = $conn->prepare('SELECT q.id, q.time_limit_minutes, q.max_attempts FROM quizzes q LEFT JOIN quiz_group_access qga ON qga.quiz_id = q.id WHERE q.id = ? AND q.status = "active" AND (q.group_id = ? OR qga.group_id = ?) LIMIT 1');
            $quizCheck->bind_param('iii', $quizId, $studentGroupId, $studentGroupId);
            $quizCheck->execute();
            $quiz = $quizCheck->get_result()->fetch_assoc();
        }

        if (!$quiz) {
            $error = 'This quiz is not available for your group.';
        } else {
            $maxAttempts = (int)($quiz['max_attempts'] ?? 0);
            $attemptCountStmt = $conn->prepare('SELECT COUNT(*) AS attempts FROM quiz_attempts WHERE student_id = ? AND quiz_id = ?');
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

                    $activeAttemptStmt = $conn->prepare('SELECT id FROM quiz_attempts WHERE student_id = ? AND quiz_id = ? AND status = "in_progress" ORDER BY id DESC LIMIT 1');
                    $activeAttemptStmt->bind_param('ii', $user['id'], $quizId);
                    $activeAttemptStmt->execute();
                    $activeAttempt = $activeAttemptStmt->get_result()->fetch_assoc();

                    if ($activeAttempt) {
                        $updateAttempt = $conn->prepare('UPDATE quiz_attempts SET score = ?, submitted_at = NOW(), started_at = ?, status = "submitted" WHERE id = ?');
                        $updateAttempt->bind_param('isi', $score, $startedAt, $activeAttempt['id']);
                        $updateAttempt->execute();
                    } else {
                        $insertAttempt = $conn->prepare('INSERT INTO quiz_attempts (student_id, quiz_id, score, submitted_at, started_at, status) VALUES (?, ?, ?, NOW(), ?, "submitted")');
                        $insertAttempt->bind_param('iiis', $user['id'], $quizId, $score, $startedAt);
                        $insertAttempt->execute();
                    }

                    $success = 'Quiz submitted successfully. Your score: ' . $score . '/' . $total;
                }
            }
        }
    }
}

$activeQuizAttempt = null;
if ($studentGroupId > 0) {
    $quizzesStmt = $conn->prepare('SELECT DISTINCT q.id, q.title, q.time_limit_minutes, q.max_attempts FROM quizzes q LEFT JOIN quiz_group_access qga ON qga.quiz_id = q.id WHERE q.status = "active" AND (q.group_id = ? OR qga.group_id = ?) ORDER BY q.created_at DESC');
    $quizzesStmt->bind_param('ii', $studentGroupId, $studentGroupId);
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
                        <p class="text-muted small mb-3">
                            <?php
                            $timeLimitLabel = ((int)($quiz['time_limit_minutes'] ?? 0) > 0) ? ((int)$quiz['time_limit_minutes'] . ' minute' . (((int)$quiz['time_limit_minutes'] > 1) ? 's' : '')) : 'No time limit';
                            $attemptLabel = ((int)($quiz['max_attempts'] ?? 0) > 0) ? ((int)$quiz['max_attempts'] . ' attempt' . (((int)$quiz['max_attempts'] > 1) ? 's' : '')) : 'Unlimited attempts';
                            echo htmlspecialchars('Time limit: ' . $timeLimitLabel . ' | Max attempts: ' . $attemptLabel, ENT_QUOTES, 'UTF-8');
                            ?>
                        </p>
                        <form method="post" class="mt-3 quiz-form" data-time-limit-minutes="<?php echo (int)($quiz['time_limit_minutes'] ?? 0); ?>">
                            <input type="hidden" name="quiz_id" value="<?php echo (int)$quiz['id']; ?>">
                            <input type="hidden" name="started_at" class="started-at-input" value="">
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                                <button type="button" class="btn btn-primary start-quiz-btn" data-quiz-id="<?php echo (int)$quiz['id']; ?>" <?php echo (!empty($activeQuizAttempt) && ((int)($activeQuizAttempt['quiz_id'] ?? 0) !== (int)$quiz['id'])) ? 'disabled' : ''; ?>><?php echo (!empty($activeQuizAttempt) && ((int)($activeQuizAttempt['quiz_id'] ?? 0) === (int)$quiz['id'])) ? 'Continue Attempt' : 'Start Attempt'; ?></button>
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
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="alert alert-info">No quizzes are available for your group yet.</div>
        <?php endif; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('form.quiz-form').forEach(function (form) {
                const startedAtInput = form.querySelector('input.started-at-input');
                const startButton = form.querySelector('.start-quiz-btn');
                const questionContainer = form.querySelector('.quiz-questions');
                const timerLabel = form.querySelector('.quiz-timer');
                const timeLimitMinutes = parseInt(form.getAttribute('data-time-limit-minutes') || '0', 10);

                if (!startedAtInput || !startButton || !questionContainer || !timerLabel) {
                    return;
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

                        if (timeLimitMinutes > 0) {
                            const startedAtValue = result.started_at ? result.started_at.replace(' ', 'T') : null;
                            const startedAt = startedAtValue ? new Date(startedAtValue) : now;
                            const deadline = new Date(startedAt.getTime() + (timeLimitMinutes * 60000));
                            const updateTimer = function () {
                                const remainingMs = deadline.getTime() - Date.now();
                                if (remainingMs <= 0) {
                                    timerLabel.textContent = 'Time is up';
                                    const allInputs = questionContainer.querySelectorAll('input, button');
                                    allInputs.forEach(function (element) {
                                        element.disabled = true;
                                    });
                                    return;
                                }

                                const minutes = Math.floor(remainingMs / 60000);
                                const seconds = Math.floor((remainingMs % 60000) / 1000);
                                timerLabel.textContent = 'Time remaining: ' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
                                window.setTimeout(updateTimer, 1000);
                            };

                            updateTimer();
                        } else {
                            timerLabel.textContent = 'No time limit';
                        }
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
