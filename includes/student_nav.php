<?php
if (!isset($user)) {
    $user = currentUser();
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">Eyad LMS Student</a>
        <div class="ms-auto">
            <span class="navbar-text text-white me-3">Welcome, <?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></span>
            <a class="btn btn-outline-light btn-sm" href="../logout.php">Logout</a>
        </div>
    </div>
</nav>
