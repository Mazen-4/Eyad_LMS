<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['student']);
$conn = getDbConnection();

$studentGroupId = (int)($user['group_id'] ?? 0);
$lectureId = (int)($_GET['lecture_id'] ?? 0);
$error = '';
$lecture = null;
$sourceType = 'empty';
$sourceUrl = '';

function resolveLectureSource($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return ['type' => 'empty', 'url' => ''];
    }

    if (preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $value)) {
        return ['type' => 'video', 'url' => $value];
    }

    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]+)/i', $value, $matches)) {
        return ['type' => 'youtube', 'url' => 'https://www.youtube.com/embed/' . $matches[1]];
    }

    if (preg_match('#/file/d/([^/?#]+)#i', $value, $matches)) {
        return ['type' => 'drive_file', 'url' => 'https://drive.google.com/uc?export=download&id=' . $matches[1]];
    }

    if (preg_match('#/drive/folders/([^/?#]+)#i', $value, $matches)) {
        return ['type' => 'folder', 'url' => 'https://drive.google.com/drive/folders/' . $matches[1]];
    }

    if (preg_match('#/drive/u/\d+/view\?usp=sharing&id=([^&#]+)#i', $value, $matches)) {
        return ['type' => 'drive_file', 'url' => 'https://drive.google.com/uc?export=download&id=' . $matches[1]];
    }

    if (preg_match('#[?&]id=([^&#]+)#i', $value, $matches)) {
        return ['type' => 'drive_file', 'url' => 'https://drive.google.com/uc?export=download&id=' . $matches[1]];
    }

    if (preg_match('#/folders/([^/?#]+)#i', $value, $matches)) {
        return ['type' => 'folder', 'url' => 'https://drive.google.com/drive/folders/' . $matches[1]];
    }

    if (preg_match('/^[a-zA-Z0-9\-_]+$/', $value)) {
        return ['type' => 'folder', 'url' => 'https://drive.google.com/drive/folders/' . $value];
    }

    if (preg_match('#^https?://#i', $value)) {
        if (stripos($value, 'drive.google.com') !== false) {
            return ['type' => 'drive_file', 'url' => $value];
        }

        return ['type' => 'link', 'url' => $value];
    }

    return ['type' => 'unsupported', 'url' => $value];
}

if ($lectureId > 0 && $studentGroupId > 0) {
    $lectureStmt = $conn->prepare('SELECT l.id, l.title, l.description, l.drive_folder_id FROM lectures l INNER JOIN lecture_folder_access lfa ON lfa.lecture_id = l.id WHERE l.id = ? AND l.status = "active" AND lfa.group_id = ? LIMIT 1');
    $lectureStmt->bind_param('ii', $lectureId, $studentGroupId);
    $lectureStmt->execute();
    $lecture = $lectureStmt->get_result()->fetch_assoc();
}

if (!$lecture) {
    $error = 'This lecture is not available for your group.';
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
    <title>Lecture Player</title>
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
        <a href="lectures.php" class="btn btn-outline-secondary mb-3">&larr; Back to Lectures</a>

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
                        <div class="alert alert-warning">
                            This lecture source is a Google Drive file. If Drive returns a 403 error, the file is not accessible to the browser because of Drive sharing permissions or because the file is not directly playable. In that case, use a direct public video URL or upload the video to the LMS instead.
                        </div>
                        <video class="player-frame" controls preload="metadata" playsinline>
                            <source src="<?php echo htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8'); ?>">
                            Your browser does not support the video player.
                        </video>
                        <a href="<?php echo htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-outline-primary mt-3">Open source in Drive</a>
                    <?php elseif ($sourceType === 'folder' && $sourceUrl !== ''): ?>
                        <div class="alert alert-warning">
                            This lecture source points to a Drive folder, which cannot be embedded directly in this player. Please use a direct video file link or a public Drive file link instead.
                        </div>
                        <a href="<?php echo htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-outline-primary">Open source in Drive</a>
                    <?php elseif ($sourceType === 'link' && $sourceUrl !== ''): ?>
                        <div class="alert alert-info">
                            A lecture source was found, but it needs to be shared publicly or opened directly. Use the button below to access it.
                        </div>
                        <a href="<?php echo htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-outline-primary">Open lecture source</a>
                    <?php else: ?>
                        <div class="alert alert-info">
                            This lecture does not have a video source yet. The admin can add a direct video link or upload a file later.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
