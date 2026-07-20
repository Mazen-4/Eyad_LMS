<?php
require_once __DIR__ . '/auth.php';
if (!isset($pageTitle)) {
    $pageTitle = 'Eyad LMS';
}
$dashboardLink = 'login.php';
$dashboardLabel = 'Login';
if (isLoggedIn()) {
    $user = currentUser();
    if (!empty($user['role']) && $user['role'] === 'admin') {
        $dashboardLink = '../admin/dashboard.php';
    } else {
        $dashboardLink = '../student/dashboard.php';
    }
    $dashboardLabel = 'Dashboard';
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img src="../Images/eyad_logo1.jpeg" alt="Eyad LMS Logo" style="height: 54px; width: 54px; margin-right: 10px; object-fit: cover; border-radius: 50%; border: 2px solid rgba(255,255,255,0.9); box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
            <span>Eng. Eyad Mazhar</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="publicNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars($dashboardLink, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($dashboardLabel, ENT_QUOTES, 'UTF-8'); ?></a></li>
            </ul>
        </div>
    </div>
</nav>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
