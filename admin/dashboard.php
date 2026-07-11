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
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Students</h5>
                        <p class="card-text">Manage student accounts and group assignment.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Lectures</h5>
                        <p class="card-text">Create and organize lecture folders and access.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Quizzes</h5>
                        <p class="card-text">Build simple MCQ quizzes for student groups.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
