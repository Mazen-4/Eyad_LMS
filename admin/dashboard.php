<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['admin']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/admin_nav.php'; ?>

    <div class="container py-4">
        <h1 class="mb-3">Admin Dashboard</h1>
        <p class="text-muted">This is the starting point for the teacher control panel.</p>
        <div class="row g-3">
            <div class="col-md-4">
                <a href="students.php" class="text-decoration-none text-dark">
                    <div class="card h-100 shadow-sm hover-shadow">
                        <div class="card-body">
                            <h5 class="card-title">Students</h5>
                            <p class="card-text">Manage student accounts and group assignment.</p>
                            <span class="btn btn-outline-primary btn-sm mt-2">Open Students</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="lectures.php" class="text-decoration-none text-dark">
                    <div class="card h-100 shadow-sm hover-shadow">
                        <div class="card-body">
                            <h5 class="card-title">Lectures</h5>
                            <p class="card-text">Create and organize lecture folders and access.</p>
                            <span class="btn btn-outline-primary btn-sm mt-2">Open Lectures</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="quizzes.php" class="text-decoration-none text-dark">
                    <div class="card h-100 shadow-sm hover-shadow">
                        <div class="card-body">
                            <h5 class="card-title">Quizzes</h5>
                            <p class="card-text">Build simple MCQ quizzes for student groups.</p>
                            <span class="btn btn-outline-primary btn-sm mt-2">Open Quizzes</span>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-12 col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Public Pages</h5>
                        <p class="card-text">Open the public About and Contact pages from the admin panel.</p>
                        <a href="../public/about.php" class="btn btn-outline-secondary btn-sm me-2">About</a>
                        <a href="../public/contact.php" class="btn btn-outline-secondary btn-sm">Contact</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
