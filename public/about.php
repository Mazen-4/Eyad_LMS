<?php
require_once __DIR__ . '/../includes/auth.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Learn about Eyad Mazhar and his online math teaching platform for American-system students preparing for EST, DSAT, and ACT exams.">
    <meta name="robots" content="index,follow">
    <meta property="og:title" content="About Eyad Mazhar">
    <meta property="og:description" content="Discover how Eyad Mazhar supports structured math learning, exam preparation, and student access through online lessons and resources.">
    <meta property="og:type" content="website">
    <meta property="twitter:card" content="summary">
    <meta property="twitter:title" content="About Eyad Mazhar">
    <meta property="twitter:description" content="Discover how Eyad Mazhar supports structured math learning, exam preparation, and student access through online lessons and resources.">
    <title>About - Eyad LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/public_nav.php'; ?>
    <div class="container py-5">
        <h1 class="mb-3">About the Platform</h1>
        <p class="lead text-muted">This LMS is built to make Eng. Eyad Mazhar’s American-system mathematics teaching easy to access and easy to manage.</p>
        <p>It provides a secure home for EST, DSAT, and ACT preparation, helping students stay organized and focused on the topics that matter most.</p>

        <div class="row gy-4 mt-4">
            <div class="col-lg-6">
                <div class="card section-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Focused on American-system math</h5>
                        <p class="card-text text-muted">This platform supports Eng. Eyad Mazhar’s teaching for American curriculum students in Egypt.</p>
                        <p class="card-text text-muted">It is especially suited for students preparing for EST, DSAT, and ACT exams.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <h2 class="mb-3">Why it works</h2>
            <ul class="text-muted">
                <li>Secure group-based access for lectures, resources, and quizzes.</li>
                <li>Simple design so students can focus on learning, not navigation.</li>
                <li>Teacher-managed content with no student self-registration or payments.</li>
            </ul>
        </div>

        <div class="mt-4">
            <a href="index.php" class="btn btn-primary me-2">Back to Home</a>
            <a href="contact.php" class="btn btn-outline-secondary">Contact the Instructor</a>
        </div>
    </div>
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
