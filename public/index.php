<?php
require_once __DIR__ . '/../includes/auth.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Eyad LMS - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/public_nav.php'; ?>
    <div class="container py-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="hero-panel">
                    <h1 class="display-6 fw-bold page-title">A modern learning space for Eng. Eyad Mazhar.</h1>
                    <p class="lead text-muted">Students can access lectures, resources, and quizzes through a clean, secure, and easy-to-use portal.</p>
                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <a href="login.php" class="btn btn-primary">Login to Portal</a>
                        <a href="about.php" class="btn btn-outline-secondary">Learn More</a>
                        <a href="contact.php" class="btn btn-outline-secondary">Contact Us</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card shadow-sm section-card">
                    <div class="card-body">
                        <h5 class="card-title page-title">What students can access</h5>
                        <ul class="mb-0 text-muted">
                            <li>Assigned lectures</li>
                            <li>PDF resources</li>
                            <li>Quizzes for their group</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
