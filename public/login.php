<?php
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    redirectToRoleDashboard(currentUser());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $user = authenticateUser($username, $password);

        if ($user) {
            setLoggedInUser($user);
            redirectToRoleDashboard($user);
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sign in to access Eyad Mazhar’s online math lessons, study resources, and quiz content for American-system exam preparation.">
    <meta name="robots" content="noindex,follow">
    <meta property="og:title" content="Login to Eyad Mazhar’s Online Math Portal">
    <meta property="og:description" content="Access your student portal for lessons, resources, and quizzes from Eyad Mazhar.">
    <meta property="og:type" content="website">
    <meta property="twitter:card" content="summary">
    <meta property="twitter:title" content="Login to Eyad Mazhar’s Online Math Portal">
    <meta property="twitter:description" content="Access your student portal for lessons, resources, and quizzes from Eyad Mazhar.">
    <title>Login - Eyad LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css?v=2" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/public_nav.php'; ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow-sm section-card">
                    <div class="card-body p-4">
                        <h2 class="page-title mb-3">Eng.Eyad Mazhar</h2>
                        <p class="text-black mb-3">Sign in to continue</p>

                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label fw-semibold" style="color: var(--text);">Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold" style="color: var(--text);">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
