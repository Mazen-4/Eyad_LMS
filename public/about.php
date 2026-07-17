<?php
require_once __DIR__ . '/../includes/auth.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>About - Eyad LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/public_nav.php'; ?>
    <div class="container py-5">
        <h1 class="mb-3">About the Platform</h1>
        <p class="lead text-muted">This LMS is designed to keep teaching organized, simple, and accessible for one instructor and their students.</p>
        <p>It supports secure login, group-based content assignment, quizzes, and an easy-to-manage admin workflow without unnecessary complexity.</p>
        <div class="mt-4">
            <a href="index.php" class="btn btn-primary me-2">Back to Home</a>
            <a href="contact.php" class="btn btn-outline-secondary">Contact the Instructor</a>
        </div>
    </div>
</body>
</html>
