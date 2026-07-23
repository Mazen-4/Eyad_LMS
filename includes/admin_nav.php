<?php
if (!isset($user)) {
    $user = currentUser();
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <img src="<?php echo htmlspecialchars(appAssetUrl('Images/eyad_logo1.jpeg'), ENT_QUOTES, 'UTF-8'); ?>" alt="Eyad LMS Logo" style="height: 54px; width: 54px; margin-right: 10px; object-fit: cover; border-radius: 50%; border: 2px solid rgba(255,255,255,0.9); box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
            <span>Eng. Eyad Mazhar</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="students.php">Students</a></li>
                <li class="nav-item"><a class="nav-link" href="admins.php">Admins</a></li>
                <li class="nav-item"><a class="nav-link" href="groups.php">Groups</a></li>
                <li class="nav-item"><a class="nav-link" href="lectures.php">Sessions</a></li>
                <li class="nav-item"><a class="nav-link" href="resources.php">Resources</a></li>
                <li class="nav-item"><a class="nav-link" href="quizzes.php">Quizzes</a></li>
                <li class="nav-item"><a class="nav-link" href="activity_log.php">Activity Log</a></li>
            </ul>
            <span class="navbar-text text-white me-3">Welcome, <?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></span>
            <a class="btn btn-outline-light btn-sm" href="../logout.php">Logout</a>
        </div>
    </div>
</nav>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
