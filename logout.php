<?php
require_once __DIR__ . '/includes/auth.php';

logoutUser();
header('Location: public/login.php');
exit;
