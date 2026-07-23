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
    <link href="../assets/css/theme.css?v=2" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/student_nav.php'; ?>

    <div class="container py-4">
        <h1 class="mb-3">Student Dashboard</h1>
        <p class="text-muted">This is the starting point for the student portal.</p>
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Sessions</h5>
                        <p class="card-text">View sessions assigned to your group.</p>
                        <a href="lectures.php" class="btn btn-outline-primary btn-sm">Open Sessions</a>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Resources</h5>
                        <p class="card-text">Download PDFs assigned to your group.</p>
                        <a href="resources.php" class="btn btn-outline-primary btn-sm">Open Resources</a>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Quizzes</h5>
                        <p class="card-text">Take the quizzes available for your class.</p>
                        <a href="quizzes.php" class="btn btn-outline-primary btn-sm">Open Quizzes</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-12 col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">More Information</h5>
                        <p class="card-text">Open the public Contact page from here.</p>
                        <a href="../public/contact.php" class="btn btn-outline-secondary btn-sm">Contact</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
