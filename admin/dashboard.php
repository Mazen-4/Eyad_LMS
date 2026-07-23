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
    <link href="<?php echo htmlspecialchars(appAssetUrl('assets/css/theme.css?v=2'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/admin_nav.php'; ?>

    <div class="container py-4">
        <div class="hero-panel mb-4">
            <h1 class="page-light mb-2">Admin Dashboard</h1>
            <p class="text-muted mb-0">Manage students, admins, groups, sessions, resources, and quizzes from one clean workspace.</p>
        </div>
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
                <a href="admins.php" class="text-decoration-none text-dark">
                    <div class="card h-100 shadow-sm hover-shadow">
                        <div class="card-body">
                            <h5 class="card-title">Admins</h5>
                            <p class="card-text">Create and manage additional admin accounts.</p>
                            <span class="btn btn-outline-primary btn-sm mt-2">Open Admins</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="groups.php" class="text-decoration-none text-dark">
                    <div class="card h-100 shadow-sm hover-shadow">
                        <div class="card-body">
                            <h5 class="card-title">Groups</h5>
                            <p class="card-text">Create learning groups and control access to content.</p>
                            <span class="btn btn-outline-primary btn-sm mt-2">Open Groups</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="lectures.php" class="text-decoration-none text-dark">
                    <div class="card h-100 shadow-sm hover-shadow">
                        <div class="card-body">
                            <h5 class="card-title">Sessions</h5>
                            <p class="card-text">Create and organize session folders and access.</p>
                            <span class="btn btn-outline-primary btn-sm mt-2">Open Sessions</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="resources.php" class="text-decoration-none text-dark">
                    <div class="card h-100 shadow-sm hover-shadow">
                        <div class="card-body">
                            <h5 class="card-title">Resources</h5>
                            <p class="card-text">Upload PDFs and make them available to selected groups.</p>
                            <span class="btn btn-outline-primary btn-sm mt-2">Open Resources</span>
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
            <div class="col-md-4">
                <a href="activity_log.php" class="text-decoration-none text-dark">
                    <div class="card h-100 shadow-sm hover-shadow">
                        <div class="card-body">
                            <h5 class="card-title">Activity Log</h5>
                            <p class="card-text">View student lecture and quiz activity in one place.</p>
                            <span class="btn btn-outline-primary btn-sm mt-2">Open Activity Log</span>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="student-view-section mt-4">
            <div class="mb-3">
                <h2 class="h4">Student View</h2>
                <p class="text-muted mb-0">Preview the content students see from a single admin dashboard.</p>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <a href="../student/lectures.php?preview=1" class="text-decoration-none text-dark">
                        <div class="card h-100 shadow-sm hover-shadow">
                            <div class="card-body">
                                <h5 class="card-title">Sessions Preview</h5>
                                <p class="card-text">Preview the sessions content exactly as a student sees it.</p>
                                <span class="btn btn-outline-primary btn-sm mt-2">View Sessions</span>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="../student/quizzes.php?preview=1" class="text-decoration-none text-dark">
                        <div class="card h-100 shadow-sm hover-shadow">
                            <div class="card-body">
                                <h5 class="card-title">Quizzes Preview</h5>
                                <p class="card-text">Preview the quiz listing and content as a student experiences it.</p>
                                <span class="btn btn-outline-primary btn-sm mt-2">View Quizzes</span>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="../student/resources.php?preview=1" class="text-decoration-none text-dark">
                        <div class="card h-100 shadow-sm hover-shadow">
                            <div class="card-body">
                                <h5 class="card-title">Resources Preview</h5>
                                <p class="card-text">Preview the students' resource library and file links.</p>
                                <span class="btn btn-outline-primary btn-sm mt-2">View Resources</span>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
