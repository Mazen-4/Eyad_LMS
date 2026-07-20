<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['student']);
$conn = getDbConnection();

$studentGroupIds = array_values(array_unique(array_filter(array_map('intval', $user['group_ids'] ?? []))));
if (empty($studentGroupIds) && !empty($user['group_id'])) {
    $studentGroupIds = [(int)$user['group_id']];
}
$lectureId = (int)($_GET['lecture_id'] ?? 0);
$error = '';
$lecture = null;
$sourceType = 'empty';
$sourceUrl = '';

function normalizeDriveLinkUrl($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $value)) {
        if (preg_match('#/file/d/([^/?#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/file/d/' . $matches[1] . '/preview?rm=minimal';
        }

        if (preg_match('#/drive/u/\d+/view\?usp=sharing&id=([^&#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/file/d/' . $matches[1] . '/preview?rm=minimal';
        }

        if (preg_match('#[?&]id=([^&#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/file/d/' . $matches[1] . '/preview?rm=minimal';
        }

        if (preg_match('#/drive/folders/([^/?#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/drive/folders/' . $matches[1];
        }

        if (preg_match('#/folders/([^/?#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/drive/folders/' . $matches[1];
        }

        return $value;
    }

    if (preg_match('/^[a-zA-Z0-9\-_]+$/', $value)) {
        return 'https://drive.google.com/drive/folders/' . $value;
    }

    return $value;
}

function resolveLectureSource($value)
{
    $normalizedValue = normalizeDriveLinkUrl($value);
    if ($normalizedValue === '') {
        return ['type' => 'empty', 'url' => ''];
    }

    if (preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $normalizedValue)) {
        return ['type' => 'video', 'url' => $normalizedValue];
    }

    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]+)/i', $normalizedValue, $matches)) {
        return ['type' => 'youtube', 'url' => 'https://www.youtube.com/embed/' . $matches[1]];
    }

    if (preg_match('#/file/d/([^/?#]+)#i', $normalizedValue, $matches)) {
        return ['type' => 'drive_file', 'url' => 'https://drive.google.com/file/d/' . $matches[1] . '/preview?rm=minimal'];
    }

    if (preg_match('#/drive/folders/([^/?#]+)#i', $normalizedValue, $matches)) {
        return ['type' => 'folder', 'url' => 'https://drive.google.com/drive/folders/' . $matches[1]];
    }

    if (preg_match('#/folders/([^/?#]+)#i', $normalizedValue, $matches)) {
        return ['type' => 'folder', 'url' => 'https://drive.google.com/drive/folders/' . $matches[1]];
    }

    if (preg_match('#^https?://#i', $normalizedValue)) {
        if (stripos($normalizedValue, 'drive.google.com') !== false) {
            return ['type' => 'drive_file', 'url' => $normalizedValue];
        }

        return ['type' => 'link', 'url' => $normalizedValue];
    }

    return ['type' => 'unsupported', 'url' => $normalizedValue];
}

if ($lectureId > 0 && !empty($studentGroupIds)) {
    $placeholders = implode(', ', array_fill(0, count($studentGroupIds), '?'));
    $lectureStmt = $conn->prepare('SELECT l.id, l.title, l.description, l.drive_folder_id FROM lectures l INNER JOIN lecture_folder_access lfa ON lfa.lecture_id = l.id WHERE l.id = ? AND l.status = "active" AND lfa.group_id IN (' . $placeholders . ') LIMIT 1');
    $params = array_merge([(int)$lectureId], $studentGroupIds);
    bindPreparedParams($lectureStmt, $params);
    $lectureStmt->execute();
    $lecture = $lectureStmt->get_result()->fetch_assoc();
}

if (!$lecture) {
    $error = 'This session is not available for your group.';
} else {
    $lectureSource = resolveLectureSource($lecture['drive_folder_id'] ?? '');
    $sourceType = $lectureSource['type'];
    $sourceUrl = $lectureSource['url'];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session Player</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <style>
        .player-frame {
            width: 100%;
            min-height: 56vh;
            max-height: 75vh;
            border: 0;
            border-radius: 0.5rem;
            background: #000;
            display: block;
        }

        @media (max-width: 768px) {
            .player-frame {
                min-height: 45vh;
                max-height: 60vh;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/student_nav.php'; ?>

    <div class="container py-4">
        <a href="lectures.php" class="btn btn-outline-secondary mb-3">&larr; Back to Sessions</a>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h1 class="h4 mb-2"><?php echo htmlspecialchars($lecture['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h1>
                    <?php if (!empty($lecture['description'])): ?>
                        <p class="text-muted"><?php echo htmlspecialchars($lecture['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>

                    <?php if ($sourceType === 'video' && $sourceUrl !== ''): ?>
                        <video class="player-frame" controls preload="metadata">
                            <source src="<?php echo htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8'); ?>">
                            Your browser does not support the video player.
                        </video>
                    <?php elseif ($sourceType === 'youtube' && $sourceUrl !== ''): ?>
                        <iframe class="player-frame" src="<?php echo htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8'); ?>" allow="autoplay; fullscreen" allowfullscreen></iframe>
                    <?php elseif ($sourceType === 'drive_file' && $sourceUrl !== ''): ?>
                        <iframe class="player-frame" src="<?php echo htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8'); ?>" allow="autoplay; fullscreen" allowfullscreen frameborder="0"></iframe>
                        <!-- <a href="<?php echo htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-outline-primary mt-3">Open source in Drive</a> -->
                    <?php elseif ($sourceType === 'folder' && $sourceUrl !== ''): ?>
                        <div class="alert alert-warning">
                            This session source points to a Drive folder, which cannot be embedded directly in this player. Please use a direct video file link or a public Drive file link instead.
                        </div>
                        <!-- <a href="<?php echo htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-outline-primary">Open source in Drive</a> -->
                    <?php elseif ($sourceType === 'link' && $sourceUrl !== ''): ?>
                        <div class="alert alert-info">
                            A session source was found, but it needs to be shared publicly or opened directly. Use the button below to access it.
                        </div>
                        <a href="<?php echo htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-outline-primary">Open lecture source</a>
                    <?php else: ?>
                        <div class="alert alert-info">
                            This session does not have a video source yet. The admin can add a direct video link or upload a file later.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
