<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['admin']);
$conn = getDbConnection();

$success = '';
$error = '';
$editingQuizId = (int)($_GET['edit'] ?? 0);
$deletingQuizId = (int)($_GET['delete'] ?? 0);

$conn->query("CREATE TABLE IF NOT EXISTS quiz_group_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    group_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_quiz_group (quiz_id, group_id),
    KEY group_id (group_id)
)");

$conn->query("CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question TEXT NOT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    choice_1 VARCHAR(255) NOT NULL,
    choice_2 VARCHAR(255) NOT NULL,
    choice_3 VARCHAR(255) NOT NULL,
    choice_4 VARCHAR(255) NOT NULL,
    correct_answer VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY quiz_id (quiz_id)
)");

$columnsResult = $conn->query("SHOW COLUMNS FROM questions LIKE 'image_path'");
if ($columnsResult && $columnsResult->num_rows === 0) {
    $conn->query('ALTER TABLE questions ADD COLUMN image_path VARCHAR(255) DEFAULT NULL');
}

$quizTimeColumnResult = $conn->query("SHOW COLUMNS FROM quizzes LIKE 'time_limit_minutes'");
if ($quizTimeColumnResult && $quizTimeColumnResult->num_rows === 0) {
    $conn->query('ALTER TABLE quizzes ADD COLUMN time_limit_minutes INT DEFAULT NULL');
}

$quizAttemptsColumnResult = $conn->query("SHOW COLUMNS FROM quizzes LIKE 'max_attempts'");
if ($quizAttemptsColumnResult && $quizAttemptsColumnResult->num_rows === 0) {
    $conn->query('ALTER TABLE quizzes ADD COLUMN max_attempts INT DEFAULT NULL');
}

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

