<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/course_content_access.php';

$user = requireLogin(['student', 'admin']);
$conn = getDbConnection();
$isAdmin = ($user['role'] === 'admin');
$groupIds = array_values(array_unique(array_filter(array_map('intval', $user['group_ids'] ?? []))));
if (empty($groupIds) && !empty($user['group_id'])) {
    $groupIds = [(int) $user['group_id']];
}

$resourceId = max(0, (int) ($_GET['resource_id'] ?? 0));
$courseId = max(0, (int) ($_GET['course_id'] ?? 0));
$resource = getAccessibleResource($conn, $resourceId, $groupIds, $isAdmin, $courseId);
if (!$resource) {
    http_response_code(403);
    exit('This resource is not available to your account.');
}

$rootPath = realpath(__DIR__ . '/..');
$relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $resource['pdf_path']);
$filePath = $rootPath !== false
    ? realpath($rootPath . DIRECTORY_SEPARATOR . ltrim($relativePath, DIRECTORY_SEPARATOR))
    : false;
$rootPrefix = $rootPath !== false ? rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : '';
if (
    $filePath === false
    || $rootPrefix === ''
    || stripos($filePath, $rootPrefix) !== 0
    || !is_file($filePath)
) {
    http_response_code(404);
    exit('The PDF file could not be found.');
}

header('Content-Type: application/pdf');
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: inline; filename*=UTF-8\'\'' . rawurlencode(basename($filePath)));
header('X-Content-Type-Options: nosniff');
readfile($filePath);
