<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['admin']);
$conn = getDbConnection();

$_SESSION['admin_courses_csrf'] ??= bin2hex(random_bytes(32));
$csrfToken = $_SESSION['admin_courses_csrf'];
$success = isset($_GET['notice']) ? (string) $_GET['notice'] : '';
$error = '';

function adminCoursesEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function adminCoursesRequireCsrf(): void
{
    $expected = $_SESSION['admin_courses_csrf'] ?? '';
    $provided = $_POST['csrf_token'] ?? '';
    if (!is_string($provided) || $expected === '' || !hash_equals($expected, $provided)) {
        throw new RuntimeException('Your security token expired. Refresh the page and try again.');
    }
}

function adminCoursesBindParams(mysqli_stmt $statement, array $params): void
{
    if ($params === []) {
        return;
    }

    $types = '';
    $references = [];
    foreach ($params as $index => $value) {
        $types .= is_int($value) ? 'i' : 's';
        $references[$index] = &$params[$index];
    }
    $statement->bind_param($types, ...$references);
}

function adminCoursesNormalizeVideoUrl(string $url, string $type): string
{
    $url = trim($url);
    if ($url === '') {
        throw new RuntimeException('A content URL or application path is required.');
    }

    if ($type === 'video' && preg_match('/^[A-Za-z0-9_-]{20,}$/', $url)) {
        return 'https://drive.google.com/file/d/' . $url . '/preview?rm=minimal';
    }

    if (preg_match('#^https?://#i', $url)) {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            throw new RuntimeException('Enter a valid HTTP or HTTPS URL.');
        }

        $host = strtolower($parts['host']);
        if ($type === 'video') {
            if (preg_match('#/(?:drive/)?folders/[^/?#]+#i', $parts['path'] ?? '') === 1) {
                throw new RuntimeException('This is a Drive folder link. Add a direct video link instead.');
            }

            if (preg_match('#/file/d/([^/?#]+)#i', $url, $matches)) {
                return 'https://drive.google.com/file/d/' . rawurlencode($matches[1]) . '/preview?rm=minimal';
            }

            if (preg_match('#[?&]id=([^&#]+)#i', $url, $matches) && strpos($host, 'drive.google.com') !== false) {
                return 'https://drive.google.com/file/d/' . rawurlencode($matches[1]) . '/preview?rm=minimal';
            }

            if ($host === 'youtu.be' && preg_match('#^/([A-Za-z0-9_-]+)#', $parts['path'] ?? '', $matches)) {
                return 'https://www.youtube-nocookie.com/embed/' . $matches[1];
            }

            if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
                parse_str($parts['query'] ?? '', $query);
                if (!empty($query['v']) && preg_match('/^[A-Za-z0-9_-]+$/', $query['v'])) {
                    return 'https://www.youtube-nocookie.com/embed/' . $query['v'];
                }
            }
        }

        return $url;
    }

    if (strpos($url, '//') === 0 || preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
        throw new RuntimeException('Use an HTTP(S) URL or a local application path.');
    }

    $path = ltrim($url, '/');
    foreach (explode('/', $path) as $segment) {
        if ($segment === '..') {
            throw new RuntimeException('Parent-directory paths are not allowed.');
        }
    }

    return '/' . $path;
}