$quizExtraAttemptsTableResult = $conn->query("SHOW TABLES LIKE 'quiz_extra_attempts'");
if ($quizExtraAttemptsTableResult && $quizExtraAttemptsTableResult->num_rows === 0) {
    $conn->query("CREATE TABLE quiz_extra_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        quiz_id INT NOT NULL,
        student_id INT NOT NULL,
        extra_attempts INT NOT NULL DEFAULT 1,
        reason VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_quiz_student (quiz_id, student_id),
        KEY quiz_id (quiz_id),
        KEY student_id (student_id)
    )");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $quizId = (int)($_POST['quiz_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $selectedGroups = array_map('intval', (array)($_POST['group_ids'] ?? []));
    $status = $_POST['status'] ?? 'active';
    $timeLimitMinutes = (int)($_POST['time_limit_minutes'] ?? 0);
    $maxAttempts = (int)($_POST['max_attempts'] ?? 0);

    if ($action === 'grant_extra_attempt') {
        $grantQuizId = (int)($_POST['grant_quiz_id'] ?? 0);
        $grantStudentId = (int)($_POST['grant_student_id'] ?? 0);
        $grantExtraAttempts = (int)($_POST['extra_attempts'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        if ($grantQuizId <= 0 || $grantStudentId <= 0 || $grantExtraAttempts <= 0) {
            $error = 'Please select a quiz, student, and a valid number of extra attempts.';
        } else {
            $existingOverrideStmt = $conn->prepare('SELECT id FROM quiz_extra_attempts WHERE quiz_id = ? AND student_id = ? LIMIT 1');
            $existingOverrideStmt->bind_param('ii', $grantQuizId, $grantStudentId);
            $existingOverrideStmt->execute();
            $existingOverride = $existingOverrideStmt->get_result()->fetch_assoc();

            if ($existingOverride) {
                $updateOverrideStmt = $conn->prepare('UPDATE quiz_extra_attempts SET extra_attempts = ?, reason = ? WHERE quiz_id = ? AND student_id = ?');
                $updateOverrideStmt->bind_param('isii', $grantExtraAttempts, $reason, $grantQuizId, $grantStudentId);
                $updateOverrideStmt->execute();
            } else {
                $insertOverrideStmt = $conn->prepare('INSERT INTO quiz_extra_attempts (quiz_id, student_id, extra_attempts, reason) VALUES (?, ?, ?, ?)');
                $insertOverrideStmt->bind_param('iiis', $grantQuizId, $grantStudentId, $grantExtraAttempts, $reason);
                $insertOverrideStmt->execute();
            }

            $success = 'Extra attempt access granted successfully.';
        }
    } elseif ($title === '' || empty($selectedGroups)) {
        $error = 'Quiz title and at least one group are required.';
    } else {
        $primaryGroupId = (int)$selectedGroups[0];

        if ($action === 'edit' && $quizId > 0) {
            $stmt = $conn->prepare('UPDATE quizzes SET title = ?, group_id = ?, status = ?, time_limit_minutes = ?, max_attempts = ? WHERE id = ?');
            $stmt->bind_param('sisiii', $title, $primaryGroupId, $status, $timeLimitMinutes, $maxAttempts, $quizId);
            $stmt->execute();

            $clearAccessStmt = $conn->prepare('DELETE FROM quiz_group_access WHERE quiz_id = ?');
            $clearAccessStmt->bind_param('i', $quizId);
            $clearAccessStmt->execute();

            $existingQuestionsStmt = $conn->prepare('SELECT image_path FROM questions WHERE quiz_id = ?');
            $existingQuestionsStmt->bind_param('i', $quizId);
            $existingQuestionsStmt->execute();
            $existingQuestions = $existingQuestionsStmt->get_result();
            while ($existingQuestion = $existingQuestions->fetch_assoc()) {
                if (!empty($existingQuestion['image_path'])) {
                    $existingFilePath = __DIR__ . '/../' . $existingQuestion['image_path'];
                    if (is_file($existingFilePath)) {
                        unlink($existingFilePath);
                    }
                }
            }

            $deleteQuestionsStmt = $conn->prepare('DELETE FROM questions WHERE quiz_id = ?');
            $deleteQuestionsStmt->bind_param('i', $quizId);
            $deleteQuestionsStmt->execute();

            $success = 'Quiz updated successfully.';
        } else {
            $stmt = $conn->prepare('INSERT INTO quizzes (title, group_id, status, time_limit_minutes, max_attempts) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sisii', $title, $primaryGroupId, $status, $timeLimitMinutes, $maxAttempts);
            $stmt->execute();
            $quizId = $stmt->insert_id;
            $success = 'Quiz created successfully.';
        }

        $accessStmt = $conn->prepare('INSERT INTO quiz_group_access (quiz_id, group_id) VALUES (?, ?)');
        foreach ($selectedGroups as $groupId) {
            $gid = (int)$groupId;
            if ($gid > 0) {
                $accessStmt->bind_param('ii', $quizId, $gid);
                $accessStmt->execute();
            }
        }

        $questions = $_POST['questions'] ?? [];
        $choices1 = $_POST['choice_1'] ?? [];
        $choices2 = $_POST['choice_2'] ?? [];
        $choices3 = $_POST['choice_3'] ?? [];
        $choices4 = $_POST['choice_4'] ?? [];
        $correctAnswers = $_POST['correct_answer'] ?? [];
        $existingImages = $_POST['existing_image'] ?? [];
        $removeQuestions = array_map('intval', (array)($_POST['remove_question'] ?? []));
        $removeImages = array_map('intval', (array)($_POST['remove_image'] ?? []));
        $oldQuestionImagePaths = [];

        if ($action === 'edit' && $quizId > 0) {
            $existingQuestionsStmt = $conn->prepare('SELECT image_path FROM questions WHERE quiz_id = ?');
            $existingQuestionsStmt->bind_param('i', $quizId);
            $existingQuestionsStmt->execute();
            $existingQuestionsResult = $existingQuestionsStmt->get_result();
            while ($existingQuestion = $existingQuestionsResult->fetch_assoc()) {
                $oldImagePath = trim($existingQuestion['image_path'] ?? '');
                if ($oldImagePath !== '') {
                    $oldQuestionImagePaths[] = $oldImagePath;
                }
            }
        }

        $questionStmt = $conn->prepare('INSERT INTO questions (quiz_id, question, image_path, choice_1, choice_2, choice_3, choice_4, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $retainedImagePaths = [];

        foreach ($questions as $index => $question) {
            $removeQuestion = (int)($removeQuestions[$index] ?? 0);
            if ($removeQuestion === 1) {
                continue;
            }

            $questionText = trim($question ?? '');
            if ($questionText === '') {
                continue;
            }

            $choice1 = trim($choices1[$index] ?? '');
            $choice2 = trim($choices2[$index] ?? '');
            $choice3 = trim($choices3[$index] ?? '');
            $choice4 = trim($choices4[$index] ?? '');
            $correctAnswer = trim($correctAnswers[$index] ?? '1');
            $imagePath = trim($existingImages[$index] ?? '');
            $shouldRemoveImage = (int)($removeImages[$index] ?? 0) === 1;

            if ($shouldRemoveImage) {
                if ($imagePath !== '') {
                    $oldImageFilePath = __DIR__ . '/../' . $imagePath;
                    if (is_file($oldImageFilePath)) {
                        unlink($oldImageFilePath);
                    }
                }
                $imagePath = '';
            }

            if (isset($_FILES['question_image']) && is_array($_FILES['question_image']) && isset($_FILES['question_image']['tmp_name'][$index])) {
                $imageFile = $_FILES['question_image'];
                $tmpName = $imageFile['tmp_name'][$index] ?? '';
                $originalName = basename($imageFile['name'][$index] ?? '');
                $imageSize = (int)($imageFile['size'][$index] ?? 0);
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                if ($tmpName !== '' && ($imageFile['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'], true)) {
                        $error = 'Only JPG, PNG, GIF images are allowed for quiz questions.';
                    } elseif ($imageSize > 2 * 1024 * 1024) {
                        $error = 'Quiz images must be 2MB or smaller.';
                    } else {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mimeType = finfo_file($finfo, $tmpName);
                        finfo_close($finfo);

                        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif'], true)) {
                            $error = 'Only valid image files are allowed.';
                        }
                    }

                    if ($error === '') {
                        if ($imagePath !== '') {
                            $oldImageFilePath = __DIR__ . '/../' . $imagePath;
                            if (is_file($oldImageFilePath)) {
                                unlink($oldImageFilePath);
                            }
                        }

                        $uploadDir = __DIR__ . '/../uploads/quizzes/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        $fileName = time() . '_' . $index . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
                        $targetPath = $uploadDir . $fileName;
                        if (move_uploaded_file($tmpName, $targetPath)) {
                            $imagePath = 'uploads/quizzes/' . $fileName;
                        } else {
                            $error = 'Failed to upload quiz image.';
                        }
                    }
                }
            }

            $questionStmt->bind_param('isssssss', $quizId, $questionText, $imagePath, $choice1, $choice2, $choice3, $choice4, $correctAnswer);
            $questionStmt->execute();

            if ($imagePath !== '') {
                $retainedImagePaths[] = $imagePath;
            }
        }

        foreach ($oldQuestionImagePaths as $oldImagePath) {
            if ($oldImagePath === '' || in_array($oldImagePath, $retainedImagePaths, true)) {
                continue;
            }

            $oldImageFilePath = __DIR__ . '/../' . $oldImagePath;
            if (is_file($oldImageFilePath)) {
                unlink($oldImageFilePath);
            }
        }
    }
}

if ($deletingQuizId > 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $questionsToDeleteStmt = $conn->prepare('SELECT image_path FROM questions WHERE quiz_id = ?');
    $questionsToDeleteStmt->bind_param('i', $deletingQuizId);
    $questionsToDeleteStmt->execute();
    $questionsToDelete = $questionsToDeleteStmt->get_result();
    while ($questionToDelete = $questionsToDelete->fetch_assoc()) {
        if (!empty($questionToDelete['image_path'])) {
            $questionFilePath = __DIR__ . '/../' . $questionToDelete['image_path'];
            if (is_file($questionFilePath)) {
                unlink($questionFilePath);
            }
        }
    }

    $deleteQuestionsStmt = $conn->prepare('DELETE FROM questions WHERE quiz_id = ?');
    $deleteQuestionsStmt->bind_param('i', $deletingQuizId);
    $deleteQuestionsStmt->execute();

    $deleteAttemptsStmt = $conn->prepare('DELETE FROM quiz_attempts WHERE quiz_id = ?');
    $deleteAttemptsStmt->bind_param('i', $deletingQuizId);
    $deleteAttemptsStmt->execute();

    $clearAccessStmt = $conn->prepare('DELETE FROM quiz_group_access WHERE quiz_id = ?');
    $clearAccessStmt->bind_param('i', $deletingQuizId);
    $clearAccessStmt->execute();

    $deleteQuizStmt = $conn->prepare('DELETE FROM quizzes WHERE id = ?');
    $deleteQuizStmt->bind_param('i', $deletingQuizId);
    $deleteQuizStmt->execute();
    $success = 'Quiz deleted successfully.';
}

$groupsResult = $conn->query('SELECT id, name, level FROM `groups` ORDER BY name');
$groups = [];
while ($group = $groupsResult->fetch_assoc()) {
    $groups[] = $group;
}
$studentsResult = $conn->query('SELECT id, name, username FROM users WHERE role = "student" ORDER BY name');
$students = [];
while ($student = $studentsResult->fetch_assoc()) {
    $students[] = $student;
}
$quizzesResult = $conn->query('SELECT id, title, status, created_at, group_id, time_limit_minutes, max_attempts FROM quizzes ORDER BY created_at DESC');
$quizSelectResult = $conn->query('SELECT id, title FROM quizzes ORDER BY title');
$quizOptions = [];
while ($quizOption = $quizSelectResult->fetch_assoc()) {
    $quizOptions[] = $quizOption;
}
$extraAttemptsResult = $conn->query('SELECT qea.id, q.title AS quiz_title, u.name AS student_name, u.username, qea.extra_attempts, qea.reason, qea.created_at FROM quiz_extra_attempts qea INNER JOIN quizzes q ON q.id = qea.quiz_id INNER JOIN users u ON u.id = qea.student_id ORDER BY qea.created_at DESC');
$attemptsSummaryResult = $conn->query('SELECT qa.id, qa.student_id, u.name AS student_name, u.username, q.title AS quiz_title, qa.score, qa.total_questions, qa.score_percent, qa.status, qa.submitted_at FROM quiz_attempts qa INNER JOIN users u ON u.id = qa.student_id INNER JOIN quizzes q ON q.id = qa.quiz_id ORDER BY qa.submitted_at DESC, qa.id DESC LIMIT 100');

$editingQuiz = null;
$editingQuestions = [];
$selectedGroupIds = [];
if ($editingQuizId > 0) {
    $editingStmt = $conn->prepare('SELECT id, title, status, group_id, time_limit_minutes, max_attempts FROM quizzes WHERE id = ? LIMIT 1');
    $editingStmt->bind_param('i', $editingQuizId);
    $editingStmt->execute();
    $editingQuiz = $editingStmt->get_result()->fetch_assoc();

    if ($editingQuiz) {
        $accessStmt = $conn->prepare('SELECT group_id FROM quiz_group_access WHERE quiz_id = ? ORDER BY group_id');
        $accessStmt->bind_param('i', $editingQuizId);
        $accessStmt->execute();
        $accessResult = $accessStmt->get_result();
        while ($accessRow = $accessResult->fetch_assoc()) {
            $selectedGroupIds[(int)$accessRow['group_id']] = true;
        }

        $questionsStmt = $conn->prepare('SELECT id, question, image_path, choice_1, choice_2, choice_3, choice_4, correct_answer FROM questions WHERE quiz_id = ? ORDER BY id');
        $questionsStmt->bind_param('i', $editingQuizId);
        $questionsStmt->execute();
        $editingQuestionsResult = $questionsStmt->get_result();
        while ($questionRow = $editingQuestionsResult->fetch_assoc()) {
            $editingQuestions[] = $questionRow;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quizzes - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(appAssetUrl('assets/css/theme.css?v=2'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/admin_nav.php'; ?>

    <div class="container py-4">
        <h1 class="mb-3">Quizzes</h1>
        <p class="text-muted">Create simple MCQ quizzes and assign them to one or more groups.</p>

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

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Grant Extra Attempts</h5>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="grant_extra_attempt">
                    <div class="col-md-4">
                        <label class="form-label">Quiz</label>
                        <select name="grant_quiz_id" class="form-select" required>
                            <option value="">Select quiz</option>
                            <?php foreach ($quizOptions as $quizOption): ?>
                                <option value="<?php echo (int)$quizOption['id']; ?>"><?php echo htmlspecialchars($quizOption['title'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Student</label>
                        <select name="grant_student_id" class="form-select" required>
                            <option value="">Select student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo (int)$student['id']; ?>"><?php echo htmlspecialchars($student['name'] . ' (' . $student['username'] . ')', ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Extra Attempts</label>
                        <input type="number" name="extra_attempts" class="form-control" min="1" value="1" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control" placeholder="Optional">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-outline-primary">Grant Access</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($extraAttemptsResult && $extraAttemptsResult->num_rows > 0): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Current Extra Attempt Rules</h5>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Quiz</th>
                                    <th>Student</th>
                                    <th>Extra Attempts</th>
                                    <th>Reason</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($extraAttempt = $extraAttemptsResult->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($extraAttempt['quiz_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($extraAttempt['student_name'] . ' (' . $extraAttempt['username'] . ')', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int)$extraAttempt['extra_attempts']; ?></td>
                                        <td><?php echo htmlspecialchars($extraAttempt['reason'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(formatDisplayDateTime($extraAttempt['created_at']), ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title"><?php echo $editingQuiz ? 'Edit Quiz' : 'Add Quiz'; ?></h5>
                <form method="post" enctype="multipart/form-data" class="row g-3">
                    <input type="hidden" name="action" value="<?php echo $editingQuiz ? 'edit' : 'create'; ?>">
                    <?php if ($editingQuiz): ?>
                        <input type="hidden" name="quiz_id" value="<?php echo (int)$editingQuiz['id']; ?>">
                    <?php endif; ?>
                    <div class="col-md-5">
                        <label class="form-label">Quiz Title</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($editingQuiz['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?php echo (($editingQuiz['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (($editingQuiz['status'] ?? 'active') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Time Limit (min)</label>
                        <input type="number" name="time_limit_minutes" class="form-control" min="0" value="<?php echo (int)($editingQuiz['time_limit_minutes'] ?? 0); ?>" placeholder="0 = no limit">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Max Attempts</label>
                        <input type="number" name="max_attempts" class="form-control" min="0" value="<?php echo (int)($editingQuiz['max_attempts'] ?? 0); ?>" placeholder="0 = unlimited">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Visible to Groups</label>
                        <div class="row g-2">
                            <?php foreach ($groups as $group): ?>
                                <?php $groupId = (int)$group['id']; ?>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="group_ids[]" value="<?php echo $groupId; ?>" id="quiz_group_<?php echo $groupId; ?>" <?php echo $editingQuiz && isset($selectedGroupIds[$groupId]) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="quiz_group_<?php echo $groupId; ?>">
                                            <?php echo htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($group['level'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>)
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="border rounded p-3">
                            <h6 class="mb-3">Questions</h6>
                            <div id="questionsContainer">
                                <?php $questionBlocks = $editingQuiz ? $editingQuestions : []; ?>
                                <?php if (empty($questionBlocks)): ?>
                                    <?php $questionBlocks = [[
                                        'question' => '',
                                        'image_path' => '',
                                        'choice_1' => '',
                                        'choice_2' => '',
                                        'choice_3' => '',
                                        'choice_4' => '',
                                        'correct_answer' => '1'
                                    ]]; ?>
                                <?php endif; ?>
                                <?php foreach ($questionBlocks as $index => $question): ?>
                                    <div class="question-block border rounded p-3 mb-3">
                                        <input type="hidden" name="existing_image[]" value="<?php echo htmlspecialchars($question['image_path'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="remove_question[]" value="0">
                                        <input type="hidden" name="remove_image[]" value="0">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label mb-0">Question</label>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-danger remove-question-btn">Remove Question</button>
                                                <button type="button" class="btn btn-outline-secondary remove-image-btn">Remove Picture</button>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <input type="text" name="questions[]" class="form-control" value="<?php echo htmlspecialchars($question['question'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Question Image (optional)</label>
                                            <input type="file" name="question_image[]" class="form-control" accept="image/*">
                                            <div class="form-text">Supported formats: JPG, PNG, GIF. Maximum file size: 2MB.</div>
                                            <?php if (!empty($question['image_path'])): ?>
                                                <div class="form-text current-image-info">Current image: <a href="../<?php echo htmlspecialchars($question['image_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">View</a></div>
                                            <?php endif; ?>
                                            <div class="form-text text-danger image-remove-status d-none">Picture will be removed.</div>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-md-6"><input type="text" name="choice_1[]" class="form-control" placeholder="Choice 1" value="<?php echo htmlspecialchars($question['choice_1'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required></div>
                                            <div class="col-md-6"><input type="text" name="choice_2[]" class="form-control" placeholder="Choice 2" value="<?php echo htmlspecialchars($question['choice_2'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required></div>
                                            <div class="col-md-6"><input type="text" name="choice_3[]" class="form-control" placeholder="Choice 3" value="<?php echo htmlspecialchars($question['choice_3'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required></div>
                                            <div class="col-md-6"><input type="text" name="choice_4[]" class="form-control" placeholder="Choice 4" value="<?php echo htmlspecialchars($question['choice_4'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required></div>
                                        </div>
                                        <div class="mt-2">
                                            <label class="form-label">Correct Answer</label>
                                            <select name="correct_answer[]" class="form-select">
                                                <option value="1" <?php echo (($question['correct_answer'] ?? '1') === '1') ? 'selected' : ''; ?>>Choice 1</option>
                                                <option value="2" <?php echo (($question['correct_answer'] ?? '1') === '2') ? 'selected' : ''; ?>>Choice 2</option>
                                                <option value="3" <?php echo (($question['correct_answer'] ?? '1') === '3') ? 'selected' : ''; ?>>Choice 3</option>
                                                <option value="4" <?php echo (($question['correct_answer'] ?? '1') === '4') ? 'selected' : ''; ?>>Choice 4</option>
                                            </select>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addQuestionBlock()">Add Question</button>
                        </div>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><?php echo $editingQuiz ? 'Update Quiz' : 'Save Quiz'; ?></button>
                        <?php if ($editingQuiz): ?>
                            <a href="quizzes.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Existing Quizzes</h5>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Group</th>
                                <th>Time</th>
                                <th>Attempts</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($quizzesResult->num_rows > 0): ?>
                                <?php while ($quiz = $quizzesResult->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php
                                            $quizGroupsStmt = $conn->prepare('SELECT g.name, g.level FROM quiz_group_access qga INNER JOIN `groups` g ON g.id = qga.group_id WHERE qga.quiz_id = ? ORDER BY g.name');
                                            $quizGroupsStmt->bind_param('i', $quiz['id']);
                                            $quizGroupsStmt->execute();
                                            $quizGroupsResult = $quizGroupsStmt->get_result();
                                            ?>
                                            <?php if ($quizGroupsResult->num_rows > 0): ?>
                                                <ul class="mb-0 ps-3">
                                                    <?php while ($quizGroup = $quizGroupsResult->fetch_assoc()): ?>
                                                        <li><?php echo htmlspecialchars($quizGroup['name'] . (!empty($quizGroup['level']) ? ' (' . $quizGroup['level'] . ')' : ''), ENT_QUOTES, 'UTF-8'); ?></li>
                                                    <?php endwhile; ?>
                                                </ul>
                                            <?php else: ?>
                                                <span class="text-muted">-<?php echo htmlspecialchars($quiz['group_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($quiz['time_limit_minutes'] > 0 ? $quiz['time_limit_minutes'] . ' min' : 'No limit', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($quiz['max_attempts'] > 0 ? (string)$quiz['max_attempts'] : 'Unlimited', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($quiz['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(formatDisplayDateTime($quiz['created_at']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <a href="quizzes.php?edit=<?php echo (int)$quiz['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <a href="quizzes.php?delete=<?php echo (int)$quiz['id']; ?>" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Delete this quiz and all its questions/attempts?');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-muted">No quizzes created yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function bindQuestionBlockEvents(block) {
            const removeQuestionButton = block.querySelector('.remove-question-btn');
            const removeImageButton = block.querySelector('.remove-image-btn');
            const removeQuestionInput = block.querySelector('input[name="remove_question[]"]');
            const removeImageInput = block.querySelector('input[name="remove_image[]"]');
            const existingImageInput = block.querySelector('input[name="existing_image[]"]');
            const imageStatus = block.querySelector('.image-remove-status');
            const currentImageInfo = block.querySelector('.current-image-info');
            const fileInput = block.querySelector('input[name="question_image[]"]');

            if (removeQuestionButton && removeQuestionInput) {
                removeQuestionButton.addEventListener('click', function () {
                    removeQuestionInput.value = '1';
                    block.style.display = 'none';
                });
            }

            if (removeImageButton && removeImageInput) {
                removeImageButton.addEventListener('click', function () {
                    removeImageInput.value = '1';
                    if (existingImageInput) {
                        existingImageInput.value = '';
                    }
                    if (fileInput) {
                        fileInput.value = '';
                    }
                    if (imageStatus) {
                        imageStatus.classList.remove('d-none');
                    }
                    if (currentImageInfo) {
                        currentImageInfo.classList.add('d-none');
                    }
                });
            }
        }

        function addQuestionBlock() {
            const container = document.getElementById('questionsContainer');
            const block = document.createElement('div');
            block.className = 'question-block border rounded p-3 mb-3';
            block.innerHTML = `
                <input type="hidden" name="existing_image[]" value="">
                <input type="hidden" name="remove_question[]" value="0">
                <input type="hidden" name="remove_image[]" value="0">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label mb-0">Question</label>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-danger remove-question-btn">Remove Question</button>
                        <button type="button" class="btn btn-outline-secondary remove-image-btn">Remove Picture</button>
                    </div>
                </div>
                <div class="mb-2">
                    <input type="text" name="questions[]" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Question Image (optional)</label>
                    <input type="file" name="question_image[]" class="form-control" accept="image/*">
                    <div class="form-text">Supported formats: JPG, PNG, GIF. Maximum file size: 2MB.</div>
                    <div class="form-text text-danger image-remove-status d-none">Picture will be removed.</div>
                </div>
                <div class="row g-2">
                    <div class="col-md-6"><input type="text" name="choice_1[]" class="form-control" placeholder="Choice 1" required></div>
                    <div class="col-md-6"><input type="text" name="choice_2[]" class="form-control" placeholder="Choice 2" required></div>
                    <div class="col-md-6"><input type="text" name="choice_3[]" class="form-control" placeholder="Choice 3" required></div>
                    <div class="col-md-6"><input type="text" name="choice_4[]" class="form-control" placeholder="Choice 4" required></div>
                </div>
                <div class="mt-2">
                    <label class="form-label">Correct Answer</label>
                    <select name="correct_answer[]" class="form-select">
                        <option value="1">Choice 1</option>
                        <option value="2">Choice 2</option>
                        <option value="3">Choice 3</option>
                        <option value="4">Choice 4</option>
                    </select>
                </div>`;
            container.appendChild(block);
            bindQuestionBlockEvents(block);
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.question-block').forEach(bindQuestionBlockEvents);
        });
    </script>
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
