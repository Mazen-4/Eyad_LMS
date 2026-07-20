<?php
session_start();

function getEnvValue($name, $default = null) {
    $value = getenv($name);
    if ($value === false || $value === '') {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? '';
    }

    return $value !== '' ? $value : $default;
}

$credentials = [];
if (file_exists(__DIR__ . '/../db_credentials.php')) {
    $credentials = include __DIR__ . '/../db_credentials.php';
}

$DB_HOST = getEnvValue('DB_HOST', $credentials['DB_HOST'] ?? 'localhost');
$DB_USER = getEnvValue('DB_USER', $credentials['DB_USER'] ?? 'root');
$DB_PASS = getEnvValue('DB_PASS', $credentials['DB_PASS'] ?? '');
$DB_NAME = getEnvValue('DB_NAME', $credentials['DB_NAME'] ?? 'eyad_lms');
$DB_PORT = (int) getEnvValue('DB_PORT', $credentials['DB_PORT'] ?? 3306);
$DB_SOCKET = getEnvValue('DB_SOCKET', $credentials['DB_SOCKET'] ?? '');

if (PHP_SAPI !== 'cli' && isset($_SERVER['HTTP_HOST']) && strpos(strtolower($_SERVER['HTTP_HOST']), 'localhost') !== false) {
    $DB_HOST = 'localhost';
    $DB_USER = 'root';
    $DB_PASS = '';
    $DB_NAME = 'eyad_lms';
    $DB_PORT = 3306;
    $DB_SOCKET = '';
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function getDbConnection() {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT, $DB_SOCKET;

    static $connection = null;

    if ($connection === null) {
        try {
            $connection = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT, $DB_SOCKET);

            if ($connection->connect_error) {
                $connection = null;
                return null;
            }

            $connection->set_charset('utf8mb4');
        } catch (Throwable $e) {
            $connection = null;
            return null;
        }
    }

    return $connection;
}

function dbFetchOne($sql, $params = []) {
    $conn = getDbConnection();
    if ($conn === null) {
        return null;
    }

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return null;
    }

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
