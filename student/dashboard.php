<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['student']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Eyad LMS Student</a>
            <div class="ms-auto">
                <span class="navbar-text text-white me-3">Welcome, <?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                <a class="btn btn-outline-light btn-sm" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <h1 class="mb-3">Student Dashboard</h1>
        <p class="text-muted">This is the starting point for the student portal.</p>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Lectures</h5>
                        <p class="card-text">View lectures assigned to your group.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Resources</h5>
                        <p class="card-text">Download PDFs assigned to your group.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Quizzes</h5>
                        <p class="card-text">Take the quizzes available for your class.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
