<?php
require __DIR__ . '/config/database.php';
$conn = getDbConnection();
if (!$conn) {
    echo "No DB connection\n";
    exit(1);
}
$res = $conn->query('SELECT id, image_path FROM questions WHERE image_path IS NOT NULL AND image_path <> "" LIMIT 10');
while ($row = $res->fetch_assoc()) {
    echo $row['id'] . ' => ' . $row['image_path'] . PHP_EOL;
}
