<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/course_outline_service.php';

header('Content-Type: application/json; charset=utf-8');

function sendJsonResponse(int $statusCode, array $payload)
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    sendJsonResponse(405, ['error' => 'Method not allowed.']);
}

$user = currentUser();
if (empty($user['id'])) {
    sendJsonResponse(401, ['error' => 'Authentication required.']);
}

if (($user['role'] ?? '') !== 'student') {
    sendJsonResponse(403, ['error' => 'Student access required.']);
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if (!is_string($token) || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
    sendJsonResponse(403, ['error' => 'Invalid CSRF token.']);
}

$request = json_decode(file_get_contents('php://input'), true);
if (!is_array($request) || json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse(400, ['error' => 'A valid JSON request body is required.']);
}

$itemId = filter_var($request['item_id'] ?? null, FILTER_VALIDATE_INT);
if ($itemId === false || $itemId === null || $itemId < 1) {
    sendJsonResponse(422, ['error' => 'A valid item_id is required.']);
}

$connection = getDbConnection();
if ($connection === null) {
    sendJsonResponse(503, ['error' => 'The database is currently unavailable.']);
}

try {
    $outlineService = new CourseOutlineService($connection);
    if (!$outlineService->hasCourseSchema()) {
        sendJsonResponse(503, ['error' => 'Course access is not configured yet.']);
    }

    $userId = (int) $user['id'];
    $accountStatement = $connection->prepare(
        "SELECT status FROM users WHERE id = ? AND role = 'student' LIMIT 1"
    );
    $accountStatement->bind_param('i', $userId);
    $accountStatement->execute();
    $account = $accountStatement->get_result()->fetch_assoc();

    if (!$account || !isAccountActive(['status' => $account['status']])) {
        sendJsonResponse(403, ['error' => 'This account is not active.']);
    }

    $groupIds = getUserGroupIds($userId, $connection);
    if ($groupIds === []) {
        sendJsonResponse(404, ['error' => 'Item not found.']);
    }

    $placeholders = implode(', ', array_fill(0, count($groupIds), '?'));
    $accessStatement = $connection->prepare("
        SELECT mi.id
        FROM module_items mi
        INNER JOIN course_modules cm
            ON cm.id = mi.module_id
           AND cm.course_id IS NOT NULL
           AND cm.status = 'active'
        INNER JOIN lectures l
            ON l.id = cm.lecture_id
           AND l.status = 'active'
        WHERE mi.id = ?
          AND mi.status = 'active'
          AND EXISTS (
              SELECT 1
              FROM course_group_access cga
              INNER JOIN lecture_folder_access lfa
                  ON lfa.group_id = cga.group_id
              WHERE cga.course_id = cm.course_id
                AND lfa.lecture_id = cm.lecture_id
                AND cga.group_id IN ($placeholders)
          )
        LIMIT 1
    ");
    $accessParams = array_merge([(int) $itemId], $groupIds);
    bindPreparedParams($accessStatement, $accessParams);
    $accessStatement->execute();

    if (!$accessStatement->get_result()->fetch_assoc()) {
        sendJsonResponse(404, ['error' => 'Item not found.']);
    }

    $progressStatement = $connection->prepare("
        INSERT INTO user_item_progress (user_id, item_id)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE completed_at = completed_at
    ");
    $progressStatement->bind_param('ii', $userId, $itemId);
    $progressStatement->execute();

    sendJsonResponse(200, [
        'ok' => true,
        'item_id' => (int) $itemId,
        'completed' => true,
    ]);
} catch (Throwable $exception) {
    error_log('Failed to mark course item complete: ' . $exception->getMessage());
    sendJsonResponse(500, ['error' => 'Could not record item completion.']);
}
