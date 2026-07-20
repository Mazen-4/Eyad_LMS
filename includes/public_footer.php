<?php
require_once __DIR__ . '/auth.php';
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$publicRoot = preg_replace('@/admin.*$|/student.*$|/public.*$@', '/public', $scriptPath);
$publicRoot = rtrim($publicRoot, '/');
if ($publicRoot === '') {
    $publicRoot = '/public';
}
$footerLink = "{$publicRoot}/login.php";
$footerLabel = 'Login';
if (isLoggedIn()) {
    $user = currentUser();
    $footerLabel = 'Dashboard';
    $footerLink = ($user['role'] === 'admin') ? '../admin/dashboard.php' : '../student/dashboard.php';
}
?>
<footer class="site-footer mt-5">
    <div class="container py-4">
        <div class="row g-4 align-items-start">
            <div class="col-lg-4">
                <h5 class="fw-bold mb-2">Eng. Eyad Mazhar</h5>
                <p class="mb-0 text-muted">A modern, secure learning platform for Eng. Eyad Mazhar and his students.</p>
            </div>
            <div class="col-sm-6 col-lg-4">
                <h6 class="mb-3">Quick Links</h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><a href="<?php echo htmlspecialchars($publicRoot, ENT_QUOTES, 'UTF-8'); ?>/index.php">Home</a></li>
                    <li class="mb-2"><a href="<?php echo htmlspecialchars($publicRoot, ENT_QUOTES, 'UTF-8'); ?>/about.php">About</a></li>
                    <li class="mb-2"><a href="<?php echo htmlspecialchars($publicRoot, ENT_QUOTES, 'UTF-8'); ?>/contact.php">Contact</a></li>
                    <li><a href="<?php echo htmlspecialchars($footerLink, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($footerLabel, ENT_QUOTES, 'UTF-8'); ?></a></li>
                </ul>
            </div>
            <div class="col-sm-6 col-lg-4">
                <h6 class="mb-3">Contact</h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><strong>Phone:</strong> 01068161808</li>
                    <li class="mb-2"><strong>Support:</strong> Contact form available on the website</li>
                    <li><strong>Focus:</strong> Lectures, resources, and quizzes</li>
                </ul>
            </div>
        </div>
        <div class="border-top mt-4 pt-3 small text-muted">
            © <?php echo date('Y'); ?> Eng. Eyad Mazhar. All rights reserved.
        </div>
    </div>
</footer>
