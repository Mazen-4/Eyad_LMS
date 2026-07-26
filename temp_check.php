<?php
require 'config/database.php';
$conn = getDbConnection();
if (!$conn) {
    echo "no_connection\n";
    exit(1);
}

$res = $conn->query("SHOW TABLES LIKE 'quiz_extra_attempts'");
echo "table_exists=" . ($res->num_rows > 0 ? 'yes' : 'no') . "\n";
if ($res->num_rows > 0) {
    $cols = $conn->query('SHOW COLUMNS FROM quiz_extra_attempts');
    while ($c = $cols->fetch_assoc()) {
        echo $c['Field'] . ' ' . $c['Type'] . "\n";
    }
    echo "----\n";
    $sql = "SELECT qea.id, q.title AS quiz_title, u.name AS student_name, u.username, qea.extra_attempts, qea.reason, qea.created_at FROM quiz_extra_attempts qea INNER JOIN quizzes q ON q.id = qea.quiz_id INNER JOIN users u ON u.id = qea.student_id ORDER BY qea.created_at DESC";
    $result = $conn->query($sql);
    if ($result === false) {
        echo 'query_error=' . $conn->error . "\n";
    } else {
        echo 'rows=' . $result->num_rows . "\n";
    }
}
