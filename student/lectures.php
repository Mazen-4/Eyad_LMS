<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/course_outline_service.php';

$user = requireLogin(['student', 'admin']);
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$conn = getDbConnection();

$isAdmin = ($user['role'] === 'admin');
$studentGroupIds = array_values(array_unique(array_filter(array_map('intval', $user['group_ids'] ?? []))));
if (empty($studentGroupIds) && !empty($user['group_id'])) {
    $studentGroupIds = [(int) $user['group_id']];
}

function normalizeDriveLink($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('#/view(\?.*)?$#i', '/preview?rm=minimal', $value);
    $value = preg_replace('#/preview(\?.*)?$#i', '/preview?rm=minimal', $value);

    if (preg_match('#^https?://#i', $value)) {
        if (preg_match('#/folders/([^/?#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/drive/folders/' . $matches[1];
        }

        if (preg_match('#/file/d/([^/?#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/file/d/' . $matches[1] . '/preview?rm=minimal';
        }

        if (preg_match('#[?&]id=([^&#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/file/d/' . $matches[1] . '/preview?rm=minimal';
        }

        return $value;
    }

    if (preg_match('/^[a-zA-Z0-9\-_]+$/', $value)) {
        return 'https://drive.google.com/file/d/' . $value . '/preview?rm=minimal';
    }

    return '';
}

function courseOutlineSafeUrl($value)
{
    $value = trim((string) $value);
    if ($value === '' || preg_match('/[\x00-\x1F\x7F]/', $value)) {
        return '';
    }

    if (preg_match('#^https?://#i', $value)) {
        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return '';
        }

        return $value;
    }

    if (strpos($value, '//') === 0 || preg_match('#^[a-z][a-z0-9+.-]*:#i', $value)) {
        return '';
    }

    $path = ltrim($value, '/');
    foreach (explode('/', $path) as $segment) {
        if ($segment === '..') {
            return '';
        }
    }

    return appAssetUrl($path);
}

function courseOutlineVideoUrl($value)
{
    $value = normalizeDriveLink($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('#^https?://(www\.)?youtu\.be/([A-Za-z0-9_-]+)#i', $value, $matches)) {
        return 'https://www.youtube-nocookie.com/embed/' . $matches[2];
    }

    if (preg_match('#^https?://(www\.)?youtube\.com/watch\?[^#]*v=([A-Za-z0-9_-]+)#i', $value, $matches)) {
        return 'https://www.youtube-nocookie.com/embed/' . $matches[2];
    }

    if (preg_match('#^https?://(www\.)?youtube\.com/embed/([A-Za-z0-9_-]+)#i', $value, $matches)) {
        return 'https://www.youtube-nocookie.com/embed/' . $matches[2];
    }

    return courseOutlineSafeUrl($value);
}

$outlineService = new CourseOutlineService($conn);
$courseSchemaReady = $outlineService->hasCourseSchema();
$courses = $courseSchemaReady ? $outlineService->getCoursesForUser(
    (int) ($user['id'] ?? 0),
    $studentGroupIds,
    $isAdmin
) : [];
$selectedCourseId = (int) ($_GET['course_id'] ?? 0);
$selectedCourse = null;
foreach ($courses as $course) {
    if ((int) $course['id'] === $selectedCourseId) {
        $selectedCourse = $course;
        break;
    }
}
if ($selectedCourse === null) {
    $selectedCourseId = 0;
}

$useOutline = $selectedCourse !== null;
$showLegacySessions = !$useOutline && (string) ($_GET['legacy'] ?? '') === '1';
$sessions = $useOutline
    ? $outlineService->getSessionsForCourse(
        $selectedCourseId,
        (int) ($user['id'] ?? 0),
        $studentGroupIds,
        $isAdmin
    )
    : [];
$courseResources = $useOutline
    ? $outlineService->getResourcesForCourse($selectedCourseId, $studentGroupIds, $isAdmin)
    : [];
$courseQuizzes = $useOutline
    ? $outlineService->getQuizzesForCourse($selectedCourseId, $studentGroupIds, $isAdmin)
    : [];

// Sessions remain in the original group-filtered view until an admin assigns
// them to a course. The outline never combines sessions across courses.
if (!$useOutline) {
    if (!$isAdmin && !empty($studentGroupIds)) {
        $placeholders = implode(', ', array_fill(0, count($studentGroupIds), '?'));
        $courseFilter = '';
        $params = $studentGroupIds;
        if ($courseSchemaReady) {
            $courseGroupPlaceholders = implode(', ', array_fill(0, count($studentGroupIds), '?'));
            $courseFilter = "
                AND NOT EXISTS (
                    SELECT 1
                    FROM course_modules cm
                    INNER JOIN courses c
                        ON c.id = cm.course_id
                       AND c.status = 'active'
                    INNER JOIN course_group_access cga
                        ON cga.course_id = c.id
                    WHERE cm.lecture_id = l.id
                      AND cga.group_id IN ($courseGroupPlaceholders)
                      AND EXISTS (
                          SELECT 1
                          FROM lecture_folder_access course_lfa
                          WHERE course_lfa.lecture_id = cm.lecture_id
                            AND course_lfa.group_id = cga.group_id
                      )
                )
            ";
            $params = array_merge($params, $studentGroupIds);
        }
        $lecturesStmt = $conn->prepare('SELECT l.id, l.title, l.description, l.drive_folder_id, l.display_order FROM lectures l INNER JOIN lecture_folder_access lfa ON lfa.lecture_id = l.id WHERE l.status = "active" AND lfa.group_id IN (' . $placeholders . ') ' . $courseFilter . ' ORDER BY l.display_order, l.id');
        bindPreparedParams($lecturesStmt, $params);
        $lecturesStmt->execute();
        $lecturesResult = $lecturesStmt->get_result();
    } elseif (!$isAdmin) {
        $lecturesResult = $conn->query('SELECT id, title, description, drive_folder_id, display_order FROM lectures WHERE 1 = 0');
    } else {
        $courseFilter = $courseSchemaReady
            ? "AND NOT EXISTS (
                SELECT 1 FROM course_modules cm
                INNER JOIN courses c ON c.id = cm.course_id AND c.status = 'active'
                WHERE cm.lecture_id = l.id
            )"
            : '';
        $lecturesResult = $conn->query('SELECT l.id, l.title, l.description, l.drive_folder_id, l.display_order FROM lectures l WHERE l.status = "active" ' . $courseFilter . ' ORDER BY l.display_order, l.id');
    }
}

$totalItems = 0;
$completedItems = 0;
if ($useOutline) {
    foreach ($sessions as $session) {
        $totalItems += $session['item_count'];
        $completedItems += $session['completed_count'];
    }
}
$courseProgress = $totalItems > 0 ? (int) round(($completedItems / $totalItems) * 100) : 0;
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sessions - Student</title>
    <link rel="icon" href="<?php echo $escape(appAssetUrl('Images/eyad_logo1.jpeg?v=' . filemtime(__DIR__ . '/../Images/eyad_logo1.jpeg'))); ?>" type="image/jpeg">
    <link rel="shortcut icon" href="<?php echo $escape(appAssetUrl('Images/eyad_logo1.jpeg?v=' . filemtime(__DIR__ . '/../Images/eyad_logo1.jpeg'))); ?>" type="image/jpeg">
    <link rel="apple-touch-icon" href="<?php echo $escape(appAssetUrl('Images/eyad_logo1.jpeg?v=' . filemtime(__DIR__ . '/../Images/eyad_logo1.jpeg'))); ?>">
    <link rel="icon" href="<?php echo $escape(appAssetUrl('favicon.ico?v=' . filemtime(__DIR__ . '/../favicon.ico'))); ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo $escape(appVersionedAssetUrl('assets/css/theme.css')); ?>" rel="stylesheet">
    <?php if ($useOutline || !empty($courses)): ?>
        <link href="<?php echo $escape(appVersionedAssetUrl('assets/css/course-outline.css')); ?>" rel="stylesheet">
    <?php endif; ?>
</head>
<body>
    <?php include __DIR__ . '/../includes/student_nav.php'; ?>

    <div class="container py-4">
        <a href="dashboard.php" class="btn btn-outline-secondary mb-3">&larr; Back to Dashboard</a>
        <h1 class="mb-2">My Sessions</h1>
        <p class="text-muted mb-4">
            <?php if (!empty($courses) && !$useOutline): ?>
                <?php echo $showLegacySessions ? 'These sessions are still available through your group access.' : 'Choose a course or open sessions that have not been assigned to a course.'; ?>
            <?php else: ?>
                These sessions are available for your assigned group.
            <?php endif; ?>
        </p>

        <?php if ($useOutline): ?>
            <a href="lectures.php" class="btn btn-outline-secondary mb-3">&larr; Back to courses</a>
            <h2 class="h4 mb-3"><?php echo $escape($selectedCourse['title'] ?? 'Course outline'); ?></h2>
            <?php if (!empty($selectedCourse['description'])): ?>
                <p class="text-muted mb-4"><?php echo $escape($selectedCourse['description']); ?></p>
            <?php endif; ?>
            <?php if ($courseResources !== []): ?>
                <section class="mb-4" aria-labelledby="course-resources-title">
                    <h3 class="h5 mb-3" id="course-resources-title">Course resources</h3>
                    <div class="row g-3">
                        <?php foreach ($courseResources as $resource): ?>
                            <div class="col-12 col-md-6 col-lg-4">
                                <article class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h4 class="h6 card-title"><?php echo $escape($resource['title']); ?></h4>
                                        <?php if (!empty($resource['description'])): ?>
                                            <p class="card-text text-muted"><?php echo $escape($resource['description']); ?></p>
                                        <?php endif; ?>
                                        <a class="btn btn-outline-primary btn-sm" href="resource_download.php?resource_id=<?php echo (int) $resource['id']; ?>&amp;course_id=<?php echo $selectedCourseId; ?>" target="_blank" rel="noopener noreferrer">Open PDF</a>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            <?php if ($courseQuizzes !== []): ?>
                <section class="mb-4" aria-labelledby="course-quizzes-title">
                    <h3 class="h5 mb-3" id="course-quizzes-title">Course quizzes</h3>
                    <div class="row g-3">
                        <?php foreach ($courseQuizzes as $quiz): ?>
                            <div class="col-12 col-md-6 col-lg-4">
                                <article class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h4 class="h6 card-title"><?php echo $escape($quiz['title']); ?></h4>
                                        <a class="btn btn-outline-primary btn-sm" href="quizzes.php?course_id=<?php echo $selectedCourseId; ?>&amp;quiz_id=<?php echo (int) $quiz['id']; ?>">Open quiz</a>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            <?php if (empty($sessions)): ?>
                <?php if ($courseResources === [] && $courseQuizzes === []): ?>
                    <div class="alert alert-info">This course does not have any available content yet.</div>
                <?php else: ?>
                    <div class="alert alert-info">This course does not have any sessions yet.</div>
                <?php endif; ?>
            <?php else: ?>
            <div
                class="course-outline"
                data-course-outline
                data-api-url="<?php echo $escape(appAssetUrl('api/mark-completed.php')); ?>"
                data-csrf-token="<?php echo $escape($_SESSION['csrf_token']); ?>"
            >
                <main class="course-outline__main">
                    <h2 class="visually-hidden">Course session outline</h2>

                    <?php foreach ($sessions as $sessionIndex => $session): ?>
                        <?php
                        $panelId = 'session-items-' . (int) $session['session_id'];
                        $expanded = $sessionIndex === 0;
                        ?>
                        <section class="outline-session" data-session>
                            <h3 class="outline-session__heading">
                                <button
                                    class="outline-session__toggle"
                                    type="button"
                                    aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>"
                                    aria-controls="<?php echo $escape($panelId); ?>"
                                >
                                    <span class="outline-session__title"><?php echo $escape($session['title']); ?></span>
                                    <span class="outline-session__summary">
                                        <span data-session-count><?php echo (int) $session['item_count']; ?> <?php echo $session['item_count'] === 1 ? 'item' : 'items'; ?></span>
                                        <span class="outline-session__progress-badge" data-session-progress><?php echo (int) $session['progress_percent']; ?>%</span>
                                    </span>
                                </button>
                            </h3>

                            <div
                                class="outline-session__items"
                                id="<?php echo $escape($panelId); ?>"
                                <?php echo $expanded ? '' : 'hidden'; ?>
                            >
                                <?php if (empty($session['items'])): ?>
                                    <p class="outline-session__empty">Items for this session will be added soon.</p>
                                <?php else: ?>
                                    <?php foreach ($session['items'] as $item): ?>
                                        <?php
                                        $type = $item['type'];
                                        $safeUrl = courseOutlineSafeUrl($item['content_url']);
                                        $videoUrl = $type === 'video' ? courseOutlineVideoUrl($item['content_url']) : '';
                                        $isDirectVideo = $videoUrl !== '' && preg_match('/\.(mp4|webm|ogg|m4v|mov)(\?.*)?$/i', $videoUrl) === 1;
                                        $canEmbedVideo = $videoUrl !== '' && (
                                            $isDirectVideo
                                            || preg_match('#^https://(drive\.google\.com|www\.youtube-nocookie\.com)/#i', $videoUrl) === 1
                                        );
                                        ?>
                                        <article
                                            class="outline-item<?php echo $item['completed'] ? ' is-completed' : ''; ?>"
                                            data-item-row
                                            data-item-id="<?php echo (int) $item['item_id']; ?>"
                                            data-completed="<?php echo $item['completed'] ? 'true' : 'false'; ?>"
                                        >
                                            <span class="outline-item__icon outline-item__icon--<?php echo $escape($type); ?>" aria-hidden="true">
                                                <?php if ($type === 'video'): ?>&#9654;<?php elseif ($type === 'quiz'): ?>?<?php else: ?>PDF<?php endif; ?>
                                            </span>
                                            <div class="outline-item__body">
                                                <span class="outline-item__step">Step <?php echo (int) $item['step_index']; ?> &middot; <?php echo $escape(ucfirst($type)); ?></span>
                                                <span class="outline-item__title"><?php echo $escape($item['title']); ?></span>
                                            </div>

                                            <div class="outline-item__actions">
                                                <?php if ($type === 'video' && $canEmbedVideo): ?>
                                                    <?php if ($isDirectVideo): ?>
                                                        <button
                                                            class="btn btn-sm btn-outline-primary"
                                                            type="button"
                                                            data-video-open
                                                            data-video-kind="file"
                                                            data-video-url="<?php echo $escape($videoUrl); ?>"
                                                        >Play video</button>
                                                    <?php else: ?>
                                                        <button
                                                            class="btn btn-sm btn-outline-primary"
                                                            type="button"
                                                            data-video-open
                                                            data-video-kind="embed"
                                                            data-video-url="<?php echo $escape($videoUrl); ?>"
                                                        >Play video</button>
                                                    <?php endif; ?>
                                                <?php elseif ($safeUrl !== ''): ?>
                                                    <a
                                                        class="btn btn-sm btn-outline-primary"
                                                        href="<?php echo $escape($safeUrl); ?>"
                                                        <?php echo preg_match('#^https?://#i', $safeUrl) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
                                                    ><?php echo $type === 'document' ? 'Open document' : ($type === 'quiz' ? 'Open quiz' : 'Open video'); ?></a>
                                                <?php else: ?>
                                                    <span class="text-muted small">Content link unavailable</span>
                                                <?php endif; ?>

                                                <?php if (!$isAdmin): ?>
                                                    <button
                                                        class="btn btn-sm btn-success"
                                                        type="button"
                                                        data-complete
                                                        data-item-id="<?php echo (int) $item['item_id']; ?>"
                                                        <?php echo $item['completed'] ? 'disabled aria-pressed="true"' : 'aria-pressed="false"'; ?>
                                                    ><?php echo $item['completed'] ? 'Completed' : 'Mark as complete'; ?></button>
                                                <?php endif; ?>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </main>

                <aside class="course-sidebar" aria-label="Course progress">
                    <h2 class="course-sidebar__title">Course progress</h2>
                    <div class="course-sidebar__progress">
                        <div
                            class="progress"
                            role="progressbar"
                            aria-label="Overall course completion"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-valuenow="<?php echo $courseProgress; ?>"
                            data-course-progress
                        >
                            <div class="progress-bar" style="width: <?php echo $courseProgress; ?>%"></div>
                        </div>
                        <p class="course-sidebar__percent" data-course-progress-label><?php echo $courseProgress; ?>% complete</p>
                    </div>
                    <dl class="course-sidebar__stats">
                        <div>
                            <dt>Items completed</dt>
                            <dd><span data-course-completed><?php echo $completedItems; ?></span> / <span data-course-total><?php echo $totalItems; ?></span></dd>
                        </div>
                        <div>
                            <dt>Access expiry</dt>
                            <dd>No expiry date set</dd>
                        </div>
                    </dl>
                    <p class="course-sidebar__note">Your progress is saved to your account when you mark an item complete.</p>
                </aside>
            </div>

            <dialog class="video-modal" data-video-modal aria-label="Video player">
                <div class="video-modal__header">
                    <h2 class="video-modal__title">Course video</h2>
                    <div class="video-modal__actions">
                        <button class="btn btn-outline-success" type="button" data-video-complete data-item-id="" hidden>Mark as complete</button>
                        <button class="btn btn-outline-primary" type="button" data-video-fullscreen>Fullscreen</button>
                        <button class="btn-close" type="button" data-video-close aria-label="Close video"></button>
                    </div>
                </div>
                <div class="video-modal__content">
                    <iframe
                        title="Course video"
                        data-video-frame
                        sandbox="allow-scripts allow-same-origin"
                        allow="autoplay; encrypted-media; fullscreen"
                        allowfullscreen
                        referrerpolicy="strict-origin-when-cross-origin"
                        hidden
                    ></iframe>
                    <video data-video-element controls playsinline disablepictureinpicture disableremoteplayback hidden></video>
                </div>
            </dialog>
            <p class="course-outline-feedback alert alert-info py-2 d-none" role="status" aria-live="polite" data-outline-status></p>
            <?php endif; ?>
        <?php elseif (!empty($courses) && !$showLegacySessions): ?>
            <section class="course-picker" aria-labelledby="course-picker-title">
                <h2 class="course-picker__heading" id="course-picker-title">Available courses</h2>
                <div class="course-picker__grid">
                    <?php foreach ($courses as $course): ?>
                        <a class="course-picker__card" href="?course_id=<?php echo (int) $course['id']; ?>">
                            <span class="course-picker__eyebrow">Course</span>
                            <span class="course-picker__title"><?php echo $escape($course['title']); ?></span>
                            <?php if (!empty($course['description'])): ?>
                                <span class="course-picker__description"><?php echo $escape($course['description']); ?></span>
                            <?php endif; ?>
                            <span class="course-picker__action">View sessions</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php if ($lecturesResult->num_rows > 0): ?>
                <section class="mt-4" aria-labelledby="legacy-sessions-title">
                    <h2 class="course-picker__heading" id="legacy-sessions-title">Other sessions</h2>
                    <p class="text-muted">These sessions have not been assigned to a course and remain available through their existing group access.</p>
                    <a href="?legacy=1" class="btn btn-outline-primary">View other sessions</a>
                </section>
            <?php endif; ?>
        <?php elseif ($lecturesResult->num_rows > 0): ?>
            <?php if ($showLegacySessions && !empty($courses)): ?>
                <a href="lectures.php" class="btn btn-outline-secondary mb-3">&larr; Back to courses</a>
                <h2 class="h4 mb-3">Other sessions</h2>
            <?php endif; ?>
            <div class="row g-3">
                <?php while ($lecture = $lecturesResult->fetch_assoc()): ?>
                    <div class="col-12 col-md-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $escape($lecture['title']); ?></h5>
                                <p class="card-text"><?php echo $escape($lecture['description'] ?? '-'); ?></p>
                                <?php if (!empty($lecture['drive_folder_id'])): ?>
                                    <a href="lecture_player.php?lecture_id=<?php echo (int) $lecture['id']; ?>" class="btn btn-outline-primary btn-sm w-100 w-md-auto">Open Session</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No sessions are available for your group yet.</div>
        <?php endif; ?>
    </div>
    <?php if ($useOutline): ?>
        <script src="<?php echo $escape(appVersionedAssetUrl('assets/js/course-outline.js')); ?>" defer></script>
    <?php endif; ?>
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