function adminCoursesHasSchema(mysqli $connection): bool
{
    $statement = $connection->prepare("
        SELECT
            (SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name IN ('courses', 'course_group_access', 'module_items')) AS table_count,
            (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'course_modules'
               AND column_name = 'course_id') AS column_count
    ");
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();

    return (int) ($row['table_count'] ?? 0) === 3
        && (int) ($row['column_count'] ?? 0) === 1;
}

function adminCoursesHasContentLinkSchema(mysqli $connection): bool
{
    $statement = $connection->prepare("
        SELECT COUNT(*) AS table_count
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name IN ('course_resources', 'course_quizzes')
    ");
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();

    return (int) ($row['table_count'] ?? 0) === 2;
}

$schemaReady = adminCoursesHasSchema($conn);
$contentLinksReady = adminCoursesHasContentLinkSchema($conn);
$courseId = max(0, (int) ($_GET['course_id'] ?? 0));
$selectedSessionId = max(0, (int) ($_GET['session_id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        adminCoursesRequireCsrf();
        if (!$schemaReady) {
            throw new RuntimeException('The course database migration has not been applied. Apply database/course_entities_migration.sql first.');
        }

        $action = $_POST['action'] ?? '';
        if (in_array($action, ['assign_resource', 'unassign_resource', 'assign_quiz', 'unassign_quiz'], true) && !$contentLinksReady) {
            throw new RuntimeException('Apply database/course_content_links_migration.sql before linking resources or quizzes to courses.');
        }

        if ($action === 'save_course') {
            $id = max(0, (int) ($_POST['course_id'] ?? 0));
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $displayOrder = filter_var($_POST['display_order'] ?? '0', FILTER_VALIDATE_INT);
            $status = (string) ($_POST['status'] ?? 'draft');
            $groupIds = array_values(array_unique(array_filter(
                array_map('intval', (array) ($_POST['group_ids'] ?? [])),
                static function (int $groupId): bool {
                    return $groupId > 0;
                }
            )));

            if ($title === '') {
                throw new RuntimeException('Course title is required.');
            }
            if (strlen($title) > 255) {
                throw new RuntimeException('Course title must be 255 characters or fewer.');
            }
            if ($displayOrder === false) {
                throw new RuntimeException('Display order must be a whole number.');
            }
            if (!in_array($status, ['active', 'draft', 'archived'], true)) {
                throw new RuntimeException('Choose a valid course status.');
            }
            if ($status === 'active' && $groupIds === []) {
                throw new RuntimeException('Assign at least one student group before activating a course.');
            }

            $conn->begin_transaction();
            if ($id > 0) {
                $update = $conn->prepare('UPDATE courses SET title = ?, description = ?, display_order = ?, status = ? WHERE id = ?');
                $update->bind_param('ssisi', $title, $description, $displayOrder, $status, $id);
                $update->execute();
                if ($update->affected_rows === 0) {
                    $exists = $conn->prepare('SELECT id FROM courses WHERE id = ? LIMIT 1');
                    $exists->bind_param('i', $id);
                    $exists->execute();
                    if (!$exists->get_result()->fetch_assoc()) {
                        throw new RuntimeException('The course no longer exists.');
                    }
                }
            } else {
                $insert = $conn->prepare('INSERT INTO courses (title, description, display_order, status) VALUES (?, ?, ?, ?)');
                $insert->bind_param('ssis', $title, $description, $displayOrder, $status);
                $insert->execute();
                $id = (int) $insert->insert_id;
            }

            if ($groupIds !== []) {
                $placeholders = implode(', ', array_fill(0, count($groupIds), '?'));
                $checkGroups = $conn->prepare("SELECT COUNT(DISTINCT id) AS group_count FROM `groups` WHERE id IN ($placeholders)");
                adminCoursesBindParams($checkGroups, $groupIds);
                $checkGroups->execute();
                $validGroupCount = (int) $checkGroups->get_result()->fetch_assoc()['group_count'];
                if ($validGroupCount !== count($groupIds)) {
                    throw new RuntimeException('One or more selected groups no longer exist.');
                }

                $invalidAssignments = $conn->prepare("
                    SELECT cm.id
                    FROM course_modules cm
                    WHERE cm.course_id = ?
                      AND NOT EXISTS (
                          SELECT 1
                          FROM lecture_folder_access lfa
                          WHERE lfa.lecture_id = cm.lecture_id
                            AND lfa.group_id IN ($placeholders)
                      )
                    LIMIT 1
                ");
                adminCoursesBindParams($invalidAssignments, array_merge([$id], $groupIds));
                $invalidAssignments->execute();
                if ($invalidAssignments->get_result()->fetch_assoc()) {
                    throw new RuntimeException('The selected groups would lose access to an assigned session. Adjust the session access groups first.');
                }
            } else {
                $assignedSessions = $conn->prepare('SELECT id FROM course_modules WHERE course_id = ? LIMIT 1');
                $assignedSessions->bind_param('i', $id);
                $assignedSessions->execute();
                if ($assignedSessions->get_result()->fetch_assoc()) {
                    throw new RuntimeException('This course has sessions assigned. Keep at least one course group or unassign its sessions first.');
                }
            }

            $clearGroups = $conn->prepare('DELETE FROM course_group_access WHERE course_id = ?');
            $clearGroups->bind_param('i', $id);
            $clearGroups->execute();
            if ($groupIds !== []) {
                $addGroup = $conn->prepare('INSERT INTO course_group_access (course_id, group_id) VALUES (?, ?)');
                foreach ($groupIds as $groupId) {
                    $addGroup->bind_param('ii', $id, $groupId);
                    $addGroup->execute();
                }
            }
            $conn->commit();

            $courseId = $id;
            $success = 'Course saved. Existing session access permissions were preserved.';
        } elseif ($action === 'assign_session') {
            $targetCourseId = max(0, (int) ($_POST['course_id'] ?? 0));
            $moduleId = max(0, (int) ($_POST['module_id'] ?? 0));
            if ($targetCourseId < 1 || $moduleId < 1) {
                throw new RuntimeException('Choose a course and a session.');
            }

            $assignmentCheck = $conn->prepare("
                SELECT cm.id
                FROM course_modules cm
                INNER JOIN courses c ON c.id = ?
                WHERE cm.id = ?
                  AND (cm.course_id IS NULL OR cm.course_id = c.id)
                  AND EXISTS (
                      SELECT 1
                      FROM course_group_access cga
                      INNER JOIN lecture_folder_access lfa
                          ON lfa.group_id = cga.group_id
                      WHERE cga.course_id = c.id
                        AND lfa.lecture_id = cm.lecture_id
                  )
                LIMIT 1
            ");
            $assignmentCheck->bind_param('ii', $targetCourseId, $moduleId);
            $assignmentCheck->execute();
            if (!$assignmentCheck->get_result()->fetch_assoc()) {
                throw new RuntimeException('Session not assigned: ensure it is unassigned (or already in this course) and at least one course group has session access.');
            }

            $assign = $conn->prepare('UPDATE course_modules SET course_id = ? WHERE id = ? AND (course_id IS NULL OR course_id = ?)');
            $assign->bind_param('iii', $targetCourseId, $moduleId, $targetCourseId);
            $assign->execute();
            $courseId = $targetCourseId;
            $success = 'Session assigned to the course.';
        } elseif ($action === 'unassign_session') {
            $targetCourseId = max(0, (int) ($_POST['course_id'] ?? 0));
            $moduleId = max(0, (int) ($_POST['module_id'] ?? 0));
            $unassign = $conn->prepare('UPDATE course_modules SET course_id = NULL WHERE id = ? AND course_id = ?');
            $unassign->bind_param('ii', $moduleId, $targetCourseId);
            $unassign->execute();
            if ($unassign->affected_rows !== 1) {
                throw new RuntimeException('The session was not assigned to this course.');
            }
            $courseId = $targetCourseId;
            $success = 'Session unassigned. Its legacy group permissions remain unchanged.';
        } elseif ($action === 'assign_resource' || $action === 'assign_quiz') {
            $targetCourseId = max(0, (int) ($_POST['course_id'] ?? 0));
            $contentId = max(0, (int) ($_POST['content_id'] ?? 0));
            $displayOrder = filter_var($_POST['display_order'] ?? '0', FILTER_VALIDATE_INT);
            $isResource = $action === 'assign_resource';
            $table = $isResource ? 'resources' : 'quizzes';
            $linkTable = $isResource ? 'course_resources' : 'course_quizzes';
            $idColumn = $isResource ? 'resource_id' : 'quiz_id';

            if ($targetCourseId < 1 || $contentId < 1) {
                throw new RuntimeException('Choose a course and existing content item.');
            }
            if ($displayOrder === false || $displayOrder < 0) {
                throw new RuntimeException('Display order must be a non-negative whole number.');
            }

            $courseCheck = $conn->prepare('SELECT id FROM courses WHERE id = ? LIMIT 1');
            $courseCheck->bind_param('i', $targetCourseId);
            $courseCheck->execute();
            if (!$courseCheck->get_result()->fetch_assoc()) {
                throw new RuntimeException('The selected course no longer exists.');
            }
            $contentCheck = $conn->prepare("SELECT id FROM `$table` WHERE id = ? AND status = 'active' LIMIT 1");
            $contentCheck->bind_param('i', $contentId);
            $contentCheck->execute();
            if (!$contentCheck->get_result()->fetch_assoc()) {
                throw new RuntimeException('Choose an existing active resource or quiz.');
            }

            $assign = $conn->prepare("INSERT INTO `$linkTable` (course_id, `$idColumn`, display_order) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE display_order = VALUES(display_order)");
            $assign->bind_param('iii', $targetCourseId, $contentId, $displayOrder);
            $assign->execute();
            $courseId = $targetCourseId;
            $success = $isResource ? 'Resource linked to the course.' : 'Quiz linked to the course.';
        } elseif ($action === 'unassign_resource' || $action === 'unassign_quiz') {
            $targetCourseId = max(0, (int) ($_POST['course_id'] ?? 0));
            $contentId = max(0, (int) ($_POST['content_id'] ?? 0));
            $isResource = $action === 'unassign_resource';
            $linkTable = $isResource ? 'course_resources' : 'course_quizzes';
            $idColumn = $isResource ? 'resource_id' : 'quiz_id';

            $remove = $conn->prepare("DELETE FROM `$linkTable` WHERE course_id = ? AND `$idColumn` = ?");
            $remove->bind_param('ii', $targetCourseId, $contentId);
            $remove->execute();
            if ($remove->affected_rows !== 1) {
                throw new RuntimeException('The content item was not linked to this course.');
            }
            $courseId = $targetCourseId;
            $success = $isResource ? 'Resource unlinked. Its legacy group permissions are active again.' : 'Quiz unlinked. Its legacy group permissions are active again.';
        } elseif ($action === 'save_item') {
            $targetCourseId = max(0, (int) ($_POST['course_id'] ?? 0));
            $moduleId = max(0, (int) ($_POST['module_id'] ?? 0));
            $itemId = max(0, (int) ($_POST['item_id'] ?? 0));
            $type = (string) ($_POST['item_type'] ?? '');
            $title = trim((string) ($_POST['item_title'] ?? ''));
            $displayOrder = filter_var($_POST['item_order'] ?? '0', FILTER_VALIDATE_INT);
            $contentUrl = adminCoursesNormalizeVideoUrl((string) ($_POST['content_url'] ?? ''), $type);

            if (!in_array($type, ['video', 'quiz', 'document'], true)) {
                throw new RuntimeException('Choose a valid item type.');
            }
            if ($title === '' || strlen($title) > 255) {
                throw new RuntimeException('Enter an item title of 1 to 255 characters.');
            }
            if ($displayOrder === false || $displayOrder < 0) {
                throw new RuntimeException('Step order must be a non-negative whole number.');
            }

            $moduleCheck = $conn->prepare('SELECT id FROM course_modules WHERE id = ? AND course_id = ? LIMIT 1');
            $moduleCheck->bind_param('ii', $moduleId, $targetCourseId);
            $moduleCheck->execute();
            if (!$moduleCheck->get_result()->fetch_assoc()) {
                throw new RuntimeException('Select a session assigned to this course.');
            }

            if ($itemId > 0) {
                $save = $conn->prepare('UPDATE module_items SET item_type = ?, title = ?, content_url = ?, display_order = ?, status = "active" WHERE id = ? AND module_id = ?');
                $save->bind_param('sssiii', $type, $title, $contentUrl, $displayOrder, $itemId, $moduleId);
                $save->execute();
                if ($save->affected_rows === 0) {
                    $itemCheck = $conn->prepare('SELECT id FROM module_items WHERE id = ? AND module_id = ? LIMIT 1');
                    $itemCheck->bind_param('ii', $itemId, $moduleId);
                    $itemCheck->execute();
                    if (!$itemCheck->get_result()->fetch_assoc()) {
                        throw new RuntimeException('The item no longer exists in this session.');
                    }
                }
                $success = 'Item updated. Existing completion history was retained.';
            } else {
                $save = $conn->prepare('INSERT INTO module_items (module_id, item_type, title, content_url, display_order, status) VALUES (?, ?, ?, ?, ?, "active")');
                $save->bind_param('isssi', $moduleId, $type, $title, $contentUrl, $displayOrder);
                $save->execute();
                $success = 'Item added to the session.';
            }
            $courseId = $targetCourseId;
            $selectedSessionId = $moduleId;
        } elseif ($action === 'archive_item') {
            $targetCourseId = max(0, (int) ($_POST['course_id'] ?? 0));
            $moduleId = max(0, (int) ($_POST['module_id'] ?? 0));
            $itemId = max(0, (int) ($_POST['item_id'] ?? 0));
            $archive = $conn->prepare("
                UPDATE module_items mi
                INNER JOIN course_modules cm ON cm.id = mi.module_id
                SET mi.status = 'archived'
                WHERE mi.id = ? AND cm.id = ? AND cm.course_id = ?
            ");
            $archive->bind_param('iii', $itemId, $moduleId, $targetCourseId);
            $archive->execute();
            if ($archive->affected_rows !== 1) {
                throw new RuntimeException('Item could not be archived; it may already be archived or belong to another course.');
            }
            $courseId = $targetCourseId;
            $selectedSessionId = $moduleId;
            $success = 'Item archived. Its completion history was retained.';
        } else {
            throw new RuntimeException('Unknown course action.');
        }
    } catch (Throwable $exception) {
        if ($conn->in_transaction) {
            $conn->rollback();
        }
        $error = $exception->getMessage();
        $courseId = max(0, (int) ($_POST['course_id'] ?? $_POST['course_id_input'] ?? $courseId));
        $selectedSessionId = max(0, (int) ($_POST['module_id'] ?? $selectedSessionId));
    }

    if ($error === '' && $success !== '') {
        $location = 'courses.php?notice=' . rawurlencode($success);
        if ($courseId > 0) {
            $location .= '&course_id=' . $courseId;
        }
        if ($selectedSessionId > 0) {
            $location .= '&session_id=' . $selectedSessionId;
        }
        header('Location: ' . $location, true, 303);
        exit;
    }
}

$groups = [];
$groupsResult = $conn->query('SELECT id, name, level FROM `groups` ORDER BY name');
while ($row = $groupsResult->fetch_assoc()) {
    $groups[] = $row;
}

$courses = [];
$courseGroups = [];
$sessions = [];
$availableModules = [];
$items = [];
$courseResources = [];
$courseQuizzes = [];
$availableResources = [];
$availableQuizzes = [];
$editingCourse = null;
$selectedModule = null;

if ($schemaReady) {
    $courseResult = $conn->query("
        SELECT c.id, c.title, c.description, c.display_order, c.status,
               COUNT(DISTINCT cm.id) AS session_count
        FROM courses c
        LEFT JOIN course_modules cm ON cm.course_id = c.id
        GROUP BY c.id
        ORDER BY c.display_order, c.id
    ");
    while ($row = $courseResult->fetch_assoc()) {
        $courses[] = $row;
    }

    if ($courseId > 0) {
        $courseStatement = $conn->prepare('SELECT id, title, description, display_order, status FROM courses WHERE id = ? LIMIT 1');
        $courseStatement->bind_param('i', $courseId);
        $courseStatement->execute();
        $editingCourse = $courseStatement->get_result()->fetch_assoc();

        if ($editingCourse) {
            $groupStatement = $conn->prepare('SELECT group_id FROM course_group_access WHERE course_id = ?');
            $groupStatement->bind_param('i', $courseId);
            $groupStatement->execute();
            $groupResult = $groupStatement->get_result();
            while ($row = $groupResult->fetch_assoc()) {
                $courseGroups[(int) $row['group_id']] = true;
            }

            if ($contentLinksReady) {
                $resourceStatement = $conn->prepare("
                    SELECT r.id, r.title, cr.display_order
                    FROM course_resources cr
                    INNER JOIN resources r ON r.id = cr.resource_id
                    WHERE cr.course_id = ?
                    ORDER BY cr.display_order, r.title
                ");
                $resourceStatement->bind_param('i', $courseId);
                $resourceStatement->execute();
                $courseResources = $resourceStatement->get_result()->fetch_all(MYSQLI_ASSOC);

                $quizStatement = $conn->prepare("
                    SELECT q.id, q.title, cq.display_order
                    FROM course_quizzes cq
                    INNER JOIN quizzes q ON q.id = cq.quiz_id
                    WHERE cq.course_id = ?
                    ORDER BY cq.display_order, q.title
                ");
                $quizStatement->bind_param('i', $courseId);
                $quizStatement->execute();
                $courseQuizzes = $quizStatement->get_result()->fetch_all(MYSQLI_ASSOC);

                $availableResources = $conn->query("SELECT id, title FROM resources WHERE status = 'active' ORDER BY title")->fetch_all(MYSQLI_ASSOC);
                $availableQuizzes = $conn->query("SELECT id, title FROM quizzes WHERE status = 'active' ORDER BY title")->fetch_all(MYSQLI_ASSOC);
            }

            $sessionsStatement = $conn->prepare("
                SELECT cm.id AS module_id, cm.lecture_id, cm.title AS session_title,
                       cm.display_order, l.status AS lecture_status
                FROM course_modules cm
                INNER JOIN lectures l ON l.id = cm.lecture_id
                WHERE cm.course_id = ?
                ORDER BY cm.display_order, cm.id
            ");
            $sessionsStatement->bind_param('i', $courseId);
            $sessionsStatement->execute();
            $sessionsResult = $sessionsStatement->get_result();
            while ($row = $sessionsResult->fetch_assoc()) {
                $sessions[] = $row;
            }

            $availableStatement = $conn->prepare("
                SELECT cm.id AS module_id, cm.lecture_id, cm.title AS session_title,
                       cm.display_order, l.status AS lecture_status
                FROM course_modules cm
                INNER JOIN lectures l ON l.id = cm.lecture_id
                WHERE cm.course_id IS NULL
                  AND l.status = 'active'
                  AND EXISTS (
                      SELECT 1
                      FROM course_group_access cga
                      INNER JOIN lecture_folder_access lfa
                          ON lfa.group_id = cga.group_id
                      WHERE cga.course_id = ?
                        AND lfa.lecture_id = cm.lecture_id
                  )
                ORDER BY cm.display_order, cm.id
            ");
            $availableStatement->bind_param('i', $courseId);
            $availableStatement->execute();
            $availableResult = $availableStatement->get_result();
            while ($row = $availableResult->fetch_assoc()) {
                $availableModules[] = $row;
            }

            if ($selectedSessionId > 0) {
                $selectedStatement = $conn->prepare('SELECT id, title FROM course_modules WHERE id = ? AND course_id = ? LIMIT 1');
                $selectedStatement->bind_param('ii', $selectedSessionId, $courseId);
                $selectedStatement->execute();
                $selectedModule = $selectedStatement->get_result()->fetch_assoc();

                if ($selectedModule) {
                    $itemsStatement = $conn->prepare("
                        SELECT id, item_type, title, content_url, display_order, status
                        FROM module_items
                        WHERE module_id = ?
                        ORDER BY (status = 'active') DESC, display_order, id
                    ");
                    $itemsStatement->bind_param('i', $selectedSessionId);
                    $itemsStatement->execute();
                    $itemsResult = $itemsStatement->get_result();
                    while ($row = $itemsResult->fetch_assoc()) {
                        $items[] = $row;
                    }
                }
            }
        } else {
            $error = $error !== '' ? $error : 'The selected course was not found.';
            $courseId = 0;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Courses - Admin</title>
    <link rel="icon" href="<?php echo adminCoursesEscape(appAssetUrl('Images/eyad_logo1.jpeg')); ?>" type="image/jpeg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo adminCoursesEscape(appVersionedAssetUrl('assets/css/theme.css')); ?>" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/admin_nav.php'; ?>
    <main class="container py-4">
        <a href="dashboard.php" class="btn btn-outline-secondary mb-3">&larr; Back to Dashboard</a>
        <h1 class="mb-2">Courses &amp; Outline</h1>
        <p class="text-muted">Organize existing sessions into courses, link student groups, and attach existing PDF resources and quizzes.</p>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success" role="status"><?php echo adminCoursesEscape($success); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert"><?php echo adminCoursesEscape($error); ?></div>
        <?php endif; ?>

        <?php if (!$schemaReady): ?>
            <div class="alert alert-warning">
                Course management is unavailable until the course schema migration is applied.
                Back up the local database, then import <code>database/course_entities_migration.sql</code>
                after <code>database/course_sessions_modules_migration.sql</code>.
            </div>
        <?php else: ?>
            <?php if (!$contentLinksReady): ?>
                <div class="alert alert-warning" role="status">
                    Course resource and quiz linking requires <code>database/course_content_links_migration.sql</code>.
                </div>
            <?php endif; ?>
            <div class="row g-4">
                <div class="col-12 col-xl-4">
                    <section class="card shadow-sm">
                        <div class="card-body">
                            <h2 class="h4"><?php echo $editingCourse ? 'Edit course' : 'Create course'; ?></h2>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo adminCoursesEscape($csrfToken); ?>">
                                <input type="hidden" name="action" value="save_course">
                                <input type="hidden" name="course_id" value="<?php echo (int) ($editingCourse['id'] ?? 0); ?>">
                                <div class="mb-3">
                                    <label class="form-label" for="course-title">Title</label>
                                    <input class="form-control" id="course-title" name="title" maxlength="255" required value="<?php echo adminCoursesEscape($editingCourse['title'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="course-description">Description</label>
                                    <textarea class="form-control" id="course-description" name="description" rows="3"><?php echo adminCoursesEscape($editingCourse['description'] ?? ''); ?></textarea>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label" for="course-order">Display order</label>
                                        <input class="form-control" type="number" id="course-order" name="display_order" value="<?php echo (int) ($editingCourse['display_order'] ?? 0); ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="course-status">Status</label>
                                        <?php $courseStatus = $editingCourse['status'] ?? 'draft'; ?>
                                        <select class="form-select" id="course-status" name="status">
                                            <option value="draft" <?php echo $courseStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                            <option value="active" <?php echo $courseStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="archived" <?php echo $courseStatus === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                        </select>
                                    </div>
                                </div>
                                <fieldset class="mb-3">
                                    <legend class="fs-6">Student groups</legend>
                                    <?php if ($groups === []): ?>
                                        <p class="text-muted mb-0">Create a group before activating a course.</p>
                                    <?php else: ?>
                                        <?php foreach ($groups as $group): ?>
                                            <label class="form-check mb-1">
                                                <input class="form-check-input" type="checkbox" name="group_ids[]" value="<?php echo (int) $group['id']; ?>" <?php echo isset($courseGroups[(int) $group['id']]) ? 'checked' : ''; ?>>
                                                <span class="form-check-label"><?php echo adminCoursesEscape($group['name']); ?><?php echo !empty($group['level']) ? ' - ' . adminCoursesEscape($group['level']) : ''; ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <div class="form-text">Linked PDF resources and quizzes use course-group access. Sessions continue to require the existing group permissions on the Sessions page.</div>
                                </fieldset>
                                <button class="btn btn-primary" type="submit">Save course</button>
                                <?php if ($editingCourse): ?>
                                    <a class="btn btn-outline-secondary" href="courses.php">Cancel edit</a>
                                <?php endif; ?>
                            </form>
                                <?php if ($editingCourse && $contentLinksReady): ?>
                                    <section class="card shadow-sm mt-4">
                                        <div class="card-body">
                                            <h2 class="h4">Course resources</h2>
                                            <p class="text-muted">Linked resources are shown to students with access to this course. Unlinked resources keep their existing group access.</p>
                                            <form method="post" class="row g-2 align-items-end mb-3">
                                                <input type="hidden" name="csrf_token" value="<?php echo adminCoursesEscape($csrfToken); ?>">
                                                <input type="hidden" name="action" value="assign_resource">
                                                <input type="hidden" name="course_id" value="<?php echo $courseId; ?>">
                                                <div class="col-12 col-md">
                                                    <label class="form-label" for="course-resource">Existing PDF resource</label>
                                                    <select class="form-select" id="course-resource" name="content_id" required>
                                                        <option value="">Select resource</option>
                                                        <?php foreach ($availableResources as $resource): ?>
                                                            <option value="<?php echo (int) $resource['id']; ?>"><?php echo adminCoursesEscape($resource['title']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-6 col-md-2">
                                                    <label class="form-label" for="course-resource-order">Display order</label>
                                                    <input class="form-control" type="number" min="0" id="course-resource-order" name="display_order" value="0">
                                                </div>
                                                <div class="col-6 col-md-auto"><button class="btn btn-primary" type="submit">Link resource</button></div>
                                            </form>
                                            <?php if ($courseResources === []): ?>
                                                <p class="text-muted mb-0">No resources are linked to this course yet.</p>
                                            <?php else: ?>
                                                <ul class="list-group">
                                                    <?php foreach ($courseResources as $resource): ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                                                            <span><?php echo adminCoursesEscape($resource['title']); ?> <span class="text-muted small">(order <?php echo (int) $resource['display_order']; ?>)</span></span>
                                                            <form method="post" class="m-0">
                                                                <input type="hidden" name="csrf_token" value="<?php echo adminCoursesEscape($csrfToken); ?>">
                                                                <input type="hidden" name="action" value="unassign_resource">
                                                                <input type="hidden" name="course_id" value="<?php echo $courseId; ?>">
                                                                <input type="hidden" name="content_id" value="<?php echo (int) $resource['id']; ?>">
                                                                <button class="btn btn-sm btn-outline-danger" type="submit">Unlink</button>
                                                            </form>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    </section>

                                    <section class="card shadow-sm mt-4">
                                        <div class="card-body">
                                            <h2 class="h4">Course quizzes</h2>
                                            <p class="text-muted">Linked quizzes are shown to students with access to this course. Unlinked quizzes keep their existing group access.</p>
                                            <form method="post" class="row g-2 align-items-end mb-3">
                                                <input type="hidden" name="csrf_token" value="<?php echo adminCoursesEscape($csrfToken); ?>">
                                                <input type="hidden" name="action" value="assign_quiz">
                                                <input type="hidden" name="course_id" value="<?php echo $courseId; ?>">
                                                <div class="col-12 col-md">
                                                    <label class="form-label" for="course-quiz">Existing quiz</label>
                                                    <select class="form-select" id="course-quiz" name="content_id" required>
                                                        <option value="">Select quiz</option>
                                                        <?php foreach ($availableQuizzes as $quiz): ?>
                                                            <option value="<?php echo (int) $quiz['id']; ?>"><?php echo adminCoursesEscape($quiz['title']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-6 col-md-2">
                                                    <label class="form-label" for="course-quiz-order">Display order</label>
                                                    <input class="form-control" type="number" min="0" id="course-quiz-order" name="display_order" value="0">
                                                </div>
                                                <div class="col-6 col-md-auto"><button class="btn btn-primary" type="submit">Link quiz</button></div>
                                            </form>
                                            <?php if ($courseQuizzes === []): ?>
                                                <p class="text-muted mb-0">No quizzes are linked to this course yet.</p>
                                            <?php else: ?>
                                                <ul class="list-group">
                                                    <?php foreach ($courseQuizzes as $quiz): ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                                                            <span><?php echo adminCoursesEscape($quiz['title']); ?> <span class="text-muted small">(order <?php echo (int) $quiz['display_order']; ?>)</span></span>
                                                            <form method="post" class="m-0">
                                                                <input type="hidden" name="csrf_token" value="<?php echo adminCoursesEscape($csrfToken); ?>">
                                                                <input type="hidden" name="action" value="unassign_quiz">
                                                                <input type="hidden" name="course_id" value="<?php echo $courseId; ?>">
                                                                <input type="hidden" name="content_id" value="<?php echo (int) $quiz['id']; ?>">
                                                                <button class="btn btn-sm btn-outline-danger" type="submit">Unlink</button>
                                                            </form>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    </section>
                                <?php endif; ?>

                        </div>
                    </section>
                </div>

                <div class="col-12 col-xl-8">
                    <section class="card shadow-sm">
                        <div class="card-body">
                            <h2 class="h4">Existing courses</h2>
                            <?php if ($courses === []): ?>
                                <p class="text-muted mb-0">No courses have been created yet. Existing sessions remain available in the legacy Sessions list until assigned.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead>
                                            <tr><th>Course</th><th>Status</th><th>Sessions</th><th></th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($courses as $course): ?>
                                                <tr>
                                                    <td><?php echo adminCoursesEscape($course['title']); ?></td>
                                                    <td><span class="badge text-bg-<?php echo $course['status'] === 'active' ? 'success' : ($course['status'] === 'draft' ? 'secondary' : 'dark'); ?>"><?php echo adminCoursesEscape(ucfirst($course['status'])); ?></span></td>
                                                    <td><?php echo (int) $course['session_count']; ?></td>
                                                    <td class="text-end">
                                                        <a class="btn btn-sm btn-outline-primary" href="courses.php?course_id=<?php echo (int) $course['id']; ?>">Manage</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <?php if ($editingCourse): ?>
                        <section class="card shadow-sm mt-4">
                            <div class="card-body">
                                <h2 class="h4">Sessions in <?php echo adminCoursesEscape($editingCourse['title']); ?></h2>
                                <p class="text-muted">Only sessions whose existing lecture-group access overlaps this course’s groups can be assigned.</p>

                                <?php if ($availableModules !== []): ?>
                                    <form method="post" class="row g-2 align-items-end mb-4">
                                        <input type="hidden" name="csrf_token" value="<?php echo adminCoursesEscape($csrfToken); ?>">
                                        <input type="hidden" name="action" value="assign_session">
                                        <input type="hidden" name="course_id" value="<?php echo $courseId; ?>">
                                        <div class="col-12 col-md">
                                            <label class="form-label" for="assign-module">Unassigned session</label>
                                            <select class="form-select" id="assign-module" name="module_id" required>
                                                <option value="">Select session</option>
                                                <?php foreach ($availableModules as $module): ?>
                                                    <?php if ($module['lecture_status'] !== 'active') { continue; } ?>
                                                    <option value="<?php echo (int) $module['module_id']; ?>"><?php echo adminCoursesEscape($module['session_title']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-auto"><button class="btn btn-primary" type="submit">Assign session</button></div>
                                    </form>
                                <?php else: ?>
                                    <p class="alert alert-info">No assignable sessions are available. Check that a session is active, not assigned to another course, and grants access to at least one of this course’s groups.</p>
                                <?php endif; ?>

                                <?php if ($sessions === []): ?>
                                    <p class="text-muted">No sessions assigned. Unassigned existing sessions are still accessible through the legacy student list.</p>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($sessions as $session): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                                    <div>
                                                        <h3 class="h6 mb-1"><?php echo adminCoursesEscape($session['session_title']); ?></h3>
                                                        <p class="small text-muted mb-2">Session order: <?php echo (int) $session['display_order']; ?> · <?php echo adminCoursesEscape(ucfirst($session['lecture_status'])); ?></p>
                                                        <a class="btn btn-sm btn-outline-primary" href="courses.php?course_id=<?php echo $courseId; ?>&amp;session_id=<?php echo (int) $session['module_id']; ?>">Manage items</a>
                                                    </div>
                                                    <form method="post" onsubmit="return confirm('Unassign this session? It will remain available through its existing group permissions.');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo adminCoursesEscape($csrfToken); ?>">
                                                        <input type="hidden" name="action" value="unassign_session">
                                                        <input type="hidden" name="course_id" value="<?php echo $courseId; ?>">
                                                        <input type="hidden" name="module_id" value="<?php echo (int) $session['module_id']; ?>">
                                                        <button class="btn btn-sm btn-outline-danger" type="submit">Unassign</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if ($editingCourse && $selectedModule): ?>
                        <section class="card shadow-sm mt-4">
                            <div class="card-body">
                                <h2 class="h4">Items: <?php echo adminCoursesEscape($selectedModule['title']); ?></h2>
                                <p class="text-muted">Archived items are hidden from students; their completion records are preserved.</p>

                                <form method="post" class="row g-3 border rounded p-3 mb-4">
                                    <input type="hidden" name="csrf_token" value="<?php echo adminCoursesEscape($csrfToken); ?>">
                                    <input type="hidden" name="action" value="save_item">
                                    <input type="hidden" name="course_id" value="<?php echo $courseId; ?>">
                                    <input type="hidden" name="module_id" value="<?php echo $selectedSessionId; ?>">
                                    <input type="hidden" name="item_id" value="0">
                                    <div class="col-12 col-md-3">
                                        <label class="form-label" for="new-item-type">Type</label>
                                        <select class="form-select" id="new-item-type" name="item_type">
                                            <option value="video">Video</option>
                                            <option value="document">Document</option>
                                            <option value="quiz">Quiz / link</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="new-item-title">Title</label>
                                        <input class="form-control" id="new-item-title" name="item_title" maxlength="255" required>
                                    </div>
                                    <div class="col-12 col-md-3">
                                        <label class="form-label" for="new-item-order">Step order</label>
                                        <input class="form-control" type="number" min="0" id="new-item-order" name="item_order" value="1" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="new-item-url">Video URL, PDF path, or quiz URL</label>
                                        <input class="form-control" id="new-item-url" name="content_url" placeholder="Direct Google Drive video link or uploads/pdfs/file.pdf" required>
                                        <div class="form-text">For videos, use a direct Drive video link, YouTube link, or video file URL—not a Drive folder URL. Local paths must stay within the app.</div>
                                    </div>
                                    <div class="col-12"><button class="btn btn-primary" type="submit">Add item</button></div>
                                </form>

                                <?php if ($items === []): ?>
                                    <p class="text-muted">No items in this session yet.</p>
                                <?php else: ?>
                                    <div class="accordion" id="module-items-<?php echo $selectedSessionId; ?>">
                                        <?php foreach ($items as $index => $item): ?>
                                            <?php $itemPanelId = 'item-edit-' . (int) $item['id']; ?>
                                            <div class="accordion-item">
                                                <h3 class="accordion-header" id="<?php echo $itemPanelId; ?>-heading">
                                                    <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $itemPanelId; ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="<?php echo $itemPanelId; ?>">
                                                        Step <?php echo (int) $item['display_order']; ?> · <?php echo adminCoursesEscape($item['title']); ?>
                                                        <?php if ($item['status'] !== 'active'): ?><span class="badge text-bg-secondary ms-2">Archived</span><?php endif; ?>
                                                    </button>
                                                </h3>
                                                <div id="<?php echo $itemPanelId; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" aria-labelledby="<?php echo $itemPanelId; ?>-heading" data-bs-parent="#module-items-<?php echo $selectedSessionId; ?>">
                                                    <div class="accordion-body">
                                                        <form method="post" class="row g-3">
                                                            <input type="hidden" name="csrf_token" value="<?php echo adminCoursesEscape($csrfToken); ?>">
                                                            <input type="hidden" name="action" value="save_item">
                                                            <input type="hidden" name="course_id" value="<?php echo $courseId; ?>">
                                                            <input type="hidden" name="module_id" value="<?php echo $selectedSessionId; ?>">
                                                            <input type="hidden" name="item_id" value="<?php echo (int) $item['id']; ?>">
                                                            <div class="col-12 col-md-3">
                                                                <label class="form-label" for="type-<?php echo (int) $item['id']; ?>">Type</label>
                                                                <select class="form-select" id="type-<?php echo (int) $item['id']; ?>" name="item_type">
                                                                    <?php foreach (['video' => 'Video', 'document' => 'Document', 'quiz' => 'Quiz / link'] as $value => $label): ?>
                                                                        <option value="<?php echo $value; ?>" <?php echo $item['item_type'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-12 col-md-6">
                                                                <label class="form-label" for="title-<?php echo (int) $item['id']; ?>">Title</label>
                                                                <input class="form-control" id="title-<?php echo (int) $item['id']; ?>" name="item_title" maxlength="255" value="<?php echo adminCoursesEscape($item['title']); ?>" required>
                                                            </div>
                                                            <div class="col-12 col-md-3">
                                                                <label class="form-label" for="order-<?php echo (int) $item['id']; ?>">Step order</label>
                                                                <input class="form-control" type="number" min="0" id="order-<?php echo (int) $item['id']; ?>" name="item_order" value="<?php echo (int) $item['display_order']; ?>" required>
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label" for="url-<?php echo (int) $item['id']; ?>">Content URL or local path</label>
                                                                <input class="form-control" id="url-<?php echo (int) $item['id']; ?>" name="content_url" value="<?php echo adminCoursesEscape($item['content_url']); ?>" required>
                                                            </div>
                                                            <div class="col-12">
                                                                <button class="btn btn-primary" type="submit">Save item</button>
                                                            </div>
                                                        </form>
                                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                                                <?php if ($item['status'] === 'active'): ?>
                                                                    <form method="post" onsubmit="return confirm('Archive this item? Its completion history will be kept.');">
                                                                        <input type="hidden" name="csrf_token" value="<?php echo adminCoursesEscape($csrfToken); ?>">
                                                                        <input type="hidden" name="action" value="archive_item">
                                                                        <input type="hidden" name="course_id" value="<?php echo $courseId; ?>">
                                                                        <input type="hidden" name="module_id" value="<?php echo $selectedSessionId; ?>">
                                                                        <input type="hidden" name="item_id" value="<?php echo (int) $item['id']; ?>">
                                                                        <button class="btn btn-outline-danger" type="submit">Archive item</button>
                                                                    </form>
                                                                <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
