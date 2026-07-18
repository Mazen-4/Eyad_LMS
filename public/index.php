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
                    <h1 class="display-6 fw-bold page-title">A dedicated platform for American-system math students.</h1>
                    <p class="lead text-muted">Secure online access to EST, DSAT, and ACT preparation materials from Eng. Eyad Mazhar.</p>
                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <a href="login.php" class="btn btn-primary">Login to Portal</a>
                        <a href="about.php" class="btn btn-outline-secondary">About the Platform</a>
                        <a href="contact.php" class="btn btn-outline-secondary">Contact</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card shadow-sm section-card">
                    <div class="card-body">
                        <h5 class="card-title page-title">What students can access</h5>
                        <ul class="mb-0 text-muted">
                            <li>Assigned lectures and guided study paths</li>
                            <li>PDF resources for exam preparation</li>
                            <li>Group-specific quizzes and performance support</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-4">
            <div class="col-md-4">
                <div class="card section-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Secure access</h5>
                        <p class="card-text text-muted">Each student logs in to a private portal and sees only the content assigned to their group.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card section-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Organized study materials</h5>
                        <p class="card-text text-muted">Lectures, PDFs, and quizzes are grouped by course and exam track for easy review.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card section-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Built for exam prep</h5>
                        <p class="card-text text-muted">Designed specifically for American math learners preparing for EST, DSAT, and ACT.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
