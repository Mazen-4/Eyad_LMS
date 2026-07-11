<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['admin']);
$conn = getDbConnection();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $groupId = (int)($_POST['group_id'] ?? 0);
    $status = $_POST['status'] ?? 'active';

    if ($title === '' || $groupId <= 0) {
        $error = 'Title and group are required.';
    } else {
        $stmt = $conn->prepare('INSERT INTO quizzes (title, group_id, status) VALUES (?, ?, ?)');
        $stmt->bind_param('sis', $title, $groupId, $status);
        $stmt->execute();
        $quizId = $stmt->insert_id;

        $questions = $_POST['questions'] ?? [];
        $choices1 = $_POST['choice_1'] ?? [];
        $choices2 = $_POST['choice_2'] ?? [];
        $choices3 = $_POST['choice_3'] ?? [];
        $choices4 = $_POST['choice_4'] ?? [];
        $correctAnswers = $_POST['correct_answer'] ?? [];

        $questionStmt = $conn->prepare('INSERT INTO questions (quiz_id, question, choice_1, choice_2, choice_3, choice_4, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?)');

        foreach ($questions as $index => $question) {
            $questionText = trim($question ?? '');
            if ($questionText === '') {
                continue;
            }

            $choice1 = trim($choices1[$index] ?? '');
            $choice2 = trim($choices2[$index] ?? '');
            $choice3 = trim($choices3[$index] ?? '');
            $choice4 = trim($choices4[$index] ?? '');
            $correctAnswer = trim($correctAnswers[$index] ?? '1');

            $questionStmt->bind_param('issssss', $quizId, $questionText, $choice1, $choice2, $choice3, $choice4, $correctAnswer);
            $questionStmt->execute();
        }

        $success = 'Quiz created successfully.';
    }
}

$groupsResult = $conn->query('SELECT id, name, level FROM `groups` ORDER BY name');
$quizzesResult = $conn->query('SELECT q.id, q.title, q.status, q.created_at, g.name AS group_name, g.level AS group_level FROM quizzes q LEFT JOIN `groups` g ON g.id = q.group_id ORDER BY q.created_at DESC');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quizzes - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/admin_nav.php'; ?>

    <div class="container py-4">
        <h1 class="mb-3">Quizzes</h1>
        <p class="text-muted">Create simple MCQ quizzes and assign them to a group.</p>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Add Quiz</h5>
                <form method="post" class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Quiz Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Group</label>
                        <select name="group_id" class="form-select" required>
                            <option value="">Select group</option>
                            <?php while ($group = $groupsResult->fetch_assoc()): ?>
                                <option value="<?php echo (int)$group['id']; ?>"><?php echo htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($group['level'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <div class="border rounded p-3">
                            <h6 class="mb-3">Questions</h6>
                            <div id="questionsContainer">
                                <div class="question-block border rounded p-3 mb-3">
                                    <div class="mb-2">
                                        <label class="form-label">Question</label>
                                        <input type="text" name="questions[]" class="form-control" required>
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
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addQuestionBlock()">Add Question</button>
                        </div>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Save Quiz</button>
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
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($quizzesResult->num_rows > 0): ?>
                                <?php while ($quiz = $quizzesResult->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(($quiz['group_name'] ?? '-') . ' (' . ($quiz['group_level'] ?? '-') . ')', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($quiz['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(formatDisplayDateTime($quiz['created_at']), ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-muted">No quizzes created yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function addQuestionBlock() {
            const container = document.getElementById('questionsContainer');
            const block = document.createElement('div');
            block.className = 'question-block border rounded p-3 mb-3';
            block.innerHTML = `
                <div class="mb-2">
                    <label class="form-label">Question</label>
                    <input type="text" name="questions[]" class="form-control" required>
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
        }
    </script>
</body>
</html>
