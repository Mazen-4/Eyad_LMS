<?php
require __DIR__ . '/config/database.php';
$conn = getDbConnection();
if ($conn === null) {
    echo "FAIL: no DB connection\n";
    exit(1);
}
$res = $conn->query("SELECT id, username, name FROM users WHERE role='admin' LIMIT 5");
if ($res === false) {
    echo "FAIL: query error\n";
    exit(1);
}
while ($row = $res->fetch_assoc()) {
    echo $row['id'] . ' ' . $row['username'] . ' ' . $row['name'] . "\n";
}

echo "\nQUIZZES:\n";
$res2 = $conn->query("SELECT id, title, status, group_id FROM quizzes ORDER BY created_at DESC LIMIT 20");
if ($res2 === false) {
    echo "FAIL: quiz query error\n";
    exit(1);
}
$quizIds = [];
while ($row = $res2->fetch_assoc()) {
    echo $row['id'] . ' | ' . $row['title'] . ' | ' . $row['status'] . ' | group=' . $row['group_id'] . "\n";
    $quizIds[] = (int)$row['id'];
}

echo "\nQUIZ QUESTIONS:\n";
foreach ($quizIds as $quizId) {
    $res3 = $conn->query("SELECT id, question, image_path FROM questions WHERE quiz_id = " . (int)$quizId . " ORDER BY id");
    if ($res3 === false) {
        echo "FAIL: question query error for quiz " . $quizId . "\n";
        continue;
    }
    echo "Quiz " . $quizId . ":\n";
    while ($row = $res3->fetch_assoc()) {
        echo '  ' . $row['id'] . ' | image=' . ($row['image_path'] ?: '<none>') . ' | ' . substr($row['question'], 0, 80) . "\n";
    }
}
