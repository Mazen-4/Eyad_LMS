<?php
session_start();

$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_NAME = getenv('DB_NAME') ?: 'eyad_lms';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function getDbConnection() {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

    static $connection = null;

    if ($connection === null) {
        $connection = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
        $connection->set_charset('utf8mb4');
    }

    return $connection;
}

function dbFetchOne($sql, $params = []) {
    $stmt = getDbConnection()->prepare($sql);

    if (!empty($params)) {
        $types = '';
        foreach ($params as $param) {
            $types .= is_int($param) ? 'i' : 's';
        }
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}
?>
